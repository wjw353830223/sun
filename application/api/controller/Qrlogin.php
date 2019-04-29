<?php
namespace app\api\controller;
use think\Cache;
use think\Controller;
class Qrlogin extends Controller
{
    protected $third_member_health_model,$third_auth_token_model,$member_info,$third_auth_info;
    //客户端类型
    protected $client_type_array = array('android', 'wap', 'wechat', 'ios');
    protected function _initialize() {
        $param = input('param.');
        //访问接口时验证token是否正确
        if ($this->check_access($param)) {
            $token_info = model('MemberToken')->with('member')->where(['token' => $param['token']])->find();
            $third_token_info = model('ThirdAuthToken')->where(['access_token'=>$param['token']])->find();
            if(empty($third_token_info) && empty($token_info)){
                $this->ajax_return('10004','invalid token');
            }
            if(!empty($token_info)){
                $token_info = $token_info->toArray();
                $this->member_info = $token_info['member'];
            }
            //第三方access_token认证
            if(!empty($third_token_info)){
                if(!model('ThirdAuthToken')->validate_access_token($third_token_info['access_token'])){
                    $this->ajax_return('10004','invalid token');
                }
                $this->third_auth_info = $third_token_info->toArray();
            }
        }
        $this->third_member_health_model = model('ThirdMemberHealth');
        $this->third_auth_token_model = model('ThirdAuthToken');
	}
    /**
     * 二维码验证
     * @param uuid GET|POST 唯一码
     */
	public function index(){
        $uuid = input('param.uuid');
        $cache_name = 'rp_'.$uuid;
        $cache_info = Cache::get($cache_name);
        if ($cache_info === false) {
            $this->ajax_return('11700', 'invalid uuid');
        }
        //排除不同机构得到相同的的uuid 或者排除不同用户得到相同的uuid
        if ($cache_info > 0) {
            $this->ajax_return('11705', 'uuid has been checked');
        }
        //第三方检测机构
        if(!empty($this->third_auth_info)){
            if(Cache::set($cache_name, 'third:'.$this->third_auth_info['id'],7200) === true){
                $member_name = input('param.member_name','','trim');
                $member_height = input('param.member_height','','trim');
                $member_weight = input('param.member_weight','','trim');
                $member_age = input('param.member_age/d',0);
                $member_sex = input('param.member_sex/d',0);
                if(empty($member_name)){
                    $this->ajax_return('11521', 'invalid customer_name');
                }
                if(empty($member_height)){
                    $this->ajax_return('11703', 'invalid member_height');
                }
                if(empty($member_weight)){
                    $this->ajax_return('11704', 'invalid member_weight');
                }
                if(!isset($member_sex)){
                    $this->ajax_return('11701', 'empty member_sex');
                }
                $cache_name = 'third_member_health_id_'. $this->third_auth_info['id'] . '_' .$uuid;
                //排除同一个机构下不同的用户得到相同的的uuid
                if (Cache::get($cache_name) > 0) {
                    $this->ajax_return('11705', 'uuid has been checked');
                }
                $member_info = $this->third_member_health_model->add_member_health($this->third_auth_info['id'],$member_name,$member_height,$member_weight,$member_age,$member_sex);
                if(!$member_info){
                    $this->ajax_return('11931', 'third member health data save failed');
                }
                if(Cache::set($cache_name, $member_info['member_healthy_id'],7200)){
                    $this->ajax_return('200', 'success', ['access_key'=>$member_info['access_key']]);
                }
            }
            $this->ajax_return('11702', 'failed to update data');
        }
        if(!empty($this->member_info)){
            if( Cache::set($cache_name, $this->member_info['member_id'],7200) === true){
                $this->ajax_return('200', 'success');
            }
            $this->ajax_return('11702', 'failed to update data');
        }
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
        if (empty($token)) {
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
}