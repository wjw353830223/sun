<?php
namespace app\admin\controller;
use think\db;
class Special extends Adminbase
{
	public function _initialize() {
		parent::_initialize();
	}

	/**
	* 专题列表
	*/
	public function index(){
		$count = model('Special')->count();
		$lists = model('Special')->order('special_id DESC')->paginate(20,$count);
        $domain = config('qiniu.buckets')['images']['domain'];

        foreach ($lists as $key => $value) {
            if (!empty($value['special_image'])) {
                $lists[$key]['special_image'] = $domain . '/uploads/advert/' .$value['special_image'];
            }
        }
    	$page = $lists->render();

		$this->assign('page',$page);
		$this->assign('lists',$lists);

		return $this->fetch();
	}

	//专题添加
	public function add(){
		return $this->fetch();
	}

	//专题添加提交
	public function add_post(){
        if (request()->isPost()) {
            $param = input('post.');
//            $special_field = $param['special_field'];
//            $param['special_field'] = implode('|',$special_field);
            $regex = '/^(http|https|ftp):\/\/[A-Za-z0-9]+\.[A-Za-z0-9]+[\/=\?%\-&_~`@[\]\’:+!]*([^<>\”])*$/';
            if(!preg_match($regex, $param['special_link_url'])){
                $this->error('专题连接格式有误');
            }
            $param['special_modify_time'] = time();
            if(!empty($_FILES) && $_FILES['photo']['size'] > 0){
                $file = request()->file('photo');
                $image = \think\Image::open($file);
                $save_path = 'public/uploads/advert';
                $type = $image->type();
                $save_name = uniqid() . '.' . $type;
                $info = $file->rule('uniqid')->validate(['size'=>1048576,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.$save_path.'/',true,false);
                $file_name = $info->getFilename();
                if(!$info){
                    $this->error($file->getError());
                }
                $bucket = config('qiniu.buckets')['images']['name'];
                $key = 'uploads/advert/'. $info->getSaveName();
                $path = './uploads/advert/' . $info->getSaveName();
                add_file_to_qiniu($key,$path,$bucket);
                $param['special_image'] = $file_name;
            }else{
                $this->error('专题图不能为空');
            }
            $res = model('Special')->insert($param);
            if($res === false){
                $this->error('添加失败');
            }
            $this->success('添加成功',url('special/index'));
        }
	}

	//专题修改
	public function edit(){
	    $special_id = input('get.id','','intval');
        $special = model('Special')->where(['special_id'=>$special_id])->find();
        if (empty($special)){
            $this->error('专题id传入有误');
        }
//        $special_field = explode('|',$special['special_field']);
//        $special['special_field'] = array_filter($special_field);
        $this->assign('special',$special);
		return $this->fetch();
	}

	//专题修改提交
	public function edit_post(){
		if (request()->isPost()) {
			$param = input('post.');
//            $special_field = $param['special_field'];
//            $param['special_field'] = implode('|',$special_field);
            $regex = '/^(http|https|ftp):\/\/[A-Za-z0-9]+\.[A-Za-z0-9]+[\/=\?%\-&_~`@[\]\’:+!]*([^<>\”])*$/';
            if(!preg_match($regex, $param['special_link_url'])){
                $this->error('专题连接格式有误');
            }
            $param['special_modify_time'] = time();
            if(!empty($_FILES) && $_FILES['photo']['size'] > 0){
                $file = request()->file('photo');
                $image = \think\Image::open($file);
                $save_path = 'public/uploads/advert';
                $type = $image->type();
                $save_name = uniqid() . '.' . $type;
                $info = $file->rule('uniqid')->validate(['size'=>1048576,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.$save_path.'/',true,false);
                $file_name = $info->getFilename();
                if(!$info){
                    $this->error($file->getError());
                }
                $bucket = config('qiniu.buckets')['images']['name'];
                $key = 'uploads/advert/'. $info->getSaveName();
                $path = './uploads/advert/' . $info->getSaveName();
                add_file_to_qiniu($key,$path,$bucket);
            }else{
                $file_name = input('post.special_image','','trim');
            }
            $param['special_image'] = $file_name;
            $res = model('Special')->where(['special_id'=>$param['special_id']])->update($param);
            if($res === false){
                $this->error('修改失败');
            }
            $this->success('修改成功',url('special/index'));
        }
	}

	//专题删除
	public function delete(){
		$special_id = input('get.id',0,'intval');
    	if (!empty($special_id)) {
    		$result = model('Special')->where(['special_id' => $special_id])->delete();
    		if ($result!==false) {
    			$this->success("版本删除成功！");
    		} else {
    			$this->error('版本删除失败！');
    		}
    	} else {
    		$this->error('数据传入失败！');
    	}
	}

	//专题下架
	public function lock(){
		$special_id = input('get.id',0,'intval');
    	if (!empty($special_id)) {
    		$result = model('Special')->update(['special_state' => '1','special_id' => $special_id]);
    		if ($result!==false) {
    			$this->success("版本下架成功！");
    		} else {
    			$this->error('版本下架失败！');
    		}
    	} else {
    		$this->error('数据传入失败！');
    	}
	}

	//专题上架
	public function unlock(){
		$special_id = input('get.id',0,'intval');
    	if (!empty($special_id)) {
    		$result = model('Special')->update(['special_state' => '2','special_id' => $special_id]);
    		if ($result!==false) {
    			$this->success("版本上架成功！");
    		} else {
    			$this->error('版本上架失败！');
    		}
    	} else {
    		$this->error('数据传入失败！');
    	}
	}
}