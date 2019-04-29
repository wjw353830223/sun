<?php
namespace app\admin\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Cache;
use think\Hook;

class Test extends Command
{
    const ORDER_CLOSE_DELAY_TIME = 252000;//订单70小时未支付自动关闭订单
    const DELIVERY_SIGN_TIME = 864000;//已发货订单10天后自动签收
    const DELIVERY_FINISH_TIME = 604800;//已收货的订单7天后自动完成
    const UNFREEZE_POINTS_DELAY_TIME = 604800;//付款七天后，冻结积分自动转为可用积分
    protected function configure(){
        $this->setName('test')->setDescription('Command Test');
    }

    protected function execute(Input $input, Output $output){
        //会员升级成为消费商
        $this->_order_finished();
        $this->_order_commit();
        $this->_member_upgrade();
        $this->_order_close();
        $this->_seller_upgrade();
        $this->_send_points();
    }

    /**
     * 冻结积分自动转为可用积分
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    private function _send_points(){
        $order_model = db('Order');
        $member_model = db('Member');
        $pl_model = db('PointsLog');
        $message_model = db('Message');

        $order = $order_model->alias('om')
            ->join('OrderGoods og','om.order_id = og.order_id')
            ->where(['om.is_send'=>0,'om.order_state'=>['gt',10]])->select();
        foreach($order as $k=>$v){
            $time = time() - $v['payment_time'];
            if ($time < self::UNFREEZE_POINTS_DELAY_TIME) {
                continue;
            }
            $member_model->startTrans();
            $pl_model->startTrans();
            $message_model->startTrans();
            $order_model->startTrans();
            $parent_id = $member_model->where(['member_id'=>$v['buyer_id']])->value('parent_id');
            $message = [];
            if($v['goods_points'] > 0){
                $result = $member_model->where(['member_id'=>$v['buyer_id']])
                    ->update([
                        'points' => ['exp','points+'.$v['goods_points']],
                        'freeze_points' => ['exp','freeze_points-'.$v['goods_points']]
                    ]);
                if($result === false){
                    continue;
                }
                $data = [
                    'member_id' => $v['buyer_id'],
                    'points' => $v['goods_points'],
                    'add_time' => time(),
                    'pl_desc' => '冻结积分转为可用积分',
                    'type' => 'order_add',
                    'pl_data' => json_encode(['goods_name'=>$v['goods_name']])
                ];
                $pl_res = $pl_model->insertGetId($data);
                if($pl_res === false){
                    $member_model->rollback();
                    continue;
                }
                $body = json_encode(['title'=>'积分解冻','points'=>'+'.$v['goods_points']]);
                $message[] = [
                    'from_member_id' => 0,
                    'to_member_id'   => $v['buyer_id'],
                    'message_title'  => '积分解冻',
                    'message_time'   => time(),
                    'message_state'  => 0,
                    'message_type'   => 4,
                    'is_more'        => 0,
                    'is_push'        => 2,
                    'message_body'   => $body,
                    'push_detail'    => '您有'.$v['goods_points'].'冻结积分已解冻，快去使用吧！',
                    'push_title'     => '积分解冻'
                ];
            }
            if($parent_id > 0){
                if($v['goods_parent_points'] > 0){
                    $result = $member_model->where(['member_id'=>$parent_id])
                        ->update([
                            'points' => ['exp','points+'.$v['goods_parent_points']],
                            'freeze_points' => ['exp','freeze_points-'.$v['goods_parent_points']]
                        ]);
                    if($result === false){
                        $member_model->rollback();
                        $pl_model->rollback();
                        continue;
                    }
                    $data = [
                        'member_id' => $parent_id,
                        'points' => $v['goods_parent_points'],
                        'add_time' => time(),
                        'pl_desc' => '冻结积分转为可用积分',
                        'type' => 'order_parent_present',
                        'pl_data' => json_encode(['goods_name'=>$v['goods_name']])
                    ];
                    $pl_res = $pl_model->insertGetId($data);
                    if($pl_res === false){
                        $member_model->rollback();
                        $pl_model->rollback();
                        continue;
                    }
                    $body = json_encode(['title'=>'积分解冻','points'=>'+'.$v['goods_parent_points']]);
                    $message[] = [
                        'from_member_id' => 0,
                        'to_member_id'   => $parent_id,
                        'message_title'  => '积分解冻',
                        'message_time'   => time(),
                        'message_state'  => 0,
                        'message_type'   => 4,
                        'is_more'        => 0,
                        'is_push'        => 2,
                        'message_body'   => $body,
                        'push_detail'    => '您有'.$v['goods_parent_points'].'冻结积分已解冻，快去使用吧！',
                        'push_title'     => '积分解冻'
                    ];
                }
            }
            if(count($message) > 0){
                $res = $message_model->insertAll($message);
                if($res === false){
                    $member_model->rollback();
                    $pl_model->rollback();
                    continue;
                }
            }
            $order_res = $order_model->where(['order_id'=>$v['order_id']])->update(['is_send'=>1]);
            if($order_res === false){
                $member_model->rollback();
                $pl_model->rollback();
                $message_model->rollback();
                continue;
            }
            $member_model->commit();
            $pl_model->commit();
            $message_model->commit();
            $order_model->commit();
            if(!empty($data)){
                Hook::listen('create_points_log',$data);
            }
        }
    }

    /**
     * 自动签收
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    private function _order_commit(){
        $order_model = db('Order');
        $log_model = db('OrderLog');
        $order_partition_mdoel = db('OrderPartition');
        $order_data = $order_model->alias('om')
            ->join('OrderCommon oc','oc.order_id = om.order_id')
            ->where(['om.order_state'=>30])->select();
        $unique_delivery = [];
        foreach($order_data as $key => $order){
            if(empty($unique_delivery)){
                $unique_delivery[]=[
                    'shipping_express_id'=>$order['shipping_express_id'],
                    'shipping_code'=>$order['shipping_code']
                ];
            }else{
                foreach($unique_delivery as $delivery){
                    if($delivery['shipping_express_id'] === $order['shipping_express_id'] && $delivery['shipping_code'] === $order['shipping_code']){
                        unset($order_data[$key]);
                        continue 2;
                    }
                }
                $unique_delivery[]=[
                    'shipping_express_id'=>$order['shipping_express_id'],
                    'shipping_code'=>$order['shipping_code']
                ];
            }
        }
        foreach($order_data as $key => $value){
            $time = time() - $value['shipping_time'];
            if($time < self::DELIVERY_SIGN_TIME){
                continue;
            }
            $order_partition_nums = $order_partition_mdoel->where(['order_sn'=>$value['order_sn']])->count();
            $car_order = $order_partition_nums <= 0 ? false:true;
            $order_model->startTrans();
            $log_model->startTrans();
            $car_order && $order_partition_mdoel->startTrans();
            $order_data = [
                'order_state' => 40,
                'sign_time' => time()
            ];
            $order_result = $order_model->where(['order_id' => $value['order_id']])->update($order_data);
            if ($order_result === false) {
                continue;
            }
            if($car_order){
                $res = $order_partition_mdoel->where(['order_sn'=>$value['order_sn']])->update($order_data);
                if ($res === false) {
                    $order_model->rollback();
                    continue;
                }
            }
            $log_data = [
                'order_id' => $value['order_id'],
                'log_msg' => '订单于'.date('Y-m-d H:i:s').'自动签收,签收订单编号'.$value['order_sn'],
                'log_time' => time(),
                'log_role' => '系统',
                'log_user' => 'admin',
                'log_orderstate' => 40
            ];
            $log_result = $log_model->insert($log_data);
            if ($log_result === false) {
                $order_model->rollback();
                $car_order && $order_partition_mdoel->rollback();
                continue;
            }

            $order_model->commit();
            $log_model->commit();
            $car_order && $order_partition_mdoel->commit();
        }
    }

    /**
     *	订单完成操作
     */
    private function _order_finished()
    {
        $order_model = db('Order');
        $log_model = db('OrderLog');
        $message_model = db('Message');
        $order_goods_model = db('OrderGoods');
        $oc_model = db('OrderCommon');
        $ex_model = db('Express');
        $order_partition_mdoel = db('OrderPartition');
        $order_data = $order_model->where(['order_state' => 40])->select();
        foreach ($order_data as $key => $value) {
            $time = time() - $value['sign_time'];
            if ($time < self::DELIVERY_FINISH_TIME) {
                continue;
            }
            $order_partition_nums = $order_partition_mdoel->where(['order_sn'=>$value['order_sn']])->count();
            $car_order = $order_partition_nums <= 0 ? false:true;
            $order_model->startTrans();
            $log_model->startTrans();
            $message_model->startTrans();
            $car_order && $order_partition_mdoel->startTrans();
            $order_data = [
                'order_state' => 50,
                'finnshed_time' => time()
            ];
            $order_result = $order_model->where(['order_id' => $value['order_id']])->update($order_data);
            if (empty($order_result)) {
                continue;
            }
            if($car_order){
                $res = $order_partition_mdoel->where(['order_sn'=>$value['order_sn']])->update($order_data);
                if ($res === false) {
                    $order_model->rollback();
                    continue;
                }
            }
            $log_data = [
                'order_id' => $value['order_id'],
                'log_msg' => '订单于' . date('Y-m-d H:i:s') . '自动完成,完成订单编号' . $value['order_sn'],
                'log_time' => time(),
                'log_role' => '系统',
                'log_user' => 'admin',
                'log_orderstate' => 50
            ];
            $log_result = $log_model->insert($log_data);
            if (empty($log_result)) {
                $car_order && $order_partition_mdoel->rollback();
                $order_model->rollback();
                continue;
            }
            $order_goods = $order_goods_model->where(['order_id'=>$value['order_id']])->select();
            foreach($order_goods as $og_info){
                $express_id = $oc_model->where(['order_id'=>$value['order_id']])->value('shipping_express_id');
                $express_name = $ex_model->where(['express_id'=>$express_id])->value('express_name');
                $body = json_encode([
                    'goods_name'  => $og_info['goods_name'],
                    'order_sn'    => $value['order_sn'],
                    'goods_image' => config('qiniu.buckets')['images']['domain'].'/uploads/product/'.$og_info['goods_image'],
                    'express'     => $express_name,
                    'title'       => '您的'.$og_info['goods_name'].'订单已完成，祝您使用愉快！'
                ]);
                $message = [
                    'from_member_id' => 0,
                    'to_member_id'   => $value['buyer_id'],
                    'message_title'  => '订单已完成',
                    'message_time'   => time(),
                    'message_state'  => 0,
                    'message_type'   => 5,
                    'is_more'        => 0,
                    'is_push'        => 2,
                    'message_body'   => $body,
                    'push_detail'    => '您的'.$og_info['goods_name'].'订单已完成，祝您使用愉快！',
                    'push_title'     => '订单已完成'
                ];
                $res = $message_model->insertGetId($message);
                if($res === false){
                    $order_model->rollback();
                    $log_model->rollback();
                    $car_order && $order_partition_mdoel->rollback();
                    continue 2;
                }
            }

            $order_model->commit();
            $log_model->commit();
            $message_model->commit();
            $car_order && $order_partition_mdoel->commit();
        }
    }

    /**
     *	订单完成操作
     */
    private function _return_finished(){
        $pl_model = db('PointsLog');
        $member_model = db('Member');
        $order_model = db('Order');
        $og_model = db('OrderGoods');
        $log_model = db('OrderLog');

        $order_data = $order_model->where(['order_state'=>40])->select();

        foreach ($order_data as $key => $value) {
            $time = time() - $value['sign_time'];
            if($time < 604800){
                continue;
            }

            $pl_model->startTrans();
            $member_model->startTrans();
            $order_model->startTrans();
            $log_model->startTrans();
            $order_data = [
                'order_state' => 50,
                'finnshed_time' => time()
            ];
            $order_result = $order_model->where(['order_id' => $value['order_id']])->update($order_data);
            if (empty($order_result)) {
                continue;
            }

            $log_data = [
                'order_id' => $value['order_id'],
                'log_msg' => '订单于'.date('Y-m-d H:i:s').'自动完成,完成订单编号'.$value['order_sn'],
                'log_time' => time(),
                'log_role' => '系统',
                'log_user' => 'admin',
                'log_orderstate' =>50
            ];
            $log_result = $log_model->insert($log_data);
            if (empty($log_result)) {
                continue;
            }

            $member_info = $member_model->where(['member_id'=>$value['buyer_id']])->field('member_id,parent_id,member_grade,experience,member_name')->find();

            $og_result = $og_model->where(['order_id'=>$value['order_id']])->find();
            $goods_points = !empty($og_result['goods_points']) ? $og_result['goods_points'] : 0;
            $gp_points = !empty($og_result['goods_parent_points']) ? $og_result['goods_parent_points'] : 0;
            $member_grade = $grade_points = 0;


            //写入积分日志
            if($goods_points > 0){
                if($gp_points > 0){
                    //用户升级
                    if(isset($member_grade)  && $member_grade > 0){
                        $up_grade = ['upgrade'=>$to_grade,'grade_return'=>$grade_points,'from_member'=>$member_info['member_id'],'customer'=>$member_info['member_name'],'consume_num'=>$value['order_amount']];
                        $parent_points['grade_return'] = $gp_points;

                        if($is_return > 0){
                            $pl_log = [
                                ['member_id'=>$value['buyer_id'],'points'=>$goods_points,'add_time'=>time(),'pl_desc'=>'订单完成,冻结积分转为可用积分','type' =>'order_add','pl_data' => $json_data],
                                ['member_id' => $member_info['parent_id'],'points' => $gp_points,'add_time' => time(),'pl_desc' => '订单完成,赠送上级冻结积分转为可用积分','type' =>'order_parent_present','pl_data' => json_encode($parent_points)],
                            ];

                            $gps_points = $gp_points;
                        }else{
                            $pl_log = [
                                ['member_id'=>$value['buyer_id'],'points'=>$goods_points,'add_time'=>time(),'pl_desc'=>'订单完成,冻结积分转为可用积分','type' =>'order_add','pl_data' => $json_data],
                                ['member_id' => $member_info['parent_id'],'points' => $gp_points,'add_time' => time(),'pl_desc' => '订单完成,赠送上级冻结积分转为可用积分','type' =>'order_parent_present','pl_data' => json_encode($parent_points)],
                            ];

                            $gps_points = $grade_points + $gp_points;
                        }
                    }else{
                        $pl_log = [
                            ['member_id'=>$value['buyer_id'],'points'=>$goods_points,'add_time'=>time(),'pl_desc'=>'订单完成,冻结积分转为可用积分','type' =>'order_add','pl_data' => $json_data],
                            ['member_id' => $member_info['parent_id'],'points' => $gp_points,'add_time' => time(),'pl_desc' => '订单完成,赠送上级冻结积分转为可用积分','type' =>'order_parent_present','pl_data' => json_encode($parent_points)],
                        ];

                        $gps_points = $gp_points;
                    }

                    $result = $pl_model->insertAll($pl_log);
                    if(empty($result)){
                        $log_model->rollback();
                        $order_model->rollback();
                        continue;
                    }
                }else{
                    if(isset($member_grade)  && $member_grade > 0){
                        $up_grade = ['upgrade'=>$to_grade,'grade_return'=>$grade_points,'from_member'=>$member_info['member_id'],'customer'=>$member_info['member_name'],'consume_num'=>$value['order_amount']];
                        if($is_return > 0){
                            $pl_log = ['member_id'=>$value['buyer_id'],'points'=>$goods_points,'add_time'=>time(),'pl_desc'=>'订单完成,冻结积分转为可用积分','type' =>'order_add','pl_data' => $json_data];
                            $result = $pl_model->insert($pl_log);

                        }else{
                            $pl_log = [
                                ['member_id'=>$value['buyer_id'],'points'=>$goods_points,'add_time'=>time(),'pl_desc'=>'订单完成,冻结积分转为可用积分','type' =>'order_add','pl_data' => $json_data],
                            ];
                            $gps_points = $grade_points;

                            $result = $pl_model->insertAll($pl_log);
                        }

                        if(empty($result)){
                            $log_model->rollback();
                            $order_model->rollback();
                            continue;
                        }
                    }else{
                        $pl_log = [
                            'member_id' => $value['buyer_id'],
                            'points' => $goods_points,
                            'add_time' => time(),
                            'pl_desc' => '订单完成,冻结积分转为可用积分',
                            'type' => 'order_add',
                            'pl_data' => $json_data
                        ];

                        $result = $pl_model->insert($pl_log);
                        if(empty($result)){
                            $log_model->rollback();
                            $order_model->rollback();
                            continue;
                        }
                    }
                }
            }else{

                if($gp_points > 0){
                    $parent_points['grade_return'] = $gp_points;
                    //用户升级
                    if(isset($member_grade)  && $member_grade > 0){
                        $up_grade = ['upgrade'=>$to_grade,'grade_return'=>$grade_points,'from_member'=>$member_info['member_id'],'customer'=>$member_info['member_name'],'consume_num'=>$value['order_amount']];

                        if($is_return > 0){
                            $pl_log = ['member_id' => $member_info['parent_id'],'points' => $gp_points,'add_time' => time(),'pl_desc' => '订单完成,赠送上级冻结积分转为可用积分','type' =>'order_parent_present','pl_data' => json_encode($parent_points)];
                            $gps_points = $gp_points;
                            $result = $pl_model->insert($pl_log);
                        }

                        if(empty($result)){
                            $log_model->rollback();
                            $order_model->rollback();
                            continue;
                        }
                    }else{
                        $pl_log = ['member_id' => $member_info['parent_id'],'points' => $gp_points,'add_time' => time(),'pl_desc' => '订单完成,赠送上级冻结积分转为可用积分','type' =>'order_parent_present','pl_data' => json_encode($parent_points)];
                        $gps_points = $gp_points;

                        $result = $pl_model->insert($pl_log);

                        if(empty($result)){
                            $log_model->rollback();
                            $order_model->rollback();
                            continue;
                        }
                    }
                }else{
                    if(isset($member_grade)  && $member_grade > 0){
                        if($is_return < 1){
                            $up_grade = ['upgrade'=>$to_grade,'grade_return'=>$grade_points,'from_member'=>$member_info['member_id'],'customer'=>$member_info['member_name'],'consume_num'=>$value['order_amount']];
                            if($is_return < 1){
                                $pl_log = ['member_id' => $member_info['parent_id'],'points' => $grade_points,'add_time' => time(),'pl_desc' => '用户升级,赠送上级积分','type' =>'grade_return_present','pl_data' => json_encode($up_grade)];
                                $gps_points = $grade_points;
                                $result = $pl_model->insert($pl_log);
                                if(empty($result)){
                                    // $ex_model->rollback();
                                    $log_model->rollback();
                                    $order_model->rollback();
                                    continue;
                                }
                            }
                        }
                    }
                }
            }

            //7天后返积分(用户冻结积分、上级冻结积分)
            if($member_info['parent_id'] > 0){
                $ordinary_result = $member_model->where(['member_id'=>$value['buyer_id']])->inc('member_grade',$member_grade)->inc('points',$goods_points)->dec('freeze_points',$goods_points)->update();
                $parent_result = $member_model->where(['member_id'=>$member_info['parent_id']])->inc('points',$gps_points)->dec('freeze_points',$gp_points)->update();
            }else{
                $ordinary_result = $member_model->where(['member_id'=>$value['buyer_id']])->inc('member_grade',$member_grade)->inc('points',$goods_points)->dec('freeze_points',$goods_points)->update();
            }

            if(empty($ordinary_result) || (isset($parent_result) && $parent_result < 1)){
                $order_model->rollback();
                $pl_model->rollback();
                $order_model->rollback();
                continue;
            }

            $pl_model->commit();
            $member_model->commit();
            $order_model->commit();
            $log_model->commit();
            if(!empty($pl_log)){
                Hook::listen('create_points_log',$pl_log);
            }
        }

    }

    /**
     *	自动关闭订单
     */
    private function _order_close(){
        $order_model = db('Order');
        $log_model = db('OrderLog');
        $order_data = $order_model->where(['order_state'=>10])->select();

        foreach ($order_data as $key => $value) {
            $time = time();
            $time_space = $time - $value['create_time'];
            if ($time_space < self::ORDER_CLOSE_DELAY_TIME) {
                continue;
            }

            $order_model->startTrans();
            $log_model->startTrans();
            db('Member')->startTrans();
            db('PointsLog')->startTrans();
            db('PdLog')->startTrans();
            $order_data = [
                'order_state' => 0,
            ];
            $order_result = $order_model->where(['order_id' => $value['order_id']])->setField($order_data);

            if (empty($order_result)) {
                continue;
            }

            $log_data = [
                'order_id' => $value['order_id'],
                'log_msg' => '订单超时关闭,关闭订单号'.$value['order_sn'],
                'log_time' => time(),
                'log_role' => '系统',
                'log_user' => 'admin',
                'log_orderstate' => 0
            ];
            $log_result = $log_model->insert($log_data);
            if (empty($log_result)) {
                $order_model->rollback();
                continue;
            }

            if($value['pd_amount'] > 0 || $value['pos_amount'] > 0){
                $result = db('Member')->where(['member_id'=>$value['buyer_id']])->inc('points',$value['pos_amount'])->inc('av_predeposit',$value['pd_amount'])->update();
                if(empty($result)){
                    $order_model->rollback();
                    $log_model->rollback();
                    continue;
                }

                if($value['pos_amount'] > 0){
                    $points_log = [
                        'member_id' => $value['buyer_id'],
                        'type' => 'order_refund',
                        'points' => $value['pos_amount'],
                        'add_time' => time(),
                        'pl_desc' => '订单自动关闭,返回扣除积分'
                    ];

                    $result = db('PointsLog')->insert($points_log);
                    if(empty($result)){
                        $order_model->rollback();
                        $log_model->rollback();
                        db('Member')->rollback();
                        continue;
                    }
                }

                if($value['pd_amount'] > 0){
                    $pd_log = [
                        'member_id' => $value['buyer_id'],
                        'type' => 'refund',
                        'av_amount' => $value['pd_amount'],
                        'add_time' => time(),
                        'log_desc' => '订单自动关闭,返回扣除余额'
                    ];

                    $result = db('PdLog')->insert($pd_log);
                    if(empty($result)){
                        $order_model->rollback();
                        $log_model->rollback();
                        db('Member')->rollback();
                        db('PointsLog')->rollback();
                        continue;
                    }
                }

            }
            $order_model->commit();
            $log_model->commit();
            db('Member')->commit();
            db('PointsLog')->commit();
            db('PdLog')->commit();
            if(!empty($points_log)){
                Hook::listen('create_points_log',$points_log);
            }
        }
    }

    /**
     *  普通会员(6级)升级成为消费商
     */
    private function _member_upgrade(){
        $member_model = db('Member');
        $seller_model = db('StoreSeller');
        $message = db('Message');

        $member_data = $member_model->where(['member_grade'=>6])->select();

        if(!empty($member_data)){
            $member_model->startTrans();
            $seller_model->startTrans();
            $message->startTrans();
            foreach($member_data as $mem_key=>$mem_val){
                //判断当前用户是否可以越级升级
                $member = $member_model->where(['parent_id'=>$mem_val['member_id'],'member_grade'=>['gt',6]])->select();
                $num = count($member);
                $tmp = [];
                $plus_grade = 0;
                if($num >= 1){
                    foreach($member as $k=>$v){
                        if($v['is_pass'] < 1){
                            $tmp[] = $v['member_grade'];
                            $plus_grade++;
                        }
                    }
                    if($plus_grade > 0){
                        rsort($tmp);
                        $grade = $tmp[0];
                        $data = ['member_grade' => $grade, 'is_alert' => 1 ,'is_pass' => 1];
                        $res = $member_model->where(['member_id' => $mem_val['member_id']])->update($data);
                        if (empty($res)) {
                            continue;
                        }

                        $seller_data = [
                            'member_id' => $mem_val['member_id'],
                            'seller_mobile' => $mem_val['member_mobile'],
                            'apply_time' => time(),
                            'seller_state' => 2,
                            'seller_role' => 1
                        ];

                        $result = $seller_model->insert($seller_data);
                        if (empty($result)) {
                            $member_model->rollback();
                            continue;
                        }
                        $grade_name = db('Grade')->where(['grade'=>$grade])->value('grade_name');
                        $message_data = [
                            'from_member_id' => 0,
                            'to_member_id'   => $mem_val['member_id'],
                            'message_title'  => '您已升级成为'.$grade_name,
                            'push_detail'    => '您已升级成为'.$grade_name,
                            'push_title'     => '',
                            'message_time'   => time(),
                            'message_state'  => 0,
                            'message_type'   => 8,
                            'is_more'        => 0,
                            'is_push'        => 0,
                            'message_body'   => '现在起，您可以享受更高的权限和更多的奖励'
                        ];
                        $msg_res = $message->insert($message_data);
                        if($msg_res === false){
                            $member_model->rollback();
                            $seller_model->rollback();
                            continue;
                        }
                        continue;
                    }
                }
                //判断当前用户的绑定用户中有几个6级用户
                $next_num = $member_model->where(['parent_id'=>$mem_val['member_id'],'member_grade'=>6])->count();
                if($next_num >= 3) {
                    $data = ['member_grade' => 7, 'is_alert' => 1];
                    $result = $member_model->where(['member_id' => $mem_val['member_id']])->update($data);
                    if (empty($result)) {
                        continue;
                    }

                    $data = [
                        'member_id' => $mem_val['member_id'],
                        'seller_mobile' => $mem_val['member_mobile'],
                        'apply_time' => time(),
                        'seller_state' => 2,
                        'seller_role' => 1
                    ];

                    $result = $seller_model->insert($data);
                    if (empty($result)) {
                        $member_model->rollback();
                        continue;
                    }
                    $grade_name = db('Grade')->where(['grade'=>7])->value('grade_name');
                    $message_data = [
                        'from_member_id' => 0,
                        'to_member_id'   => $mem_val['member_id'],
                        'message_title'  => '您已升级成为'.$grade_name,
                        'push_detail'    => '您已升级成为'.$grade_name,
                        'push_title'     => '',
                        'message_time'   => time(),
                        'message_state'  => 0,
                        'message_type'   => 8,
                        'is_more'        => 0,
                        'is_push'        => 0,
                        'message_body'   => '现在起，您可以享受更高的权限和更多的奖励'
                    ];
                    $msg_res = $message->insert($message_data);
                    if($msg_res === false){
                        $member_model->rollback();
                        $seller_model->rollback();
                        continue;
                    }
                }
            }

            $member_model->commit();
            $seller_model->commit();
            $message->commit();
        }
    }

    /**
     * 消费商升级
     */
    private function _seller_upgrade(){
        $seller_model = db('StoreSeller');
        $member_model = db('Member');
        $message = db('Message');

        $seller_role = ['1','3'];
        $seller_info = $seller_model->field('member_id')->where(['seller_state'=>2,'seller_role'=>['in',$seller_role]])->select();

        $tmp = [];
        foreach($seller_info as $si_key=>$si_val){
            $self_grade = $member_model->where(['member_id'=>$si_val['member_id']])->value('member_grade');

            if($self_grade == 12){
                continue;
            }

            $member_info = $member_model->field('member_grade,member_id,is_pass')->where(['parent_id'=>$si_val['member_id'],'member_grade'=>['egt',7]])->select();
            $member_count = count($member_info);
            if($member_count >= 3){
                $self_grade = $member_model->where(['member_id'=>$si_val['member_id']])->value('member_grade');
                $equal_grade = $plus_grade = 0;
                $message->startTrans();
                $member_model->startTrans();

                foreach($member_info as $mi_key=>$mi_val){
                    if($mi_val['member_grade'] == $self_grade){
                        $equal_grade += 1;
                    }elseif($mi_val['member_grade'] > $self_grade){
                        if($mi_val['is_pass'] < 1){
                            $tmp[] = $mi_val['member_grade'];
                            $plus_grade += 1;
                        }
                    }
                }

                if($plus_grade > 0){
                    rsort($tmp);
                    $next_grade = $tmp[0];
                    $data = [
                        'member_grade' => $next_grade,
                        'is_pass' => 1,
                        'is_alert' => 1
                    ];
                    $result = $member_model->where(['member_id'=>$si_val['member_id']])->update($data);
                }

                if($equal_grade >= 3){
                    $next_grade = $self_grade + 1;
                    $data = [
                        'member_grade' => $next_grade,
                        'is_alert' => 1
                    ];
                    $result = $member_model->where(['member_id'=>$si_val['member_id']])->update($data);
                }
                if (isset($result)){
                    if($result === false){
                        continue;
                    }
                }

                if(isset($next_grade)){
                    $grade_name = db('Grade')->where(['grade'=>$next_grade])->value('grade_name');
                    $message_data = [
                        'from_member_id' => 0,
                        'to_member_id'   => $si_val['member_id'],
                        'message_title'  => '您已升级成为'.$grade_name,
                        'push_detail'    => '您已升级成为'.$grade_name,
                        'push_title'     => '',
                        'message_time'   => time(),
                        'message_state'  => 0,
                        'message_type'   => 8,
                        'is_more'        => 0,
                        'is_push'        => 0,
                        'message_body'   => '现在起，您可以享受更高的权限和更多的奖励'
                    ];
                    $msg_res = $message->insert($message_data);
                    if($msg_res === false){
                        $member_model->rollback();
                        continue;
                    }
                }

                $message->commit();
                $member_model->commit();
            }
        }
    }

    /*
     *	更改报备用户绑定关系
    */
    private function _change_custom(){
        $sm_model = db('SellerMember');
        $slg_model = db('SellerLog');
        $order_model = db('order');
        $StoreSeller_model = db('StoreSeller');
        $sm_data = $sm_model->alias('a')->join('sun_store_seller b','a.seller_id = b.seller_id')->where(['customer_state'=>1])->field('a.sm_id,a.seller_id,a.member_id,a.add_time,a.customer_name,b.seller_name,b.member_id smember_id')->select();

        $sm_id_arr = array();
        foreach ($sm_data as $key => $value) {
            $time = time();
            $time_space = $time - $value['add_time'];
            $sm_model->startTrans();
            $slg_model->startTrans();
            if ($time_space > 2592000) {

                $sm_data = [
                    'customer_state' => 0,
                ];

                $sm_result = $sm_model->where(['sm_id'=> $value['sm_id']])->setField('customer_state',0);
                if($sm_result === false){
                    continue;
                }

                $slg_data = [
                    'lg_sm_id' => $value['sm_id'],
                    'lg_type' => 'report_expire',
                    'lg_add_time' => time(),
                    'lg_desc' => '过期 : 销售员'. $value['seller_name'] .'与用户'.$value['customer_name'].'已过期'.date('Y-m-d H:i:s'),
                ];

                $slg_result = $slg_model->insert($slg_data);
                if ($slg_result === false) {
                    $sm_model->rollback();
                    continue;
                }

            }else{
                $has_info = $order_model->where(['buyer_id'=>$value['member_id'],'order_state'=>['egt',20]])->count();

                if($has_info > 0){
                    $sm_result = $sm_model->where(['sm_id'=>$value['sm_id']])->setField('customer_state',2);
                    if ($sm_result === false) {
                        continue;
                    }

                    $slg_data = [
                        'lg_sm_id' => $value['sm_id'],
                        'lg_type' => 'customer_bind',
                        'lg_add_time' => time(),
                        'lg_desc' => '绑定 : 销售员'. $value['seller_name'] .'与用户'.$value['customer_name'].'已绑定'.date('Y-m-d H:i:s'),
                    ];

                    $slg_result = $slg_model->insert($slg_data);
                    if ($slg_result === false) {
                        $sm_model->rollback();
                        continue;
                    }
                }
            }
            $sm_model->commit();
            $slg_model->commit();
        }
    }
}



