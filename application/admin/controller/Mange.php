<?php
namespace app\admin\controller;
class Mange extends Adminbase
{
	protected $users_model,$role_model,$admin_model;

	public function _initialize() {
		parent::_initialize();
		$this->admin_model = model("admin");
		$this->role_model = model("AuthRole");
	}

	//管理员首页
    public function index(){
    	$count = $this->admin_model->count();
    	$users = $this->admin_model->with('role')->paginate(20,$count);
    	$page =$users->render();
    	$this->assign('page',$page);
    	$this->assign('users', $users);
		return $this->fetch();
    }

    // 管理员添加
    public function add(){
    	$roles = $this->role_model->where(array('status' => 1))->order("role_id", "ASC")->select();
    	$this->assign('roles',$roles);
		return $this->view->fetch();
    }

    // 管理员添加提交
	public function add_post(){
		if (request()->isPost()) {
			$role_id = input('post.role_id',0,'intval');
			if(empty($role_id)){
				$this->error("请为此用户指定角色！");
			}
			$admin_name = input('post.admin_name',0,'trim');
			if(empty($admin_name)){
				$this->error("请为此用户指定用户名！");
			}
			if(sp_get_current_admin_id() != 1 && $role_id == 1){
				$this->error("非网站创建者不可创建超级管理员！");
			}
			if (!$this->admin_model->checkNameUpdate(array('id' => 0,'admin_name' => $admin_name))) {
				$this->error("用户名重复！");
			}
			$admin_pass = input('post.admin_password','','trim');
			if(empty($admin_pass)){
				$this->error("请为此用户填写密码！");
			}
			$salt = random_string(6,5);
			$new_pass = sp_password($admin_pass,$salt);
			$data = ['role_id' => $role_id,'admin_name' => $admin_name,'admin_password' => $new_pass,'encrypt' => $salt];
			$result = $this->admin_model->data($data)->save();
			if ($result === false) {
				$this->error("保存失败！");
			}
			$this->success("保存成功！",url('admin/mange/index'));
		}
	}

    // 管理员编辑
    public function edit(){
    	$id = input('get.id',0,'intval');
    	$users = $this->admin_model->field('admin_id,admin_name,role_id')->where('admin_id',$id)->find();
    	if (empty($users)) {
    		$this->error('管理员不存在');
    	}

		$roles = $this->role_model->where(array('status' => 1))->order("role_id", "ASC")->select();
		$this->assign('users',$users);
		$this->assign('roles',$roles);
		return $this->fetch();
    }

    // 管理员编辑提交
	public function edit_post(){
		if (request()->isPost()) {
			$admin_id = input('post.id',0,'intval');
			if (empty($admin_id)) {
				$this->error("当前用户不存在！");
			}

			$admin_info = $this->admin_model->get($admin_id);
			if (empty($admin_info)) {
				$this->error("当前用户不存在！");
			}
			$role_id = input('post.role_id',0,'intval');
			if(empty($role_id)){
				$this->error("请为此用户指定角色！");
			}

			$admin_name = input('post.admin_name',0,'trim');
			if(empty($admin_name)){
				$this->error("请为此用户指定用户名！");
			}

			if(sp_get_current_admin_id() != 1 && $role_id == 1){
				$this->error("非网站创建者不可创建超级管理员！");
			}

			if (!$this->admin_model->checkNameUpdate(['id' => $admin_id,'admin_name' => $admin_name])) {
				$this->error("用户名重复！");
			}

			$data = ['role_id' => $role_id,'admin_name' => $admin_name];
			$admin_pass = input('post.admin_password','','trim');
			if(!empty($admin_pass)){
				$salt = random_string(6,5);
				$new_pass = sp_password($admin_pass,$salt);
				$data['admin_password'] = $new_pass;
				$data['encrypt'] = $salt;
			}

			$result = $this->admin_model->save($data,['admin_id' => $admin_id]);
			if ($result === false) {
				$this->error("保存失败！");
			}
			$this->success("保存成功！",url('admin/mange/index'));
		}
	}

	// 管理员删除
    public function delete(){
		$admin_id = input('get.id',0,'intval');
		if ($admin_id == 1) {
			$this->error("网站创始人禁止删除");
		}

		if (empty($admin_id)) {
			$this->error("当前用户不存在！");
		}

		$admin_info = $this->admin_model->get($admin_id);
		if (empty($admin_info)) {
			$this->error("当前用户不存在！");
		}

		$del_result = $this->admin_model->destroy($admin_id);

        if ($del_result === false) {
            $this->error("删除失败！");
        }
        $this->success("删除菜单成功！");
	}

	// 停用管理员
    public function lock(){
        $admin_id = input('get.id',0,'intval');
    	if (!empty($admin_id)) {
    		if ($admin_id == 1) {
				$this->error("网站创始人禁止锁定");
			}
    		$result = $this->admin_model->save(['admin_status' => '0'],['admin_id' => $admin_id]);
    		if ($result!==false) {
    			$this->success("管理员停用成功！",url('mange/index'));
    		} else {
    			$this->error('管理员停用失败！');
    		}
    	} else {
    		$this->error('数据传入失败！');
    	}
    }

    // 启用管理员
    public function unlock(){
    	$admin_id = input('get.id',0,'intval');
    	if (!empty($admin_id)) {
    		$result = $this->admin_model->save(['admin_status' => '1'],['admin_id' => $admin_id]);
    		if ($result!==false) {
    			$this->success("管理员启用成功！",url('mange/index'));
    		} else {
    			$this->error('管理员启用失败！');
    		}
    	} else {
    		$this->error('数据传入失败！');
    	}
    }
}