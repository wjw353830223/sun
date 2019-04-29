<?php
namespace app\robot\controller;
use think\Controller;
class Robotbase extends Controller
{

    /**
     * 机器人服务器认证接口url
     */
    protected $send_url = 'http://robot.healthywo.cn/api/auth/check';

    //当前域名
    protected $base_url;

    //机器人token信息
    protected $token_info;
    /**
     * 初始化
     */
    protected function _initialize()
    {
        $controller = request()->controller();
        if ($controller != 'Oauth') {
            //验证token
            $token = input('param.token','','trim');
            if (empty($token)) {
                $this->ajax_return('100001','Invalid TOKEN');
            }

            $token_info = db('robot_token')->where(['token' => $token])->find();
            if (empty($token_info)) {
                $this->ajax_return('100001','Invalid TOKEN');
            }

            //验证token是否过期
            $now_time = time();

            if($now_time - $token_info['create_time'] > 86400){
                $this->ajax_return('100002','TOKEN expired');
            }

            $this->token_info = $token_info;

        }
        $this->base_url = $this->host_url();
    }

    /**
    * 全局中断输出
    * @param $code string 响应码
    * @param $msg string 简要描述
    * @param $result array 返回数据
    */

    public function ajax_return($code = '200',$msg = '',$result = array())
    {
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