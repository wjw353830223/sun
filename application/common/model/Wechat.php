<?php

namespace app\common\model;
use think\Model;
class Wechat extends Model{
    const APPID = 'wxbcf1781ee37efb37';
    const APPSECRET = '06683afb6a0229f041ae6f306df6263b';
    public function __construct()
    {
        parent::__construct();
    }

    public function accessToken(){
        $access_token = cache('access_token');
        if(!$access_token){
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".self::APPID."&secret=".self::APPSECRET;
            $data = json_decode($this->httpGet($url),true);
            if(isset($data['access_token']) && $data['access_token'] != ''){
                $access_token = $data['access_token'];
                cache('access_token',$data['access_token'],'7200');
            }else{
                return false;
            }
        }
        return $access_token;
    }

    public function jsapiTicket()
    {
        $access_token = $this->accessToken();
        if($access_token){
            $jsapi_ticket = cache('jsapi_ticket');
            if(!$jsapi_ticket){
                $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=".$access_token."&type=jsapi";
                $data = json_decode($this->httpGet($url),true);
                if(isset($data['errcode']) && $data['errcode']== 0){//请求成功
                    $jsapi_ticket = $data['ticket'];
                    cache('jsapi_ticket',$jsapi_ticket,'7200');
                }else{
                    return false;
                }
            }
        }else{
            return false;
        }
        return $jsapi_ticket;
    }


    public function signature($nonceStr,$timestamp,$url)
    {
        $jsapi_ticket = $this->jsapiTicket();

        $signature = '';
        if($jsapi_ticket) {
            $string = 'jsapi_ticket='.$jsapi_ticket.'&noncestr='.$nonceStr.'&timestamp='.$timestamp.'&url='.$url;
            $signature = sha1($string);//对string1进行sha1签名，得到signature
        }else{
            return false;
        }
        return $signature;
    }

    public function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    public function httpGet($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }
}