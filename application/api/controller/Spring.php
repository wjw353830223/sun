<?php
namespace app\api\controller;
class Spring extends Apibase 
{
	//春雨合作方key
	protected $partner_key;

	protected function _initialize() {
		parent::_initialize();
		$this->partner_key = 'AHJvdbfPKNDHifb4';
	}

	/**
	* 获取验证
	*/
	public function index(){
		$user_id = $this->member_info['member_id'];
		$result = $this->get_sign($user_id);
		$this->ajax_return("200","success",$result);
	}

	/**
	* 获取认证码
	*/
	protected function get_sign($user_id = 0){
		if (empty($user_id)) {
			return false;
		}

		$time = time();
		$sign = substr(md5($this->partner_key.$time.$user_id), 8, 16);
		$data = [
			'time' => $time,
			'sign' => $sign,
			'user_id' => $user_id
		];
		return $data;
	}
}