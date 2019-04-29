<?php
namespace app\admin\controller;
use Rbac\Tree;
class Category extends Adminbase
{
	protected $cate_model;
	public function _initialize() {
		parent::_initialize();
		$this->cate_model = model('category');
	}

	//文章分类管理首页
	public function index(){
		$result = $this->cate_model->order(array("list_order"=>"asc"))->select()->toArray();
		$categorys = '';
		if (!empty($result)) {
			$tree = new Tree();
			$tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
			$tree->nbsp = '&nbsp;&nbsp;&nbsp;';
			
			foreach ($result as $r) {
				$r['str_manage'] = '<a href="' . url("category/add", array("parent_id" => $r['category_id'])) . '">添加子类</a> | <a href="' . url("category/edit", array("id" => $r['category_id'])) . '">编辑</a> | <a class="js-ajax-delete" href="' . url("category/delete", array("id" => $r['category_id'])) . '">删除</a> ';
				$r['id']=$r['category_id'];
				$r['type'] = $r['type'] == 1 ? '文章' : '图集';
				$array[] = $r;
			}
			
			$tree->init($array);
			$str = "<tr>
						<td><input name='list_orders[\$id]' type='text' size='3' value='\$list_order' class='input input-order'></td>
						<td>\$id</td>
						<td>\$spacer\$catname</td>
		    			<td>\$type</td>
						<td>\$str_manage</td>
					</tr>";
			$categorys = $tree->get_tree(0, $str);
		}
		$this->assign('categorys',$categorys);
		return $this->fetch();
	}

	//文章分类添加
	public function add(){
		if(request()->isGet()){
			$parent_id = input('get.parent_id',0,'intval');
		}
        $result = $this->cate_model->order(["list_order" => "ASC"])->select()->toArray();
        $categorys ='';
        if(!empty($result)){
			$tree = new Tree();
			$tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
			$tree->nbsp = '&nbsp;&nbsp;&nbsp;';
			foreach ($result as $r) {
				$r['id']=$r['category_id'];
				if(!empty($parent_id)){
					$r['selected'] = $r['category_id'] == $parent_id ? 'selected' : '';
				}
				$array[] = $r;
			}
			$tree->init($array);
			$str = "
					<option value=\$id >\$spacer\$catname</option>
					";
			$categorys = $tree->get_tree(0, $str);
        }
		$this->assign('categorys',$categorys);
		return $this->fetch();
	}

	//文章分类添加提交
	public function add_post(){
		if(request()->isPost()){
			$param = input("post.");
			if(empty(trim($param['catname']))){
				$this->error('分类名称不能为空');
			}
			$category_result = $this->cate_model->allowField(true)->save($param);
			if ($category_result !== false) {
    		    $this->success("添加分类成功",url("category/index"));
	    	}else{
	    		$this->error("添加失败！");
	    	}
		}
	}

	//文章分类编辑
	public function edit(){
		$category_id = input('get.id','','intval');
		if(!empty($category_id)){
			$cate_data = $this->cate_model->where(["category_id"=>$category_id])
                ->field("category_id,parent_id,catname,description,type")
                ->find()->toArray();
		}
		$this->assign("cate_data",$cate_data);
		$result = $this->cate_model->order(["list_order"=>"asc"])->select()->toArray();
		$categorys = '';
		if(!empty($result)){
			$tree = new Tree();
			$tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
			$tree->nbsp = '&nbsp;&nbsp;&nbsp;';
			foreach ($result as $r) {
				$r['id']=$r['category_id'];
				$r['selected'] = $r['category_id'] == $cate_data['parent_id'] ? 'selected' : '';
				$array[] = $r;
			}
			$tree->init($array);
			$str = "
					<option value=\$id \$selected>\$spacer\$catname</option>
					";
			$categorys = $tree->get_tree(0, $str);
		}
		$this->assign('categorys',$categorys);
		return $this->fetch();
	}

	//文章分类编辑提交
	public function edit_post(){
    	if(request()->isPost()){
    		$param = input("post.");
    		if (empty($param['catname'])) {
	    		$this->error("请填写分类名称");
	    	}
	        $cate_result = $this->cate_model->allowField(true)->save($param,array("category_id"=>$param['category_id']));
	        if ($cate_result !== false) {
	    		$this->success("修改分类成功",url("category/index"));
	    	}else{
	    		$this->error("修改失败！");
	    	}
    	}
	}

	// 后台菜单排序
    public function list_orders() {
        $ids = $_POST['list_orders'];
        if (!is_array($ids)) {
            $this->error("参数错误！");
        }
        foreach ($ids as $key => $r) {
            if ($r > 0) {
                $this->cate_model->save(['list_order' => $r],['category_id' => $key]);
            }
        }
        $this->success("排序更新成功！");
    }

   // 分类删除
   public function delete(){
        $category_id = input("get.id",0,"intval");
        //查询是否有子分类
        $has_category_son = $this->cate_model->where(["parent_id"=>$category_id])->count();
        $has_article = $this->cate_model->where(["category_id"=>$category_id])->field("count")->find();
        if($has_category_son > 0){
       		$this->error("请先删除下属分类！");
        }
        if($has_article['count'] > 0){
       	    $this->error("请先删除下属文章！");
       	}

   		$cate_delete = $this->cate_model->where(["category_id"=>$category_id])->delete();
   	    if ($cate_delete !== false) {
			$this->success("删除分类成功",url("category/index"));
    	}else{
    		$this->error("删除失败！");
    	}
   }
}