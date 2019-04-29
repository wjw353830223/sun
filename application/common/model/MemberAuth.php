<?php

namespace app\common\model;

use think\Model;
use Unipay\AcpService;
use Unipay\LogUtil;
use Unipay\SdkConfig;

class MemberAuth extends Model
{
    protected $createTime = false;
    const AUTH_STATE_DEFAULT = 0;//审核中
    const AUTH_STATE_SUCCESS = 1;//审核成功
    const AUTH_STATE_FAIL = 2;//审核失败
    /**
     * 银联代付实名认证
     * 如使用真实商户号，要素为根据申请表配置，请参考自己的申请表上送。
      测试商户号交易卡要素控制配置：
      借记卡必送：
        （实名认证交易-后台）卡号，姓名，证件类型，证件号码，手机号
      贷记卡必送：（使用绑定标识码的代收）
        （实名认证交易-后台）卡号，有效期，cvn2,姓名，证件类型，证件号码，手机号
     * @param $phone_no 银行预留电话
     * @param $certif_id 身份证号
     * @param $customer_name 姓名
     * @param $acc_no 银行卡号
     * @param null $card_cvn2  贷记卡背面cvn2码
     * @param null $expired 贷记卡背面过期时间
     * @return array
     */
    public function unipay_back_auth($phone_no, $certif_id, $customer_name, $acc_no, $card_cvn2 = null, $expired = null)
    {
        //$phone_no = '13552535506';
        $customerinfo = [
            'phoneNo' => $phone_no, //手机号
            'certifTp' => '01', //证件类型，01-身份证
            'certifId' => $certif_id, //证件号，15位身份证不校验尾号，18位会校验尾号，请务必在前端写好校验代码
            'customerNm' => $customer_name, //姓名
            'cvn2' => $card_cvn2, //cvn2
            'expired' => $expired, //有效期，YYMM格式，持卡人卡面印的是MMYY的，请注意代码设置倒一下
        ];
        if(empty($card_cvn2)){
            unset($customerinfo['cvn2']);
        }
        if(empty($expired)){
            unset($customerinfo['expired']);
        }
        $customer_info = AcpService::getCustomerInfoWithEncrypt($customerinfo);
        $encrypt_cert_id = AcpService::getEncryptCertId();
        $order_id = date('YmdHis');
        $acc_no = AcpService::encryptData($acc_no);
        $params = array(
            //以下信息非特殊情况不需要改动
            'version' => SdkConfig::getSDKConfig()->version,                   //版本号
            'encoding' => 'utf-8',                   //编码方式
            'signMethod' => SdkConfig::getSDKConfig()->signMethod,                   //签名方法
            'txnType' => '72',                       //交易类型
            'txnSubType' => '01',                   //交易子类
            'bizType' => '000401',                   //业务类型
            'accessType' => '0',                   //接入类型
            'channelType' => '07',                   //渠道类型
            'encryptCertId' => $encrypt_cert_id, //验签证书序列号
            //TODO 以下信息需要填写
            'merId' => config('unipay.mer_id'),    //商户代码，请改自己的测试商户号，此处默认取demo演示页面传递的参数
            'orderId' => $order_id,    //商户订单号，8-32位数字字母，不能含“-”或“_”，此处默认取demo演示页面传递的参数，可以自行定制规则
            'txnTime' => date('YmdHis'),    //订单发送时间，格式为YYYYMMDDhhmmss，取北京时间，此处默认取demo演示页面传递的参数
            'accNo' => $acc_no,     //卡号，新规范请按此方式填写
            'customerInfo' => $customer_info, //持卡人身份信息，新规范请按此方式填写
        );
        AcpService::sign($params);
        $url = SdkConfig::getSdkConfig()->backTransUrl;
        $logger = LogUtil::getLogger();
        $logger->LogInfo ( PHP_EOL."实名认证接收返回数据开始：");
        $result_arr = AcpService::post($params, $url);
        if (count($result_arr) <= 0) { //没收到200应答的情况
            $logger->LogInfo ('未获取到返回报文或返回http状态码非200' );
            return false;
        }
        if(!AcpService::validate($result_arr)){
            $logger->LogInfo('验签失败，请检查银联证书');
            return false;
        }
        if($result_arr['respCode'] !== '00'){
            $logger->LogInfo('失败，' . $result_arr['respMsg']);
            return false;
        }
        $logger->LogInfo ( PHP_EOL."实名认证接收返回数据结束。");
        return $result_arr;
    }

    /**
     * 保存认证信息
     * @param $auth
     * @return bool|mixed
     */
    public function auth_save($auth){
        $res = $this->save($auth);
        if($res === false){
            return false;
        }
        return $this->ma_id;
    }
    /**
     * 阿里云银行卡实名认证
     * @param $bank_card 银行卡号
     * @param $real_name 姓名
     * @param $card_no 身份证号
     * @param $mobile 手机号码
     * @return bool
     */
    public function aliyun_bankcard_verify($mobile, $bank_card,$real_name,$card_no){
        $params = [
            'bankcard' => $bank_card,
            'realName' => $real_name,
            'cardNo' => $card_no,
            'Mobile' => $mobile
        ];
        $app_code = config('aliyun.bankcard_verify_app_code');
        $url = config('aliyun.bankcard_verify_url');
        $result = $this->aliyun_api_store($url, $params, $app_code, "GET");
        return $result;
    }

    /**
     * 增加身份证认证图片
     * @param $ma_id
     * @param $card_face
     * @param $card_back
     * @param $card_hand
     * @return bool
     */
    public function add_auth_card($ma_id,$card_face,$card_back,$card_hand){
        if(empty($card_face) || empty($card_back) || empty($card_hand) ){
            return false;
        }
        $auth = [
            'card_face' => $card_face,
            'card_back' => $card_back,
            'card_hand' => $card_hand,
            'ma_id'=> $ma_id
        ];
        if($this->auth_save($auth) === false){
            return false;
        }
        return true;
    }
    /**
     * 实名认证
     * @param $member_id
     * @param $bank_card
     * @param $real_name
     * @param $card_no
     * @param $card_face 身份证正面
     * @param $card_back 身份证背面
     * @param $card_hand 手持身份证
     * @param $auth_from 认证接口来源 阿里云/银联代付
     * @return bool
     */
    public function auth_verify($member_id, $bank_card,$real_name,$card_no,$api_from = 'aliyun'){
        $mobile = model('Member')->where(['member_id'=>$member_id])->value('member_mobile');
        $auth = [
            'bank_no' => $bank_card,
            'member_id'  => $member_id,
            'auth_name'  => $real_name,
            'id_card'   => $card_no,
            'apply_time' => time(),
        ];
        $message = [
            'from_member_id' => 0,
            'to_member_id'   => $member_id,
            'message_title'  => '实名认证通知',
            'message_time'   => time(),
            'message_state'  => 0,
            'message_type'   => 6,
            'is_more'        => 0,
            'is_push'        => 2,
            'push_member_id' => $member_id,
        ];
        if($api_from == 'aliyun'){
            $result = $this->aliyun_bankcard_verify($mobile, $bank_card,$real_name,$card_no);
            $auth_success = $result['error_code'] == 0 ? true : false;
            $reason = $result['reason'];
        }
        if($api_from == 'unipay'){
            $result = $this->unipay_back_auth($mobile, $card_no, $real_name, $bank_card);
            $auth_success = $result === false ? false : true;
            $reason = $result['respMsg'];
        }
        $member_model = model('Member');
        $message_model = model('Message');
        $message_model->startTrans();
        $this->startTrans();
        if($auth_success){
            $member_model->startTrans();
            $push_detail = '您的实名认证申请已经通过。';
            $title = '实名认证通过';
            $message['message_body'] = $push_detail;
            $message['push_detail'] = $push_detail;
            $message['push_title'] = $title;
            $auth['auth_state'] = MemberAuth::AUTH_STATE_SUCCESS;
        }else{
            $push_detail = "您的实名认证申请未通过，已提交人工审核。\n原因：".$reason;
            $title = '实名认证未通过';
            $message['message_body'] = $push_detail;
            $message['push_detail'] = $push_detail;
            $message['push_title']  = $title;
            $auth['auth_state'] = MemberAuth::AUTH_STATE_DEFAULT;
        }
        if($this->auth_save($auth) === false){
            return false;
        }
        if($auth_success && $member_model->where(['member_id'=>$member_id])->update(['is_auth'=>Member::AUTH_SUCCESS]) === false){
            $this->rollback();
            return false;
        }
        if(!$message_model->send_message($mobile,$push_detail,$title,$message)){
            $this->rollback();
            $message_model->rollback();
            $auth_success && $member_model->rollback();
            return false;
        }
        $this->commit();
        $message_model->commit();
        $auth_success && $member_model->commit();
        return $this->ma_id;
    }
    /**
     * 阿里云api请求
     * @param $url
     * @param array $params
     * @param $appCode
     * @param string $method
     * @return array|mixed
     */
    protected function aliyun_api_store($url, $params = array(), $appCode, $method = "GET")
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $method == "POST" ? $url : $url . '?' . http_build_query($params));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization:APPCODE ' . $appCode
        ));
        //如果是https协议
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
            //CURL_SSLVERSION_TLSv1
            curl_setopt($curl, CURLOPT_SSLVERSION, 1);
        }
        //超时时间
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //通过POST方式提交
        if ($method == "POST") {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        //返回内容
        $callbcak = curl_exec($curl);
        //http status
        $CURLINFO_HTTP_CODE = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        //关闭,释放资源
        curl_close($curl);
        //如果返回的不是200,请参阅错误码 https://help.aliyun.com/document_detail/43906.html
        if ($CURLINFO_HTTP_CODE == 200)
            return json_decode($callbcak, true);
        else if ($CURLINFO_HTTP_CODE == 403)
            return array("error_code" => $CURLINFO_HTTP_CODE, "reason" => "剩余次数不足");
        else if ($CURLINFO_HTTP_CODE == 400)
            return array("error_code" => $CURLINFO_HTTP_CODE, "reason" => "APPCODE错误");
        else
            return array("error_code" => $CURLINFO_HTTP_CODE, "reason" => "APPCODE错误");
    }

    /**
     * 验证用户是否已经实名认证成功
     * @param $member_id
     * @return bool
     */
    public function member_auth_judge($member_id){
        $has_one = $this->where(['auth_state'=>Member::AUTH_SUCCESS,'member_id'=>$member_id])->count();
        if($has_one > 0){
            return true;
        }
        return false;
    }
    /**
     * 通过身份证号获取已认证用户认证信息
     * @param $id_card
     * @return array|false|\PDOStatement|string|Model
     */
    public function member_get_by_idcard($id_card){
        return  $this->where(['id_card'=>$id_card,'auth_state'=>MemberAuth::AUTH_STATE_SUCCESS])->find();
    }
    /**
     * 获取最新一次认证状态
     * @param $member_id
     * @param $id_card
     * @return mixed
     */
    public function member_last_auth_state($member_id,$id_card){
        return $this->where(['member_id'=>$member_id,'id_card'=>$id_card])->order(['apply_time'=>'DESC'])->limit(1)->value('auth_state');
    }

    /**
     * 获取用户实名认证信息
     * @param $member_id
     * @return array|false|\PDOStatement|string|Model
     */
    public function member_auth_last_verify($member_id){
        return $this->where(['member_id'=>$member_id,'auth_state'=>MemberAuth::AUTH_STATE_SUCCESS])->field('auth_name,id_card,bank_no,auth_state')->find();
    }
}