<?php
/**
 * 会员登录令牌验证
 */
namespace app\open\controller;
use think\Controller;
class TokenAuth extends Controller {

	protected $token_model;
	//互联43位密钥
	protected $secret_key;
	public function _initialize() {
		$this->token_model = model('MemberToken');
		$this->secret_key = 'hKJQmtrhMw3DG3xWn26pZyKKxwlJl6kzB1rRq5yLSgu';
	}

	public function index(){
		$nonce_str = input('param.nonce_str','','trim');
		if (empty($nonce_str) || strlen($nonce_str) < 32) {
			$data = [
				'return_code' => 'FAIL',
				'return_msg' => 'Invalid nonce string'
			];
			ajax_return($data);
		}

		$now_time = time();
		$timestamp = intval(input('param._timestamp'));
		if (!is_int($timestamp) || $now_time - $timestamp > 300 || $timestamp - $now_time > 300) {
			$data = [
				'return_code' => 'FAIL',
				'return_msg' => 'Invalid timestamp'
			];
			ajax_return($data);
    	}

    	$token = input('param.token','','trim');
    	if (empty($token)) {
    		$data = [
				'return_code' => 'FAIL',
				'return_msg' => 'Invalid token'
			];
			ajax_return($data);
    	}

    	//严格验证签名格式
    	$signature = input('param.signature','','trim');
    	if (empty($signature) || !preg_match("/^(?!([a-f]+|\d+)$)[a-f\d]{40}$/",$signature)) {
    		$data = [
				'return_code' => 'FAIL',
				'return_msg' => 'Invalid signature'
			];
			ajax_return($data);
    	}

    	//缓存存在签名的忽略处理
    	if (cache('sign_'.$signature)) {
    		$data = [
				'return_code' => 'FAIL',
				'return_msg' => 'Invalid access'
			];
			ajax_return($data);
    	}

    	$param = input('param.');

    	ksort($param);
    	unset($param['signature']);

    	$sort_str = http_build_query($param) . '&secret_key='.$this->secret_key;
    	$oper_sign = sha1($sort_str);

    	if ($oper_sign !== $signature) {
    		$data = [
				'return_code' => 'FAIL',
				'return_msg' => 'Invalid access'
			];
			ajax_return($data);
    	}
    	cache('sign_'.$signature,$now_time,600);

    	//验证token是否正确
    	$token_info = model('MemberToken')->with('member')->where(['token' => $token])->find();
		if (empty($token_info)) {
			$data = [
				'return_code' => 'FAIL',
				'return_msg' => 'Invalid token'
			];
			ajax_return($data);
		}

		//拆解会员信息
		$token_info = $token_info->toArray();
		$member_info = $token_info['member'];
    	$data = [
			'return_code' => 'SUCCESS',
			'return_msg' => '',
			'member_id' => $member_info['member_id']
		];
		ajax_return($data);
	}

}