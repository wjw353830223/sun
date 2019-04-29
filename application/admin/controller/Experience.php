<?php

namespace app\admin\controller;


class Experience extends Adminbase
{
    public function index(){
        $where = [];
        $query = [];
        if(request()->isGet()){
            $param = input('param.');
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
        }
        $count = model('ExperienceLog')->alias('el')
            ->join('Member m','el.member_id = m.member_id')
            ->where($where)->count();
        $experience = model('ExperienceLog')->alias('el')
            ->join('Member m','el.member_id = m.member_id')
            ->where($where)->field('el.ex_id,el.member_id,el.type,el.experience,el.add_time,el.ex_desc,m.member_name,m.member_mobile')

            ->order('ex_id desc')
            ->paginate(15,$count,['query'=>$query]);
        $member = model('ExperienceLog')->field('member_id')->select();
        $tmp = [];
        foreach($member as $v){
            $tmp[] = $v['member_id'];
        }
        $member_name = model('Member')->where(['member_id'=>['in',$tmp]])->select();
        foreach($experience as $k=>$v){
            foreach($member_name as $key=>$vo){
                if($v['member_id'] == $vo['member_id']){
                    $experience[$k]['member_name'] = $member_name[$key]['member_name'];
                    $experience[$k]['member_mobile'] = $member_name[$key]['member_mobile'];
                    break;
                }
            }
        }
        $page = $experience->render();
        $this->assign('page',$page);
        $this->assign('experience',$experience);
        return $this->fetch();
    }
}