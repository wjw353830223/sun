<?php
namespace app\api\controller;
use think\Controller;
class Apibase extends Controller
{
	//用户详情
	protected $member_info;
	//客户端类型
    protected $client_type_array = array('android', 'wap', 'wechat', 'ios');
    //当前域名
    protected $base_url;
    //开放控制器
    protected $open_controller = ['Open','Index','Article','Store','Wechat','CarOffLine'];

    protected function _initialize(){
    	$param = input('param.');
    	//访问未开放接口时验证token是否正确
    	$controller = request()->controller();
    	if (!in_array($controller,$this->open_controller) && $this->check_access($param)) {
    		$token_info = model('MemberToken')->with('member')->where(['token' => $param['token']])->find();
    		if (empty($token_info)) {
    			$this->ajax_return('10004','invalid token');
    		}
            $token_info = $token_info->toArray();
    		$this->member_info = $token_info['member'];
    	}
        $this->base_url = $this->host_url();
    }

    protected function check_access($param){
    	if (!in_array($param['client_type'],$this->client_type_array) ||!isset($param['client_type'])) {
			$this->ajax_return('10001','invalid client_type');
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

    	$token = isset($param['token']) ? trim($param['token']) : '';
    	//开放接口token可以为空
    	$controller = request()->controller();
    	if (!in_array($controller,$this->open_controller) && empty($token)) {
    		$this->ajax_return('10004','invalid token');
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
