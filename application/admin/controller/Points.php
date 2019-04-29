<?php
namespace app\admin\controller;

use think\Hook;

class Points extends Adminbase
{
    public function index(){
        $where = [];
        $query = [];
        if(request()->isGet()){
            $param = input('get.');
            if(isset($param['member_mobile']) && !empty(trim($param['member_mobile'])) && is_mobile(trim($param['member_mobile']))){
                $where['m.member_mobile'] = trim($param['member_mobile']);
                $query['member_mobile'] = trim($param['member_mobile']);
            }
            if(!empty($param['start_time'])){
                $start_time = strtotime($param['start_time']);
                $query['start_time'] = strtotime($param['start_time']);
                $where['add_time'] = ['egt',$start_time];
            }
            if(!empty($param['end_time'])){
                $end_time = strtotime($param['end_time']);
                $query['end_time'] = strtotime($param['end_time']);
                $where['add_time'] = ['lt',$end_time];
            }
            if(!empty($param['start_time']) && !empty($param['end_time'])){
                $start_time = strtotime($param['start_time']);
                $query['start_time'] = strtotime($param['start_time']);
                $end_time = strtotime($param['end_time']);
                $query['end_time'] = strtotime($param['end_time']);
                $where['add_time'] = [['egt',$start_time],['lt',$end_time]];
            }
            if(isset($param['pl_stage']) && !empty(trim($param['pl_stage']))){
                $query['pl_stage'] = trim($param['pl_stage']);
                $where['pl_stage'] = trim($param['pl_stage']);
            }
            if(isset($param['admin_name']) && !empty(trim($param['admin_name']))){
                $query['admin_name'] = trim($param['admin_name']);
                $where['admin_name'] = trim($param['admin_name']);
            }
        }
        $count = model('PointsLog')->alias('pl')
            ->join('Member m','pl.member_id = m.member_id')
            ->where($where)
            ->count();
        $points = model('PointsLog')->alias('pl')
            ->join('Member m','pl.member_id = m.member_id')
            ->where($where)
            ->field('pl.pl_id,pl.points,pl.member_id,pl.admin_id,admin_name,pl.add_time,pl.pl_stage,pl.pl_desc,pl.type,m.member_name,m.member_mobile')
            ->order('pl_id desc')
            ->paginate(15,$count,['query'=>$query]);
        $admin = model('Admin')->select();
        $auth_role = model('AuthRole')->select();
        foreach($points as $k=>$v){
            foreach($admin as $key=>$vo){
                if($points[$k]['admin_id'] == $admin[$key]['admin_id']){
                    $points[$k]['role_id'] = $admin[$key]['role_id'];
                }
                if(empty($points[$k]['admin_id'])){
                    $points[$k]['role_id'] = "";
                }
            }
        }
        foreach($points as $k=>$v){
            foreach($auth_role as $key=>$vo){
                if($points[$k]['role_id'] == $auth_role[$key]['role_id']){
                    $points[$k]['name'] = $auth_role[$key]['name'];
                }
                if(empty($points[$k]['role_id'])){
                    $points[$k]['name'] = "";
                }
            }
        }
        $member = model('PointsLog')->field('member_id')->select();
        $tmp = [];
        foreach ($member as $v){
            $tmp[] = $v['member_id'];
        }
        $member_name = model('Member')->where(['member_id'=>['in',$tmp]])
            ->field('member_id,member_name,member_mobile')->select();
        foreach ($points as $k=>$v){
            foreach($member_name as $key=>$vo){
                if($v['member_id'] == $vo['member_id']){
                    $points[$k]['member_name'] = $member_name[$key]['member_name'];
                    $points[$k]['member_mobile'] = $member_name[$key]['member_mobile'];
                    break;
                }
            }
        }
        foreach($points as $k=>$v){
            switch (strtolower($v['type'])) {
                case 'admin_del':
                case 'order_pay':
                case 'order_cancel_freeze':
                case 'order_refund_return':
                case 'order_add_freeze':
                case 'order_cancel_parent_freeze':
                case 'points_to_cash':
                case 'parterner_imbanlance_reduce':
                    $v['points'] = "-".$v['points'];
                    break;
                default:
                    break;
            }
        }
        $page = $points->render();
        $this->assign('page',$page);
        $this->assign('points',$points);
        return $this->fetch();
    }

    public function points_option(){
        if(request()->isGet()){
            return $this->fetch();
        }
        if(request()->isPost()){
            $param = input('post.');
            $data = [];
            $member_mobile = trim($param['member_mobile']);
            if(!is_mobile($member_mobile)){
                $this->error('会员账号填写错误');
            }
            $member_info = model('Member')->where(['member_mobile'=>$member_mobile])->find();
            if(!$member_info){
                $this->error('该会员不存在，请重新填写');
            }
            $data['member_id'] = $member_info['member_id'];
            $points = trim($param['points']);
            if($points < 0 || !$points){
                $this->error("积分数值有误");
            }
            if($param['type'] == "admin_add"){
                $data['points'] = $points;
            }
            if($param['type'] == "admin_del"){
                $data['points'] = $points;
            }
            $data['type'] = $param['type'];
            $data['member_mobile'] = $member_mobile;
            $data['pl_desc'] = trim($param['pl_desc']);
            $data['admin_name'] = session('name');
            $data['admin_id'] = session('ADMIN_ID');
            $data['add_time'] = time();
            $data['pl_stage'] = "system";
            model('Member')->startTrans();
            model('PointsLog')->startTrans();
            if($param['type'] == "admin_add") {
                $member = model('Member')->where(['member_id'=>$member_info['member_id']])->setInc("points",$points);
            }
            if($param['type'] == "admin_del"){
                if($member_info['points'] >= $points){
                    $member = model('Member')->where(['member_id'=>$member_info['member_id']])->setDec("points",$points);
                }else{
                    $this->error('积分不足，不能扣除');
                }
            }
            if(!$member){
                $this->error('积分操作失败');
            }
            $pid = model('PointsLog')->insertGetId($data);
            if(!$pid){
                model('Member')->rollback();
                $this->error('积分操作日志记录失败');
            }
            model('Member')->commit();
            model('PointsLog')->commit();
            if(!empty($data)){
                Hook::listen('create_points_log',$data);
            }
            $this->success('操作成功','admin/points/index');
        }
    }
}