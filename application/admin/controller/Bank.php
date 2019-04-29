<?php

namespace app\admin\controller;
use think\Image;

class Bank extends Adminbase
{
    protected $bank_model;
    protected function _initialize(){
        parent::_initialize();
        $this->bank_model = model('Bank');
    }

    public function index(){
        $count = $this->bank_model->count();
        $bank = $this->bank_model->paginate(10,$count);
        foreach($bank as $k=>$v){
            if(!empty($v['bank_img'])){
                $v['bank_img'] = strexists($v['bank_img'],'http')
                    ? $v['bank_img']
                    :config('qiniu.buckets')['images']['domain'] . '/uploads/bank/' . $v['bank_img'];
            }
        }
        $page = $bank->render();
        $this->assign('bank',$bank);
        $this->assign('page',$page);
        return $this->fetch();
    }

    public function add_bank(){
        if(request()->isGet()){
            return $this->fetch();
        }
        if(request()->isPost()){
            $bank_name = input('post.bank_name','','trim');
            if(empty($bank_name)){
                $this->error('请填写银行名称');
            }
            $bank_img = input('post.bank_img','','trim');
            if(empty($bank_img)){
                $this->error('请上传银行图标');
            }
            $bank_state = input('param.bank_state');
            $data = [
                'bank_name'  => $bank_name,
                'bank_img'   => $bank_img,
                'bank_state' => $bank_state,
                'add_time'   => time()
            ];
            $result = $this->bank_model->insertGetId($data);
            if($result === false){
                $this->error('银行添加失败');
            }
            $this->success('银行添加成功',url('bank/index'));
        }
    }

    public function upload(){
        $file = request()->file('avatar');
        $image = Image::open($file);
        $type = $image->type();
        $save_path = 'public/uploads/bank';
        $save_name = uniqid() . '.' . $type;
        $image->save(ROOT_PATH . $save_path . '/' . $save_name);
        if (is_file(ROOT_PATH . $save_path . '/' . $save_name)) {
            $data = [
                'file' => $save_name,
                'msg'  => '上传成功',
                'code' => 1
            ];
            $bucket = config('qiniu.buckets')['images']['name'];
            $key = 'uploads/bank/' . $save_name;
            $path = './' . $key;
            add_file_to_qiniu($key,$path,$bucket);
            echo json_encode($data);
        } else {
            $data = [
                'data' => '',
                'msg'  => $file->getError(),
                'code' => 0
            ];
            echo json_encode($data);
        }
    }

    public function edit(){
        if(request()->isGet()){
            $id = input('get.id',0,'intval');
            if(!$id){
                $this->error('参数传入错误');
            }
            $bank = $this->bank_model->where(['bank_id'=>$id])->find();
            $this->assign('bank',$bank);
            return $this->fetch();
        }
        if(request()->isPost()){
            $id = input('post.bank_id','','trim');
            $bank_name = input('post.bank_name','','trim');
            if(empty($bank_name)){
                $this->error('请填写银行名称');
            }
            $bank_img = input('post.bank_img','','trim');
            if(empty($bank_img)){
                $this->error('请上传银行图标');
            }
            $bank_state = input('param.bank_state');
            $data = [
                'bank_name'  => $bank_name,
                'bank_img'   => $bank_img,
                'bank_state' => $bank_state,
                'add_time'   => time()
            ];
            $result = $this->bank_model->save($data,['bank_id'=>$id]);
            if($result === false){
                $this->error('银行信息编辑失败');
            }
            $this->success('银行信息编辑成功',url('bank/index'));
        }
    }

    public function delete(){
        $id = input('get.id',0,'intval');
        if(!$id){
            $this->error('参数传入错误');
        }
        $result = $this->bank_model->where(['bank_id'=>$id])->delete();
        if($result === false){
            $this->error('银行信息删除失败');
        }
        $this->success('银行信息删除成功',url('bank/index'));
    }

    public function update(){
        $id = input('get.id',0,'intval');
        if(!$id){
            $this->error('参数传入错误');
        }
        $bank_state = input('param.bank_state',0,'intval');
        if($bank_state === ''){
            $this->error('参数传入错误');
        }
        $result = $this->bank_model->save(['bank_state'=>$bank_state],['bank_id'=>$id]);
        if($result === false){
            $this->error('银行状态修改失败');
        }
        $this->success('银行状态修改成功');
    }
}