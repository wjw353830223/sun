<?php

namespace app\admin\controller;

use think\Cache;
use think\Image;

class Grade extends Adminbase
{   
    protected $grade_model;
    protected function _initialize() {
        parent::_initialize();
        $this->grade_model = model('Grade');
    }
    public function index(){
        $grade = Cache::get('grade');
        if(empty($grade)){
            $grade = $this->grade_model->order('grade ASC')->select();
            Cache::set('grade',$grade);
        }
        $this->assign('grade',$grade);
        return $this->fetch();
    }

    public function add_grade(){
        if(request()->isGet()){
            return $this->fetch();
        }
        if(request()->isPost()){
            $param = input('post.');
            $data = [];
            $grade_type = intval($param['grade_type']);
            if(empty($grade_type)){
                $this->error('请选择等级身份');
            }
            if($grade_type == 1){
                //普通会员
                $grade_points = trim($param['grade_points']);

                $grade_parent_points = intval($param['grade_parent_points']);
                if(empty($grade_parent_points)){
                    $this->error('请填写升级奖励上级的积分');
                }
                $data['grade_points'] = $grade_points;
                $data['grade_parent_points'] = $grade_parent_points;
            }
            $grade = trim($param['grade']);
            if(empty($grade)){
                $this->error('等级不能为空');
            }
            $grade_name = trim($param['grade_name']);
            if(empty($grade_name)){
                $this->error('等级名称不能为空');
            }
            $grade_min_expend = trim($param['grade_min_expend']);
            $grade_expend_present = trim($param['grade_expend_present']);
            $data['grade'] = $grade;
            $data['grade_name'] = $grade_name;
            $data['grade_img'] = $param['grade_img'];
            $data['grade_state'] = $param['grade_state'];
            $data['create_time'] = time();
            $data['grade_type'] = $grade_type;
            $data['grade_min_expend'] = $grade_min_expend;
            $data['grade_expend_present'] = $grade_expend_present;
            $data['team_min_amount'] = trim($param['team_min_amount']);
            $data['team_present_points'] = trim($param['team_present_points']);

            $result = $this->grade_model->save($data);
            if($result === false){
                $this->error('添加失败');
            }
            $this->success('添加成功',url('grade/index'));
        }
    }

    public function edit_grade(){
        if(request()->isGet()){
            $id = input('get.id');
            $grade = $this->grade_model->where(['grade_id'=>$id])->find();
            $this->assign('grade',$grade);
            return $this->fetch();
        }
        if(request()->isPost()){
            $param = input('post.');
            $data = [];
            $grade_type = intval($param['grade_type']);
            if(empty($grade_type)){
                $this->error('请选择等级身份');
            }
            if($grade_type == 1){
                $grade_points = trim($param['grade_points']);

                $grade_parent_points = intval($param['grade_parent_points']);
                if(empty($grade_parent_points)){
                    $this->error('请填写升级奖励上级的积分');
                }
                $data['grade_points'] = $grade_points;
                $data['grade_parent_points'] = $grade_parent_points;
            }
            $grade = trim($param['grade']);
            if(empty($grade)){
                $this->error('等级不能为空');
            }
            $grade_name = trim($param['grade_name']);
            if(empty($grade_name)){
                $this->error('等级名称不能为空');
            }
            $grade_min_expend = trim($param['grade_min_expend']);
            $grade_expend_present = trim($param['grade_expend_present']);
            $data['grade'] = $param['grade'];
            $data['grade_name'] = $grade_name;
            $data['grade_img'] = $param['grade_img'];
            $data['grade_state'] = $param['grade_state'];
            $data['grade_type'] = $grade_type;
            $data['grade_min_expend'] = $grade_min_expend;
            $data['grade_expend_present'] = $grade_expend_present;
            $data['team_min_amount'] = trim($param['team_min_amount']);
            $data['team_present_points'] = trim($param['team_present_points']);
            $result = $this->grade_model->save($data,['grade_id'=>$param['grade_id']]);
            if($result === false){
                $this->error('编辑失败');
            }
            $this->success('编辑成功',url('grade/index'));
        }
    }

    public function del_grade(){
        $grade_id = input('get.id',0,'intval');
        $result = $this->grade_model->destroy($grade_id);
        if($result === false){
            $this->error('删除失败');
        }
        $this->success('删除成功',url('grade/index'));
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

    /**
     * 状态转换
     */
    public function update(){
        $grade_id = input('get.id',0,'intval');
        $state = input('get.state',0,'intval');
        
        $data = [
            'grade_state' => $state
        ];
        $result = $this->grade_model->save($data,['grade_id'=>$grade_id]);
        if($result === false){
            $this->error('修改失败');
        }
        $this->success('修改成功','grade/index');
    }
}