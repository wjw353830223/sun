<?php
namespace app\common\model;

use think\exception\PDOException;
use think\Hook;
use think\Model;
class Order extends Model
{
	// 关闭自动时间格式化
    protected $createTime = false;
    const PAY_TYPE_PREDEPOSIT = 1;//余额支付
    const PAY_TYPE_POINTS = 2;//积分支付
    const PAY_TYPE_THIRD = 4;//第三方支付

    protected $goods_attribute_model,$shop_car_model,$goods_common_model,$goods_images_model;
    protected $order_goods_model,$address_model,$order_common_model,$seller_member_model;
    protected $order_pay_model,$store_seller_model,$order_log_model,$member_model;
    protected $grade_model,$message_model,$experience_log_model,$points_log_model,$goods_model;
    protected $order_partition_model,$pd_model,$express_model,$goods_log_model;
    protected function initialize(){
        parent::initialize();
        $this->goods_attribute_model = model('GoodsAttribute');
        $this->shop_car_model = model('ShopCar');
        $this->goods_common_model = model('GoodsCommon');
        $this->goods_images_model = model('GoodsImages');
        $this->order_goods_model = model('OrderGoods');
        $this->address_model = model('Address');
        $this->order_common_model = model('OrderCommon');
        $this->seller_member_model = model('SellerMember');
        $this->order_pay_model = model('OrderPay');
        $this->store_seller_model = model('StoreSeller');
        $this->order_log_model = model("OrderLog");
        $this->member_model = model('Member');
        $this->grade_model = model('Grade');
        $this->message_model = model('Message');
        $this->experience_log_model = model('ExperienceLog');
        $this->points_log_model = model('PointsLog');
        $this->pd_model = model('PdLog');
        $this->order_partition_model = model('OrderPartition');
        $this->express_model = model('Express');
        $this->goods_model = model('Goods');
        $this->goods_log_model = model('GoodsLog');
    }

    /**
	* 商品详细关联
	*/
	public function goodsInfo(){
		return $this->hasOne('OrderGoods','order_id','order_id')->field('order_id,goods_commonid,goods_name,goods_price,goods_num,gc_id,goods_id,spec_value,spec_name');
	}
    /**
     * 商品详细关联
     */
    public function goodsInfos(){
        return $this->hasMany('OrderGoods','order_id','order_id')->field('order_id,goods_commonid,goods_name,goods_price,goods_num,gc_id,goods_id,is_scan_code');
    }
    /**
     * 关联订单信息扩展表
     */
    public function orderCommons(){
        return $this->hasMany('OrderCommon','order_id','order_id')->field('order_id,shipping_express_id,shipping_time,shipping_code,order_partition_id');
    }
	/**
	 * 生成支付单编号(两位随机 + 从2000-01-01 00:00:00 到现在的秒数+微秒+会员ID%1000)，该值会传给第三方支付接口
	 * 长度 =2位 + 10位 + 3位 + 3位  = 18位
	 * 1000个会员同一微秒提订单，重复机率为1/100
	 * @return string
	 */
	public function make_paysn($member_id) {
		return mt_rand(10,99)
		      . sprintf('%010d',time() - 946656000)
		      . sprintf('%03d', (float) microtime() * 1000)
		      . sprintf('%03d', (int) $member_id % 1000);
	}

	/**
	 * 订单编号生成规则，n(n>=1)个订单表对应一个支付表，
	 * 生成订单编号(年取1位 + $pay_id取13位 + 第N个子订单取2位)
	 * 1000个会员同一微秒提订单，重复机率为1/100
	 * @param $pay_id 支付表自增ID
	 * @return string
	 */
	public function make_ordersn($pay_id) {
	    //记录生成子订单的个数，如果生成多个子订单，该值会累加
	    static $num;
	    if (empty($num)) {
	        $num = 1;
	    } else {
	        $num ++;
	    }
		return (date('y',time()) % 9+1) . sprintf('%013d', $pay_id) . sprintf('%02d', $num);
	}
    /**
     * 获取用户下单支付方式
     * @param $points 下单积分 需引用传值 支付的积分需修正 防止用户多花积分
     * @param $predeposit 下单余额 需引用传值 支付的金额大于订单总金额则修正支付金额
     * @param $order_amount 订单总金额
     * @param int $is_points 是否使用积分
     * @param int $is_predeposit 是否使用余额
     * @return int/false
     */
    public function order_pay_type(&$points, &$predeposit, $order_amount, $member_id, $is_points=0, $is_predeposit=0){
        $member_info = $this->member_model->getByMemberId($member_id);
        $pay_type = Order::PAY_TYPE_THIRD;
        if($is_points > 0 ){
	        if($points > $member_info['points']){
                return false;
            }
            $pay_type = Order::PAY_TYPE_POINTS;
            $is_enough = round($points - $order_amount,2) >= 0 ? 1 : 0;
            if(!$is_enough){
                $pay_type = Order::PAY_TYPE_POINTS + Order::PAY_TYPE_THIRD;
                if($is_predeposit > 0){
                    if($predeposit > $member_info['av_predeposit']){
                        return false;
                    }
                    $pay_type = Order::PAY_TYPE_POINTS + Order::PAY_TYPE_PREDEPOSIT;
                    $amount = number_format($predeposit + $points,2,'.','');
                    if($amount < $order_amount){
                        $pay_type = Order::PAY_TYPE_PREDEPOSIT + Order::PAY_TYPE_POINTS + Order::PAY_TYPE_THIRD;
                    }else{
                        $predeposit = $order_amount - $points;
                    }
                }else{
                    $predeposit = 0;
                }
            }else{
                $points = $order_amount;
                $predeposit = 0;
            }
        }
        if($is_points <= 0 && $is_predeposit > 0){
            $points = 0;
            if($predeposit > $member_info['av_predeposit']){
                return false;
            }
            $pay_type = Order::PAY_TYPE_PREDEPOSIT;
            if($predeposit < $order_amount){
                $pay_type = Order::PAY_TYPE_PREDEPOSIT + Order::PAY_TYPE_THIRD;
            }else{
                $predeposit = $order_amount;
            }
        }
        if($is_predeposit <= 0 ){
            $predeposit = 0;
        }
        return $pay_type;
    }
    /**
     * 选择支付方式下单
     * @param $member_info
     * @param $buy_info
     * @param int $predeposit
     * @param int $points
     * @param int $pay_type
     * @return array|bool|
     */
    public function create_order($member_info,$buy_info,$predeposit = 0, $points = 0, $pay_type = 4){
        //下单支付方式：第三方支付 积分+第三方支付 余额+第三方支付 积分+余额+第三方支付
        if($pay_type & Order::PAY_TYPE_THIRD ){
            $data = $this->predeposit_or_points_and_third_order($member_info,$buy_info,$predeposit,$points);
            return $data;
        }
        //下单支付方式：积分支付 余额支付 积分+余额支付
        if($pay_type & Order::PAY_TYPE_POINTS || $pay_type & Order::PAY_TYPE_PREDEPOSIT){
            $data = $this->predeposit_or_points_order($member_info,$buy_info,$predeposit,$points);
            return $data;
        }
        return false;
    }

    /**
     * 购物车商品结算
     * @param $member_info
     * @param $buy_info
     * @param int $predeposit
     * @param int $points
     * @param int $pay_type
     * @return array|bool
     */
    public function create_car_order($member_info,$buy_info,$predeposit = 0, $points = 0, $pay_type = 4){
        //下单支付方式：第三方支付 积分+第三方支付 余额+第三方支付 积分+余额+第三方支付
        if($pay_type & Order::PAY_TYPE_THIRD ){
            $data = $this->predeposit_or_points_and_third_order($member_info,$buy_info,$predeposit,$points);
            return $data;
        }
        //下单支付方式：积分支付 余额支付 积分+余额支付
        if($pay_type & Order::PAY_TYPE_POINTS || $pay_type & Order::PAY_TYPE_PREDEPOSIT){
            $data = $this->predeposit_or_points_order($member_info,$buy_info,$predeposit,$points);
            return $data;
        }
        return false;
    }
    /**
     * 积分或余额和第三方支付下单
     * @param $predeposit
     * @param $points
     * @param $member_info
     * @param $buy_info
     * @return array|bool
     */
    public function predeposit_or_points_and_third_order($member_info, $buy_info, $predeposit = 0,$points = 0){
        $pay_type_has_predeposit = !empty($predeposit) ? true : false;
        $pay_type_has_points = !empty($points) ? true : false;
        $payment_code = empty($points) && empty($predeposit) ? '' : 'mixed';
        $car_order = isset($buy_info['car_ids'])?true:false;
        $member_data = [];
        if(!empty($points)){
            $member_data['points'] = $member_info['points'] - $points;
        }
        if(!empty($predeposit)){
            $member_data['av_predeposit'] = $member_info['av_predeposit'] - $predeposit;
        }
        if(!empty($member_data)){
            $this->member_model->startTrans();
            $result = $this->member_model->save($member_data,['member_id' => $member_info['member_id']]);
            if ($result === false) {
                return false;
            }
        }
        if($pay_type_has_points){
            $this->points_log_model->startTrans();
            $result = $this->points_log_model->save_log($member_info['member_id'], $member_info['member_mobile'],
                'order_pay',$points, '用户支付订单');
            if($result === false){
                if(!empty($member_data)){
                    $this->member_model->rollback();
                }
                return false;
            }
        }
        if($pay_type_has_predeposit){
            $this->pd_model->startTrans();
            $result = $this->pd_model->save_log($member_info['member_id'],
                $member_info['member_mobile'],$predeposit);
            if($result === false){
                if(!empty($member_data)){
                    $this->member_model->rollback();
                }
                return false;
            }
        }
        $this->order_pay_model->startTrans();
        $this->startTrans();
        $this->order_goods_model->startTrans();
        $this->order_common_model->startTrans();
        $this->order_log_model->startTrans();
        $pay_info = [
            'pos_amount' => $points,
            'pd_amount' => $predeposit,
            'order_state' => 10,
            'payment_code' => $payment_code,
        ];
        if(($order_sn = $this->order_gen_order_record($member_info,$buy_info,$pay_info)) == false){
            $this->order_pay_model->rollback();
            $this->rollback();
            $this->order_goods_model->rollback();
            $this->order_common_model->rollback();
            $this->order_log_model->rollback();
            if($pay_type_has_predeposit) {
                $this->pd_model->rollback();
            }
            if($pay_type_has_points) {
                $this->points_log_model->rollback();
            }
            if(!empty($member_data)){
                $this->member_model->rollback();
            }
            return false;
        }
        if($car_order){
            //删除购物车中的商品
            $this->shop_car_model->startTrans();
            if(!$this->shop_car_model->delete_products($member_info['member_id'], $buy_info['car_ids'])){
                !empty($member_data) && $this->member_model->rollback();
                $this->order_pay_model->rollback();
                $this->rollback();
                $this->order_goods_model->rollback();
                $this->order_common_model->rollback();
                $this->order_log_model->rollback();
                $pay_type_has_predeposit && $this->pd_model->rollback();
                $pay_type_has_points && $this->points_log_model->rollback();
                return false;
            }
        }
        $this->order_pay_model->commit();
        $this->commit();
        $this->order_goods_model->commit();
        $this->order_common_model->commit();
        $this->order_log_model->commit();
        if($pay_type_has_predeposit) {
            $this->pd_model->commit();
        }
        if($pay_type_has_points) {
            $this->points_log_model->commit();
        }
        if(!empty($member_data)){
            $this->member_model->commit();
        }
        $car_order && $this->shop_car_model->commit();
        return [
            'is_pay' => 1,
            'order_sn' =>  $order_sn
        ];
    }
    /**
     * 积分或余额支付下单
     * @param $predeposit
     * @param $points
     * @param $member_info
     * @param $buy_info
     * @return array|bool
     */
    public function predeposit_or_points_order($member_info, $buy_info, $predeposit = 0, $points = 0){
        if(empty($predeposit) && empty($points)){
            return false;
        }
        $pay_type_has_predeposit = !empty($predeposit) ? true : false;
        $payment_code = !empty($predeposit) && !empty($points) ? 'mixed' : (!empty($predeposit) ? 'predeposit' : 'points');
        $this->member_model->startTrans();
        $this->points_log_model->startTrans();
        $this->message_model->startTrans();
        $this->experience_log_model->startTrans();
        $car_order = isset($buy_info['car_ids'])?true:false;
        if($pay_type_has_predeposit){
            $this->pd_model->startTrans();
        }
        if(!$car_order){
            $goods_common = $this->goods_common_model->where('goods_commonid',$buy_info['goods_commonid'])->find();
        }
        //账户增加经验和余额
        if((!$car_order && !$this->order_get_points_and_experience($goods_common, $member_info,
                $buy_info['goods_num'], $points, $predeposit))
            || ($car_order && !$this->car_get_points_and_experience($buy_info['car_ids'], $member_info,
                    $points, $predeposit))){
            $this->member_model->rollback();
            $this->points_log_model->rollback();
            $this->experience_log_model->rollback();
            return false;
        }
        //会员升级
        $seller = $this->store_seller_model->where(['member_id'=>$member_info['member_id']])->find();
        if(empty($seller) || $seller['seller_role'] == 2){
            if(!$this->order_member_upgrade($member_info['member_id'])){
                $this->member_model->rollback();
                $this->points_log_model->rollback();
                $this->experience_log_model->rollback();
                $this->message_model->rollback();
                return false;
            }
        }
        //用户上级获取积分
        if((!$car_order && !$this->order_parent_get_points($member_info, $goods_common, $buy_info['goods_num']))
            || ($car_order && !$this->car_parent_get_points($member_info, $buy_info['car_ids']))){
            $this->member_model->rollback();
            $this->points_log_model->rollback();
            $this->experience_log_model->rollback();
            $this->message_model->rollback();
            return false;
        }
        //记录扣除余额日志
        if($pay_type_has_predeposit && !$this->pd_model->save_log($member_info['member_id'],$member_info['member_mobile'],$predeposit)){
           $this->member_model->rollback();
           $this->points_log_model->rollback();
           $this->experience_log_model->rollback();
           $this->message_model->rollback();
           return false;
        }
        $pay_info = [
            'pos_amount' => $points,
            'pd_amount' => $predeposit,
            'order_state' => 20,
            'payment_code' => $payment_code,
        ];
        $this->order_pay_model->startTrans();
        $this->startTrans();
        $this->order_goods_model->startTrans();
        $this->order_common_model->startTrans();
        $this->order_log_model->startTrans();
        $car_order  && $this->order_partition_model->startTrans();
        $car_order  && $this->shop_car_model->startTrans();
        //生成订单相关记录
        if(($order_sn = $this->order_gen_order_record($member_info,$buy_info,$pay_info)) == false){
            $this->order_pay_model->rollback();
            $this->rollback();
            $this->order_goods_model->rollback();
            $this->order_common_model->rollback();
            $this->order_log_model->rollback();
            $this->member_model->rollback();
            $this->points_log_model->rollback();
            $this->experience_log_model->rollback();
            $this->message_model->rollback();
            $car_order  && $this->order_partition_model->rollback();
            $pay_type_has_predeposit  && $this->pd_model->rollback();
            return false;
        }
        if($car_order){
            //删除购物车中的商品
            if(!$this->shop_car_model->delete_products($member_info['member_id'], $buy_info['car_ids'])){
                $this->member_model->rollback();
                $this->order_pay_model->rollback();
                $this->rollback();
                $this->order_goods_model->rollback();
                $this->order_common_model->rollback();
                $this->points_log_model->rollback();
                $this->order_log_model->rollback();
                $this->message_model->rollback();
                $this->experience_log_model->rollback();
                $car_order  && $this->order_partition_model->rollback();
                $pay_type_has_predeposit && $this->pd_model->rollback();
                return false;
            }
        }
        $this->member_model->commit();
        $this->order_pay_model->commit();
        $this->commit();
        $this->order_goods_model->commit();
        $this->order_common_model->commit();
        $this->points_log_model->commit();
        $this->order_log_model->commit();
        $this->message_model->commit();
        $this->experience_log_model->commit();
        $car_order  && $this->order_partition_model->commit();
        $car_order  && $this->shop_car_model->commit();
        if($pay_type_has_predeposit){
            $this->pd_model->commit();
        }
        return [
            'is_pay' => 0,
            'order_sn' =>  $order_sn
        ];
    }

    /**
     * 用户下单成功上级用户积分变更情况
     * @param $member_info
     * @param $goods_common
     * @param int $goods_num
     * @return bool
     */
    public function order_parent_get_points($member_info, $goods_common, $goods_num = 1){
        if(empty($member_info['parent_id'])){
            return true;
        }
        $parent_points = $goods_common['goods_parent_points'] * $goods_num;
        if(empty($parent_points)){
            return true;
        }
        $goods_parent_points = $this->member_model->where(['member_id'=>$member_info['parent_id']])
            ->setInc('freeze_points',$parent_points);
        if($goods_parent_points === false){
           return false;
        }
        //积分变更记录
        $parent_member = $this->member_model->where(['member_id'=>$member_info['parent_id']])->find();
        $pl_data = ['goods_name'=> $goods_common['goods_name']];
        $result = $this->points_log_model
            ->save_log($member_info['parent_id'],$parent_member->member_mobile,'order_return_parent', $parent_points,'用户消费赠送上级冻结积分',json_encode($pl_data));
        if($result === false){
            return false;
        }
        //消息记录
        $push_detail = '您的家族成员消费奖励您'.$parent_points.'积分';
        $body = json_encode(['title'=>'积分奖励','points'=>'+'.$parent_points]);
        $message = [
            'from_member_id' => 0,
            'to_member_id'   => $member_info['parent_id'],
            'message_title'  => '积分奖励',
            'message_time'   => time(),
            'message_state'  => 0,
            'message_type'   => 4,
            'is_more'        => 0,
            'is_push'        => 2,
            'message_body'   => $body,
            'push_title'     => '',
            'push_detail'    => $push_detail
        ];
        return $res = $this->message_model->insertGetId($message);
    }

    /**
     * 购物车结算成功上级用户积分变更情况
     * @param $member_info
     * @param $car_ids
     * @return bool
     */
    public function car_parent_get_points($member_info, $car_ids){
        if(empty($member_info['parent_id'])){
            return true;
        }
        if(empty($car_ids) || !is_array($car_ids)){
            return false;
        }
        $car_goods_info = $this->shop_car_model->car_products_points_and_experiences($car_ids);
        foreach($car_ids as $car_id){
            $parent_points = $car_goods_info['goods'][$car_id]['goods_parent_points'];
            if(empty($parent_points)){
                continue;
            }
            $goods_parent_points = $this->member_model->where(['member_id'=>$member_info['parent_id']])
                ->setInc('freeze_points',$parent_points);
            if($goods_parent_points === false){
                return false;
            }
            $goods_name = $this->shop_car_model->where('id',$car_id)->value('good_name');
            //积分变更记录
            $parent_member = $this->member_model->getByMemberId($member_info['parent_id']);
            $pl_data = ['goods_name'=> $goods_name];
            $result = $this->points_log_model
                ->save_log($member_info['parent_id'],$parent_member->member_mobile,'order_return_parent', $parent_points,'用户消费赠送上级冻结积分',json_encode($pl_data));
            if($result === false){
                return false;
            }
            //消息记录
            $push_detail = '您的家族成员消费奖励您'.$parent_points.'积分';
            $body = json_encode(['title'=>'积分奖励','points'=>'+'.$parent_points]);
            $message = [
                'from_member_id' => 0,
                'to_member_id'   => $member_info['parent_id'],
                'message_title'  => '积分奖励',
                'message_time'   => time(),
                'message_state'  => 0,
                'message_type'   => 4,
                'is_more'        => 0,
                'is_push'        => 2,
                'message_body'   => $body,
                'push_title'     => '',
                'push_detail'    => $push_detail
            ];
            $res = $this->message_model->insertGetId($message);
            if(!$res){
                return false;
            }
        }
        return true;
    }
    /**
     * 用户下单积分和经验变更
     * @param $goods_common
     * @param $member_info
     * @param int $goods_num
     * @param int $points
     * @param int $predeposit
     * @return bool
     */
    public function order_get_points_and_experience($goods_common, $member_info, $goods_num =1, $points=0, $predeposit=0){
        //用户账户积分 经验 冻结积分 余额变更
        $result = $this->member_model->save_all($member_info['member_id'],
            $points,$goods_common['goods_experience'] * $goods_num,$goods_common['goods_present'] * $goods_num,$predeposit);
        if($result === false){
            return false;
        }
        //积分变更记录
        if($goods_common['goods_present'] > 0){
            $pl_data = ['goods_name'=>$goods_common['goods_name']];
            $pl_data = json_encode($pl_data);
            $result = $this->points_log_model->save_logs($member_info['member_id'],$member_info['member_mobile'],$points,$goods_common['goods_present'] * $goods_num,$pl_data);
        }elseif($goods_common['goods_present'] == 0){
            $result = $this->points_log_model->save_log($member_info['member_id'],$member_info['member_mobile'],'order_pay',$points,'用户支付订单');
        }
        if($result === false){
            return false;
        }
        //经验变更记录
        if($goods_common['goods_experience'] > 0){
            $ex_res = $this->experience_log_model->save_log($member_info['member_id'],$member_info['member_mobile'], $goods_common['goods_experience'] * $goods_num);
            if($ex_res === false){
                return false;
            }
        }
        return true;
    }

    /**
     * 购物车结算积分和经验变更
     * @param $car_ids
     * @param $member_info
     * @param int $points
     * @param int $predeposit
     * @return bool
     */
    public function car_get_points_and_experience($car_ids, $member_info,$points=0, $predeposit=0){
        if(empty($car_ids)){
            return false;
        }
        //用户账户积分 经验 冻结积分 余额变更
        $car_goods_info = $this->shop_car_model->car_products_points_and_experiences($car_ids);
        $goods_present = $car_goods_info['goods_present'];
        $goods_experience = $car_goods_info['goods_experience'];
        $result = $this->member_model->save_all($member_info['member_id'],
            $points,$goods_experience,$goods_present,$predeposit);
        if($result === false){
            return false;
        }
        if($points > 0){
            $result = $this->points_log_model->save_log($member_info['member_id'],$member_info['member_mobile'],
                'order_pay',$points,'用户支付订单');
        }
        if($result === false){
            return false;
        }
        //积分变更记录
        if($goods_present > 0){
           foreach($car_ids as $car_id){
               $present = $car_goods_info['goods'][$car_id]['goods_present'];
               if(!empty($present)){
                   $goods_name = $this->shop_car_model->where('id',$car_id)->value('good_name');
                   $pl_data = ['goods_name'=>$goods_name];
                   $pl_data = json_encode($pl_data);
                   $result = $this->points_log_model->save_log($member_info['member_id'],$member_info['member_mobile'],
                       'order_freeze',$present,'创建订单获得冻结积分',$pl_data);
                   if($result === false){
                       return false;
                   }
               }
           }
        }
        //经验变更记录
        if($goods_experience > 0){
            $ex_res = $this->experience_log_model->save_log($member_info['member_id'],$member_info['member_mobile'],
                $goods_experience);
            if($ex_res === false){
                return false;
            }
        }
        return true;

    }
    /**
     * 库管员/普通消费者  升级
     * @param $member_id 买家id
     * @return bool|int|string
     */
    public function order_member_upgrade($member_id){
        $seller = $this->store_seller_model->where(['member_id'=>$member_id])->find();
        //判断当前用户是否为消费商
        if(empty($seller) || $seller['seller_role'] == 2){
            //判断当前会员是否可以升级
            $member = $this->member_model
                ->where(['member_id'=>$member_id])
                ->field('experience,member_grade')
                ->find();
            if($member['member_grade'] > 6){
                return true;
            }
            $where = [
                'grade_points' => ['elt',$member['experience']],
                'grade_type'   => 1,
                'grade'        => ['gt',$member['member_grade']]
            ];
            $grade = $this->grade_model->where($where)->order('grade desc')->find();
            if(!empty($grade)){
                $grade_res = $this->member_model
                    ->where(['member_id'=>$member_id])
                    ->update(['member_grade'=>$grade['grade'],'is_alert'=>1]);
                if($grade_res === false){
                    return false;
                }
                $message = [
                    'from_member_id' => 0,
                    'to_member_id'   => $member_id,
                    'message_title'  => '您已升级成为消费者LV.'.$grade['grade'],
                    'push_detail'    => '您已升级成为消费者LV.'.$grade['grade'],
                    'push_title'     => '',
                    'message_time'   => time(),
                    'message_state'  => 0,
                    'message_type'   => 8,
                    'is_more'        => 0,
                    'is_push'        => 0,
                    'message_body'   => '现在起，您可以享受更高的权限和更多的奖励'
                ];
                return $this->message_model->insertGetId($message);
            }
            return true;
        }
        return true;
    }

    /**
     * 生成订单信息、订单商品信息、收货地址信息、订单信息扩展、订单处理历史记录
     * @param $member_info 用户信息
     * @param $buy_info 买家购买信息 $buy_info['business_type','true_name','mobile','address_id','goods_num','goods_commonid','address_id']
     * @param $pay_info 支付信息 $pay_info['order_state','payment_code','pos_amount','pd_amount']
     * @return bool
     */
    public function order_gen_order_record($member_info,$buy_info,$pay_info){
        $pay_sn = $this->gen_order_pay_record($member_info['member_id']);
        if(!$pay_sn){
            return false;
        }
        $order_sn = $this->gen_order_sn($pay_sn);
        if(!$order_sn){
            return false;
        }
        $referee_id = $this->get_referee_id($member_info['member_id']);
        $order_id = $this->gen_order_common_record($pay_sn,$order_sn,$referee_id,$member_info['member_id'],
            $buy_info,$pay_info);
        if(!$order_id){
            return false;
        }
        $order_state = !empty($pay_info['order_state']) ? $pay_info['order_state'] : 10;
        if(isset($buy_info['car_ids'])){
            if(!$this->gen_order_partition($pay_sn,$order_sn,
                $member_info['member_id'],$referee_id,$buy_info,$order_state)){
                return false;
            }
            $order_partitions = $this->order_partition_model->where(['order_sn'=>$order_sn,'pay_sn'=>$pay_sn])->select()->toArray();
            foreach($order_partitions as $order_partition){
                if(!$this->gen_order_address_record($order_id,$buy_info['address_id'],$buy_info['mobile'],$order_partition['id'])){
                    return false;
                }
            }
        }else{
            if(!$this->gen_order_address_record($order_id,$buy_info['address_id'],$buy_info['mobile'])){
                return false;
            }
        }
        if(!$this->gen_order_product_record($order_id,$buy_info)){
            return false;
        }
        if(isset($pay_info['order_state']) && $pay_info['order_state'] == 20){
            $result = $this->order_log_model
                ->save_log($member_info['member_name'],$pay_info['pos_amount'],$pay_info['pd_amount'],$order_sn,$order_id);
            if($result === false){
                return false;
            }
        }
        return $order_sn;
    }
    /**
     * 生成订单商品信息
     * @param $order_id
     * @param $buy_info
     * @return bool
     */
    public function gen_order_product_record($order_id,$buy_info){
        if(empty($buy_info)){
            return false;
        }
        if(!isset($buy_info['car_ids']) && !isset($buy_info['ga_data'])){
            return false;
        }
        if(isset($buy_info['ga_data'])){
            $goods_common = $this->goods_common_model->where('goods_commonid',$buy_info['goods_commonid'])->find();
            $goods_image = $this->goods_images_model->where(['goods_commonid'=>$buy_info['goods_commonid']])->field('goods_image')->find();
            if(!empty($goods_image)){
                $image = $goods_image['goods_image'];
            }else{
                $image = $goods_common['goods_image'];
            }
            $og_data = [
                'order_id'            => $order_id,
                'goods_commonid'      => $buy_info['goods_commonid'],
                'gc_id'               => $goods_common['gc_id'],
                'goods_name'          => $goods_common['goods_name'],
                'goods_image'         => $image,
                'goods_price'         => $buy_info['ga_data']['goods_price'],
                'cost_price'          => $buy_info['ga_data']['cost_price'],
                'goods_num'           => $buy_info['goods_num'],
                'goods_points'        => $goods_common['goods_present'] * $buy_info['goods_num'],
                'goods_experience'    => $goods_common['goods_experience'] * $buy_info['goods_num'],
                'goods_parent_points' => $goods_common['goods_parent_points'] * $buy_info['goods_num'],
                'is_scan_code'        => $goods_common['is_scan_code'],
                'goods_id'            => $buy_info['goods_id'],
                'spec_value'          => isset($buy_info['ga_data']['spec_value']) ? $buy_info['ga_data']['spec_value'] : '',
                'spec_name'           => isset($buy_info['ga_data']['spec_name']) ? $buy_info['ga_data']['spec_name'] : '',
            ];
            if(!$this->order_goods_model->save($og_data)){
                return false;
            }
            if($buy_info['goods_id'] > 0){
                $res = $this->goods_attribute_model
                    ->where(['goods_id'=>$buy_info['goods_id']])
                    ->update(['goods_storage' => ['exp','goods_storage-'.$buy_info['goods_num']]]);
                if(!$res){
                    return false;
                }
                return $this->goods_common_model->where(['goods_commonid'=>$buy_info['goods_commonid']])
                    ->update(
                        [
                            'goods_storage' => ['exp','goods_storage-'.$buy_info['goods_num']],
                            'goods_salenum' => ['exp','goods_salenum+'.$buy_info['goods_num']]
                        ]
                    );
            }
        }
        if(isset($buy_info['car_ids'])){
            foreach($buy_info['car_ids'] as $car_id){
                $car_good = $this->shop_car_model->field('good_common_id,good_nums,good_attr_id')->getById($car_id);
                $goods_common = $this->goods_common_model->where('goods_commonid',$car_good['good_common_id'])->find();
                $goods_image = $this->goods_images_model->where(['goods_commonid'=>$car_good['good_common_id']])->field('goods_image')->find();
                if(!empty($goods_image)){
                    $image = $goods_image['goods_image'];
                }else{
                    $image = $goods_common['goods_image'];
                }
                if($car_good['good_common_id']==0 && $car_good['good_attr_id']==0){
                    return false;
                }
                if($car_good['good_attr_id']){
                    $good_attribution = $this->goods_attribute_model
                        ->field('goods_name,goods_price,cost_price,spec_value,spec_name,goods_image')
                        ->find($car_good['good_attr_id']);
                    if(!empty($good_attribution['goods_image'])){
                        $image = $good_attribution['goods_image'];
                    }
                    $res = $this->goods_attribute_model
                        ->where('goods_id',$car_good['good_attr_id'])
                        ->update(['goods_storage' => ['exp','goods_storage-'.$car_good['good_nums']]]);
                    if(!$res){
                        return false;
                    }
                }
                if($car_good['good_attr_id']==0 && $car_good['good_common_id']){
                    $good_attribution = $this->goods_common_model
                        ->field('goods_name,goods_price,cost_price,spec_value,spec_name')
                        ->getByGoodsCommonid($car_good['good_common_id']);
                }
                $res = $this->goods_common_model
                    ->where('goods_commonid',$car_good['good_common_id'])
                    ->update([
                        'goods_storage' => ['exp','goods_storage-' . $car_good['good_nums']],
                        'goods_salenum' => ['exp','goods_salenum+' . $car_good['good_nums']]
                    ]);
                if(!$res){
                    return false;
                }
                $og_data = [
                    'order_id'            => $order_id,
                    'goods_commonid'      => $car_good['good_common_id'],
                    'gc_id'               => $goods_common['gc_id'],
                    'goods_name'          => $good_attribution['goods_name'],
                    'goods_image'         => $image,
                    'goods_price'         => $good_attribution['goods_price'],
                    'cost_price'          => $good_attribution['cost_price'],
                    'goods_num'           => $car_good['good_nums'],
                    'goods_points'        => $goods_common['goods_present'] * $car_good['good_nums'],
                    'goods_experience'    => $goods_common['goods_experience'] * $car_good['good_nums'],
                    'goods_parent_points' => $goods_common['goods_parent_points'] * $car_good['good_nums'],
                    'is_scan_code'        => $goods_common['is_scan_code'],
                    'goods_id'            => $car_good['good_attr_id']?$car_good['good_attr_id']:0,
                    'spec_value'          => isset($good_attribution['spec_value']) ? $good_attribution['spec_value'] : '',
                    'spec_name'           => isset($good_attribution['spec_name']) ? $good_attribution['spec_name'] : '',
                ];
                if(!$this->order_goods_model->create($og_data)){
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 生成订单地址信息
     * @param $order_id
     * @param $address_id
     * @param $mobile
     * @return bool
     */
    public function gen_order_address_record($order_id,$address_id,$mobile,$order_partition_id=0){
        $address_info = $this->address_model->where(['address_id'=>$address_id])->find();
        if(empty($address_info)){
            return false;
        }
        $order_datas['order_id'] = $order_id;
        $order_datas['order_partition_id'] = $order_partition_id;
        $order_datas['reciver_name'] = $address_info['true_name'];
        $order_datas['reciver_province_id'] = $address_info['province_id'];
        $order_datas['reciver_area_id'] = $address_info['area_id'];
        $order_datas['reciver_city_id'] = $address_info['city_id'];
        $order_datas['store_id'] = 1;
        $order_datas['reciver_info'] = json_encode([
            'mobile' => $mobile,
            'address'=> $address_info['address']
        ]);
        return $this->order_common_model->create($order_datas);
    }

    /**
     * @param $pay_sn
     * @param $order_sn
     * @param $member_id
     * @param $referee_id
     * @param $buy_info
     * @param int $order_state
     * @return bool
     */
    public function gen_order_partition($pay_sn,$order_sn,$member_id,$referee_id,$buy_info,$order_state=10){
        if(empty($buy_info)){
            return false;
        }
        if(!isset($buy_info['car_ids']) || empty($buy_info['car_ids']) || !is_array($buy_info['car_ids'])){
            return false;
        }
        $payment_time = time();
        foreach($buy_info['car_ids'] as $car_id){
            $car_good = $this->shop_car_model->getById($car_id);
            $order_amount = round(($car_good['good_price'] * $car_good['good_nums']) / 1000, 2);
            $order_partition_sn = $this->gen_order_sn($pay_sn);
            $partition_order[] = [
                'order_sn' => $order_sn,
                'order_partition_sn' => $order_partition_sn,
                'pay_sn' => $pay_sn,
                'order_type' => 1,
                'order_state' => $order_state,
                'business_type' => $buy_info['business_type'],
                'buyer_id' => $member_id,
                'buyer_name' => $buy_info['true_name'],
                'buyer_mobile' => $buy_info['mobile'],
                'buyer_idcard' => $buy_info['card_no'],
                'referee_id' => $referee_id,
                'create_time' => time(),
                'payment_time' => $payment_time,
                'order_amount' => $order_amount,
                'good_common_id' => $car_good['good_common_id'],
                'good_attr_id' => $car_good['good_attr_id']
            ];
        }
        return $this->order_partition_model->saveAll($partition_order);
    }

    /**
     * 获取销售员id
     * @param $member_id
     * @return string
     */
    public function get_referee_id($member_id){
        $seller_id = $this->seller_member_model->where(['member_id' => $member_id, 'customer_state' => 2])->value('seller_id');
        $seller_member_id = $this->store_seller_model->where(['seller_id' => $seller_id])->value('member_id');
        if (empty($seller_member_id)) {
            $referee_id = '';
        } else {
            $referee_id = $seller_member_id;
        }
        return $referee_id;
    }
    /**
     * 生成订单记录
     * @param $member_id
     * @param $buy_info
     * @param $pay_info
     * @return bool
     */
    public function gen_order_common_record($pay_sn,$order_sn,$referee_id,$member_id,$buy_info,$pay_info)
    {
        $order_by_car = isset($buy_info['car_ids']) ? true : false;
        $payment_time = time();
        $order_data = [
            'order_sn' => $order_sn,
            'pay_sn' => $pay_sn,
            'order_type' => 1,
            'order_state' => !empty($pay_info['order_state']) ? $pay_info['order_state'] : 10,
            'business_type' => $buy_info['business_type'],
            'buyer_id' => $member_id,
            'buyer_name' => $buy_info['true_name'],
            'buyer_mobile' => $buy_info['mobile'],
            'buyer_idcard' => $buy_info['card_no'],
            'referee_id' => $referee_id,
            'create_time' => time(),
            'pos_amount' => !empty($pay_info['pos_amount']) ? $pay_info['pos_amount'] : '',
            'pd_amount' => !empty($pay_info['pd_amount']) ? $pay_info['pd_amount'] : '',
            'payment_time' => $payment_time,
            'payment_code' => !empty($pay_info['payment_code']) ? $pay_info['payment_code'] : '',
        ];
        if (isset($buy_info['ga_data']['goods_price'])) {
            $order_data['order_amount'] = round($buy_info['ga_data']['goods_price'] * $buy_info['goods_num'], 2);
        }
        if ($order_by_car) {
            $order_data['order_amount'] = $this->shop_car_model->car_products_amount($buy_info['car_ids']);
        }
        $order = $this->create($order_data);
        if (empty($order)) {
            return false;
        }
        return $order->order_id;
    }

    /**
     * 生成预支付记录
     * @param $member_id
     * @return bool|string
     */
    public function gen_order_pay_record($member_id){
        $paysn = $this->make_paysn($member_id);
        $pay_result = $this->order_pay_model->save(['pay_sn' => $paysn, 'buyer_id' => $member_id]);
        if (empty($pay_result)) {
            return false;
        }
        return $paysn;
    }

    /**
     * 生成order_sn
     * @param $pay_sn
     * @return string
     */
    public function gen_order_sn($pay_sn){
        $order_pay = $this->order_pay_model->getByPaySn($pay_sn);
        $order_sn =  $this->make_ordersn($order_pay['pay_id']);
        return $order_sn;
    }

    /**
     * 订单支付成功更新大小订单状态
     * @param $buyer_id
     * @param $pay_sn
     * @param string $payment_code
     * @param bool $order_car
     * @return bool
     */
    public function update_order_record($buyer_id, $pay_sn, $payment_code = 'unipay', $order_car = false){
        $data = [
            'payment_code' => $payment_code,
            'payment_time' => time(),
            'order_state' => '20'
        ];
        if(!$this->where(['buyer_id' => $buyer_id,'pay_sn' => $pay_sn])->update($data)){
            return false;
        }
        if($order_car){
            return $this->order_partition_model
                ->where(['buyer_id' => $buyer_id,'pay_sn' => $pay_sn])
                ->update([
                    'order_state'=>'20'
                ]);
        }
        return true;
    }

    /**
     * 订单支付成功用户 积分、经验账户变更 写入积分日志 经验日志 用户账户升级  写入升级消息记录
     * @param $buyer_id
     * @param $order_id
     * @return bool
     */
    public function upgrade_after_pay($buyer_id,$order_id){
        $order_goods = $this->order_goods_model->order_products_amount($order_id);
        if(empty($order_goods)){
            return false;
        }
        $goods_points = $order_goods['goods_present'];
        $experience = $order_goods['goods_experience'];
        $goods_name = $order_goods['goods_name'];
        if($goods_points > 0 || $experience > 0){
            if(!$this->member_model->save_all($buyer_id,0,$experience,$goods_points)){
               return false;
            }
            if(!$this->points_log_model->add_log_after_pay($buyer_id,$goods_points,$goods_name)){
                return false;
            }

            $member_mobile = $this->member_model->where(['member_id'=>$buyer_id])->value('member_mobile');
            if(!$this->experience_log_model->save_log($buyer_id,$member_mobile,$experience)){
                return false;
            }
            if(!$this->order_member_upgrade($buyer_id)){
                return false;
            }
        }
        return true;
    }

    /**
     * 订单支付成功上级用户积分变动
     * @param $parent_id
     * @param $order_id
     * @return bool
     */
    public function parent_upgrade_after_pay($parent_id,$order_id){
        if($parent_id){
            $order_goods = $this->order_goods_model->order_products_amount($order_id);
            if(empty($order_goods)){
                return false;
            }
            $goods_parent_points = $order_goods['goods_parent_points'];
            $goods_name = $order_goods['goods_name'];
            if($goods_parent_points > 0){
                if(!$this->member_model->save_all($parent_id,0,0,$goods_parent_points)){
                    return false;
                }
                if(!$this->points_log_model->add_parent_log_after_pay($parent_id,$goods_parent_points,$goods_name)){
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 获取订单详情
     * @param $order_id
     * @param $order_sn
     * @param $member_id
     * @return array
     */
    public function get_order_info($order_id,$order_sn,$member_id){
        $data = [];
        $partition_order_nums = $this->order_partition_model->where(['order_sn'=>$order_sn])->count();
        $car_order = $partition_order_nums>0?1:0;
        $order_info = $this->getByOrderId($order_id);
        $data['car_order'] = $car_order;
        $goods_info = $this->order_goods_model->get_order_products($order_id,$order_sn,$order_info['order_state'],$car_order);
        $data['goods'] = $goods_info;
        $state_arr = ['0','10','20','30','40','50'];
        if(in_array($order_info['order_state'],$state_arr)){
            $order_common_info = $this->order_common_model->getByOrderId($order_id);
            $order_extend = $this->order_goods_model->order_products_amount($order_id);
            $order = [
                'order_sn' => $order_info['order_sn'],
                'order_amount' => $order_info['order_amount'],
                'order_state' => $order_info['order_state'],
                'stroe_name' => '常仁总部',
                'business_type' => $order_info['business_type'],
                'pay_type' => 'online',
                'goods_points'=>$order_extend['goods_present'],
                'goods_experience'=>$order_extend['goods_experience'],
                'create_time'=>$order_info['create_time'],
                'sign_time'=>empty($order_info['sign_time']) ? '' : $order_info['sign_time'],
                'payment_time'=>empty($order_info['payment_time']) ? '' : $order_info['payment_time'],
                'order_amount'=>$order_info['order_amount'],
                'pos_amount'=>$order_info['pos_amount'],
                'pd_amount'=>$order_info['pd_amount'],
            ];
            if($order_info['order_state'] != 30){
                $order['pay_sn'] = $order_info['pay_sn'];
                $order['remaining_time'] = $order_info['create_time'] + 252000 -time();
            }
            $data['order'] = $order;
            switch($order_info['order_state']){
                case 10:
                case 20:
                case 30:
                case 40:
                case 50:
                    $area_info = model('area')->get_name($order_common_info['reciver_area_id']);
                    $reciver_info = json_decode($order_common_info['reciver_info'],true);
                    $default_address = [
                        'true_name' => $order_common_info['reciver_name'],
                        'mob_phone' => $reciver_info['mobile'],
                        'area_info' => $area_info,
                        'address' => $reciver_info['address'],
                    ];
                    break;
                case 0:
                    $default_address = $this->address_model
                        ->where(['member_id' => $member_id,'is_default' => 1])
                        ->field('address_id,true_name,mob_phone,area_info,address')
                        ->find();
                    break;
                default:
                    $default_address = [];
                    break;
            }
            if (!is_null($default_address)) {
                $data['address'] = $default_address;
            }else{
                $data['address'] = [];
            }
            $data['address'] = $default_address;
        }
        return $data;
    }

    /**
     * 获取订单下的图片信息
     * @param $order_id
     * @return array
     */
    public function get_order_product_images($order_id){
        $order_goods = $this->order_goods_model->where(['order_id'=>$order_id])->select();
        if(empty($order_goods)){
           return [];
        }
        $images = [];
        foreach($order_goods as &$good){
            $goods_image = $this->goods_images_model->where(['goods_commonid'=>$good['goods_commonid'],'is_default'=>1])->value('goods_image');
            $images[$good['goods_commonid']] = !empty($goods_image) ? config('qiniu.buckets')['images']['domain'] . '/uploads/product/'.str_replace(strrchr($goods_image,"."),"",$goods_image).'_140x140.'.substr(strrchr($goods_image, '.'), 1) : '';
        }
        unset($good);
        return $images;
    }

    /**
     * 商品出库修改库存
     * @param $goods_commonid
     * @param $goods_attr_id
     * @param $goods_num
     * @param $seller_id
     * @param string $order_sn
     * @param int $is_scan_code
     * @param array $goods_sns
     * @return bool
     */
    public function outbound($goods_commonid,$goods_attr_id,$goods_num,$seller_id,$order_sn = '',$is_scan_code = 0,$goods_sns=[]){
        if($is_scan_code < 0){
            return false;
        }
        if($is_scan_code == 0){
            $result = $this->goods_model
                ->where(['goods_commonid'=>$goods_commonid, 'goods_attr_id'=>$goods_attr_id,'seller_id'=>$seller_id])
                ->setDec('upload_num',$goods_num);
            if(!$result){
                return false;
            }
        }
        if($is_scan_code > 0){
            if(empty($goods_sns)){
                return false;
            }
            foreach ($goods_sns as $goods_sn){
                $result = $this->goods_model
                    ->where(['goods_commonid'=>$goods_commonid, 'goods_attr_id'=>$goods_attr_id,'goods_sn'=>$goods_sn,'seller_id'=>$seller_id])
                    ->update(['order_sn'=>$order_sn,'goods_state'=>3]);
                if(!$result){
                    return false;
                }
            }
        }
        return true;
    }
    /**
     * 订单发货
     * @param $delivery_goods [{"goods_commonid":72,"goods_num":1,"goods_id":0,"company_id":79,"deli_code":"3881580817133"},{....}]
     * @param $order_id
     * @param $member_id
     * @return bool
     * @throws \think\exception\PDOException
     */
    public function delivery($delivery_goods,$order_id,$member_id){
        if(empty($delivery_goods)){
            return false;
        }
        $order = $this->getByOrderId($order_id);
        if(empty($order)){
            return false;
        }
        $deli_code_arr = [];
        foreach($delivery_goods as $good){
            $deli_code_arr[] = $good['deli_code'];
        }
        $deli_code_arr = array_unique($deli_code_arr);
        $this->goods_model->startTrans();
        $this->goods_log_model->startTrans();
        $this->goods_common_model->startTrans();
        $this->startTrans();
        $this->order_log_model->startTrans();
        $this->message_model->startTrans();
        $this->order_partition_model->startTrans();
        $this->order_common_model->startTrans();
        foreach($delivery_goods as &$good){
            if(empty($good['goods_commonid']) || empty($good['goods_num']) || empty($good['company_id']) || empty($good['deli_code'])){
                $result = false;
                break;
            }
            $good_info = $this->goods_common_model->getByGoodsCommonid($good['goods_commonid']);
            $good['goods_sns'] = isset($good['goods_sns'])?$good['goods_sns']:[];
            //减少库存
            $result = $this->outbound($good['goods_commonid'],$good['goods_id'],$good['goods_num'],$member_id,$order['order_sn'],$good_info['is_scan_code'],$good['goods_sns']);
            if(!$result){
                break;
            }
            //记录操作日志
            $member_name = $this->member_model->where(['member_id'=>$member_id])->value('member_name');
            $log_msg = '库管员'.$member_name.'于'.date('Y-m-d H:i:s',time()).'出库'.$good_info['goods_name'].$good['goods_num'].'件';
            if($good_info['is_scan_code']){
                $log_msg = '库管员'.$member_name.'于'.date('Y-m-d H:i:s',time()).'扫码出库机器人 '.$good['goods_num'].'件,机器人编号为'.implode(',',$good['goods_sns']);
            }
            $goods_log = [
                'goods_id' => $good['goods_commonid'],
                'log_msg' => $log_msg,
                'log_time' => time(),
                'log_role' => '库管员',
                'log_user'=>$member_name,
                'log_goodsstate' => 1
            ];
            $result = $this->goods_log_model->create($goods_log);
            if(!$result){
                break;
            }
            //添加订阅信息
            $company_code = $this->express_model->get_express($good['company_id']);
            if(is_null($company_code)){
                break;
            }
            if(in_array($good['deli_code'],$deli_code_arr)){
                $result = $this->_test(trim($company_code['express_code']),$good['deli_code']);
                if(!$result){
                    break;
                }
                foreach($deli_code_arr as &$code){
                    if($code === $good['deli_code']){
                        unset($code);
                    }
                }
                unset($code);
            }
            //修改订单状态
            $result = $this->change_delivery_order_state($order_id,$good['goods_commonid'],$good['goods_id']);
            if(!$result){
                break;
            }
            //添加订单操作日志
            $order_log = [
                'order_id' => $order_id,
                'log_msg' => '库管员'.$member_name.'出库订单成功,出库单号'.$order['order_sn'],
                'log_time' => time(),
                'log_role'=>'库管员',
                'log_user' => $member_name,
                'log_orderstate' => '3'
            ];
            $result = $this->order_log_model->create($order_log);
            if(!$result){
                break;
            }
            //更改订单信息扩展表
            $order_common_info = [
                'shipping_express_id' => $good['company_id'],
                'shipping_code' => $good['deli_code'],
                'shipping_time' => time()
            ];
            $order_partition_id = $this->order_partition_model
                ->where(['order_sn'=>$order['order_sn'],'good_common_id'=>$good['goods_commonid'],'good_attr_id'=>$good['goods_id']])
                ->value('id');
            $order_partition_id = !is_null($order_partition_id)?$order_partition_id:0;
            $result = $this->order_common_model
                ->where(['order_id'=>$order_id,'order_partition_id'=>$order_partition_id])
                ->update($order_common_info);
            if(!$result){
                break;
            }
            //添加消息记录
            $img_base = config('qiniu.buckets')['images']['domain'];
            $body = json_encode([
                'goods_name'  => $good_info['goods_name'],
                'order_sn'    => $order['order_sn'],
                'goods_image' => $img_base.'/uploads/product/'.$good_info['goods_image'],
                'express'     => $company_code['express_name'],
                'title'       => '您的'.$good_info['goods_name'].'订单已发货，请耐心等待哦！'
            ]);
            $message = [
                'from_member_id' => 0,
                'to_member_id'   => $order['buyer_id'],
                'message_title'  => '订单配送中',
                'message_time'   => time(),
                'message_state'  => 0,
                'message_type'   => 5,
                'is_more'        => 0,
                'is_push'        => 2,
                'message_body'   => $body,
                'push_detail'    => '您的'.$good_info['goods_name'].'订单已发货，请耐心等待哦！',
                'push_title'     => '订单配送中'
            ];
            $result = $this->message_model->insertGetId($message);
            if(!$result){
                break;
            }
        }
        unset($good);
        if(!$result){
            $this->goods_model->rollback();
            $this->goods_common_model->rollback();
            $this->rollback();
            $this->order_log_model->rollback();
            $this->message_model->rollback();
            $this->order_partition_model->rollback();
            $this->order_common_model->rollback();
            return false;
        }
        $this->goods_model->commit();
        $this->goods_log_model->commit();
        $this->goods_common_model->commit();
        $this->commit();
        $this->order_log_model->commit();
        $this->message_model->commit();
        $this->order_partition_model->commit();
        $this->order_common_model->commit();
        return true;
    }

    /**
     * 更新发货后订单状态 如果有子订单 所有的子订单状态改变后大订单状态才会改变
     * @param $order_id
     * @param $good_common_id
     * @param int $good_attr_id
     * @return bool|int
     */
    public function change_delivery_order_state($order_id,$good_common_id,$good_attr_id = 0){
        $order = $this->getByOrderId($order_id);
        if(empty($order)){
            return false;
        }
        $order_partition = $this->order_partition_model
            ->where(['order_sn'=>$order['order_sn'],'good_common_id'=>$good_common_id,'good_attr_id'=>$good_attr_id])
            ->find();
        if(!empty($order_partition)){
            $res = $this->order_partition_model
                ->where(['order_sn'=>$order['order_sn'],'good_common_id'=>$good_common_id,'good_attr_id'=>$good_attr_id])
                ->setField('order_state','30');
            if(!$res){
                return false;
            }
            $order_delivery_nums = $this->order_partition_model->where(['order_sn'=>$order['order_sn'],'order_state'=>'20'])->count();
            if($order_delivery_nums == 0){
                $res = $this->where(['order_id'=>$order_id])->setField('order_state','30');
            }
        }else{
            $res = $this->where(['order_id'=>$order_id])->setField('order_state','30');
        }
        return $res;
    }
    /**
     * 添加物流订阅信息
     */
    private function _test($company_code,$deli_code){
        $appkey = '7ca0bfbc-fae6-435f-a391-6a3a12156aec';
        $appid = '1302427';
        $express = new \Lib\Express($appkey,$appid);
        $result = $express->dist($company_code,$deli_code);
        return $result;
    }

    /**
     * 订单逻辑删除
     * @param $order_id
     * @param $buyer_id
     * @return bool
     * @throws PDOException
     */
    public function order_delete($order_id,$buyer_id){
        try{
            $order = $this->where(['order_id'=>$order_id,'buyer_id'=>$buyer_id])->find();
            if(empty($order)){
                return false;
            }
        }catch (\Exception $e){
            return false;
        }
        if($order['order_state'] != 0){
            return false;
        }
        $order_partitions = $this->order_partition_model->where('order_sn',$order['order_sn'])->count();
        $car_order = $order_partitions>0?true:false;
        $this->startTrans();
        $this->order_log_model->startTrans();
        $res = $this->where(['order_id'=>$order_id,'buyer_id'=>$buyer_id])->update(['delete_state'=>'1']);
        if(!$res){
            return false;
        }
        if($car_order){
            $this->order_partition_model->startTrans();
            $res = $this->order_partition_model->where(['order_sn'=>$order['order_sn'],'buyer_id'=>$buyer_id])->update(['delete_state'=>'1']);
            if(!$res){
                $this->rollback();
                return false;
            }
        }
        $member_name = $this->member_model->where(['member_id'=>$buyer_id])->value('member_name');
        $log = [
            'order_id' =>  $order_id,
            'log_msg'  =>  $member_name.'在'.date('Y-m-d H:i:s').'删除订单编号为'.$order['order_sn'].'的订单',
            'log_time' =>  time(),
            'log_user' =>  $member_name,
            'log_orderstate' => $order['order_state']
        ];
        $res = $this->order_log_model->insert($log);
        if(!$res){
            $this->rollback();
            $car_order && $this->order_partition_model->rollback();
            return false;
        }
        $this->commit();
        $car_order && $this->order_partition_model->commit();
        $this->order_log_model->commit();
        return true;
    }

    /**
     * 取消订单
     * @param $order_id
     * @param $buyer_id
     * @return bool
     * @throws PDOException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function order_cancel($order_id,$buyer_id){
       $order = $this->where(['order_id'=>$order_id,'buyer_id'=>$buyer_id])
           ->field("pd_amount,pos_amount,order_sn,order_state")
           ->find();
       $member_info = $this->member_model->getByMemberId($buyer_id);
       $pd_log = [
            'member_id' => $buyer_id,
            'type'      => 'order_cancel',
            'add_time'  => time(),
            'log_desc'  => '用户取消订单返还账户余额'
       ];
        $log_msg = $member_info['member_name'].'在'.date('Y-m-d H:i:s').'取消订单编号为'.$order['order_sn'].'的订单';
        $this->startTrans();
        $this->order_log_model->startTrans();
        $this->member_model->startTrans();
        $this->points_log_model->startTrans();
        $this->pd_model->startTrans();
        //仅余额支付
        if($order['pd_amount']>0 && $order['pos_amount'] == 0){
            $res = $this->member_model->where(['member_id'=>$buyer_id])->setInc('av_predeposit',$order['pd_amount']);
            if(!$res){
               return false;
            }
            $pd_log['av_amount'] = $order['pd_amount'];
            $log_msg .= "退回余额￥".$order['pd_amount'].'元';
            $res = $this->pd_model->insertGetId($pd_log);
            if(!$res){
                $this->member_model->rollback();
                return false;
            }
        }
        //仅积分支付
        if($order['pd_amount']==0 && $order['pos_amount'] > 0){
            $res = $this->member_model->where(['member_id'=>$buyer_id])->setInc('points',$order['pos_amount']);
            if(!$res){
                return false;
            }
            $log_msg .= "退回积分".$order['pos_amount'];
            $points_log = [
                'member_id'     => $buyer_id,
                'member_mobile' => $member_info['member_mobile'],
                'add_time'      => time(),
                'pl_desc'       => '取消订单返回支付使用积分',
                'type'          => 'order_cancel',
                'points'        => $order['pos_amount']
            ];
            $res = $this->points_log_model->save($points_log);
            if($res === false){
                $this->member_model->rollback();
                return false;
            }
        }
        //使用积分和余额同时支付
        if($order['pos_amount'] > 0 && $order['pd_amount']>0){
            $res = $this->member_model->where(['member_id'=>$buyer_id])->setInc('points',$order['pos_amount']);
            if(!$res){
                return false;
            }
            $res = $this->member_model->where(['member_id'=>$buyer_id])->setInc('av_predeposit',$order['pd_amount']);
            if(!$res){
                return false;
            }
            $pd_log['av_amount'] = $order['pd_amount'];
            $res = $this->pd_model->insertGetId($pd_log);
            if(!$res){
                $this->member_model->rollback();
                return false;
            }
            $log_msg .= "退回余额￥".$order['pd_amount']."元,退回积分".$order['pos_amount'];
            $points_log = [
                'member_id'     => $buyer_id,
                'member_mobile' => $member_info['member_mobile'],
                'add_time'      => time(),
                'pl_desc'       => '取消订单返回支付使用积分',
                'type'          => 'order_cancel',
                'points'        => $order['pos_amount']
            ];
            $res = $this->points_log_model->save($points_log);
            if($res === false){
                $this->member_model->rollback();
                return false;
            }
        }
        $res = $this->where(['order_id'=>$order_id,'buyer_id'=>$buyer_id])->update(['order_state'=>'0']);
        if(!$res){
            $this->member_model->rollback();
            $this->points_log_model->rollback();
            $this->pd_model->rollback();
            return false;
        }
        $order_partitions = $this->order_partition_model->where('order_sn',$order['order_sn'])->count();
        $car_order = $order_partitions>0?true:false;
        if($car_order){
            $this->order_partition_model->startTrans();
            $res = $this->order_partition_model->where(['order_sn'=>$order['order_sn']])->update(['order_state'=>'0']);
            if(!$res){
                $this->member_model->rollback();
                $this->points_log_model->rollback();
                $this->pd_model->rollback();
                $this->rollback();
                return false;
            }
        }
        $log = [
            'order_id' =>  $order_id,
            'log_msg'  =>  $log_msg,
            'log_time' =>  time(),
            'log_user' =>  $member_info['member_name'],
            'log_orderstate' => $order['order_state']
        ];
        $res = $this->order_log_model->insert($log);
        if(!$res){
            $this->member_model->rollback();
            $this->points_log_model->rollback();
            $this->pd_model->rollback();
            $this->rollback();
            $car_order && $this->order_partition_model->rollback();
            return false;
        }
        $this->member_model->commit();
        $this->points_log_model->commit();
        $this->pd_model->commit();
        $this->commit();
        $this->order_log_model->commit();
        $car_order && $this->order_partition_model->commit();
        if(!empty($points_log)){
            Hook::listen('create_points_log',$points_log);
        }
        return true;
    }
}