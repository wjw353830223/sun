<?php
/**
 * 检测报告上传
 */
namespace app\open\controller;

use think\Controller;

class ThirdAccessToken extends Controller {


	protected $merchant_auth_token_model;
	public function _initialize() {
        $this->merchant_auth_token_model = model('ThirdAuthToken');
	}
    /**
     * 第三方检测机构获取 访问token
     */
    public function get_access_token(){
        $app_id = input('param.app_id','','trim');
        $secret = input('param.secret','','trim');
        if(empty($app_id)){
            ajax_return([
                'code'=>'11934',
                'msg'=>'没有传入appid'
            ]);
        }
        if(empty($secret)){
            ajax_return([
                'code'=>'11935',
                'msg'=>'没有传入secret'
            ]);
        }
        $merchant_auth = $this->merchant_auth_token_model->get_merchant($app_id,$secret);
        if(empty($merchant_auth)){
            ajax_return([
                'code'=>'11936',
                'msg'=>'第三方appid或secret错误'
            ]);
        }
        if(empty($merchant_auth['token_expired']) && empty($merchant_auth['access_token'])){
            $data = $this->merchant_auth_token_model->generate_access_token($merchant_auth['id']);
            if($data === false){
                ajax_return([
                    'code'=>'11937',
                    'msg'=>'生成access_token出错'
                ]);
            }
            ajax_return($data);
        }
        if(!empty($merchant_auth['access_token'])){
            $data = $this->merchant_auth_token_model->refresh_access_token($merchant_auth['access_token']);
            if($data === false){
                ajax_return([
                    'code'=>'11937',
                    'msg'=>'生成access_token出错'
                ]);
            }
            ajax_return($data);
        }
        ajax_return([
            'code'=>'11937',
            'msg'=>'生成access_token出错'
        ]);
    }
}