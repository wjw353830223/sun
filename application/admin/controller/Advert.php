<?php
namespace app\admin\controller;

class Advert extends Adminbase
{
    protected $position_model,$advert_model;
    public function _initialize() {
        parent::_initialize();
        $this->position_model = model('AdvertPosition');
        $this->advert_model = model('Advert');
    }

    //广告管理首页
    public function index(){
        if(request()->isGet()){
            $where = array();
            $advert_name = input("get.advert_name","","trim");
            $where['name'] = ['like','%'.$advert_name.'%'];
        }
        $count = $this->position_model->count();
        $lists = $this->position_model->where($where)->paginate(20,$count);
        $page = $lists->render();
        $this->assign('page',$page);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    //广告位添加
    public function position_add(){
        return $this->fetch();
    }

    //广告位添加提交
    public function position_add_post(){
        if (request()->isPost()) {
            $param = input('post.');
            if (empty($_POST["name"])){
                $this->error('请填写广告位名称');
            }
            if(!empty($_FILES) && $_FILES['advert']['size'] > 0){
                $file = request()->file('advert');
                $image = \think\Image::open($file);
                $save_path = 'public/uploads/advert';
                $type = $image->type();
                $save_name = uniqid() . '.' . $type;
                $info = $file->rule('uniqid')->validate(['size'=>1048576,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.$save_path.'/',true,false);
                if(!$info){
                    $this->error($file->getError());
                }
                $bucket = config('qiniu.buckets')['images']['name'];
                $key = 'uploads/advert/'. $info->getSaveName();
                $path = './uploads/advert/' . $info->getSaveName();
                add_file_to_qiniu($key,$path,$bucket);
            }else{
                $this->error('广告图不能为空');
            }
            $param['default_content'] = $info->getFilename();

            $result = $this->position_model->allowField(true)->save($param);
            if ($result!==false) {
                $this->success("广告位添加成功！",url('advert/index'));
            } else {
                $this->error('广告位添加失败！');
            }
        }
    }

    //广告位编辑
    public function position_edit(){
        $position_id = input('get.id',0,'intval');
        $advert_position = $this->position_model->where(array('position_id'=>$position_id))->find();
        if(!empty($advert_position)){
            $this->assign('data',$advert_position->toArray());
        }
        return $this->fetch();
    }

    //广告位编辑提交
    public function position_edit_post(){
        if (request()->isPost()) {
            $param = input('post.');
            if (empty($param['name'])) {
                $this->error('请填写广告位名称');
            }
            if(!empty($_FILES) && $_FILES['advert']['size'] > 0){
                $file = request()->file('advert');
                $image = \think\Image::open($file);
                $save_path = 'public/uploads/advert';
                $type = $image->type();
                $save_name = uniqid() . '.' . $type;
                $info = $file->rule('uniqid')->validate(['size'=>1048576,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.$save_path.'/',true,false);
                $param['default_content'] = !empty($info->getFilename()) ? $info->getFilename() : $param['default_content'];
                if(!$info){
                    $this->error($file->getError());
                }
                $bucket = config('qiniu.buckets')['images']['name'];
                $key = 'uploads/advert/'. $info->getSaveName();
                $path = './uploads/advert/' . $info->getSaveName();
                add_file_to_qiniu($key,$path,$bucket);
            }

            $result = $this->position_model->allowField(true)->save($param,array('position_id' => $param['id']));
            if ($result!==false) {
                $this->success("广告位修改成功！",url('advert/index'));
            } else {
                $this->error('广告位修改失败！');
            }
        }
    }

    //广告管理页
    public function advert(){
        $id = input('get.id',0,'intval');
        $count = $this->advert_model->where(['position_id' => $id])->count();
        $lists = $this->advert_model->with('position')->where(['position_id' => $id])->paginate(20,$count);
        $page = $lists->render();
        $this->assign('page',$page);
        $this->assign('lists', $lists);
        $this->assign('position_id', $id);
        return $this->fetch();
    }

    //广告增加
    public function advert_add(){
        $id = input('get.id',0,'intval');
        $advert = $this->position_model->where(array('position_id'=>$id))->field('name,type')->find();
        if(!empty($advert)){
            $this->assign('data',$advert->toArray());
        }
        $goods = model('GoodsCommon')->where(['status'=>1])->select();
        $this->assign('goods',$goods);
        $this->assign("id",$id);
        return $this->fetch();
    }

    //广告添加提交
    public function advert_add_post(){
        if (request()->isPost()) {
            $param = input('post.');

            if(empty($_POST['title'])) {
                $this->error('请填写广告名称');
            }
            if(empty($_POST['start_time'])){
                $_POST['start_time'] = date('Y-m-d H:i:s');
            }
            $param['start_time'] = strtotime($_POST['start_time']);
            $param['end_time'] = strtotime($_POST['end_time']);
            if($param['end_time'] < $param['start_time']){
                $this->error('结束时间有误');
            }
            if(!empty($_FILES) && $_FILES['advert']['size'] > 0){
                $file = request()->file('advert');
                $image = \think\Image::open($file);
                $save_path = 'public/uploads/advert';
                $type = $image->type();
                $save_name = uniqid() . '.' . $type;
                $info = $file->rule('uniqid')->validate(['size'=>1048576,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.$save_path.'/',true,false);
                if(!$info){
                    $this->error($file->getError());
                }
                $bucket = config('qiniu.buckets')['images']['name'];
                $key = 'uploads/advert/'. $info->getSaveName();
                $path = './uploads/advert/' . $info->getSaveName();
                add_file_to_qiniu($key,$path,$bucket);
            }else{
                $this->error('广告图不能为空');
            }
            if(!empty($param['goods_commonid']) && !empty($param['adv_pic_url'])){
                $this->error('广告跳转链接和跳转商品只能选择其一');
            }

            $adv_pic = [
                'adv_pic'       =>  $info->getFilename(),
                'adv_pic_url'   =>  $param['adv_pic_url'],
                'adv_share_url' =>  $param['adv_share_url'],
                'goods_commonid'=>  $param['goods_commonid']
            ];
            $param['content'] = json_encode($adv_pic);
            $result = $this->advert_model->allowField(true)->save($param);
            if ($result!==false) {
                $this->position_model->where(array("position_id"=>$param['position_id']))->setInc('advert_nums');
                $this->success("广告添加成功！",url('advert/advert?id='.$param['position_id']));
            } else {
                $this->error('广告添加失败！');
            }
        }
    }

    //广告编辑
    public function advert_edit(){
        $id = input('get.id',0,'intval');
        $advert = $this->advert_model->with('position')->where(['advert_id' => $id])->find();
        if (empty($advert)) {
            $this->error('当前广告不存在');
        }
        $goods = model('GoodsCommon')->where(['status'=>1])->select();
        $this->assign('goods',$goods);
        $this->assign('data',$advert->toArray());
        return $this->fetch();
    }

    //广告编辑提交
    public function advert_edit_post(){
        if (request()->isPost()) {
            $param = input('post.');

            if (empty($param['title'])) {
                $this->error('请填写广告名称');
            }
            if(empty($_POST['start_time'])){
                $this->error('开始时间不能为空');
            }
            $param['start_time'] = strtotime($_POST['start_time']);
            if(empty($_POST['end_time'])){
                $this->error('结束时间不能为空');
            }
            $param['end_time'] = strtotime($_POST['end_time']);
            if($param['end_time'] < $param['start_time']){
                $this->error('结束时间有误');
            }

            if(!empty($_FILES) && $_FILES['advert']['size'] > 0){
                $file = request()->file('advert');
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
                $file_name = $param['adv_pic'];
            }
            if(!empty($param['goods_commonid']) && !empty($param['adv_pic_url'])){
                $this->error('广告跳转链接和跳转商品只能选择其一');
            }
            $adv_pic = [
                'adv_pic'       =>  $file_name,
                'adv_pic_url'   =>  $param['adv_pic_url'],
                'adv_share_url' =>  $param['adv_share_url'],
                'goods_commonid'=>  $param['goods_commonid']
            ];
            $param['content'] = json_encode($adv_pic);

            $result = $this->advert_model->allowField(true)->save($param,["advert_id"=>$param['advert_id']]);

            if ($result!==false) {
                $this->success("广告修改成功！",url('advert/advert?id='.$param['id']));
            } else {
                $this->error('广告修改失败！');
            }
        }
    }

    //广告删除
    public function advert_delete(){
        if(isset($_POST['ids'])){
            $ids = implode(',', $_POST['ids']);
            $position_id = input('post.position_id',0,'intval');
            $result = $this->advert_model->where(array('advert_id'=>array('in',$ids)))->delete();
            if ($result !== false) {
                $this->position_model->where(array("position_id"=>$position_id))->setDec('advert_nums',count($_POST['ids']));
                $this->success("广告删除成功！");
            } else {
                $this->error("广告删除失败！");
            }
        }
        if(isset($_GET['id'])){
            $adv_id = input('get.id',0,'intval');
            $position_id = input('get.position_id',0,'intval');
            $result = $this->advert_model->destroy(array('advert_id' =>$adv_id));
            if ($result!==false) {
                $this->success("广告删除成功！");
            } else {
                $this->error('广告删除失败！');
            }
        }
    }

    //广告位下架
    public function lock(){
        $position_id = input("get.id","","intval");
        if (!empty($position_id)) {
            $result = $this->position_model->save(array('is_use' => '0'),array("position_id"=>$position_id));
            if ($result!==false) {
                $this->success("广告位下架成功！");
            } else {
                $this->error('广告位下架失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }

    //广告位上架
    public function unlock(){
        $position_id = input("get.id","","intval");
        if (!empty($position_id)) {
            $result = $this->position_model->save(array('is_use' => '1'),array("position_id"=>$position_id));
            if ($result!==false) {
                $this->success("广告位上架成功！");
            } else {
                $this->error('广告位上架失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }
}