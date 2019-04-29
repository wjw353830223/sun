<?php
namespace app\index\controller;

use Alipay\AopClient;
use Alipay\request\AlipayTradeWapPayRequest;
use app\common\model\OrderPay;
use Lib\wechatAppPay;

class Pay extends Indexbase
{
    protected $order_model,$pay_model,$member_model,$log_model,$payment_model;
    protected function _initialize(){
        parent::_initialize();
        $this->order_model = model('Order');
        $this->pay_model = model('OrderPay');
        $this->member_model = model('Member');
        $this->log_model = model('OrderLog');
        $this->payment_model = model('Payment');
    }
    public function index(){
        return $this->fetch();
    }
    /**
     * 支付初始化
     */
    public function initiate(){
        $pay_type = input('param.pay_type','','trim');
        if (empty($pay_type) || !in_array($pay_type,['unipay','weipay','alipay'])) {
            $this->error('支付类型无效');
        }
        $order_sn = input('param.order_sn','','trim');
        if (empty($order_sn)) {
            $this->error('订单编码无效');
        }
        $order_info = $this->order_model->with('goodsInfo')->where(['order_sn' => $order_sn])->find();
        if (is_null($order_info)) {
            $this->error('订单编码无效');
        }
        $order_amount = $order_info['order_amount'];
        $opd_amount = $order_info['pd_amount'];
        $opos_amount = $order_info['pos_amount'];
        //支付订单、关闭订单不能再被支付  pay_sn动态，
        if($order_info['order_state'] == 0){
            $this->error('订单已关闭');
        }
        if($order_info['order_state'] == 20){
            $this->error('请勿重复支付');
        }
        //剩余支付金额
        $order_amount = number_format($order_amount - $opd_amount - $opos_amount,2,'.','');
        $order_info = $order_info->toArray();
        $goods_info = $order_info['goods_info'];
        $goods_info['pay_sn'] = $order_info['pay_sn'];
        $goods_info['order_sn'] = $order_sn;
        $goods_info['order_amount'] = $order_amount;
        $return_url = url('index/open/pay_return','','html',true);
        switch ($pay_type) {
            case 'weipay':
                $pay_result = $this->weichat_pay($goods_info,$return_url);
                break;
            case 'alipay':
                $pay_result = $this->ali_pay($goods_info,$return_url);
                break;
            case 'unipay':
                $pay_result = $this->union_pay($goods_info,$return_url);
                break;
            default:
                # code...
                break;
        }
        if($pay_result){
            return $pay_result;
        }
        $this->error('订单类型无效');
    }
    /**
     * 银行卡支付 返回h5网页 自动跳转到支付
     * @param array $goods_info
     */
    protected function union_pay($goods_info = array(),$return_url){
        $back_url = $this->base_url.'/open/unipay_notify/pay_notify';
        $front_url = $return_url;
        $time = time();
        $txn_time = date('YmdHis', $time);
        $pay_html = model('OrderPay')->unipay($goods_info['pay_sn'],$goods_info['order_amount'],$back_url, $txn_time,$front_url,'MWEB');
        if($pay_html === false){
            $this->error('银行卡支付初始化失败');
        }
        $pay_data = [
            'pay_type' => OrderPay::PAY_TYPE_UNIONPAY,
            'pay_time' => $time
        ];
        $pay_result = $this->pay_model->save($pay_data,['pay_sn'=>$goods_info['pay_sn']]);
        if($pay_result === false){
            $this->error('更新支付信息失败');
        }
        return $this->display($pay_html);
    }
    /**
     * 微信统一下单 返回mweb_url链接 前端拉起支付
     */
    protected function weichat_pay($goods_info = array(),$return_url){
        $appid = config('weipay.app_id');
        $key = config('weipay.key');
        $mch_id = config('weipay.mch_id');
        $notify_url = $this->base_url.'/open/weipay_notify';
        $data = [
            'body' => '常仁科技-'.$goods_info['goods_name'],
            'out_trade_no' => $goods_info['pay_sn'],
            'total_fee' => $goods_info['order_amount'] * 100,//换算到分
            'spbill_create_ip' => get_client_ip(0,true),
            'trade_type' => 'MWEB',
            'scene_info' => json_encode(['h5_info'=>['type'=>'Wap','wap_url'=>$this->base_url.'/index/pay/index','wap_name'=>'太阳健康商城']])
        ];
        $wechatAppPay = new wechatAppPay($appid,$mch_id,$notify_url,$key);
        $result = $wechatAppPay->unifiedOrder($data);
        if ($result['return_code'] != 'SUCCESS' || $result['result_code'] != 'SUCCESS') {
            $this->error('微信支付初始化失败:' . $result['return_msg']);
        }
        $time = time();
        $pay_data = [
            'pay_type' => OrderPay::PAY_TYPE_WECHAT,
            'pay_time' => $time
        ];
        $pay_result = $this->pay_model->save($pay_data,['pay_sn'=>$goods_info['pay_sn']]);
        if($pay_result === false){
            $this->error('更新支付信息失败');
        }
        $mweb_url = $result['mweb_url'].'&redirect_url='.urlencode($return_url);
        $this->ajax_return('200','微信预支付订单生成成功',['mweb_url'=>$mweb_url]);
    }

    /**
     * 支付宝统一下单 返回form表单页 自动跳转到支付
     */
    protected function ali_pay($goods_info = array(),$return_url){
        $time = time();
        $pay_data = [
            'pay_type' => OrderPay::PAY_TYPE_ALIPAY,
            'pay_time' => $time
        ];
        $pay_result = $this->pay_model->save($pay_data,['pay_sn'=>$goods_info['pay_sn']]);
        if($pay_result === false){
            $this->error('更新订单信息失败');
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
            'product_code'=> 'QUICK_WAP_PAY'
        ]);
        $request = new  AlipayTradeWapPayRequest();
        //支付宝回调
        $request->setNotifyUrl($this->base_url.'/open/alipay_notify');
        $request->setReturnUrl($return_url);
        $request->setBizContent($bizcontent);
        $response = $aop->pageExecute($request);
        return $this->display($response);
    }
}
