<?php
namespace app\common\model;

use think\exception\DbException;
use think\Model;
use Unipay\AcpService;
use Unipay\LogUtil;
use Unipay\SdkConfig;
class PdCash extends Model
{
	// 关闭自动时间格式化
    protected $createTime = false;
    const PDC_PAYMENT_STATE_PROCESSING = 3;//提现处理中
    const PDC_PAYMENT_STATE_SUCCESS = 1;//提现成功
    const PDC_PAYMENT_STATE_FAIL = 2;//提现失败
    const PDC_PAYMENT_STATE_FEFAULT = 0;//默认
    /**
     * 银联提现（代付接口）
     * 银联提现（代付接口）
     * @param $acc_no 银行卡号
     * @param $pdc_sn 提现单号
     * @param $back_url 后台异步通知地址
     * @param $pdc_member_name 提现者姓名
     * @param $pdc_amount 提现金额（分）
     */
    public function unipay_withdraw($card_id,$acc_no,$pdc_sn,$back_url,$pdc_member_name,$pdc_amount,$txn_time){
        $customer_info = [
            //'phoneNo'=> '17635670092', //手机号'
            'certifTp' => '01',//证件类型 01：身份证02：军官证03：护照04：港澳证05：台胞证06：警官证07：士兵证99：其它证件
            'certifId' => $card_id,//证件号码
            'customerNm' => $pdc_member_name//姓名
        ];
        $customer_info = AcpService::getCustomerInfoWithEncrypt($customer_info);
        $acc_no = AcpService::encryptData($acc_no);
        $encrypt_cert_id = AcpService::getEncryptCertId();
        $params = array(
            'version' => SdkConfig::getSdkConfig()->version,
            'encoding' => 'utf-8',
            'signMethod' => SdkConfig::getSdkConfig()->signMethod,
            'txnType' => '12',
            'txnSubType' => '00',
            'bizType' => '000401',
            'channelType' => '08',
            'accessType' => '0',
            'currencyCode' => '156',
            'backUrl' => $back_url,
            'merId' => config('unipay.mer_id'),
            'orderId' => $pdc_sn,
            'txnTime' => $txn_time,
            'txnAmt' => $pdc_amount * 100,
            'accType' => '01',
            'accNo' => $acc_no,
            'encryptCertId' => $encrypt_cert_id,
            'customerInfo' => $customer_info,
            'reqReserved' => intval(session('ADMIN_ID'))

        );
        AcpService::sign($params);
        $uri = SdkConfig::getSdkConfig()->backTransUrl;
        $logger = LogUtil::getLogger();
        $logger->LogInfo ( PHP_EOL."接受同步返回数据开始：");
        $rsp_data = AcpService::post($params,$uri);
        if(empty($rsp_data)){
            $logger->LogInfo ('未获取到返回报文或返回http状态码非200' );
            return[
                'state' => 'fail',
                'msg' => '未获取到银联返回报文或返回http状态码非200'
            ];
        }
        if(!AcpService::validate($rsp_data)){
            return [
                'state' => 'fail',
                'msg' => '验签失败，请检查银联证书'
            ];
        }
        if($rsp_data['respCode'] !== '00'){
            return [
                'state' => 'fail',
                'msg' => '失败，' . $rsp_data['respMsg'],
            ];
        }
        $logger->LogInfo ( PHP_EOL."接受同步返回数据结束。");
        //解密银行卡号
        //$rsp_data['accNo'] =  AcpService::decryptData($rsp_data['accNo']);
        return [
            'state' => 'success',
            'msg' => '银联已成功接收代付请求',
        ];
    }
    /**
     * 代付交易状态查询
     * @param $orderId
     * @param $txnTime
     * @return bool
     */
    public function withdraw_query($orderId,$txnTime){
        $params = [
            'version' => SdkConfig::getSdkConfig()->version,
            'encoding' => 'utf-8',
            'signMethod' => SdkConfig::getSdkConfig()->signMethod,
            'txnType' => '00',
            'txnSubType' => '00',
            'bizType' => '000401',
            'accessType' => '0',
            'merId' => config('unipay.mer_id'),
            'orderId' => $orderId,
            'txnTime' => $txnTime,
        ];
        AcpService::sign($params);
        $uri = SdkConfig::getSdkConfig()->singleQueryUrl;
        $rsp_data = AcpService::post($params,$uri);
        $logger = LogUtil::getLogger();
        $logger->LogInfo ( '查询交易获得报文参数：' . PHP_EOL );
        if(is_array($rsp_data)){
            foreach($rsp_data as $key => $val){
                $logger->LogInfo ($key.':'.$val . PHP_EOL);
            }
        }
        $logger->LogInfo ( '查询交易结束'.PHP_EOL );
        if(empty($rsp_data)){
            return false;
        }
        return $rsp_data;
        /*if($rsp_data['respCode']==='00' && $rsp_data['origRespCode'] === '00'){
            return $rsp_data;
        }
        return false;*/
    }
    /**
     * 通过pdc_sn查询一条记录
     * @param $pdc_sn
     */
    public function pdcash_get_by_pdcsn($pdc_sn){
        $pd_cash_record = $this->where(['pdc_sn'=>$pdc_sn])->find();
        return  $pd_cash_record;
    }

    /**
     *更新提现记录
     * @param $pdc_id
     * @param $data
     * @return $this
     */
    public function pdcash_update($pdc_id,$data){
        return $this->where(['pdc_id'=>$pdc_id])->update($data);
    }
    /**
     * 当月提现次数
     * @param $member_id
     * @return int|string
     */
    public function cash_withdraw_month_count($member_id){
       return $this->where(['pdc_member_id'=>$member_id,'pdc_payment_state'=>['in',[PdCash::PDC_PAYMENT_STATE_FEFAULT, PdCash::PDC_PAYMENT_STATE_SUCCESS]]])
            ->whereTime('pdc_add_time','month')
            ->count();
    }

    /**
     * 计算个人所得税
     * @param $amount
     * @return float|int
     */
    public function  personal_income_tax($amount){
        if($amount - 3500 <= 1500 && $amount - 3500 > 0){
            $dec_amount = ($amount - 3500)*0.03;
        }elseif($amount - 3500 <= 4500 && $amount - 3500 > 1500){
            $dec_amount = ($amount - 3500)*0.1 - 105;
        }elseif($amount - 3500 > 4500 && $amount - 3500 <= 9000){
            $dec_amount = ($amount - 3500)*0.2 - 555;
        }elseif($amount - 3500 > 9000 && $amount - 3500 <= 35000){
            $dec_amount = ($amount - 3500)*0.25 - 1005;
        }elseif($amount - 3500 > 35000 && $amount - 3500 <= 55000){
            $dec_amount = ($amount - 3500)*0.3 - 2755;
        }elseif($amount - 3500 > 55000 && $amount - 3500 <= 80000){
            $dec_amount = ($amount - 3500)*0.35 - 5505;
        }elseif($amount - 3500 > 80000){
            $dec_amount = ($amount - 3500)*0.45 - 13505;
        }else{
            $dec_amount = 0;
        }
        if($dec_amount !== 0){
            $dec_amount_exp = explode('.',$dec_amount);
            if(count($dec_amount_exp) == 2){
                $dec_amount_exp[1] = substr($dec_amount_exp[1],0,2);
            }
            $dec_amount = implode('.',$dec_amount_exp);
        }
        return $dec_amount;
    }

    /**
     * 提现预处理
     * @param $member_id
     * @param $amount 提现金额
     * @param $dec_amount 税额
     * @param $bank_name 开户行
     * @param $bank_no 	银行卡账号
     * @param $bank_user 持卡人
     * @return bool
     */
    public function withdraw_update($member_id,$amount,$dec_amount,$bank_name,$bank_no,$bank_user){
        $pdc_amount = $amount - $dec_amount;//实际提现金额
        $member_model = model('Member');
        $pd_cash_model = model('PdCash');
        $order_model = model('Order');
        $member = $member_model->member_get_info_by_id($member_id);
        $pdc_sn = $order_model->make_paysn($member_id);
        $member_model->startTrans();
        $pd_cash_model->startTrans();
        //在可用余额中扣除提现金额
        if($member_model->where(['member_id'=>$member_id])->setDec('av_predeposit',$amount) === false){
            return false;
        }
        $pl_data = [
            'dec_amount' => $dec_amount,
            'amount'     => $amount,
            'pdc_amount' => $pdc_amount
        ];
        //记录提现日志
        $data = [
            'pdc_sn'            => $pdc_sn,
            'pdc_member_id'     => $member_id,
            'pdc_member_name'   => $member['member_name'],
            'pdc_amount'        => $pdc_amount,
            'pdc_bank_name'     => $bank_name,
            'pdc_bank_no'       => $bank_no,
            'pdc_bank_user'     => $bank_user,
            'pdc_add_time'      => time(),
            'pdc_desc'          => '余额转出',
            'pdc_data'          => json_encode($pl_data)
        ];
        if($pd_cash_model->save($data) === false){
            $member_model->rollback();
            return false;
        }
        $member_model->commit();
        $pd_cash_model->commit();
        return true;
    }
}