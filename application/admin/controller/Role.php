<?php
namespace app\admin\controller;
use Rbac\Tree;
class Role extends Adminbase
{
	protected $role_model;

    public function _initialize() {
        parent::_initialize();
        $this->role_model = model("AuthRole");
    }

    //角色管理首页
    public function index(){
    	$data = $this->role_model->order(["list_order" => "ASC", "role_id" => "ASC"])->select();
    	$this->assign('roles',$data);
		return $this->fetch();
    }

    // 添加角色
    public function add() {
        return $this->fetch();
    }
    
    // 添加角色提交
    public function add_post() {
    	$name = input('post.name','','trim');
    	if (empty($name)) {
    		$this->error("请填写角色名称");
    	}

    	$remark = input('post.remark','','strip_tags');
    	$status = input('post.status',1,'intval');
    	$result = $this->role_model->data(['name' => $name,'status' => $status,'remark' => $remark,'create_time' => time()])->save();
    	if ($result !== false) {
    		$this->success("添加角色成功",url("role/index"));
    	}else{
    		$this->error("添加失败！");
    	}
    }

    // 编辑角色
    public function edit() {
        $id = input("get.id",0,'intval');
        if ($id == 1) {
            $this->error("超级管理员不能被修改！");
        }
        $data = $this->role_model->where(["role_id" => $id])->find()->toArray();
        if (!$data) {
        	$this->error("该角色不存在！");
        }
        $this->assign("data", $data);
        return $this->fetch();
    }
    
    // 编辑角色提交
    public function edit_post() {
    	$id = input("post.id",0,'intval');
    	if ($id == 1) {
    		$this->error("超级管理员不能被修改！");
    	}

    	$name = input('post.name','','trim');
    	if (empty($name)) {
    		$this->error("请填写角色名称");
    	}

    	$remark = input('post.remark','','strip_tags');
    	$status = input('post.status',1,'intval');
    	$result = $this->role_model->save(['name' => $name,'status' => $status,'remark' => $remark,'update_time' => time()],['role_id' => $id]);

    	if ($result !== false) {
    		$this->success("修改角色成功",url("role/index"));
    	}else{
    		$this->error("添加失败！");
    	}
    }

    // 删除角色
    public function delete() {
        $id = input("get.id",0,'intval');
        if ($id == 1) {
            $this->error("超级管理员不能被删除！");
        }
        $admin_model = model("Admin");
        $count = $admin_model->where(array('role_id'=>$id))->count();
        if($count>0){
        	$this->error("该角色已经有用户！");
        }else{
        	$status = $this->role_model->destroy($id);
        	if ($status!==false) {
        		$this->success("删除成功！",url('role/index'));
        	} else {
        		$this->error("删除失败！");
        	}
        }
        
    }

     // 角色授权
    public function authorize() {
        $this->auth_access_model = model("AuthAccess");
       //角色ID
        $roleid = input("get.id",0,'intval');
        if (empty($roleid)) {
        	$this->error("参数错误！");
        }

        $menu = new Tree();
        $menu->icon = array('│ ', '├─ ', '└─ ');
        $menu->nbsp = '&nbsp;&nbsp;&nbsp;';
        $result = cache("menu");
        $newmenus=array();
        $priv_data=$this->auth_access_model->where(array("role_id"=>$roleid))->column("rule_name");//获取权限表数据
        foreach ($result as $m){
        	$newmenus[$m['id']]=$m;
        }
        
        foreach ($result as $n => $t) {
        	$result[$n]['checked'] = ($this->_is_checked($t, $roleid, $priv_data)) ? ' checked' : '';
        	$result[$n]['level'] = $this->_get_level($t['id'], $newmenus);
        	$result[$n]['style'] = empty($t['parent_id']) ? '' : 'display:none;';
        	$result[$n]['parentid_node'] = ($t['parent_id']) ? ' class="child-of-node-' . $t['parent_id'] . '"' : '';
        }
        $str = "<tr id='node-\$id' \$parentid_node  style='\$style'>
                   <td style='padding-left:30px;'>\$spacer<input type='checkbox' name='menuid[]' value='\$id' level='\$level' \$checked onclick='javascript:checknode(this);'> \$name</td>
    			</tr>";
        $menu->init($result);
        $categorys = $menu->get_tree(0, $str);
        
        $this->assign("categorys", $categorys);
        $this->assign("roleid", $roleid);
        return $this->fetch();
    }
    
    // 角色授权提交
    public function authorize_post() {
    	$this->auth_access_model = model("AuthAccess");
		$roleid = input("post.roleid",0,'intval');
		if(!$roleid){
			$this->error("需要授权的角色不存在！");
		}
		$menuid = isset($_POST['menuid']) ? $_POST['menuid'] : NULL;
		if (is_array($menuid) && count($menuid)>0) {
			
			$menu_model = model("menu");
			$auth_rule_model = model("AuthRule");
			$this->auth_access_model->where(array("role_id"=>$roleid,'type'=>'admin_url'))->delete();
			$data = array();
			foreach ($menuid as $menuid) {
				$menu=$menu_model->where(array("id"=>$menuid))->field("app,model,action")->find();
				if($menu){
					$app=$menu['app'];
					$model=$menu['model'];
					$action=$menu['action'];
					$name=strtolower("$app/$model/$action");
					$data[] = array("role_id"=>$roleid,"rule_name"=>$name,'type'=>'admin_url','menu_id' => $menuid);
				}
			}
			$this->auth_access_model->saveAll($data);
			$this->success("授权成功！", url("role/index"));
		}else{
			//当没有数据时，清除当前角色授权
			$this->auth_access_model->where(array("role_id" => $roleid))->delete();
			$this->error("没有接收到数据，执行清除授权成功！");
		}
    }

    /**
     *  检查指定菜单是否有权限
     * @param array $menu menu表中数组
     * @param int $roleid 需要检查的角色ID
     */
    private function _is_checked($menu, $roleid, $priv_data) {
    	
    	$app=$menu['app'];
    	$model=$menu['model'];
    	$action=$menu['action'];
    	$name=strtolower("$app/$model/$action");
    	if($priv_data){
	    	if (in_array($name, $priv_data)) {
	    		return true;
	    	} else {
	    		return false;
	    	}
    	}else{
    		return false;
    	}
    	
    }

    /**
     * 获取菜单深度
     * @param $id
     * @param $array
     * @param $i
     */
    protected function _get_level($id, $array = array(), $i = 0) {
        
        	if ($array[$id]['parent_id']==0 || empty($array[$array[$id]['parent_id']]) || $array[$id]['parent_id']==$id){
        		return  $i;
        	}else{
        		$i++;
        		return $this->_get_level($array[$id]['parent_id'],$array,$i);
        	}
        		
    }
}