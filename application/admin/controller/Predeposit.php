<?php
namespace app\admin\controller;
use app\common\model\PdCash;
use JPush\Client;
use JPush\Exceptions\JPushException;

class Predeposit extends Adminbase
{
    protected $pdLog_model,$pdCash_model,$message,$member_model,$appKey,$masterSecret,$member_auth_model;
    public function _initialize() {
        parent::_initialize();
        $this->pdLog_model = model('PdLog');
        $this->pdCash_model = model('PdCash');
        $this->message = model('Message');
        $this->member_model = model('Member');
        $this->member_auth_model = model('MemberAuth');
        $this->appKey = '25b3a2a3df4d0d26b55ea031';
        $this->masterSecret = '3efc28f3eb342950783754f2';
    }

    //产品列表
    public function index(){
        if(request()->isGet()){
            $where = array();
            $start_time = input('get.start_time');
            if(!empty($start_time)){
                $start_time = strtotime($start_time);
                $where['lg_add_time'] = ['egt',$start_time];
            }

            $end_time = input('get.end_time');
            if(!empty($end_time)){
                $end_time = strtotime($end_time)+86400;
                $where['lg_add_time'] = ['lt',$end_time];
            }

            $end_time = input('get.end_time');
            if(!empty($start_time) && !empty($end_time)){
                $over_time = strtotime($end_time)+86400;
                $where['lg_add_time'] = [['egt',$start_time],['lt',$over_time]];
            }
            //会员名称
            $member_name = input('get.member_name');
            if(!empty($member_name)){
                $where['lg_member_name'] = $member_name;
            }

            //操作管理员
            $admin_name = input('get.admin_name');
            if(!empty($admin_name)){
                $where['lg_admin_name'] = $admin_name;
            }
        }

        $count = $this->pdLog_model->where($where)->count();
        $lists = $this->pdLog_model->where($where)->order(['lg_add_time'=>'DESC'])->paginate(20,$count);
        $this->assign('lists',$lists);
        $page = $lists->render();
        $this->assign('page',$page);
        return $this->fetch();
    }


    //提现列表
    public function cash_list(){
        $where = $query =  [];
        if(request()->isGet()){
            //会员名称
            $member_mobile = input('get.member_mobile');
            if(isset($member_mobile) && !empty($member_mobile) && is_mobile($member_mobile)){
                $query['member_mobile'] = $member_mobile;
                $member_id = $this->member_model->where(['member_mobile'=>$member_mobile])->value('member_id');
                $where['pdc_member_id'] = $member_id;
            }

            $start_time = input('get.start_time');
            if(isset($start_time) && !empty($start_time)){
                $query['start_time'] = $start_time;
                $start_time = strtotime($start_time);
                $where['pdc_add_time'] = ['egt',$start_time];
            }

            $end_time = input('get.end_time');
            if(isset($end_time) && !empty($end_time)){
                $query['end_time'] = $end_time;
                $end_time = strtotime($end_time);
                $where['pdc_add_time'] = ['lt',$end_time];
            }

            if(isset($start_time) && isset($end_time) && !empty($start_time) && !empty($end_time)){
                $query['start_time'] = $start_time;
                $query['end_time'] = $end_time;
                $start_time = strtotime($start_time);
                $over_time = strtotime($end_time);
                $where['pdc_add_time'] = [['egt',$start_time],['lt',$over_time]];
            }

            //支付状态
            $pay_state = input('get.pay_state','','intval');
            if(isset($pay_state) && strlen($pay_state) > 0){
                $query['pdc_payment_state'] = $pay_state;
                $where['pdc_payment_state'] = $pay_state;
            }
        }
        $count = $this->pdCash_model->where($where)->count();
        $lists = $this->pdCash_model->where($where)
            ->order(['pdc_add_time'=>'DESC'])
            ->paginate(20,$count,['query'=>$query]);
        $ids = [];
        foreach($lists as $key=>$val){
            $ids[] = $val['pdc_member_id'];
        }
        $member = $this->member_model->where(['member_id'=>['in',$ids]])->field('member_id,member_mobile,member_name')->select();
        foreach($lists as $k=>$v){
            foreach($member as $key=>$val){
                if($val['member_id'] == $v['pdc_member_id']){
                    $lists[$k]['member_mobile'] = $member[$key]['member_mobile'];
                    $lists[$k]['pdc_member_name'] = $member[$key]['member_name'];
                }
            }
        }
        $admin_ids = [];
        foreach($lists as $key=>$val){
            $admin_ids[] = $val['pdc_payment_admin'];
        }
        $admin = model('Admin')
            ->where(['admin_id'=>['in',$admin_ids]])
            ->field('admin_name,admin_id')
            ->select();
        foreach($lists as $k=>$v){
            foreach($admin as $key=>$val){
                if($val['admin_id'] == $v['pdc_payment_admin']){
                    $lists[$k]['admin_name'] = $admin[$key]['admin_name'];
                }
            }
        }
        $page = $lists->render();
        $this->assign('lists',$lists);
        $this->assign('page',$page);
        return $this->fetch();
    }

    /**
     * 确认支付提现的金额
     */
    public function cash_pay(){
        $pdc_id = input('get.pdc_id');
        if(empty($pdc_id)){
            $this->error('参数传入错误');
        }
        $data = [
            'pdc_payment_time'  => time(),
            'pdc_payment_state'  => 1,
            'pdc_payment_admin' => intval(session('ADMIN_ID'))
        ];
        $pdc_data = $this->pdCash_model->where(['pdc_id'=>$pdc_id])->value('pdc_data');
        $amount = json_decode($pdc_data,'true')['amount'];
        $this->pdCash_model->startTrans();
        $this->message->startTrans();
        model('PdLog')->startTrans();
        $res = $this->pdCash_model->where(['pdc_id'=>$pdc_id])->update($data);
        if($res === false){
            $this->error('支付失败');
        }
        $member = $this->pdCash_model->where(['pdc_id'=>$pdc_id])->find();
        $member_mobile = model('Member')->where(['member_id'=>$member['pdc_member_id']])->value('member_mobile');
        $client = new Client($this->appKey,$this->masterSecret);
        $push = $client->push();
        $cid = $push->getCid();
        $cid = $cid['body']['cidlist'][0];
        $push_detail = '您账户的'.$amount.'元已提现成功';
        try{
            $res = $push->setCid($cid)
                ->options(['apns_production'=>true])
                ->setPlatform(['android','ios'])
                ->addAlias((string)$member_mobile)
                ->iosNotification($push_detail,['title'=>'提现通知'])
                ->androidNotification($push_detail,['title'=>'提现通知'])
                ->send();
        }catch(JPushException $e){

        }
        $body = json_encode(
            ['title'=> '提现成功','points'=>'-'.$amount]
        );
        $message = [
            'from_member_id' => 0,
            'to_member_id'   => $member['pdc_member_id'],
            'message_title'  => '提现通知',
            'message_body'   => $body,
            'message_time'   => time(),
            'message_state'  => 0,
            'message_type'   => 4,
            'is_more'        => 0,
            'is_push'        => 2,
            'push_title'     => '',
            'push_detail'    => '您账户的'.$amount.'元已提现成功',
            'push_member_id' => $member['pdc_member_id']
        ];
        $message_res = $this->message->save($message);
        if($message_res === false){
            $this->pdCash_model->rollback();
            $this->error('消息发送失败');
        }
        $pl_data = json_decode($member['pdc_data'],true);
        $pd_log = [
            'member_id'     => $member['pdc_member_id'],
            'member_mobile' => $member_mobile,
            'av_amount'     => $pl_data['amount'],
            'type'          => 'cash_withdrawn',
            'add_time'      => time(),
            'log_desc'      => '余额转出',
            'pd_data'       => $member['pdc_data']
        ];
        $pd = model('PdLog')->save($pd_log);
        if($pd === false){
            $this->member_model->rollback();
            $this->pdCash_model->rollback();
            $this->error('支付失败');
        }
        $this->pdCash_model->commit();
        $this->message->commit();
        model('PdLog')->commit();
        $this->success('支付成功');
    }
    /**
     * 确认支付提现的金额(银联代付)
     */
    public function cash_union_pay(){
        $pdc_id = input('get.pdc_id');
        if(empty($pdc_id)){
            $this->error('参数传入错误');
        }
        $pd_cash_info = $this->pdCash_model->where(['pdc_id'=>$pdc_id])->find();
        if(empty($pd_cash_info)){
            $this->error('没有该提现记录');
        }
        if($pd_cash_info['pdc_payment_state'] == PdCash::PDC_PAYMENT_STATE_PROCESSING){
            $this->error('提现处理中请勿重复提交');
        }
        $time = time();
        $data = [
            'pdc_payment_state' => PdCash::PDC_PAYMENT_STATE_PROCESSING,
            'pdc_payment_time' => $time,
            'pdc_payment_admin' => intval(session('ADMIN_ID')),
        ];
        if(!$this->pdCash_model->pdcash_update($pd_cash_info['pdc_id'],$data)){
            $this->error('更改提现记录状态出错');
        }
        $back_url =  $this->base_url . '/open/unipay_notify/notify';
        $txn_time =  date('YmdHis', $time);
        $member_auth = $this->member_auth_model->member_auth_last_verify($pd_cash_info['pdc_member_id']);
        $card_id = $member_auth['id_card'];
        $res = $this->pdCash_model->unipay_withdraw($card_id,$pd_cash_info['pdc_bank_no'],$pd_cash_info['pdc_sn'],$back_url,$pd_cash_info['pdc_bank_user'],$pd_cash_info['pdc_amount'],$txn_time);
        if($res['state'] !=='success'){
            $this->error($res['msg']);
        }
        $this->success('已向银联发起代付请求，请稍后刷新查看结果');
    }

    /**
     * 资金转出失败
     */
    public function cash_fail(){
        $pdc_id = input('get.pdc_id');
        if(empty($pdc_id)){
            $this->error('参数传入错误');
        }
        $cash = $this->pdCash_model->where(['pdc_id'=>$pdc_id])->find();
        if(empty($cash)){
            $this->error('没有该提现记录');
        }
        if($cash['pdc_payment_state'] == PdCash::PDC_PAYMENT_STATE_PROCESSING){
            $this->error('提现处理中请勿重复提交');
        }
        $data = [
            'pdc_payment_state'  => PdCash::PDC_PAYMENT_STATE_FAIL,
            'pdc_payment_admin' => intval(session('ADMIN_ID'))
        ];
        $pdc_data =$cash['pdc_data'];
        $amount = json_decode($pdc_data,'true')['amount'];
        $this->pdCash_model->startTrans();
        $this->message->startTrans();
        $this->member_model->startTrans();
        model('PdLog')->startTrans();
        $res = $this->pdCash_model->where(['pdc_id'=>$pdc_id])->update($data);
        if($res === false){
            $this->error('状态修改失败');
        }
        $member_mobile = model('Member')->where(['member_id'=>$cash['pdc_member_id']])->value('member_mobile');
        $client = new Client($this->appKey,$this->masterSecret);
        $push = $client->push();
        $cid = $push->getCid();
        $cid = $cid['body']['cidlist'][0];
        $push_detail = '您的余额转出失败，请重新申请操作。';
        try{
            $res = $push->setCid($cid)
                ->options(['apns_production'=>true])
                ->setPlatform(['android','ios'])
                ->addAlias((string)$member_mobile)
                ->iosNotification($push_detail,['title'=>'提现通知'])
                ->androidNotification($push_detail,['title'=>'提现通知'])
                ->send();
        }catch (\Exception $e){
            $this->pdCash_model->rollback();
            $this->error('推送出错：'.$e->getMessage());
        }
        if($res['http_code'] == 200){
            $body = json_encode(
                ['title'=> '提现失败','points'=>'+'.$amount]
            );
            $message = [
                'from_member_id' => 0,
                'to_member_id'   => $cash['pdc_member_id'],
                'message_title'  => '提现通知',
                'message_body'   => $body,
                'message_time'   => time(),
                'message_state'  => 0,
                'message_type'   => 4,
                'is_more'        => 0,
                'is_push'        => 2,
                'push_detail'    => '您的余额转出失败，请重新申请操作。',
                'push_title'     => '',
                'push_member_id' => $cash['pdc_member_id']
            ];
            $message_res = $this->message->save($message);
            if($message_res === false){
                $this->pdCash_model->rollback();
                $this->error('消息发送失败');
            }
        }else{
            $this->error('消息推送失败');
        }

        $pdc_data = json_decode($cash['pdc_data'],true);
        $pd_cash = $this->member_model
            ->where(['member_id'=>$cash['pdc_member_id']])
            ->setInc('av_predeposit',$pdc_data['amount']);
        if($pd_cash === false){
            $this->pdCash_model->rollback();
            $this->message->rollback();
            $this->error('余额退回失败');
        }
        $member_mobile = $this->member_model->where(['member_id'=>$cash['pdc_member_id']])->value('member_mobile');
        $pd_log = [
            'member_id'     => $cash['pdc_member_id'],
            'member_mobile' => $member_mobile,
            'av_amount'     => $pdc_data['amount'],
            'type'          => 'cash_fail',
            'add_time'      => time(),
            'log_desc'      => '余额转出失败'
        ];
        $pd = model('PdLog')->save($pd_log);
        if($pd === false){
            $this->member_model->rollback();
            $this->pdCash_model->rollback();
            $this->error('余额转出失败');
        }
        $this->pdCash_model->commit();
        $this->message->commit();
        $this->member_model->commit();
        model('PdLog')->commit();
        $this->success('确认转出失败');
    }

    //产品编辑
    public function edit(){
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
            $result = $this->ver_model->allowField(true)->save($param,array('version_id' => $param['version_id']));
            if ($result!==false) {
                $this->success("版本修改成功！",url('version/index'));
            } else {
                $this->error('版本修改失败！');
            }
        }
    }

    //产品添加
    public function add(){
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
            $result = $this->ver_model->destroy(array('version_id' => $ver_id));
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
}