<?php
namespace app\robot\controller;
use think\Controller;
use Lib\Http;
class Oauth extends Robotbase
{
	/**
     * 初始化
     */
    protected function _initialize()
    {
        parent::_initialize();
    }

    public function get_token(){
    	$param = input('param.');
    	$data = $this->check_access($param);
        // 传递参数至机器人服务器
        $content = Http::ihttp_post($this->send_url,$data);
        if(empty($content)){
            $this->ajax_return('100014','request failed');
        }

        $content = json_decode($content['content'],true);
        if($content['code'] != '200'){
            $this->ajax_return('100016',$content['msg']);
        }

        // 创建token
        $token = $this->create_token($param['robot_sn']);
        if(empty($token)){
            $this->ajax_return('100015','create token failed');
        }

        // 返回token至机器人服务器
        $this->ajax_return('200','success',['token' => $token,'expires_in' => '86400']);
    }

    protected function check_access($param = array()){
    	// 认证码
        if (!isset($param['secret_code'])) {
            $this->ajax_return('100010','invalid secret_code');
        }

        $data = array();
        $data['secret_code'] = $param['secret_code'];

        $now_time = time();
        $timestamp = isset($param['_timestamp']) ? intval($param['_timestamp']) : 0;
        if (!is_int($timestamp) || $now_time - $timestamp > 300) {
            $this->ajax_return('100011','invalid timestamp');
        }
        $data['_timestamp'] = (string)$timestamp;

        $rand_num = isset($param['random_str']) ? trim($param['random_str']) : 0;
        if (!isset($param['random_str']) || strlen($rand_num) < 6) {
            $this->ajax_return('100012','invalid random_str');
        }
        $data['random_str'] = (string)$rand_num;

        // 机器人SN码
        if (!isset($param['robot_sn']) || !is_robotsn($param['robot_sn'])) {
            $this->ajax_return('100013','invalid robot_sn');
        }
        $data['robot_sn'] = $param['robot_sn'];
        return $data;
    }

    /**
     * 生成token
     * @param $robot_sn 机器人编码
     */
    protected function create_token($robot_sn){
        $rand_num = strval(rand(0,999999));
        $data['create_time'] = strval(time());
        $data['robot_sn'] = $robot_sn;
        $data['token'] = md5($robot_sn.$rand_num.$data['create_time']);

        $has_info = db('robot_token')->where('robot_sn',$robot_sn)->count();
        if($has_info > 0){
            $result = db('robot_token')->where('robot_sn',$robot_sn)->update(['token'=>$data['token'],'create_time'=>$data['create_time']]);
        } else {
            $result = db('robot_token')->insert($data);
        }

        if ($result === false) {
            return false;
        }
        return $data['token'];
    }
}