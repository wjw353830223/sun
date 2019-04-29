<?php
/**
 * 银联代付后台通知处理
 */
namespace app\open\controller;
use think\Controller;
use Unipay\AcpService;
use Unipay\LogUtil;
use app\common\model\PdCash;
class UnipayNotify extends Controller {
    protected $pd_log_model,$pd_cash_model,$message_model,$member_model,$appKey,$masterSecret,$order_pay_model,$order_model;
    protected $order_log_model,$order_goods_model,$points_log_model,$experience_log_model,$shop_car_model;
    protected $order_partition_model,$logger;
    public function _initialize() {
        $this->pd_cash_model = model('PdCash');
        $this->pd_log_model = model('PdLog');
        $this->message_model = model('Message');
        $this->member_model = model('Member');
        $this->order_pay_model =  model('OrderPay');
        $this->order_model = model('Order');
        $this->order_log_model = model('OrderLog');
        $this->order_goods_model = model('OrderGoods');
        $this->points_log_model = model('PointsLog');
        $this->experience_log_model = model('ExperienceLog');
        $this->appKey = '25b3a2a3df4d0d26b55ea031';
        $this->masterSecret = '3efc28f3eb342950783754f2';
        $this->shop_car_model = model('ShopCar');
        $this->order_partition_model = model('OrderPartition');
        $this->logger = LogUtil::getLogger();
    }
    /**
     * 银联代付异步通知
     */
    public function notify(){
        $logger = LogUtil::getLogger();
        $logger->LogInfo (  PHP_EOL . '接收异步通知开始');
        if (!request()->isPost()) {
            abort(404,'页面不存在');
        }
        $post = input('param.');
        if(empty($post)){
            abort(404,'页面不存在');
        }
        if(!AcpService::validate($post)){
            abort(400,'Bad Request');
        };
        $pd_cash_info = $this->pd_cash_model->pdcash_get_by_pdcsn($post['orderId']);
        $member_mobile = $this->member_model->where(['member_id'=>$pd_cash_info['pdc_member_id']])->value('member_mobile');
        if(!in_array($post['respCode'],['00','A6'])){
            $logger->LogInfo ( '返回状态码：'.$post['respCode'] );
            //TODO 更新数据库。
            $data = [
                'pdc_payment_state'  => PdCash::PDC_PAYMENT_STATE_FAIL,
            ];
            $this->pd_cash_model->startTrans();
            $this->member_model->startTrans();
            model('PdLog')->startTrans();
            $this->message_model->startTrans();
            if(!$this->pd_cash_model->pdcash_update($pd_cash_info['pdc_id'],$data)){
                abort(400,'Bad Request');
            }
            $amount = json_decode($pd_cash_info['pdc_data'],true)['amount'];
            if(!$this->member_model->member_av_predeposit_update($pd_cash_info['pdc_member_id'],$amount)){
                $this->pd_cash_model->rollback();
                abort(400,'Bad Request');
            }
            $pd_log = [
                'member_id'     => $pd_cash_info['pdc_member_id'],
                'member_mobile' => $member_mobile,
                'av_amount'     => $amount,
                'type'          => 'cash_fail',
                'add_time'      => time(),
                'log_desc'      => '余额转出失败，失败原因：'.$post['respMsg'],
            ];
            if(!$this->pd_log_model->pdlog_add($pd_log)){
                $this->member_model->rollback();
                $this->pd_cash_model->rollback();
                abort(400,'Bad Request');
            }
            //发送消息
            $push_detail = '您的余额转出失败，请重新申请操作';
            $title = '提现通知';
            $message = [
                'from_member_id' => 0,
                'to_member_id'   => $pd_cash_info['pdc_member_id'],
                'message_title'  => $title,
                'message_body'   => json_encode(['title'=> '提现失败','points'=>'+'.$amount]),
                'message_time'   => time(),
                'message_state'  => 0,
                'message_type'   => 4,
                'is_more'        => 0,
                'is_push'        => 2,
                'push_title'     => '',
                'push_detail'    => $push_detail,
                'push_member_id' => $pd_cash_info['pdc_member_id']
            ];
            if(!$this->message_model->send_message($member_mobile,$push_detail,$title,$message)){
                $this->message_model->rollback();
                model('PdLog')->rollback();
                $this->member_model->rollback();
                $this->pd_cash_model->rollback();
                $logger->LogInfo ( PHP_EOL . '提现消息发送失败'. PHP_EOL);
                abort(400,'Bad Request');
            }
            $logger->LogInfo ( PHP_EOL . '提现消息发送成功'. PHP_EOL);
            $this->pd_cash_model->commit();
            $this->member_model->commit();
            model('PdLog')->commit();
            $this->message_model->commit();
            exit('fail');
        }
        $logger->LogInfo (  PHP_EOL . '接收异步通知结束');
        //判断respCode=00、A6后，对涉及资金类的交易，请再发起查询接口查询，确定交易成功后更新数据库。
        //https://open.unionpay.com/ajweb/help/faq/list?id=6&level=0&from=0&keyword=%E5%90%8E%E5%8F%B0%E9%80%9A%E7%9F%A5
        $logger->LogInfo (  PHP_EOL . '发起银联交易查询');
        $res_data = $this->pd_cash_model->withdraw_query($post['orderId'], $post['txnTime']);
        if($res_data === false){
            $logger->LogInfo (PHP_EOL . '查询交易没有数据返回' . PHP_EOL );
            abort(400,'Bad Request');
        };
        if($res_data['respCode']!=='00' || $res_data['origRespCode'] !== '00'){
            $logger->LogInfo (PHP_EOL . '查询银联得知交易失败' . PHP_EOL );
            abort(400,'Bad Request');
        }
        $logger->LogInfo ( PHP_EOL . '查询银联得知交易成功'. PHP_EOL);
        $data = [
            'pdc_payment_state'  => PdCash::PDC_PAYMENT_STATE_SUCCESS,
        ];
        $amount = json_decode($pd_cash_info['pdc_data'],'true')['amount'];
        $this->pd_cash_model->startTrans();
        $this->pd_log_model->startTrans();
        $this->message_model->startTrans();
        if(!$this->pd_cash_model->pdcash_update($pd_cash_info['pdc_id'],$data)){
            abort(400,'Bad Request');
        }
        //提现记录
        $pd_log = [
            'member_id'     => $pd_cash_info['pdc_member_id'],
            'member_mobile' => $member_mobile,
            'av_amount'     => $amount,
            'type'          => 'cash_withdrawn',
            'add_time'      => time(),
            'log_desc'      => '余额转出',
            'pd_data'       => $pd_cash_info['pdc_data']
        ];
        if(!$this->pd_log_model->pdlog_add($pd_log)){
            $this->pd_cash_model->rollback();
            abort(400,'Bad Request');
        }
        $logger->LogInfo ( PHP_EOL . '提现日志记录成功'. PHP_EOL);
        //发送消息
        $push_detail = '您账户的'.$amount.'元已提现成功';
        $title = '提现通知';
        $message = [
            'from_member_id' => 0,
            'to_member_id'   => $pd_cash_info['pdc_member_id'],
            'message_title'  => $title,
            'message_body'   => json_encode(['title'=> '提现成功','points'=>'-'.$amount]),
            'message_time'   => time(),
            'message_state'  => 0,
            'message_type'   => 4,
            'is_more'        => 0,
            'is_push'        => 2,
            'push_title'     => '',
            'push_detail'    => $push_detail,
            'push_member_id' => $pd_cash_info['pdc_member_id']
        ];
        if(!$this->message_model->send_message($member_mobile,$push_detail,$title,$message)){
            $this->pd_cash_model->rollback();
            $this->pd_log_model->rollback();
            $this->message_model->rollback();
            $logger->LogInfo ( PHP_EOL . '提现消息发送失败'. PHP_EOL);
            abort(400,'Bad Request');
        }
        $logger->LogInfo ( PHP_EOL . '提现消息发送成功'. PHP_EOL);
        $this->pd_cash_model->commit();
        $this->pd_log_model->commit();
        $this->message_model->commit();
        exit('ok');
    }

    /**
     * 银行卡支付异步通知
     */
    public function pay_notify(){
        $this->logger->LogInfo (  PHP_EOL . '接收异步通知开始');
        if (!request()->isPost()) {
            abort(404,'页面不存在');
        }
        $post = input('param.');
        if(empty($post) || !AcpService::validate($post)){
            abort(400,'Bad Request');
        };
        $pay_result = $this->order_pay_model->getByPaySn($post['orderId']);
        if (is_null($pay_result)) {
            abort(400,'Bad Request');
        }
        if ($pay_result['api_pay_state'] == 1) {
            exit('success');
        }
        $order_info = $this->order_model->where(['buyer_id' => $pay_result['buyer_id'],'pay_sn' => $post['orderId']])->find();
        if (empty($order_info)) {
            $this->logger->LogInfo (  PHP_EOL . '客户银行卡支付成功，但查无订单数据，支付单号:'.$post['orderId']);
            abort(400,'Bad Request');
        }
        $payment_only_unipay = $order_info['pos_amount'] ==0 && $order_info['pd_amount'] ==0 ? true : false;
        $order_partitions = $this->order_partition_model->where(['pay_sn'=>$post['orderId']])->select();//有分订单 购物车结算来的
        $member = $this->member_model->field('experience,member_grade,parent_id,member_name')->getByMemberId($pay_result['buyer_id']);
        $payment_code = 'unipay';
        if(!$payment_only_unipay){
            $payment_code = 'mixed';
        }
        $order_car = !$order_partitions->isEmpty() ? true : false;
        $this->order_model->startTrans();
        $this->order_pay_model->startTrans();
        $this->order_log_model->startTrans();
        $order_car && $this->order_partition_model->startTrans();
        if(!$this->order_model->update_order_record($pay_result['buyer_id'], $post['orderId'], $payment_code, $order_car)){
            $order_car && $this->order_partition_model->rollback();
            abort(400,'Bad Request');
        }
        $this->logger->LogInfo (PHP_EOL . '更新订单状态成功');
        if(!$this->order_pay_model->update_pay_record($post['orderId'], $post['txnAmt']/100)){
            $this->order_model->rollback();
            $order_car && $this->order_partition_model->rollback();
            abort(400,'Bad Request');
        }
        $this->logger->LogInfo (  PHP_EOL . '更新支付状态成功');
        if(!$this->order_log_model->add_log_after_pay($member['member_name'],$post['orderId'],$order_info['order_id'],
            $order_info['pos_amount'],$order_info['pd_amount'],$post['txnAmt']/100,'unipay')){
            $this->order_model->rollback();
            $this->order_pay_model->rollback();
            $order_car && $this->order_partition_model->rollback();
            abort(400,'Bad Request');
        }
        $this->logger->LogInfo (  PHP_EOL . '写入订单日志成功');
        $this->member_model->startTrans();
        $this->points_log_model->startTrans();
        $this->experience_log_model->startTrans();
        $this->message_model->startTrans();
        if(!$this->order_model->upgrade_after_pay($pay_result['buyer_id'],$order_info['order_id'])){
            $this->order_model->rollback();
            $this->order_pay_model->rollback();
            $this->order_log_model->rollback();
            $order_car && $this->order_partition_model->rollback();
            $this->member_model->rollback();
            $this->points_log_model->rollback();
            $this->experience_log_model->rollback();
            $this->message_model->rollback();
            abort(400,'Bad Request');
        }
        $this->logger->LogInfo (PHP_EOL . '写入经验和积分日志成功');
        if(!$this->order_model->parent_upgrade_after_pay($member['parent_id'],$order_info['order_id'])){
            $this->order_model->rollback();
            $this->order_pay_model->rollback();
            $this->order_log_model->rollback();
            $order_car && $this->order_partition_model->rollback();
            $this->member_model->rollback();
            $this->points_log_model->rollback();
            $this->experience_log_model->rollback();
            $this->message_model->rollback();
            abort(400,'Bad Request');
        }
        $this->logger->LogInfo (PHP_EOL . '上级用户获取积分成功');
        $this->order_pay_model->commit();
        $this->order_model->commit();
        $this->order_log_model->commit();
        $this->points_log_model->commit();
        $this->member_model->commit();
        $this->experience_log_model->commit();
        $this->message_model->commit();
        $order_car && $this->order_partition_model->commit();
        $this->logger->LogInfo (  PHP_EOL . '接收异步通知结束');
        exit('success');
    }
    /**
     * 测试用 正式服需删除
     */
    public function test(){
        if(APP_ENV !== 'prod'){
            $str = file_get_contents('../data/logs/unipay/' . date('Y-m-d',time()) . '.log');
            echo $str;
        }
    }
    /**
     * 测试用 正式服需删除
     */
    public function flush(){
        if(APP_ENV !== 'prod'){
            file_put_contents('../data/logs/unipay/' . date('Y-m-d',time()) . '.log','');
        }

    }
}