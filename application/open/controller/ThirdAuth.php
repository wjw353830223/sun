<?php
namespace app\open\controller;
use think\Controller;
class ThirdAuth extends Controller
{
    //当前域名
    protected $base_url,$third_auth_token,$third_auth_token_model,$third_member_health_model;

    protected function _initialize(){
    	$param = input('param.');
    	if ($this->check_access($param)) {
    		if(!model('ThirdAuthToken')->validate_access_token($param['access_token'])){
                $this->ajax_return('11927','invalid access_token');
            }
            $this->third_auth_token_model = model('ThirdAuthToken');
            $this->third_member_health_model = model('ThirdMemberHealth');
            $this->third_auth_token = $this->third_auth_token_model->get_auth_token_by_access_token($param['access_token']);
    	}
        $this->base_url = $this->host_url();
    }

    protected function check_access($param){
        $nonstr = isset($param['nonstr']) ? trim($param['nonstr']) : '';
        if (empty($nonstr) || !preg_match("/^[\w]{3,8}/",$nonstr)) {
            $this->ajax_return('11926','invalid nonstr');
        }
    	//时间戳向后不大于300 向前不大于300
    	$now_time = time();
    	$timestamp = isset($param['_timestamp']) ? intval($param['_timestamp']) : 0;
    	if (!is_int($timestamp) || $now_time - $timestamp > 300 || $timestamp - $now_time > 300) {
    		$this->ajax_return('10002','invalid timestamp');
    	}
    	//严格验证签名格式
    	$signature = isset($param['signature']) ? trim($param['signature']) : '';
    	if (empty($signature) || !preg_match("/^(?!([a-f]+|\d+)$)[a-f\d]{40}$/",$signature)) {
    		$this->ajax_return('10003','invalid signature');
    	}
    	//缓存存在签名的忽略处理
    	if (cache('sign_'.$signature)) {
    		$this->ajax_return('10005','invalid access');
    	}
    	ksort($param);
    	unset($param['signature']);
    	$sort_str = http_build_query($param);
    	$oper_sign = sha1($sort_str);
    	if ($oper_sign !== $signature) {
    		$this->ajax_return('10005','invalid access');
    	}
    	cache('sign_'.$signature,$now_time,600);
    	return true;
    }

    /**
    * 全局中断输出
    * @param $code string 响应码
    * @param $msg string 简要描述
    * @param $result array 返回数据
    */
    public function ajax_return($code = '200',$msg = '',$result = array()){
    	$data = array(
            'code'   => (string)$code,
            'msg'    =>  $msg,
            'result' => $result
        );
        ajax_return($data);
    }

    /**
    * 获取当前网站域名
    *
    */
    public function host_url(){
        $base_url = 'http';
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {
            $base_url .= "s";
        }
        $base_url .= "://";

        if (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != "80") {
            $base_url .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"];
        }else{
            $base_url .= $_SERVER["SERVER_NAME"];
        }
        return $base_url;
    }
}
