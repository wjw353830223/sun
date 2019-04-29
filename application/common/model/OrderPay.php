<?php
namespace app\common\model;

use Alipay\AopClient;
use Alipay\request\AlipayTradeQueryRequest;
use Lib\wechatAppPay;
use think\Model;
use Unipay\AcpService;
use Unipay\LogUtil;
use Unipay\SdkConfig;
use think\Log;
class OrderPay extends Model
{
    const API_PAY_STATE_SUCCESS = 1;
    const API_PAY_STATE_DEFAULT = 0;
    const PAY_TYPE_WECHAT = 1;
    const PAY_TYPE_ALIPAY = 2;
    const PAY_TYPE_UNIONPAY = 3;
    const PAY_TYPE_DEFAULT = 0;
    /**
     * 银联支付
     * @param $order_id
     * @param $order_amount
     * @param $back_url
     * @param null $front_url
     * @return bool
     */
    public function unipay($order_id,$order_amount,$back_url,$txn_time,$front_url=null,$trade_type='APP'){
        $params = array(
            'version' => SdkConfig::getSdkConfig()->version,
            'encoding' => 'utf-8',
            'signMethod' => SdkConfig::getSdkConfig()->signMethod,
            'txnType' => '01',
            'txnSubType' => '01',
            'bizType' => '000201',
            'currencyCode' => '156',
            'frontUrl' => $front_url,
            'channelType' => '08',
            'accessType' => '0',
            'backUrl' => $back_url,
            'merId' => config('unipay.mer_id'),
            'orderId' => $order_id,
            'txnTime' => $txn_time,
            'txnAmt' => $order_amount * 100,
        );
        if(empty($front_url)){
           unset($params['frontUrl']);
        }
        AcpService::sign($params);
        $logger = LogUtil::getLogger();
        if($trade_type=='MWEB'){
            $uri = SdkConfig::getSdkConfig()->frontTransUrl;
            $html_form = AcpService::createAutoFormHtml($params, $uri );
            return $html_form;
        }else{
            $uri = SdkConfig::getSdkConfig()->appTransUrl;
            $logger->LogInfo ( PHP_EOL."接受同步返回数据开始：");
            $rsp_data = AcpService::post($params,$uri);
            if(empty($rsp_data)){
                $logger->LogInfo ('未获取到返回报文或返回http状态码非200' );
                return false;
            }
            if(!AcpService::validate($rsp_data)){
                $logger->LogInfo ('验签失败，请检查银联证书' );
                return false;
            }
            if($rsp_data['respCode'] !== '00'){
                $logger->LogInfo('失败，' . $rsp_data['respMsg']);
                return false;
            }
            $logger->LogInfo ( PHP_EOL."接受同步返回数据结束。");
            //解密银行卡号
            //$rsp_data['accNo'] =  AcpService::decryptData($rsp_data['accNo']);
            return $rsp_data['tn'];
        }
    }
    /**
     * 银联交易状态查询
     * @param $orderId
     * @param $txnTime
     * @return bool
     */
    public function pay_query($orderId,$txnTime){
        $params = [
            'version' => SdkConfig::getSdkConfig()->version,
            'encoding' => 'utf-8',
            'signMethod' => SdkConfig::getSdkConfig()->signMethod,
            'txnType' => '00',
            'txnSubType' => '00',
            'bizType' => '000000',
            'accessType' => '0',
            'merId' => config('unipay.mer_id'),
            'orderId' => $orderId,
            'txnTime' => $txnTime,
        ];
        AcpService::sign($params);
        $uri = SdkConfig::getSdkConfig()->singleQueryUrl;
        $rsp_data = AcpService::post($params,$uri);
        if(empty($rsp_data)){
            return false;
        }
        return $rsp_data;
    }

    /**
     * 微信支付状态查询
     * @param $out_trade_no
     * @return array
     */
    public function wechat_pay_query($out_trade_no){
        $appid = config('weipay.app_id');
        $key = config('weipay.key');
        $mch_id = config('weipay.mch_id');
        $wechatAppPay = new wechatAppPay($appid,$mch_id,'',$key);
        return $wechatAppPay->orderQuery($out_trade_no);
    }
    /**
     * 支付宝支付状态查询
     * @param $out_trade_no
     * @param $trade_no
     * @return array
     */
    public function ali_pay_query($out_trade_no){
        $aop = new AopClient ();
        $aop->gatewayUrl = config('aliyun.gateway_url');
        $aop->appId = config('aliyun.app_id');
        $aop->rsaPrivateKey = config('aliyun.rsa_private_key');
        $aop->alipayrsaPublicKey=config('aliyun.alipay_rsa_public_key');
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';
        $aop->format='json';
        $request = new AlipayTradeQueryRequest();
        $request->setBizContent(json_encode(['out_trade_no'=>$out_trade_no]));
        $result = $aop->execute ( $request);
        return $result;
    }
    public function update_pay_record($pay_sn,$pay_amount){
        $data = [
            'api_pay_state' => OrderPay::API_PAY_STATE_SUCCESS,
            'pay_amount' => $pay_amount
        ];
        return $this->where(['pay_sn' => $pay_sn])->update($data);
    }

    /**
     * 支付状态查询
     * @param $pay_sn
     * @param $pay_time
     * @param $buyer_id
     * @param $pay_type
     * @param $callback
     * @return bool/string
     */
    public function order_query($pay_sn,$pay_time,$pay_type,$callback){
        switch($pay_type){
            case OrderPay::PAY_TYPE_WECHAT:
                $resp_data = call_user_func_array($callback,[$pay_sn]);
                $payment_code  = 'weixin';
                isset($resp_data['total_fee']) && $total_fee = $resp_data['total_fee']/100;
                $pay_name = '微信';
                break;
            case OrderPay::PAY_TYPE_UNIONPAY:
                $resp_data = call_user_func_array($callback,[$pay_sn,date('YmdHis', $pay_time)]);
                $payment_code  = 'unipay';
                isset($resp_data['txnAmt']) && $total_fee = $resp_data['txnAmt']/100;
                $pay_name = '银行卡';
                break;
            case OrderPay::PAY_TYPE_ALIPAY:
                $resp_data = call_user_func_array($callback,[$pay_sn,'']);
                $payment_code  = 'alipay';
                isset($resp_data['total_amount']) && $total_fee = $resp_data['total_amount'];
                $pay_name = '支付宝';
                break;
            default:
                break;
        }
        if(($pay_type === OrderPay::PAY_TYPE_WECHAT && $resp_data['return_code'] =='SUCCESS' && $resp_data['trade_state'] == 'SUCCESS')
            || ($pay_type === OrderPay::PAY_TYPE_UNIONPAY && $resp_data['respCode'] =='00' && $resp_data['origRespCode'] == '00')
            || ($pay_type === OrderPay::PAY_TYPE_ALIPAY && $resp_data['code'] && $resp_data['trade_status'] == 'TRADE_SUCCESS')){
            return [
                'pay_name'=>$pay_name,
                'payment_code'=>$payment_code,
                'total_fee'=>$total_fee
            ];
        }
        return false;
    }
    /**
     * 支付状态查询并做后续处理
     * @param $pay_sn 支付单号
     * @param $pay_time 支付时间
     * @param $buyer_id 用户id
     * @param $pay_type 支付方式
     * @param $callback 订单查询函数
     * @return bool
     */
    public function order_query_process($pay_sn,$pay_time,$buyer_id,$pay_type,$callback){
        $order_pay_model = model('OrderPay');
        $order_model = model('Order');
        $points_log_model = model('PointsLog');
        $member_model = model('Member');
        $experience_log_model = model('ExperienceLog');
        $message_model = model('Message');
        $order_log_model = model('OrderLog');
        $order_partition_model = model('OrderPartition');
        Log::write('支付状态查询开始','info');
        if(!empty($pay_sn) && !empty($pay_time)){
            $data = $this->order_query($pay_sn,$pay_time,$pay_type,$callback);
            if($data !== false){
                $payment_code = $pay_type_third =$data['payment_code'];
                $pay_name = $data['pay_name'];
                $total_fee = $data['total_fee'];
                Log::write('支付方式：' . $pay_name,'info');
                $order_info = $order_model->where(['buyer_id' => $buyer_id,'pay_sn' => $pay_sn])->find();
                if (empty($order_info)) {
                    Log::write('客户'.$pay_name.'支付成功，但查无订单数据，支付单号:'.$pay_sn,'notice');
                    return false;
                }
                $payment_only_third = $order_info['pos_amount'] ==0 && $order_info['pd_amount'] ==0 ? true : false;
                $order_partitions = $order_partition_model->where(['pay_sn'=>$pay_sn])->select();//有分订单 购物车结算来的
                $member = $member_model->field('experience,member_grade,parent_id,member_name')->getByMemberId($buyer_id);
                if(!$payment_only_third){
                    $payment_code = 'mixed';
                }
                $order_car = !$order_partitions->isEmpty() ? true : false;
                $order_model->startTrans();
                $order_pay_model->startTrans();
                $order_log_model->startTrans();
                $order_car && $order_partition_model->startTrans();
                if(!$order_model->update_order_record($buyer_id, $pay_sn, $payment_code, $order_car)){
                    $order_car && $order_partition_model->rollback();
                    return false;
                }
                Log::write('更新订单状态成功','info');
                if(!$order_pay_model->update_pay_record($pay_sn, $total_fee)){
                    $order_model->rollback();
                    $order_car && $order_partition_model->rollback();
                    return false;
                }
                Log::write('更新支付状态成功','info');
                if(!$order_log_model->add_log_after_pay($member['member_name'],$pay_sn,$order_info['order_id'],
                    $order_info['pos_amount'],$order_info['pd_amount'],$total_fee,$pay_type_third)){
                    $order_model->rollback();
                    $order_pay_model->rollback();
                    $order_car && $order_partition_model->rollback();
                    return false;
                }
                Log::write('写入订单日志成功','info');
                $member_model->startTrans();
                $points_log_model->startTrans();
                $experience_log_model->startTrans();
                $message_model->startTrans();
                if(!$order_model->upgrade_after_pay($buyer_id,$order_info['order_id'])){
                    $order_model->rollback();
                    $order_pay_model->rollback();
                    $order_log_model->rollback();
                    $order_car && $order_partition_model->rollback();
                    $member_model->rollback();
                    $points_log_model->rollback();
                    $experience_log_model->rollback();
                    $message_model->rollback();
                    return false;
                }
                Log::write('写入经验和积分日志成功','info');
                if(!$order_model->parent_upgrade_after_pay($member['parent_id'],$order_info['order_id'])){
                    $order_model->rollback();
                    $order_pay_model->rollback();
                    $order_log_model->rollback();
                    $order_car && $order_partition_model->rollback();
                    $member_model->rollback();
                    $points_log_model->rollback();
                    $experience_log_model->rollback();
                    $message_model->rollback();
                    return false;
                }
                Log::write('上级用户获取积分成功','info');
                $order_pay_model->commit();
                $order_model->commit();
                $order_log_model->commit();
                $points_log_model->commit();
                $member_model->commit();
                $experience_log_model->commit();
                $message_model->commit();
                $order_car && $order_partition_model->commit();
            }else{
                $order_pay_model->startTrans();
                $orp_result = $order_pay_model->where(['pay_sn' => $pay_sn])->update(['pay_type' => OrderPay::PAY_TYPE_DEFAULT,'pay_time' => 0]);
                if (empty($orp_result)) {
                    return false;
                }
                $order_pay_model->commit();
                Log::write('支付状态查询失败','notice');
            }
        }
        Log::write('支付状态查询结束','info');
    }
}