<?php
namespace app\api\controller;
use app\common\model\OrderPay;
use Lib\wechatAppPay;
use Alipay\AopClient;
use Alipay\request\AlipayTradeAppPayRequest;

class Pay extends Apibase
{
	protected $order_model,$pay_model,$order_partition_model,$member_model,$log_model,$payment_model;
	protected function _initialize(){
    	parent::_initialize();
    	$this->order_model = model('Order');
    	$this->pay_model = model('OrderPay');
        $this->member_model = model('Member');
        $this->log_model = model('OrderLog');
        $this->payment_model = model('Payment');
        $this->order_partition_model = model('OrderPartition');
    }

    public function pay_query(){
        $order_sn = input('param.order_sn','','trim');
        $pay_sn = $this->order_model->where(['order_sn'=>$order_sn])->value('pay_sn');
        $pay_info = $this->pay_model->field('buyer_id,api_pay_state,pay_type,pay_time')->getByPaySn($pay_sn);
        //支付查询
        if(($pay_info['pay_type']==1 && $this->pay_model->order_query($pay_sn,$pay_info['pay_time'],$pay_info['pay_type'],[$this,'wechat_query']))
            || ($pay_info['pay_type']==2 && $this->pay_model->order_query($pay_sn,$pay_info['pay_time'],$pay_info['pay_type'],[$this,'alipay_query']))
            || ($pay_info['pay_type']==3 && $this->pay_model->order_query($pay_sn,$pay_info['pay_time'],$pay_info['pay_type'],[$this,'unipay_query']))
        ){
            $this->ajax_return('200','success',[]);
        }
        $this->ajax_return('11593','pay status failed');
    }
    /**
     * 微信支付结果查询
     * @param $out_trade_no
     * @return bool
     */
    public function wechat_query($out_trade_no){
        $resp_data = $this->pay_model->wechat_pay_query($out_trade_no);
        if($resp_data === false || !isset($resp_data['return_code'])){
            return false;
        }
        if($resp_data['return_code']==='SUCCESS' && $resp_data['trade_state'] === 'SUCCESS'){
            return $resp_data;
        }
        return false;
    }

    /**
     * 银行卡支付结果查询
     * @param $pdc_sn
     * @param $txn_time
     * @return bool
     */
    public function unipay_query($pdc_sn,$txn_time){
        $resp_data = $this->pay_model->pay_query($pdc_sn, $txn_time);
        if($resp_data === false || !isset($resp_data['respCode'])){
            return false;
        }
        if($resp_data['respCode']==='00' && $resp_data['origRespCode'] === '00'){
            return $resp_data;
        }
        return false;
    }

    /**
     * 支付宝支付结果查询
     * @param $out_trade_no
     * @param $trade_no
     * @return bool|mixed
     */
    public function alipay_query($out_trade_no,$trade_no){
        $resp_data = $this->pay_model->ali_pay_query($out_trade_no,$trade_no);
        $resp_data = json_decode(json_encode($resp_data),true);
        if(!isset($resp_data['alipay_trade_query_response']) || empty($resp_data['alipay_trade_query_response'])){
            return false;
        }
        $resp_data = $resp_data['alipay_trade_query_response'];
        if($resp_data === false || !isset($resp_data['code'])){
            return false;
        }
        if($resp_data['code'] =='10000' && $resp_data['trade_status'] == 'TRADE_SUCCESS'){
            return $resp_data;
        }
        return false;
    }

    /**
     * 支付初始化
     */
    public function initiate(){
    	$pay_type = input('param.pay_type','','trim');
    	if (empty($pay_type)) {
    	 	$this->ajax_return('11360','invalid pay_type');
    	}
    	$order_sn = input('param.order_sn','','trim');
    	if (empty($order_sn)) {
    		$this->ajax_return('11361','invalid order_sn');
    	}
        $order_info = $this->order_model->with('goodsInfo')->where(['order_sn' => $order_sn])->find();
        if (is_null($order_info)) {
            $this->ajax_return('11361','invalid order_sn');
        }
        $order_amount = $order_info['order_amount'];
        $opd_amount = $order_info['pd_amount'];
        $opos_amount = $order_info['pos_amount'];
        //支付订单、关闭订单不能再被支付  pay_sn动态，
        if($order_info['order_state'] == 0){
            $this->ajax_return('11362','order been shut down');
        }
        if($order_info['order_state'] == 20){
            $this->ajax_return('11363','order has been pay');
        }
        //剩余支付金额
        $order_amount = number_format($order_amount - $opd_amount - $opos_amount,2,'.','');
    	$order_info = $order_info->toArray();
    	$goods_info = $order_info['goods_info'];
    	$goods_info['pay_sn'] = $order_info['pay_sn'];
        $goods_info['order_sn'] = $order_sn;
        $goods_info['order_amount'] = $order_amount;
    	switch ($pay_type) {
    		case 'weichat':
    			$pay_result = $this->weichat_pay($goods_info);
    			break;
    		case 'alipay':
                $pay_result = $this->ali_pay($goods_info);
                break;
            case 'unipay':
                $pay_result = $this->union_pay($goods_info);
                break;
    		default:
    			# code...
    			break;
    	}
        $this->ajax_return('11360','invalid pay_type');
    }
    /**
     * 银行卡支付
     * @param array $goods_info
     */
    protected function union_pay($goods_info = array()){
	    $back_url = $this->base_url.'/open/unipay_notify/pay_notify';
	    $time = time();
        $txn_time = date('YmdHis', $time);
        $tn = model('OrderPay')->unipay($goods_info['pay_sn'],$goods_info['order_amount'],$back_url, $txn_time);
        if($tn === false){
            $this->ajax_return('11933','failed to initiate union pay');
        }
        $pay_data = [
            'pay_type' => OrderPay::PAY_TYPE_UNIONPAY,
            'pay_time' => $time
        ];
        $pay_result = $this->pay_model->save($pay_data,['pay_sn'=>$goods_info['pay_sn']]);
        if($pay_result === false){
            $this->ajax_return('11591','failed to update order info');
        }
        $this->ajax_return('200','success',['tn'=>$tn]);
    }
    /**
    * 微信统一下单
    */ 
    protected function weichat_pay($goods_info = array()){
        $appid = config('weipay.app_id');
        $key = config('weipay.key');
        $mch_id = config('weipay.mch_id');
        $notify_url = $this->base_url.'/open/weipay_notify';
        $data = [
            'body' => '常仁科技-'.$goods_info['goods_name'],
            'out_trade_no' => $goods_info['pay_sn'],
            'total_fee' => $goods_info['order_amount'] * 100,//换算到分
            'spbill_create_ip' => get_client_ip(0,true),
            'trade_type' => 'APP'
        ];
        $wechatAppPay = new wechatAppPay($appid,$mch_id,$notify_url,$key);
        $result = $wechatAppPay->unifiedOrder($data);
        if ($result['return_code'] != 'SUCCESS' || $result['result_code'] != 'SUCCESS') {
            $this->ajax_return('11592','failed to initiate weichat pay');
        }
        $time = time();
        $pay_data = [
            'pay_type' => OrderPay::PAY_TYPE_WECHAT,
            'pay_time' => $time
        ];
        $pay_result = $this->pay_model->save($pay_data,['pay_sn'=>$goods_info['pay_sn']]);
        if($pay_result === false){
            $this->ajax_return('11591','failed to update order info');
        }
        $pay_result = $wechatAppPay->getAppPayParams($result['prepay_id']);
        $this->ajax_return('200','success',$pay_result);
    }

    /**
    * 支付宝统一下单
    */ 
    protected function ali_pay($goods_info = array()){
        $time = time();
        $pay_data = [
            'pay_type' => OrderPay::PAY_TYPE_ALIPAY,
            'pay_time' => $time
        ];
        $pay_result = $this->pay_model->save($pay_data,['pay_sn'=>$goods_info['pay_sn']]);
        if($pay_result === false){
            $this->ajax_return('11591','failed to update order info');
        }
        $aop = new AopClient();
        $aop->gatewayUrl = config('aliyun.gateway_url');
        $aop->appId = config('aliyun.app_id');
        $aop->rsaPrivateKey = config('aliyun.rsa_private_key');
        $aop->format = "json";
        $aop->charset = "UTF-8";
        $aop->signType = "RSA2";
        $aop->alipayrsaPublicKey = config('aliyun.alipay_rsa_public_key');
        $bizcontent = json_encode([
            'body'        => $goods_info['goods_name'],
            'subject'     => '常仁科技-'.$goods_info['goods_name'],
            'out_trade_no'=> $goods_info['pay_sn'],
            'total_amount'=> $goods_info['order_amount'],//保留两位小数
            'product_code'=> 'QUICK_MSECURITY_PAY'
        ]);
        $request = new  AlipayTradeAppPayRequest();
        //支付宝回调
        $request->setNotifyUrl($this->base_url.'/open/alipay_notify');
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);
        $this->ajax_return('200','success',['response' => $response]);
    }
}