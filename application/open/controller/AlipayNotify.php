<?php
/**
 * 支付宝支付异步通知处理
 */
namespace app\open\controller;
use think\Controller;
use Alipay\AopClient;
use think\Log;
class AlipayNotify extends Controller {
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
    }
    public function index(){
        if (!request()->isPost()) {
            abort(404,'页面不存在');
        }
        $alipayrsaPublicKey = config('aliyun.alipay_rsa_public_key');
        $param = input('param.');
        $aop = new AopClient;
        $aop->alipayrsaPublicKey = $alipayrsaPublicKey;
        $flag = $aop->rsaCheckV1($param, NULL, "RSA2");
        if (!$flag) {
            exit('failure');
        }
        $pay_result = $this->order_pay_model->getByPaySn($param['out_trade_no']);
        if (is_null($pay_result)) {
            exit('failure');
        }
        if ($pay_result['api_pay_state'] == 1) {
            exit('success');
        }
        $order_info = $this->order_model->where(['buyer_id' => $pay_result['buyer_id'],'pay_sn' => $param['out_trade_no']])->find();
        if (empty($order_info)) {
            Log::write('客户支付宝支付成功，但查无订单数据，支付单号:'.$param['out_trade_no'],'notice');
            exit('success');
        }
        $payment_only_alipay = $order_info['pos_amount'] ==0 && $order_info['pd_amount'] ==0 ? true : false;
        $order_partitions = $this->order_partition_model->where(['pay_sn'=>$param['out_trade_no']])->select();//有分订单 购物车结算来的
        $member = $this->member_model->field('experience,member_grade,parent_id,member_name')->getByMemberId($pay_result['buyer_id']);
        $payment_code = 'alipay';
        if(!$payment_only_alipay){
            $payment_code = 'mixed';
        }
        $order_car = !$order_partitions->isEmpty() ? true : false;
        $this->order_model->startTrans();
        $this->order_pay_model->startTrans();
        $this->order_log_model->startTrans();
        $order_car && $this->order_partition_model->startTrans();
        if(!$this->order_model->update_order_record($pay_result['buyer_id'], $param['out_trade_no'], $payment_code, $order_car)){
            $order_car && $this->order_partition_model->rollback();
            exit('failure');
        }
        Log::write('更新订单状态成功','info');
        if(!$this->order_pay_model->update_pay_record($param['out_trade_no'], $param['total_amount'])){
            $this->order_model->rollback();
            $order_car && $this->order_partition_model->rollback();
            exit('failure');
        }
        Log::write('更新支付状态成功','info');
        if(!$this->order_log_model->add_log_after_pay($member['member_name'],$param['out_trade_no'],$order_info['order_id'],
            $order_info['pos_amount'],$order_info['pd_amount'],$param['total_amount'],'alipay')){
            $this->order_model->rollback();
            $this->order_pay_model->rollback();
            $order_car && $this->order_partition_model->rollback();
            exit('failure');
        }
        Log::write('写入订单日志成功','info');
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
            exit('failure');
        }
        Log::write('写入经验和积分日志成功','info');
        if(!$this->order_model->parent_upgrade_after_pay($member['parent_id'],$order_info['order_id'])){
            $this->order_model->rollback();
            $this->order_pay_model->rollback();
            $this->order_log_model->rollback();
            $order_car && $this->order_partition_model->rollback();
            $this->member_model->rollback();
            $this->points_log_model->rollback();
            $this->experience_log_model->rollback();
            $this->message_model->rollback();
            exit('failure');
        }
        Log::write('上级用户获取积分成功','info');
        $this->order_pay_model->commit();
        $this->order_model->commit();
        $this->order_log_model->commit();
        $this->points_log_model->commit();
        $this->member_model->commit();
        $this->experience_log_model->commit();
        $this->message_model->commit();
        $order_car && $this->order_partition_model->commit();
        Log::write('接收异步通知结束','info');
        exit('success');
    }
}