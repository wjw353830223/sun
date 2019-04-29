<?php
namespace app\admin\controller;
use Rbac\Tree;
class Menu extends Adminbase
{
    protected $menu_model;
    protected $auth_rule_model;

    public function _initialize() {
        parent::_initialize();
        $this->menu_model = model("Menu");
        $this->auth_rule_model = model("AuthRule");
    }

    //菜单管理首页
    public function index(){
        $result = cache('menu');
        if (empty($result)) {
            $result = $this->menu_model->order(array("list_order" => "ASC"))->select()->toArray();
            cache('menu',$result);
        }

        $tree = new Tree();
        $tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
        $tree->nbsp = '&nbsp;&nbsp;&nbsp;';
        $newmenus=array();
        foreach ($result as $m){
            $newmenus[$m['id']]=$m;
        }
        foreach ($result as $n=> $r) {
            //$result[$n]['level'] = $this->_get_level($r['id'], $newmenus);
            $result[$n]['parentid_node'] = ($r['parent_id']) ? ' class="child-of-node-' . $r['parent_id'] . '"' : '';
            $result[$n]['style'] = empty($r['parent_id']) ? '' : 'display:none;';
            $result[$n]['str_manage'] = '<a href="' . url("menu/add", array("parent_id" => $r['id'], "menuid" => input("get.menuid"))) . '">添加子菜单</a> | <a href="' . url("menu/edit", array("id" => $r['id'], "menuid" => input("get.menuid"))) . '">编辑</a> | <a class="js-ajax-delete" href="' . url("Menu/delete", array("id" => $r['id'], "menuid" => input("get.menuid")) ). '">删除</a> ';
            $result[$n]['status'] = $r['status'] ? '显示' : '隐藏';
            if(config('app_debug')){
                $result[$n]['app']=$r['app']."/".$r['model']."/".$r['action'];
            }
        }

        $tree->init($result);
        $str = "<tr id='node-\$id' \$parentid_node style='\$style'>
                    <td style='padding-left:20px;'><input name='list_orders[\$id]' type='text' size='3' value='\$list_order' class='input input-order'></td>
                    <td>\$id</td>
                    <td>\$app</td>
                    <td>\$spacer\$name</td>
                    <td>\$status</td>
                    <td>\$str_manage</td>
                </tr>";
        $categorys = $tree->get_tree(0, $str);
        $this->view->categorys = $categorys;
		return $this->view->fetch();
    }

    // 后台所有菜单列表
    public function lists(){
        $result = $this->menu_model->order(["app" => "ASC","model" => "ASC","action" => "ASC"])->select();
        $this->view->menus = $result;
        return $this->view->fetch();
    }

    // 后台菜单添加
    public function add() {
        $tree = new Tree();
        $parentid = input("get.parent_id",0,'intval');
        $result = $this->menu_model->order(["list_order" => "ASC"])->select()->toArray();
        foreach ($result as $r) {
            $r['selected'] = $r['id'] == $parentid ? 'selected' : '';
            $array[] = $r;
        }
        $str = "<option value='\$id' \$selected>\$spacer \$name</option>";
        $tree->init($array);
        $select_categorys = $tree->get_tree(0, $str);
        $this->view->select_categorys = $select_categorys;
        return $this->view->fetch();
    }

    // 后台菜单添加提交
    public function add_post() {
        if (request()->isPost()) {
            $name = input('post.name');
            if (empty($name)) {
                $this->error('菜单名称不能为空！');
            }

            $app= input('post.app');
            if (empty($app)) {
                $this->error('应用不能为空！');
            }

            $model = input('post.model');
            if (empty($model)) {
                $this->error('模块名称不能为空！');
            }

            $action = input('post.action');
            if (empty($model)) {
                $this->error('方法名称不能为空！');
            }

            $check_result = $this->menu_model->checkAction(array('app' => $app,'model' => $model,'action' => $action));
            if (!$check_result) {
                $this->error('同样的记录已经存在！');
            }

            $parent_id = input('post.parent_id',0,'intval');
            $parent_result = $this->menu_model->checkParentid($parent_id);
            if (!$parent_result) {
                $this->error('菜单只支持四级！');
            }
            $data = [
                'parent_id'  =>  $parent_id,
                'name'       =>  $name,
                'app'        =>  $app,
                'model'      =>  $model,
                'action'     =>  $action,
                'url_param'  =>  input('post.url_param','','trim'),
                'icon'       =>  input('post.icon','','trim'),
                'remark'     =>  input('post.remark','','trim'),
                'status'     =>  input('post.status',0,'intval'),
                'type'       =>  input('post.type',0,'intval')
            ];
            $add_result = $this->menu_model->data($data)->save();

            $rule = strtolower("$app/$model/$action");
            $mwhere = ["name"=>$rule];
            
            $find_rule_count=$this->auth_rule_model->where($mwhere)->count();
            if(empty($find_rule_count)){
                $this->auth_rule_model->data(["name"=>$rule,"module"=>$app,"type"=>"admin_url","title"=>$name])->save();//type 1-admin rule;2-user rule
            }
            $this->success("添加成功！", url('admin/menu/index'));
        }
    }

    // 后台菜单编辑
    public function edit() {
        $tree = new Tree();
        $id = input("get.id",0,'intval');
        $rs = $this->menu_model->where(["id" => $id])->find()->toArray();
        $result = $this->menu_model->order(["list_order" => "ASC"])->select()->toArray();
        foreach ($result as $r) {
            $r['selected'] = $r['id'] == $rs['parent_id'] ? 'selected' : '';
            $array[] = $r;
        }
        $str = "<option value='\$id' \$selected>\$spacer \$name</option>";
        $tree->init($array);
        $select_categorys = $tree->get_tree(0, $str);
        $this->view->rs = $rs;
        $this->view->select_categorys = $select_categorys;
        return $this->view->fetch();
    }

    // 后台菜单编辑提交
    public function edit_post() {
        if (request()->isPost()) {
            $id = input('post.id',0,'intval');
            $old_menu = $this->menu_model->where(['id'=>$id])->find()->toArray();
            if (empty($old_menu)) {
                $this->error('当前菜单已删除');
            }
            $name = input('post.name');
            if (empty($name)) {
                $this->error('菜单名称不能为空！');
            }

            $app= input('post.app');
            if (empty($app)) {
                $this->error('应用不能为空！');
            }

            $model = input('post.model');
            if (empty($model)) {
                $this->error('模块名称不能为空！');
            }

            $action = input('post.action');
            if (empty($model)) {
                $this->error('方法名称不能为空！');
            }

            $check_result = $this->menu_model
                ->checkActionUpdate(['id' => $id,'app' => $app,'model' => $model,'action' => $action]);
            if (!$check_result) {
                $this->error('同样的记录已经存在！');
            }

            $parent_id = input('post.parent_id',0,'intval');
            $parent_result = $this->menu_model->checkParentid($parent_id);
            if (!$parent_result) {
                $this->error('菜单只支持四级！');
            }

            $data = [
                'parent_id' => $parent_id,
                'name'      => $name,
                'app'       => $app,
                'model'     => $model,
                'action'    => $action,
                'url_param' => input('post.url_param','','trim'),
                'icon'      => input('post.icon','','trim'),
                'remark'    => input('post.remark','','trim'),
                'status'    => input('post.status',0,'intval'),
                'type'      => input('post.type',0,'intval')
            ];

            $save_result = $this->menu_model->save($data,['id' => $id]);

            if ($save_result === false) {
                $this->error("更新失败！");
            } 

            $name = strtolower("$app/$model/$action");
            $mwhere = ["name"=>$name];
            
            $find_rule_count = $this->auth_rule_model->where($mwhere)->count();
            if(empty($find_rule_count)){
                $old_app = $old_menu['app'];
                $old_model = $old_menu['model'];
                $old_action = $old_menu['action'];
                $old_name = strtolower("$old_app/$old_model/$old_action");
                $find_old_rule_id = $this->auth_rule_model->where(["name"=>$old_name])->value('id');
                if(empty($find_old_rule_id)){
                    $this->auth_rule_model->data(["name"=>$name,"module"=>$app,"type"=>"admin_url","title"=>$name])->save();
                }else{
                    $this->auth_rule_model->save(["name"=>$name,"module"=>$app,"type"=>"admin_url","title"=>$name],['id'=>$find_old_rule_id]);
                }
            }else{
                $this->auth_rule_model->save(["name"=>$name,"module"=>$app,"type"=>"admin_url","title"=>$name],$mwhere);
            }
            $this->success("更新成功！",url('admin/menu/index'));
        }
    }

    // 后台菜单删除
    public function delete() {
        $id = input("get.id",0,'intval');
        $count = $this->menu_model->where(["parent_id" => $id])->count();
        if ($count > 0) {
            $this->error("该菜单下还有子菜单，无法删除！");
        }
        $menu_info = $this->menu_model->where(["id" => $id])->find();
        if (empty($menu_info)) {
            $this->error("删除失败！");
        }
        $del_result = $this->menu_model->destroy($id);

        if ($del_result === false) {
            $this->error("删除失败！");
        }

        $app = $menu_info['app'];
        $model = $menu_info['model'];
        $action = $menu_info['action'];
        $name = strtolower("$app/$model/$action");
        $mwhere = ["name"=>$name];
        $this->auth_rule_model->where($mwhere)->delete();
        $this->success("删除菜单成功！");
    }

    // 后台菜单排序
    public function list_orders() {
        $ids = $_POST['list_orders'];
        if (!is_array($ids)) {
            $this->error("参数错误！");
        }
        foreach ($ids as $key => $r) {
            if ($r > 0) {
                $this->menu_model->save(['list_order' => $r],['id' => $key]);
            }
        }
        $this->success("排序更新成功！");
    }
}
