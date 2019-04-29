<?php
namespace app\admin\controller;

class Page extends Adminbase
{
	protected $page_model;
	public function _initialize() {
		parent::_initialize();
		$this->page_model = model('Page');
	}

	//单页管理首页
	public function index(){
	    $query = [];
        $where = [];
		if(request()->isGet()){
			$start_time = input('get.start_time');
			if(!empty($start_time)){
				$start_time = strtotime($start_time);
                $query['start_time'] = strtotime($start_time);
				$where['create_time'] = ['egt',$start_time];
			}

			$end_time = input('get.end_time');
			if(!empty($end_time)){
                $query['end_time'] = strtotime($end_time);
				$end_time = strtotime($end_time);
				$where['create_time'] = ['lt',$end_time];
			}

	        $end_time = input('get.end_time');
			if(!empty($start_time) && !empty($end_time)){
                $query['start_time'] = strtotime($start_time);
                $query['end_time'] = strtotime($end_time);
				$over_time = strtotime($end_time);
				$where['create_time'] = [['egt',$start_time],['lt',$over_time]];
			}
			$keywords = input('get.keyword');
			if(!empty($keywords)){
                $query['keyword'] = $keywords;
				$where['title'] = ['like','%'.$keywords.'%'];
			}
		}
		$where['status'] = ['neq',0];
		
		$count = $this->page_model->where($where)->count();
		$lists = $this->page_model->where($where)
            ->order(['create_time'=>'DESC'])->paginate(10,$count,['query'=>$query]);
		
		$result = array();
		foreach ($lists as $key => $value) {
			$result[$key] = cache("page_".$value['page_id']);
			if(empty($result[$key])){
				$admin_name = model('admin')->where(['admin_id'=>$value['anchor_id']])->field('admin_name')->find();
				$lists[$key]['admin_name'] = $admin_name['admin_name'];
				$result[$key] = $lists[$key];
				cache('page_'.$value['page_id'],$lists[$key]);
			}
		}
        $page = $lists->render();
    	$this->assign('lists', $result);
    	$this->assign('page', $page);
		return $this->fetch();
	}

	//单页添加
	public function add(){
		return $this->fetch();
	}

	//单页添加提交
	public function add_post(){
		if(request()->isPost()){
			$param = input("post.");
			if (empty(trim($param['title']))) {
    			$this->error("请填写标题");
    		}
    		if (empty($param['content'])) {
	    		$this->error("请填写内容");
	    	}
	    	$param['create_time'] = time();
	    	$param['anchor_id'] = sp_get_current_admin_id();
	    	$page_result = $this->page_model->allowField(true)->save($param);
			if ($page_result !== false) {
    			$this->success("添加单页成功",url("page/index"));
	    	}else{
	    		$this->error("添加失败！");
	    	}
		}
	}

	//单页编辑
	public function edit(){
		$page_id = input("get.id",0,'intval');
		$page_data = $this->page_model->where(['page_id'=>$page_id])->find()->toArray();
		$this->assign('lists',$page_data);
		return $this->fetch();
	}

	//单页编辑提交
	public function edit_post(){
		if(request()->isPost()){
			$param = input("post.");
			if (empty(trim($param['title']))) {
	    		$this->error("请填写标题");
	    	}
	    	if (empty($param['content'])) {
	    		$this->error("请填写内容");
	    	}
	    	$page_info = $this->page_model->where(['page_id'=>$param['page_id']])->find()->toArray();
	    	$data = array();
	    	$data['create_time'] = time();
	    	if($param['title'] != $page_info['title']){
	    		$data['title'] = trim($param['title']);
	    	}
	    	if($param['keywords'] != $page_info['keywords']){
	    		$data['keywords'] = $param['keywords'];
	    	}
	    	if($param['description'] != $page_info['description']){
	    		$data['description'] = $param['description'];
	    	}
	    	if($param['content'] != $page_info['content']){
	    		$data['content'] = $param['content'];
	    	}
	    	$page_result = $this->page_model->allowField(true)->isUpdate(true)->save($param,['page_id' => $param['page_id']]);
		    if ($page_result !== false) {
	    		$this->success("修改单页成功",url("page/index"));
	    	}else{
	    		$this->error("修改失败！");
	    	}
		}
	}

	//单页删除
	public function delete(){
		$page_id = input("get.id",0,'intval');
   		$result = $this->page_model->isUpdate(true)->save(['status'=>0,'page_id' => $page_id]);
        if ($result !== false) {
    		$this->success("删除单页成功",url("page/index"));
    	}else{
    		$this->error("删除失败！");
    	}
	}
    
    //批量单页删除
    public function delgroup(){
    	if(request()->isPost()){
    		$ids = $_POST['ids'];
			if(isset($ids) && !empty($ids)){
		        $ids_array = implode(',',$ids);
		        $delete_result = $this->page_model->isUpdate(true)->save(["status"=>0,"page_id"=>["in",$ids_array]]);
		        if($delete_result !== false){
		            $this->success("删除单页成功",url("page/index"));
		        }else{
		            $this->error("删除失败！");
		        }
			}
    	}
    }
	//下架
	public function lock(){
        $page_id = input("get.id",0,'intval');
        if(!empty($page_id)){
        	$result = $this->page_model->isUpdate(true)->save(['status'=>2,'page_id'=>$page_id]);
	        if ($result !== false) {
	    		$this->success("下架单页成功",url("page/index"));
	    	}else{
	    		$this->error("下架失败！");
	    	}
        }else{
        	$this->error("传入数据失败！");
        }
        
	}
    //上架
	public function grounding(){
        $page_id = input("get.id",0,'intval');
        if(!empty($page_id)){
        	$result = $this->page_model->isUpdate(true)->save(['status'=>1,'page_id'=>$page_id]);
	        if ($result !== false) {
	    		$this->success("上架单页成功",url("page/index"));
	    	}else{
	    		$this->error("上架失败！");
	    	}
        }else{
        	$this->error("传入数据失败！");
        }
	}
}