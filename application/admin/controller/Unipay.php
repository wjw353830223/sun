<?php
namespace app\admin\controller;
use Unipay\AcpService;
use Unipay\SdkConfig;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16
 * Time: 10:37
 */
class Unipay extends Adminbase
{
    //银联支付测试
    public function unipay()
    {
        return $this->fetch();
    }

    //银联网关支付测试
    public function unipay_post()
    {
        header('Content-type:text/html;charset=utf-8');
        if (request()->isPost()) {
            $post = input('param.');
            $params = array(
                //以下信息非特殊情况不需要改动
                'version' => SdkConfig::getSdkConfig()->version,                //版本号
                'encoding' => 'utf-8',                  //编码方式
                'txnType' => '01',                      //交易类型
                'txnSubType' => '01',                  //交易子类
                'bizType' => '000201',                  //业务类型
                'frontUrl' => SdkConfig::getSdkConfig()->frontUrl,  //前台通知地址
                'backUrl' => SdkConfig::getSdkConfig()->backUrl,      //后台通知地址
                'signMethod' => SdkConfig::getSdkConfig()->signMethod,                  //签名方法
                'channelType' => '08',                  //渠道类型，07-PC，08-手机
                'accessType' => '0',                  //接入类型
                'currencyCode' => '156',              //交易币种，境内商户固定156
                //TODO 以下信息需要填写
                'merId' => $post["merId"],        //商户代码，请改自己的测试商户号，此处默认取demo演示页面传递的参数
                'orderId' => $post["orderId"],    //商户订单号，8-32位数字字母，不能含“-”或“_”，此处默认取demo演示页面传递的参数，可以自行定制规则
                'txnTime' => $post["txnTime"],    //订单发送时间，格式为YYYYMMDDhhmmss，取北京时间，此处默认取demo演示页面传递的参数
                'txnAmt' => $post["txnAmt"],    //交易金额，单位分，此处默认取demo演示页面传递的参数
                // 订单超时时间。
                // 超过此时间后，除网银交易外，其他交易银联系统会拒绝受理，提示超时。 跳转银行网银交易如果超时后交易成功，会自动退款，大约5个工作日金额返还到持卡人账户。
                // 此时间建议取支付时的北京时间加15分钟。
                // 超过超时时间调查询接口应答origRespCode不是A6或者00的就可以判断为失败。
                'payTimeout' => date('YmdHis', strtotime('+15 minutes')),
                // 请求方保留域，
                // 透传字段，查询、通知、对账文件中均会原样出现，如有需要请启用并修改自己希望透传的数据。
                // 出现部分特殊字符时可能影响解析，请按下面建议的方式填写：
                // 1. 如果能确定内容不会出现&={}[]"'等符号时，可以直接填写数据，建议的方法如下。
                //    'reqReserved' =>'透传信息1|透传信息2|透传信息3',
                // 2. 内容可能出现&={}[]"'符号时：
                // 1) 如果需要对账文件里能显示，可将字符替换成全角＆＝｛｝【】“‘字符（自己写代码，此处不演示）；
                // 2) 如果对账文件没有显示要求，可做一下base64（如下）。
                //    注意控制数据长度，实际传输的数据长度不能超过1024位。
                //    查询、通知等接口解析时使用base64_decode解base64后再对数据做后续解析。
                //    'reqReserved' => base64_encode('任意格式的信息都可以'),
                //TODO 其他特殊用法请查看 special_use_purchase.php
            );
            AcpService::sign($params);
            $uri = SdkConfig::getSdkConfig()->frontTransUrl;
            $html_form = AcpService::createAutoFormHtml($params, $uri);
            echo $html_form;
        }
    }

    //银联提现测试
    public function present()
    {
        return $this->fetch();
    }

    //银联提现（代付）测试
    public function unipay_daifu()
    {
        header('Content-type:text/html;charset=utf-8');
        if (request()->isPost()) {
            $post = input('param.');
            $customer_info = [
                //'certifTp' => '01',//证件类型 01：身份证02：军官证03：护照04：港澳证05：台胞证06：警官证07：士兵证99：其它证件
                //'certifId' => $post['certifId'],//证件号码
                'customerNm' => $post['customerNm']//姓名
            ];
            $customer_info = AcpService::getCustomerInfoWithEncrypt($customer_info);
            $acc_no = AcpService::encryptData($post['accNo']);
            $order_id = date('YmdHis');
            $back_url = $this->base_url . '/open/unipay_notify/notify';
            $encrypt_cert_id = AcpService::getEncryptCertId();
            $params = array(
                //以下信息非特殊情况不需要改动
                'version' => SdkConfig::getSdkConfig()->version,                //版本号
                'encoding' => 'utf-8',                  //编码方式
                'signMethod' => SdkConfig::getSdkConfig()->signMethod, //签名方法
                'txnType' => '12',                      //交易类型 代付
                'txnSubType' => '00',                  //交易子类
                'bizType' => '000401',                  //业务类型 代付
                'channelType' => '08',              //渠道类型，07-PC，08-手机
                'accessType' => '0',              //接入类型 0：普通商户直连接入 1：收单机构接入 2：平台类商户接入
                'currencyCode' => '156',              //交易币种，境内商户固定156
                //商户接入参数
                'backUrl' => $back_url,      //后台通知地址
                'merId' => config('unipay.mer_id'),        //商户代码，请改自己的测试商户号，此处默认取demo演示页面传递的参数
                'orderId' => $order_id,    //商户订单号，8-32位数字字母，不能含“-”或“_”，此处默认取demo演示页面传递的参数，可以自行定制规则
                'txnTime' => date('YmdHis'),    //订单发送时间，格式为YYYYMMDDhhmmss，取北京时间，此处默认取demo演示页面传递的参数
                'txnAmt' => $post["txnAmt"],    //交易金额，单位分，此处默认取demo演示页面传递的参数
                'accType' => '01',   //账号类型 01：银行卡02：存折03：IC卡帐号类型(卡介质)
                'accNo' => $acc_no, //非绑定类交易时需上送卡号
                'encryptCertId' => $encrypt_cert_id,
                'customerInfo' => $customer_info,// 1 代付接口姓名和证件号必须出现一个。 2证件号出现时证件类型必须出现。
            );
            AcpService::sign($params);
            $uri = SdkConfig::getSdkConfig()->backTransUrl;
            $rsp_data = AcpService::post($params, $uri);
            var_dump($rsp_data);
        }
    }
    //实名认证测试
    public function back_auth()
    {
        return $this->fetch();
    }
    /**
     * 实名认证：后台交易，只有同步应答<br>
     * 验证银行卡验证信息及身份信息如证件类型、证件号码、姓名、密码、CVN2、有效期、手机号等与银行卡号的一致性
     */
    public function unipay_back_auth()
    {
        $post = input('param.');
        /*交易卡要素说明：
        银联后台会做控制，如使用真实商户号，要素为根据申请表配置，请参考自己的申请表上送。
        测试商户号777290058110097的交易卡要素控制配置：
        借记卡必送：
        （实名认证交易-后台）卡号，姓名，证件类型，证件号码，手机号
        （实名认证交易-前台）卡号，姓名，证件类型，证件号码
        贷记卡必送：（使用绑定标识码的代收）
        （实名认证交易-后台）卡号，有效期，cvn2,姓名，证件类型，证件号码，手机号，绑定标识码
        （实名认证交易-前台）卡号，证件类型，证件号码，绑定标识码*/
        $customerinfo = [
            'phoneNo' => $post['phoneNo'], //手机号
            'certifTp' => '01', //证件类型，01-身份证
            'certifId' => $post['certifId'], //证件号，15位身份证不校验尾号，18位会校验尾号，请务必在前端写好校验代码
            'customerNm' => $post['customerNm'], //姓名
            'cvn2' => $post['cvn2'], //cvn2
            'expired' => $post['expired'], //有效期，YYMM格式，持卡人卡面印的是MMYY的，请注意代码设置倒一下
        ];
        $customer_info = AcpService::getCustomerInfoWithEncrypt($customerinfo);
        $encrypt_cert_id = AcpService::getEncryptCertId();
        $order_id = date('YmdHis');
        $acc_no = AcpService::encryptData($post['accNo']);
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
        $result_arr = AcpService::post($params, $url);
        if (count($result_arr) <= 0) { //没收到200应答的情况
            $this->printResult($url, $params, "");
            return;
        }
        $this->printResult($url, $params, $result_arr); //页面打印请求应答数据
        if (!AcpService::validate ($result_arr) ){
            echo "应答报文验签失败<br>\n";
            return;
        }
        echo "应答报文验签成功<br>\n";
        if ($result_arr["respCode"] == "00") {
            //TODO
            echo "交易成功。<br>\n";
        } else {
            //其他应答码做以失败处理
            //TODO
            echo "失败：" . $result_arr["respMsg"] . "。<br>\n";
        }

    }

    public function printResult($url, $req, $resp)
    {
        echo "=============<br>\n";
        echo "地址：" . $url . "<br>\n";
        echo "请求：" . str_replace("\n", "\n<br>", htmlentities(createLinkString($req, false, true))) . "<br>\n";
        echo "应答：" . str_replace("\n", "\n<br>", htmlentities(createLinkString($resp, false, false))) . "<br>\n";
        echo "=============<br>\n";
    }
}