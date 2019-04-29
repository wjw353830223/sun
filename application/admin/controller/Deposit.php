<?php
namespace app\admin\controller;
class Deposit extends Adminbase
{
    protected $member_model,$pod_model;
    public function _initialize() {
        parent::_initialize();
        $this->member_model = model('Member');
        $this->pod_model = model('PdLog');
    }

    //押金列表
    public function index(){
        $param = input('get.');
        $where = [];
        $query = [];

        if(isset($param['member_mobile']) && !empty(trim($param['member_mobile'])) && is_mobile(trim($param['member_mobile']))){
            $query['member_mobile'] = trim($param['member_mobile']);
            $where['m.member_mobile'] = trim($param['member_mobile']);
        }

        if(isset($param['admin_name']) && !empty(trim($param['admin_name']))){
            $where['admin_name'] = trim($param['admin_name']);
            $query['admin_name'] = trim($param['admin_name']);
        }

        $count = $this->pod_model->alias('pd')
            ->join('Member m','m.member_id = pd.member_id')
            ->where($where)->count();
        $lists = $this->pod_model->alias('pd')
            ->join('Member m','m.member_id = pd.member_id')
            ->where($where)
            ->field('m.member_name,m.member_mobile,pd.log_id,pd.member_id,pd.admin_id,pd.admin_name,pd.type,pd.av_amount,pd.add_time,pd.log_desc')
            ->order('log_id desc')
            ->paginate(15,$count,['query'=>$query]);
        $admin = model('Admin')->select();
        $auth_role = model('AuthRole')->select();

        foreach($lists as $k=>$v){
            foreach($admin as $key=>$vo){
                if($lists[$k]['admin_id'] == $admin[$key]['admin_id']){
                    $lists[$k]['role_id'] = $admin[$key]['role_id'];
                }
                if(empty($lists[$k]['admin_id'])){
                    $lists[$k]['role_id'] = "";
                }
            }
        }
        foreach($lists as $k=>$v){
            foreach($auth_role as $key=>$vo){
                if($lists[$k]['role_id'] == $auth_role[$key]['role_id']){
                    $lists[$k]['name'] = $auth_role[$key]['name'];
                }
                if(empty($lists[$k]['role_id'])){
                    $lists[$k]['name'] = "";
                }
            }
        }

        $tmp = array();
        foreach($lists as $key=>$val){
            $tmp[] = $val['member_id'];
            switch (strtolower($val['type'])) {
                case 'recharge':
                case 'points_to_cash':
                case 'order_cancel':
                case 'cash_fail':
                    $lists[$key]['type'] = '+';
                    break;
                case 'refund':
                case 'order_pay':
                case 'cash_withdrawn':
                    $lists[$key]['type'] = '-';
                    break;
                default:
                    break;
            }
        }

        $member_id = implode(',',$tmp);
        unset($tmp);

        $member_info = $this->member_model->where(['member_id'=>['in',$member_id]])
            ->field('member_id,member_name,member_mobile')->select();

        foreach($lists as $key=>$val){
            foreach($member_info as $info_key=>$info_val){
                if($val['member_id'] == $info_val['member_id']){
                    $lists[$key]['member_name'] = $info_val['member_name'];
                    $lists[$key]['member_mobile'] = $info_val['member_mobile'];
                    break;
                }
            }
        }
        //获取所有销售员姓名
        $seller = model('StoreSeller')->field('member_id,seller_name')->select();
        foreach($lists as $key=>$val){
            foreach($seller as $k=>$v){
                if($val['member_id'] == $v['member_id']){
                    $lists[$key]['seller_name'] = $v['seller_name'];
                    break;
                }else{
                    $lists[$key]['seller_name'] = "";
                }
            }
        }

        $page = $lists->render();
        $this->assign('page',$page);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    public function recharge_add(){
        return $this->fetch();
    }
    //充值管理提交
    public function recharge_add_post(){
        $param = input('post.');
        $member_mobile = trim($param['member_mobile']);
        if(empty($member_mobile) && !is_mobile($member_mobile)){
            $this->error('会员账号不正确');
        }

        //判断会员是否存在
        $member_info = $this->member_model
            ->where(['member_mobile'=>$member_mobile])
            ->field('member_id,member_mobile,av_predeposit')
            ->find();
        if(empty($member_info)){
            $this->error('该会员不存在');
        }

        if($param['points'] < 0 && empty($param['points'])){
            $this->error('充值金额有误');
        }

        $pod_data = [
            'member_id'     => $member_info['member_id'],
            'member_mobile' => $member_mobile,
            'admin_name'    => session('name'),
            'admin_id'      => session('ADMIN_ID'),
            'av_amount'     => $param['points'],
            'add_time'      => time(),
        ];
        $log_desc = strip_tags($param['log_desc']);
        if(!empty($log_desc)){
            $pod_data['log_desc'] = $log_desc;
        }

        $this->member_model->startTrans();
        $this->pod_model->startTrans();
        $has_info = $this->pod_model->where(['member_id'=>$member_info['member_id']])->count();

        if($param['updown'] > 0){
            $result = $this->member_model->where(['member_id'=>$member_info['member_id']])->setInc('av_predeposit',$param['points']);
        }

        if($param['updown'] == 0){
            if($param['points'] > $member_info['av_predeposit']){
                $this->error('当前余额不足');
            }
            $result = $this->member_model->where(['member_id'=>$member_info['member_id']])->setDec('av_predeposit',$param['points']);
        }

        if($result === false){
            $this->error('操作失败');
        }

        $type = $param['updown'] > 0 ? 'recharge' : 'refund';
        $pod_data['type'] = $type;
        $log_result = $this->pod_model->save($pod_data);
        if($log_result === false){
            $this->member_model->rollback();
            $this->error('操作失败');
        }

        $this->member_model->commit();
        $this->pod_model->commit();
        $this->success('操作成功',url('deposit/index'));
    }
}