<?php
namespace app\api\controller;

use app\common\model\Wechat as Wemodel;

class Wechat extends Apibase
{
    public function wechat_share(){
        // 指定允许其他域名访问
        header('Access-Control-Allow-Origin:*');
        // 响应类型
        header('Access-Control-Allow-Methods:*');
        // 响应头设置
        header('Access-Control-Allow-Headers:x-requested-with,content-type');
        $weixinmodel = new Wemodel();
        $weixindata = array();
        $url = urldecode(input('get.url'));
        $weixindata['appId'] = 'wxbcf1781ee37efb37';//appid
        $weixindata['jsapi_ticket'] = $weixinmodel->jsapiTicket();
        $weixindata['nonceStr'] = $weixinmodel->createNonceStr();
        $weixindata['timestamp'] = time();
        $weixindata['signature'] = $weixinmodel->signature($weixindata['nonceStr'],$weixindata['timestamp'],$url);
        $weixindata['url'] = $url;

        $this->ajax_return('200','success',$weixindata);
    }
}