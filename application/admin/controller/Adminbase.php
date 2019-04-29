<?php
/**
* 后台验证基础类
*
*/
namespace app\admin\controller;
use Rbac\Auth;
class Adminbase extends Appbase{

    //当前域名
    protected $base_url;
    protected function _initialize(){
        $session_admin_id = intval(session('ADMIN_ID'));
        $this->base_url = $this->host_url();
        if(!empty($session_admin_id)){
            $users_obj= model("Admin");
            $user=$users_obj->where(array('admin_id'=>$session_admin_id))->find();
            if(!$this->check_access($session_admin_id)){
                $this->error("您没有访问权限！");
            }
            $this->assign("admin",$user);
        }else{
            
            if(request()->isAjax()){
                $this->error("您还没有登录！",url("admin/open/login"));
            }else{

                header("Location:".url("admin/open/login"));
                exit();
            }

        }
    }

    /**
     *  检查后台用户访问权限
     * @param int $uid 后台用户id
     * @return boolean 检查通过返回true
     */
    private function check_access($uid){
        //如果用户角色是1，则无需判断
        if($uid == 1){
            return true;
        }

        $rule = request()->module().request()->controller().request()->action();
        $no_need_check_rules = array("adminIndexindex","adminIndexmain");

        if( !in_array($rule,$no_need_check_rules) ){
            $iauth_obj = new Auth();
            return $iauth_obj->check($uid);
        }else{
            return true;
        }
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
