<?php
namespace app\admin\controller;

use think\Request;

class Family extends Adminbase
{
    protected $member_model;
    protected $family_model;
    protected $affix_model;
    protected $family_member_model;
    protected $member_info_model;
    protected function _initialize() {
        parent::_initialize();
        $this->family_model = model('Family');
        $this->member_model = model('Member');
        $this->affix_model = model('FamilyAffix');
        $this->family_member_model = model('FamilyMember');
        $this->member_info_model = model('FamilyMemberInfo');
    }

    /**
     * 家庭列表
     */
    public function index()
    {
        $where = [];
        $query = [];
        $param = input('param.','trim');

        if(!empty($param['family_id'])){
            $query['family_id'] = intval($param['family_id']);
            $where['family_id'] = intval($param['family_id']);
        }

        if(!empty($param['robot_imei'])){
            $robot_imei = trim($param['robot_imei']);
            $query['robot_imei'] = $robot_imei;
            $where['robot_imei'] = ['like',"%$robot_imei%"];
        }

        if(!empty($param['keyword'])){
            $keyword = trim($param['keyword']);
            $query['keyword'] = $keyword;
            $where['family_name|member_name'] = ['like',"%$keyword%"];
            
        }
        $count = $this->family_model
            ->alias('a')
            ->join('member b','a.member_id = b.member_id')
            ->where($where)
            ->count();
        $list = $this->family_model
            ->alias('a')
            ->join('member b','a.member_id = b.member_id')
            ->where($where)
            ->order('family_id desc')
            ->paginate(10,$count,['query'=>$query]);
            
        $page = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);
        return $this->fetch();
    }

    /**
     * 家庭信息编辑页
     */
    public function edit()
    {
        $family_id = input('get.family_id','','intval');
        $family_info = $this->family_model->where('family_id',$family_id)->find();
        if(empty($family_info)){
            $this->error('所修改的家庭不存在');
        }
        $state = input('get.state','','intval');
        $arr = array(0,1);
        if(!in_array($state,$arr)){
            $this->error('非法操作');
        }

        $result = $this->family_model->where('family_id',$family_id)->setField('state',$state);
        if(!$result){
            $this->error('家庭状态修改失败，请重试');
        }

        $this->success('家庭状态修改成功','family/index');
    }

    /**
     * 家庭信息编辑提交
     */
   /*  public function edit_post()
     {
         if(request()->isPost()){
             $param = input('post.','','trim');

             $family_id = $param['family_id'];
             $family_info = $this->family_model->where('family_id',$family_id)->find();
             if(empty($family_info)){
                 $this->error('所修改的家庭不存在');
             }
             $family_name = $param['family_name'];

             if(empty($family_name)){
                 $this->error('家庭名称不能为空');
             }

             //家庭名称字数不超过4个字
             $content_len = strlen($family_name);
             $i = $count = 0;
             while($i < $content_len){
                 $chr = ord($family_name[$i]);
                 $count++;$i++;
                 if($i >= $content_len){
                     break;
                 }
                 if ($chr & 0x80){
                     $chr <<= 1;
                     while ($chr & 0x80){
                         $i++;
                         $chr <<= 1;
                     }
                 }
             }

             if($count < 4){
                 $this->error('家庭名称至少为四个字');
             }
             if (is_badword($family_name) || strlen($family_name) > 20 ) {
                 $this->ajax_return('家庭名称太长，请修改后重试');
             }

             $member_name = $param['member_name'];
             if(empty($member_name)){
                 $this->error('家庭所有者名称不能为空');
             }


             $member_id = $family_info['member_id'];

             $member_info = $this->member_model->where('member_id',$member_id)->find();
             if(empty($member_info)){
                 $this->error('家庭拥有者不存在');
             }

             $state = $param['state'];
             $arr = array(0,1);
             if(!in_array($state,$arr)){
                 $this->error('非法操作');
             }

             $family_result = $this->family_model->where('family_id',$family_id)->update(['family_name'=>$family_name,'state'=>$state]);
             $member_result = $this->member_model->where('member_id',$member_id)->setField('member_name', $member_name);

             if($family_result === 0 & $member_result === 0){
                 $this->error('家庭信息未作出修改,请重试');
             }

             $this->success('修改家庭信息成功','family/index');
         }
     }*/

    /**
     * 机器人绑定页面
     */
    public function bind()
    {
        $family_id = input('get.family_id',0,'intval');
        if(!empty($family_id)){
            $family_name = $this->family_model->where('family_id',$family_id)->value('family_name');
            $this->assign('family_name',$family_name);
            $this->assign('family_id',$family_id);
            return $this->fetch();
        } else {
            $this->error('参数传入错误');
        }
    }

    /**
     * 机器人绑定提交
     */
    public function bind_post()
    {
        if(request()->isPost()){
            $param = input('post.','','trim');
            $family_id = $param['family_id'];
            if(empty($family_id)) {
                $this->error('未指定家庭进行绑定操作');
            }
            $robot_imei = $param['robot_imei'];
            if (empty($robot_imei)) {
                $this->error('机器人IMEI未填写');
            }
            if (!preg_match("/^[A-Z\d]{18}-[A-Z\d]{4}$/",$robot_imei) && !is_robotsn($robot_imei)) {
                $this->error('机器人IMEI格式错误，请修改后重试');
            }
            $has_bind = $this->family_model->where('robot_imei',$robot_imei)->count();
            if(!empty($has_bind)) {
                $this->error('该机器人已被绑定，请更换机器人IMEI');
            }
            $result = $this->family_model->where('family_id',$family_id)->setField('robot_imei',$robot_imei);
            if($result){
                $this->success('机器人绑定成功','family/index');
            } else {
                $this->error('机器人绑定失败，请重试');
            }
        }
    }

    /**
     * 机器人换绑
     */
    public function unbind()
    {
        $family_id = input('get.family_id',0,'intval');
        if(!empty($family_id)){
            $family_info = $this->family_model->where('family_id',$family_id)->find();
            $this->assign('family_info',$family_info);
            $this->assign('family_id',$family_id);
            return $this->fetch();
        } else {
            $this->error('参数传入错误');
        }
    }

    /**
     * 机器人换绑提交
     */
    public function unbind_post()
    {
        if(request()->isPost()){
            $param = input('post.','','trim');

            $family_id = $param['family_id'];

            if(empty($family_id)) {
                $this->error('未指定家庭进行绑定操作');
            }

            $family_info = $this->family_model->where('family_id',$family_id)->find();

            $old_robot_imei = $param['old_robot_imei'];

            if($old_robot_imei !== $family_info['robot_imei']){
                $this->error('机器人原IMEI错误，请修改后重试');
            }

            $robot_imei = $param['robot_imei'];

            // 换绑操作
            if (empty($robot_imei) && !preg_match("/^[A-Z\d]{18}-[A-Z\d]{4}$/",$robot_imei)) {
                $this->error('机器人IMEI未填写或格式错误，请修改后重试');
            }

            $has_bind = $this->family_model->where('robot_imei',$robot_imei)->count();
            if(!empty($has_bind)) {
                $this->error('该机器人已被绑定，请更换机器人IMEI');
            }

            $result = $this->family_model->where('family_id',$family_id)->setField('robot_imei',$robot_imei);

            if($result){
                $this->success('机器人绑定成功','family/index');
            } else {
                $this->error('机器人绑定失败，请重试');
            }
        }
    }

    /**
     * 机器人解绑
     */
    public function remove_bind()
    {
        $family_id = input('family_id','','intval');

        // 解绑
        $result = $this->family_model->where('family_id',$family_id)->setField('robot_imei','');

        if(!$result){
            $this->error('机器人解绑失败，请重试');
        }

       $this->success('机器人解绑成功','family/index');
    }

    /**
     * 家庭会员信息页
     */
    public function member()
    {
        $profession = ['0'=>'无','1'=>'国家公务员','2'=>'专业技术人员','3'=>'职员',
            '4'=>'企业管理人员','5'=>'工人','6'=>'农民','7'=>'学生','8'=>'现役军人',
            '9'=>'自由职业者','10'=>'个体经营者','11'=>'退(离)休人员'];
        $family_id = input('family_id','','intval');
        if(empty($family_id)){
            $this->error('非法操作');
        }
        $has_family = $this->family_model->where('family_id',$family_id)->count();
        if(!$has_family){
            $this->error('该家庭不存在');
        }

        // 获取真实成员与虚拟成员ID
        $member_id[] = $this->family_member_model->where(['family_id'=>$family_id,'is_member'=>1])->column('member_id');
        $info_id[] = $this->family_member_model->where(['family_id'=>$family_id,'is_member'=>0])->column('info_id');

        $member = $this->member_model
                ->alias('a')
                ->join('member_info b','a.member_id = b.member_id')
                ->where('a.member_id','in',$member_id['0'])
                ->select();

        $info_member = $this->member_info_model->where('info_id','in',$info_id['0'])->select();
        $this->assign('member',$member);
        $this->assign('info_member',$info_member);
        $this->assign('profession',$profession);
        return $this->fetch();
    }

    /**
     * 真实会员列表页
     */
    public function member_list()
    {
        $profession = ['1'=>'国家公务员','2'=>'专业技术人员','3'=>'职员','4'=>'企业管理人员',
                    '5'=>'工人','6'=>'农民','7'=>'学生','8'=>'现役军人','9'=>'自由职业者',
                    '10'=>'个体经营者','11'=>'退(离)休人员'];
        $where = [];
        $query = [];
        $param = input('param.','','trim');

        if(!empty($param['member_id'])){
            $query['member_id'] = intval($param['member_id']);
            $where['a.member_id'] = intval($param['member_id']);
        }

        if(!empty($param['watch_imei'])){
            $watch_imei = trim($param['watch_imei']);
            $query['watch_imei'] = $watch_imei;
            $where['watch_imei'] = ['like',"%$watch_imei%"];
        }

        if(!empty($param['keyword'])) {
            $keyword = $param['keyword'];
            $query['keyword'] = $keyword;
            $where['member_mobile|member_name|true_name'] = ['like', "%$keyword%"];
        }
        $count = $this->member_model
            ->alias('a')
            ->join('member_info b','a.member_id = b.member_id')
            ->where($where)
            ->count();
        $member = $this->member_model
            ->alias('a')
            ->join('member_info b','a.member_id = b.member_id')
            ->where($where)
            ->order('a.member_id desc')
            ->paginate(10,$count,['query'=>$query]);

        $page = $member->render();
        $this->assign('member', $member);
        $this->assign('profession',$profession);
        $this->assign('page', $page);
        return $this->fetch();
    }

    /**
     * 虚拟会员列表页
     */
    public function info_member_list()
    {
        $where = [];
        $query = [];
        $param = input('param.','','trim');

        if(!empty($param['info_id'])){
            $query['info_id']=intval($param['info_id']);
            $where['info_id']=intval($param['info_id']);
        }

        if(!empty($param['watch_imei'])){
            $watch_imei = trim($param['watch_imei']);
            $query['watch_imei'] = $watch_imei;
            $where['watch_imei'] = ['like',"%$watch_imei%"];
        }

        if(!empty($param['keyword'])){
            $keyword = $param['keyword'];
            $query['keyword'] = $keyword;
            $where['mobile|nickname'] = ['like',"%$keyword%"];
        }
        $count = $this->member_info_model->where($where)->count();
        $info_member = $this->member_info_model->where($where)
            ->order('info_id desc')->paginate(10,$count,['query'=>$query]);

        $page = $info_member->render();
        $this->assign('info_member',$info_member);
        $this->assign('page', $page);
        return $this->fetch();
    }

    //手表绑定页面
    public function watch_bind()
    {
        $member_id = input('get.member_id',0,'intval');
        if(!empty($member_id)){
            $member_name = $this->member_model->where('member_id',$member_id)->value('member_name');
            $this->assign('member_name',$member_name);
            $this->assign('member_id',$member_id);
            return $this->fetch();
        } else {
            $this->error('参数传入错误');
        }
    }

    //手表换绑页面
    public function watch_change()
    {
        $member_id = input('get.member_id',0,'intval');
        if(!empty($member_id)){
            $member_info = $this->member_model->where('member_id',$member_id)->field('member_name,member_id')->find();
            $this->assign('member_name',$member_info['member_name']);
            $this->assign('member_id',$member_info['member_id']);
            return $this->fetch();
        } else {
            $this->error('参数传入错误');
        }
    }

    //手表绑定
    public function watch_add()
    {
        $watch_imei = input('post.watch_imei');
        if(empty($watch_imei)){
            $this->error('手表编码不能为空');
        }   
        $member_id = input('post.member_id');
        $result = model('MemberInfo')->save(['watch_imei'=>$watch_imei],['member_id'=>$member_id]);
        if($result){
            $this->error('绑定成功',url('family/member_list'));
        }else{
            $this->error('绑定失败');
        }
    }

    //手表换绑
    function watch_change_add()
    {
        if(request()->isPost()){
            $param = input('post.');
            if(empty($param['old_watch_imei'])){
                $this->error('原手表编码不能为空');
            }

            if(empty($param['watch_imei'])){
                $this->error('新手表编码不能为空');
            }

            $result = model('MemberInfo')->where(['member_id'=>$param['member_id']])->value('watch_imei');
            if($param['old_watch_imei'] != $result){
                $this->error('原手表编码不正确');
            }

            $data = [
                'watch_imei' => $param['watch_imei']
            ];
            $member_id = $param['member_id'];
            $result = model('MemberInfo')->save($data,['member_id'=>$member_id]);
            if($result === false){
                $this->error('换绑失败');
            }else{
                $this->success('换绑成功',url('family/member_list'));
            }
        }
    }

    //手表绑定
    public function watch_unbind()
    {
        $member_id = input('get.member_id',0,'intval');
        if(!empty($member_id)){
            $result = model('MemberInfo')->where(['member_id'=>$member_id])->setField('watch_imei','');
            if($result){
                $this->success('解绑成功',url('family/member_list'));
            }else{
                $this->error('解绑失败');
            }
        }else{
            $this->error('参数传入错误');
        }
    }

    /*
     * 虚拟会员手表绑定
     */
    public function info_watch_bind()
    {
        if(Request::instance()->isGet()){
            $info_id = input('get.info_id',0,'intval');
            if(!empty($info_id)){
                $nickname = $this->member_info_model->where(['info_id'=>$info_id])->value('nickname');
                $this->assign('info_id',$info_id);
                $this->assign('nickname',$nickname);
                return $this->fetch();
            }else{
                $this->error('参数传入错误');
            }
        }
        if(Request::instance()->isPost()){
            $watch_imei = input('post.watch_imei');
            if(empty($watch_imei)){
                $this->error('手表编码不能为空');
            }
            $info_id = input('post.info_id');
            $result = $this->member_info_model->save(['watch_imei'=>$watch_imei],['info_id'=>$info_id]);
            if($result){
                $this->success('绑定成功',url('family/info_member_list'));
            }
        }
    }


    /*
     * 虚拟会员手表解绑
     */
    public function info_watch_unbind()
    {
        $info_id = input('get.info_id',0,'intval');
        if(!empty($info_id)){
            $result = $this->member_info_model->where(['info_id'=>$info_id])->setField('watch_imei','');
            if($result){
                $this->success('解绑成功',url('family/info_member_list'));
            }else{
                $this->error('解绑失败');
            }
        }else{
            $this->error('参数传入错误');
        }
    }


    /*
     * 虚拟会员手表换绑
     */
    public function info_watch_change()
    {
        if(Request::instance()->isGet()){
            $info_id = input('get.info_id',0,'intval');
            if(!empty($info_id)){
                $member_info = $this->member_info_model->where(['info_id'=>$info_id])
                                ->field('info_id,nickname')->find();
                $this->assign('member_info',$member_info);
                return view('info_watch_change');
            }else{
                $this->error('参数传入错误');
            }
        }
        if(Request::instance()->isPost()){
            //获取旧的手表编号进行比较
            $info = input();
            if(empty($info['old_watch_imei'])){
                $this->error('原手表编码不能为空');
            }
            if(empty($info['watch_imei'])){
                $result = $this->member_info_model->where(['info_id'=>$info['info_id']])->setField('watch_imei','');
                if($result){
                    $this->success('解绑成功',url('family/info_member_list'));
                }else{
                    $this->error('解绑失败');
                }
            }else{
                $watch_imei = $this->member_info_model->where(['info_id'=>$info['info_id']])->value('watch_imei');
                if($info['old_watch_imei'] !== $watch_imei){
                    $this->error('原手表编码输入错误');
                }
                if($info['watch_imei'] == $watch_imei){
                    $this->error('新手表编码与原手表编码输入一致');
                }
                $result = $this->member_info_model->save(['watch_imei'=>$info['watch_imei']],['info_id'=>$info['info_id']]);
                if($result){
                    $this->success('换绑成功',url('family/info_member_list'));
                }else{
                    $this->error('换绑失败');
                }
            }
        }
    }
}