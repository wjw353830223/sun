<?php
namespace app\admin\controller;

class Open extends Appbase {

    //后台登陆界面
    public function login() {
        $admin_id=session('ADMIN_ID');
    	if(!empty($admin_id)){//已经登录
    		$this->redirect(url("admin/index/index"));
    	}else{
            $this->view->name = 'thinkphp';
            return $this->view->fetch();
    	}
    }
    
    public function logout(){
    	session('ADMIN_ID',null); 
    	$this->success('退出成功');
    }

    public function edit_pass(){
       return $this->fetch();
    }
    
    // 密码修改提交
    public function password_post(){
        if (request()->isPost()) {

            if(empty(input('post.old_password'))){
                $this->error("原始密码不能为空！");
            }
            if(empty(input('post.password'))){
                $this->error("新密码不能为空！");
            }
            $user_obj = model('Admin');
            $admin_id=sp_get_current_admin_id();
            $admin=$user_obj->where(array("admin_id"=>$admin_id))->find();
            $old_password=input('post.old_password');
            $password=input('post.password');
            if(sp_compare_password($old_password,$admin['admin_password'],$admin['encrypt'])){
                if($password==input('post.repassword')){
                    if(sp_compare_password($password,$admin['admin_password'],$admin['encrypt'])){
                        $this->error("新密码不能和原始密码相同！");
                    }else{
                        $data['admin_password']=sp_password($password,$admin['encrypt']);
                        $data['admin_id']=$admin_id;
                        $r=$user_obj->save($data,['admin_id'=>$admin['admin_id']]);
                        if ($r!==false) {
                            $this->success("修改成功！");
                        } else {
                            $this->error("修改失败！");
                        }
                    }
                }else{
                    $this->error("密码输入不一致！");
                }
    
            }else{
                $this->error("原始密码不正确！");
            }
        }
    }

    public function dologin(){
    	$username = input("post.username");
    	if(empty($username)){
    		$this->error('账号不能为空');
    	}
    	$password = input("post.password");
    	if(empty($password)){
    		$this->error('密码不能为空');
    	}
    	$verrify = input("post.verify");
    	if(empty($verrify)){
    		$this->error('验证码不能为空');
    	}
    	//验证码
    	if(!captcha_check($verrify)){
    		$this->error('验证码输入错误');
    	}
    		
        //密码错误剩余重试次数
        $times_model = model('times');
        $rtime = $times_model->get(array('username'=>$username,'is_admin'=>1));

        //默认错误失败次数8次
        $maxloginfailedtimes = 8;

        if($rtime['times'] >= $maxloginfailedtimes) {
            $minute = 60-floor((time()-$rtime['login_time'])/60);
            if($minute>0) $this->error('请等待1小时后重试');;
        }

        $model_admin = model('Admin');
        $admin_info = $model_admin->where('admin_name',$username)->find();
        if(empty($admin_info)){
            $this->error('账号或密码输入错误');
        }

        if ($admin_info['admin_status'] < 1) {
            $this->error('该账号已锁定，请联系管理员解锁');
        }

        $passwd = sp_password($password,$admin_info['encrypt']); 

        //密码错误，记录错误信息
        if ($passwd != $admin_info['admin_password']) {
            $ip = get_client_ip(0,true);
            if($rtime && $rtime['times'] < $maxloginfailedtimes) {
                $times = $maxloginfailedtimes-intval($rtime['times']);
                $times_model->where(array('username'=>$username))->update(array('ip'=>$ip,'is_admin'=>1,'times'=> $rtime['times'] + 1));
            } else {
                $times_model->where(array('username'=>$username,'is_admin'=>1))->delete();
                $times_model->insert(array('username'=>$username,'ip'=>$ip,'is_admin'=>1,'login_time'=>time(),'times'=>1));
                $times = $maxloginfailedtimes;
            }
            $this->error('账号或密码输入错误,剩余次数'.$times.'次');
        }

        //清除密码错误记录
        $times_model->where(array('username'=>$username,'is_admin'=>1))->delete();

        $model_admin->where(array('admin_id'=>$admin_info['admin_id']))->update(array('login_ip'=>get_client_ip(0,true),'login_time'=>time(),'login_num' => $admin_info['login_num'] + 1));
        /*$role_user_model=M("RoleUser");
                    
        $role_user_join = C('DB_PREFIX').'role as b on a.role_id =b.id';
        
        $groups=$role_user_model->alias("a")->join($role_user_join)->where(array("user_id"=>$result["id"],"status"=>1))->getField("role_id",true);
        
        if( $result["id"]!=1 && ( empty($groups) || empty($result['user_status']) ) ){
            $this->error(L('USE_DISABLED'));
        }*/

        //登入成功页面跳转
        session('ADMIN_ID',$admin_info['admin_id']);
        session('name',$admin_info['admin_name']);
        cookie("admin_username",$username,3600*24*30);
        $this->success('登录成功',url("Index/index"));
    }

}