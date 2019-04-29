<?php
namespace app\admin\controller;
class Version extends Adminbase
{
	protected $ver_model;
	public function _initialize() {
		parent::_initialize();
		$this->ver_model = model('version');
	}

	//版本管理首页
	public function index(){
		$count = $this->ver_model->count();
        $lists = $this->ver_model->order('version_id desc')->paginate(20,$count);
        $page = $lists->render();
        $this->assign('page',$page);
        $this->assign('lists', $lists);
        return $this->fetch();
	}

	//版本编辑
	public function edit(){
		$ver_id = input('get.id',0,'intval');
        $data = $this->ver_model->get($ver_id);
        if (empty($data)) {
            $this->error('参数错误');
        }
        $version_list = $this->version_list();
        $this->assign('version_list',$version_list);
        $this->assign('data',$data->toArray());
        return $this->fetch();
	}

	//版本编辑提交
	public function edit_post(){
		if (request()->isPost()) {
			$param = input('post.');
			if (!isset($param['version'])) {
				$this->error('请选择版本');
			}
			if (!isset($param['version_code'])) {
				$this->error('请填写版本号');
			}
			$result = $this->ver_model->allowField(true)->save($param,['version_id' => $param['version_id']]);
    		if ($result!==false) {
    			$this->success("版本修改成功！",url('version/index'));
    		} else {
    			$this->error('版本修改失败！');
    		}
		}
	}

	//版本添加
	public function add(){
		$version_list = $this->version_list();
		$this->assign('version_list',$version_list);
		return $this->fetch();
	}

	//版本添加提交
	public function add_post(){
		if (request()->isPost()) {
			$param = input('post.');
			if (!isset($param['version'])) {
				$this->error('请选择版本');
			}
			if (!isset($param['version_code'])) {
				$this->error('请填写版本号');
			}
			$param['create_time'] = time();
			$result = $this->ver_model->allowField(true)->save($param);
    		if ($result!==false) {
    			$this->success("版本添加成功！",url('version/index'));
    		} else {
    			$this->error('版本添加失败！');
    		}
		}
	}

	//版本删除
	public function delete(){
		$ver_id = input('get.id',0,'intval');
    	if (!empty($ver_id)) {
    		$result = $this->ver_model->destroy(['version_id' => $ver_id]);
    		if ($result!==false) {
    			$this->success("版本删除成功！");
    		} else {
    			$this->error('版本删除失败！');
    		}
    	} else {
    		$this->error('数据传入失败！');
    	}
	}

	//版本下架
	public function lock(){
		$ver_id = input('get.id',0,'intval');
    	if (!empty($ver_id)) {
    		$result = $this->ver_model->save(['status' => '0'],['version_id' => $ver_id]);
    		if ($result!==false) {
    			$this->success("版本下架成功！");
    		} else {
    			$this->error('版本下架失败！');
    		}
    	} else {
    		$this->error('数据传入失败！');
    	}
	}

	//版本上架
	public function unlock(){
		$ver_id = input('get.id',0,'intval');
    	if (!empty($ver_id)) {
    		$result = $this->ver_model->save(['status' => '1'],['version_id' => $ver_id]);
    		if ($result!==false) {
    			$this->success("版本上架成功！");
    		} else {
    			$this->error('版本上架失败！');
    		}
    	} else {
    		$this->error('数据传入失败！');
    	}
	}

	//获取版本列表
	protected function version_list(){
		$min = 1;
		$max = 10;
		$list = array();
		for ($i = $min; $i <= $max; $i++) { 
			$list[] = $i;
		}
		return $list;
	}
}