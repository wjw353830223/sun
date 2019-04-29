<?php
namespace app\admin\controller;

class Team extends Adminbase
{
    protected $team_model,$member_model;
    protected $seller_model,$store_model;
    protected $order_model,$tm_model;
    protected $points_model;
    protected function _initialize()
    {
        parent::_initialize();
        $this->team_model = model('Team');
        $this->member_model = model('Member');
        $this->seller_model = model('StoreSeller');
        $this->store_model = model('Store');
        $this->order_model = model('Order');
        $this->tm_model = model('TeamReward');
        $this->points_model = model('PointsLog');
    }

    /**
     * 团队相关信息
     */
    public function team_index(){
        $query = $where = [];
        if(request()->isGet()){
            $param = input('param.');
            if(isset($param['member_mobile']) && !empty(trim($param['member_mobile']))){
                if(!is_mobile(trim($param['member_mobile']))){
                    $this->error('创建人账号不符合要求');
                }
                $member_id = $this->member_model->where(['member_mobile'=>trim($param['member_mobile'])])->value('member_id');
                $where['member_id'] = $member_id;
                $query['member_id'] = $member_id;
            }
            if(!empty($param['start_time'])){
                $query['start_time'] = $param['start_time'];
                $start_time = strtotime($param['start_time']);
                $where['create_time'] = ['egt',$start_time];
            }
            if(!empty($param['end_time'])){
                $query['end_time'] = $param['end_time'];
                $end_time = strtotime($param['end_time']);
                $where['create_time'] = ['lt',$end_time];
            }
            if(!empty($start_time) && !empty($end_time)){
                $query['start_time'] = $param['start_time'];
                $query['end_time'] = $param['end_time'];
                $start_time = strtotime($param['start_time']);
                $end_time = strtotime($param['end_time']);
                $where['create_time'] = [['egt',$start_time],['lt',$end_time]];
            }
        }
        $count = $this->team_model->where($where)->count();
        $team = $this->team_model->where($where)->paginate(15,$count,['query'=>$query]);
        $ids = [];
        foreach($team as $k=>$v){
            $ids[] = $v['member_id'];
        }
        $member = $this->member_model
            ->where(['member_id'=>['in',$ids]])
            ->field('member_name,member_mobile,member_id')
            ->select();
        foreach($team as $k=>$v){
            foreach($member as $key=>$val){
                if($member[$key]['member_id'] == $team[$k]['member_id']){
                    $team[$k]['member_name'] = $member[$key]['member_name'];
                    $team[$k]['member_mobile'] = $member[$key]['member_mobile'];
                }
            }
        }
        $page = $team->render();
        $this->assign('page',$page);
        $this->assign('team',$team);
        return $this->fetch();
    }

    /**
     * 团队状态修改
     */
    public function update(){
        $id = input('get.id',0,'intval');
        if(!$id){
            $this->error('参数传入错误');
        }
        $status = input('get.team_status');
        $result = $this->team_model->where(['team_id'=>$id])->update(['team_status'=>$status]);
        if($result === false){
            $this->error('状态修改失败');
        }
        $this->success('状态修改失败');
    }

    /**
     * 团队详情
     */
    public function team_detail(){
        $id = input('get.id',0,'intval');
        if(!$id){
            $this->error('参数传入错误');
        }
        $query = ['id'=>$id];
        $member_id = $this->team_model->where(['team_id'=>$id])->value('member_id');
        $seller_info = $this->seller_model->where(['member_id'=>$member_id])->find();
        if(!$seller_info){
            $this->error('该用户不是消费商');
        }
        $store_name = $this->store_model->where(['store_id'=>$seller_info['store_id']])->value('store_name');
        $seller_info['seller_store'] = $store_name;
        //获取团队成员
        $count = $this->member_model->where(['parent_id'=>$member_id])->count();
        $member = $this->member_model->where(['parent_id'=>$member_id])->paginate(15,$count,['query'=>$query]);
        $ids = [];
        foreach($member as $k=>$v){
            $v['member_time'] = date('Y-m-d H:i:s',$v['member_time']);
            $ids[] = $v['member_id'];
        }
        //判断团队成员是否是消费商
        $seller = $this->seller_model->where(['member_id'=>['in',$ids]])->select()->toArray();
        if($seller){
            foreach($member as $k=>$v){
                foreach($seller as $key=>$val){
                    if($v['member_id'] == $val['member_id']){
                        $member[$k]['id_card'] = $seller[$key]['id_card'];
                        $member[$k]['seller_role'] = $seller[$key]['seller_role'];
                        $member[$k]['seller_state'] = $seller[$key]['seller_state'];
                        break;
                    }else{
                        $member[$k]['id_card'] = '';
                        $member[$k]['seller_role'] = '';
                        $member[$k]['seller_state'] = '';
                    }
                }
            }
        }else{
            foreach($member as $k=>$v){
                $member[$k]['id_card'] = '';
                $member[$k]['seller_role'] = '';
                $member[$k]['seller_state'] = '';
            }
        }

        $page = $member->render();
        $this->assign('page',$page);
        $this->assign('member',$member);
        return $this->fetch();
    }

    /**
     * 团队业绩查看
     */
    public function team_results(){
        $id = input('get.id',0,'intval');
        if(!$id){
            $this->error('参数传入错误');
        }
        $member_id = $this->team_model->where(['team_id'=>$id])->value('member_id');
        $seller_id = $this->seller_model->where(['member_id'=>$member_id])->value('seller_id');
        if(!$seller_id){
            $this->error('该用户不是消费商');
        }
        //团队业绩
        $team_name = $this->team_model->where(['team_id'=>$id])->value('team_name');
        $member_name = $this->member_model->where(['member_id'=>$member_id])->value('member_name');
        $total_amount = $this->team_model->team_perform($member_id);
        $month_amount = $this->team_model->team_mdperform('month',$member_id);
        $day_amount = $this->team_model->team_mdperform('day',$member_id);
        $data = [
            'team_name'    => $team_name,
            'member_name'  => $member_name,
            'day_amount'   => $day_amount,
            'month_amount' => $month_amount,
            'total_amount' => $total_amount
        ];
        $this->assign('data',$data);
        return $this->fetch();
    }

    /**
     * 团队奖励设置
     */
    public function team_reward(){
        $count = $this->tm_model->count();
        $team_award = $this->tm_model->order('team_number desc')->paginate(15,$count);
        $page = $team_award->render();
        $this->assign('page',$page);
        $this->assign('team_award',$team_award);
        return $this->fetch();
    }

    /**
     * 添加团队管理奖励
     */
    public function add_team_reward(){
        if(request()->isGet()) {
            return $this->fetch();
        }
        if(request()->isPost()){
            $team_number = input('post.team_number',0,'intval');
            if(!$team_number){
                $this->error('请填写团队人数');
            }
            $mouth_refund_points = input('post.mouth_refund_points',0,'intval');
            $fixed_refund_points = input('post.fixed_refund_points',0,'intval');
            $reward_state = input('param.reward_state');
            $data = [
                'team_number'         => $team_number,
                'mouth_refund_points' => $mouth_refund_points,
                'fixed_refund_points' => $fixed_refund_points,
                'reward_state'        => $reward_state,
                'create_time'         => time()
            ];
            $result = $this->tm_model->insertGetId($data);
            if($result === false){
                $this->error('团队奖励添加失败');
            }
            $this->success('团队奖励添加成功',url('team/team_reward'));
        }
    }

    /**
     * 编辑团队管理奖励
     */
    public function edit_team_reward(){
        if(request()->isGet()){
            $id = input('get.id',0,'intval');
            if(!$id){
                $this->error('参数传入错误');
            }
            $data = $this->tm_model->where(['reward_id'=>$id])->find();
            $this->assign('data',$data);
            return $this->fetch();
        }
        if(request()->isPost()){
            $reward_id = input('post.reward_id',0,'intval');
            $team_number = input('post.team_number',0,'intval');
            if(!$team_number){
                $this->error('请填写团队人数');
            }
            $mouth_refund_points = input('post.mouth_refund_points',0,'intval');
            $fixed_refund_points = input('post.fixed_refund_points',0,'intval');
            $reward_state = input('param.reward_state');
            $data = [
                'team_number'         => $team_number,
                'mouth_refund_points' => $mouth_refund_points,
                'fixed_refund_points' => $fixed_refund_points,
                'reward_state'        => $reward_state
            ];
            $result = $this->tm_model->where(['reward_id'=>$reward_id])->update($data);
            if($result === false){
                $this->error('团队奖励修改失败');
            }
            $this->success('团队奖励修改成功',url('team/team_reward'));
        }
    }

    /**
     * 团队奖励状态修改
     */
    public function update_state(){
        $id = input('get.id',0,'intval');
        if(!$id){
            $this->error('参数传入错误');
        }
        $reward_state = input('get.reward_state');
        $result = $this->tm_model->where(['reward_id'=>$id])
            ->update(['reward_state'=>$reward_state]);
        if($result === false){
            $this->error('团队状态修改失败');
        }
        $this->success('团队状态修改成功');
    }

    /**
     * 团队奖励删除
     */
    public function delete(){
        $id = input('get.id',0,'intval');
        if(!$id){
            $this->error('参数传入错误');
        }
        $result = $this->tm_model->where(['reward_id'=>$id])->delete();
        if($result === false){
            $this->error('删除失败');
        }
        $this->success('删除成功');
    }

    /**
     * 财务管理团队管理奖励
     */
    public function management(){
        $param = input('param.');
        $where = ['type'=>'month_refund_points','pl_state'=>0];
        if(isset($param['member_mobile']) && !empty(trim($param['member_mobile']))){
            if(is_mobile(trim($param['member_mobile']))){
                $member_id = model('Member')
                    ->where(['member_mobile'=>trim($param['member_mobile'])])
                    ->value('member_id');
                $where['member_id'] = $member_id;
            }
        }
        $count = $this->points_model->where($where)->count();
        $freeze_points = $this->points_model->where($where)->paginate(15,$count);
        $ids = [];
        foreach($freeze_points as $k=>$v){
            $ids[] = $v['member_id'];
        }
        $member = $this->member_model
            ->where(['member_id'=>['in',$ids]])
            ->select();
        foreach($freeze_points as $k=>$v){
            foreach($member as $key=>$val){
                if($freeze_points[$k]['member_id'] == $member[$key]['member_id']){
                    $freeze_points[$k]['member_name'] = $member[$key]['member_name'];
                    $freeze_points[$k]['member_mobile'] = $member[$key]['member_mobile'];
                }
            }
        }
        $page = $freeze_points->render();
        $this->assign('freeze_points',$freeze_points);
        $this->assign('page',$page);
        return $this->fetch();
    }

    public function manage_examine(){
        $id = input('get.id');
        if(!$id){
            $this->error('参数传入错误');
        }
        $this->member_model->startTrans();
        $this->points_model->startTrans();
        $state = $this->points_model->where(['pl_id'=>$id])->update(['pl_state'=>1]);
        if($state === false){
            $this->error('状态修改失败');
        }
        $member_id = $this->points_model->where(['pl_id'=>$id])->value('member_id');
        $points = $this->points_model->where(['pl_id'=>$id])->value('points');
        $result = $this->member_model->where(['member_id'=>$member_id])->setInc('points',$points);
        if($result === false){
            $this->points_model->rollback();
            $this->error('审核失败');
        }
        $this->member_model->commit();
        $this->points_model->commit();
        $this->success('审核通过');
    }

    /**
     * 批量通过
     */
    public function agree_sum(){
        $ids = $_POST['ids'];
        $pl_log = $this->points_model->where(['pl_id'=>['in',$ids]])->field('member_id,points')->select();
        $id = [];
        foreach($pl_log as $k=>$v){
            $id[] = $v['member_id'];
        }
        $this->member_model->startTrans();
        $this->points_model->startTrans();
        $state = $this->points_model->where(['pl_id'=>['in',$ids]])->update(['pl_state'=>1]);
        if($state === false){
            $this->error('状态修改失败');
        }
        $member = $this->member_model->where(['member_id'=>['in',$id]])->select();
        foreach($pl_log as $k=>$v){
            foreach($member as $key=>$val){
                if($v['member_id'] == $val['member_id']){
                    $result = $this->member_model
                        ->where(['member_id'=>$val['member_id']])
                        ->setInc('points',$v['points']);
                    if($result === false){
                        $this->points_model->rollback();
                        $this->error('审核失败');
                    }
                }
            }
        }
        $this->member_model->commit();
        $this->points_model->commit();
        $this->success('审核通过');
    }
}