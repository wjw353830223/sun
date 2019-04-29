<?php
namespace app\admin\controller;


use Lib\Watch;

class Index extends Adminbase
{
    public function index()
    {
        $menus = model("Menu")->menu_json();
        $this->assign('menus',$menus);
        return $this->view->fetch();
    }

    public function main()
    {
        //系统参数信息
        $mysql = model('admin')->field("VERSION() as version")->find();
        $mysql = $mysql['version'];
        $mysql = empty($mysql) ? L('UNKNOWN') : $mysql;

        $data = [];
        if(ini_get('safe_mode')){
            $data['safe_mode'] = '是';
        }else{
            $data['safe_mode'] = '否';
        }
        if(ini_get('safe_mode_gid')){
            $data['safe_mode_gid'] = '是';
        }else{
            $data['safe_mode_gid'] = '否';
        }
        $data['mysql'] = $mysql;
        if(extension_loaded('sockets')){
            $data['sockets'] = '已开启';
        }else{
            $data['sockets'] = "未开启";
        }
        $data['time'] = date_default_timezone_get();
        $data['gd'] = gd_info()['GD Version'];
        $data['file'] = ini_get('upload_max_filesize');
        $data['extra'] = round((@disk_free_space(".") / (1024 * 1024)), 2) . 'M';
        $data['run_time'] = ini_get('max_execution_time') . "s";

        //今日销售总额
        $today_order_amount = model('Order')
            ->whereTime('create_time','today')
            ->where(['order_state'=>['in',[20,30]]])
            ->value('sum(order_amount) as today_order_amount');
        $data['today_order_amount'] = $today_order_amount;
        //今日订单数
        $today_order_count = model('Order')
            ->whereTime('create_time','today')
            ->where(['order_state'=>['in',[20,30]]])
            ->count();
        $data['today_order_count'] = $today_order_count;
        //今日留言数
        $message = model('Guestbook')->whereTime('createtime','today')->count();
        $data['message'] = $message;
        //店铺数量
        $store = model('Store')->count();
        $data['store'] = $store;
        //今日新增会员
        $today_member_count = model('Member')->whereTime('member_time','today')->count();
        $data['today_member_count'] = $today_member_count;
        //昨日新增会员
        $yesterday_member_count = model('Member')->whereTime('member_time','yesterday')->count();
        $data['yesterday_member_count'] = $yesterday_member_count;
        //本月新增会员
        $month_member_count = model('Member')->whereTime('member_time','month')->count();
        $data['month_member_count'] = $month_member_count;
        //会员总数
        $member_count = model('Member')->count();
        $data['member_count'] = $member_count;
        //已完成订单数
        $cancel = model('Order')->where(['order_state' => 0])->count();
        $data['cancel'] = $cancel;
        //待支付订单数
        $pay = model('Order')->where(['order_state' => 10])->count();
        $data['pay'] = $pay;
        //待收货订单数
        $wait = model('Order')->where(['order_state' => 20])->count();
        $data['wait'] = $wait;
        //已成交订单数
        $receive = model('Order')->where(['order_state' => 40])->count();
        $data['receive'] = $receive;
        //已发货订单数
        $deliver = model('Order')->where(['order_state' => 30])->count();
        $data['deliver'] = $deliver;
        $finish = model('Order')->where(['order_state' => 50])->count();
        $data['finish'] = $finish;
        //所有用户中有绑定关系的用户数量
        $count = model('Member')->count();
        //计算有健康报告的人数
        $health_member = model('MemberReport')->field('member_id')->select();
        $health_member_ids =[];
        foreach($health_member as $v){
            $health_member_ids[] = $v['member_id'];
        }
        $health_member_ids = array_unique($health_member_ids);
        $mr_count = count($health_member_ids);
        //有绑定关系的人数
        $bind_member = model('Member')->where(['parent_id'=>['gt',0]])->count();
        $member_info = model('Member')->where(['parent_id'=>['gt',0]])->select();
        $member_ids = [];
        foreach($member_info as $v){
            //所有有绑定关系的会员id
            $member_ids[] = $v['member_id'];
        }
        $bind_ids = [];
        foreach($member_info as $v){
            if(!in_array($v['parent_id'],$member_ids)){
                $bind_ids[] = $v['parent_id'];
            }
        }
        $bind_ids = array_unique($bind_ids);
        $bind_member = $bind_member + count($bind_ids);
        $bind_ids = array_merge($bind_ids,$member_ids);
        //有绑定关系的有健康报告的人数
        $have_report_num = model('MemberReport')->where(['member_id'=>['in',$bind_ids]])->count();
        //有绑定关系无健康报告的人数
        $no_report_num = $bind_member - $have_report_num;
        //无绑定关系的人数
        $no_bind_member = $count - $bind_member;
        //无健康报告的人数
        $no_mr_count = $count - $mr_count;
        //无绑定关系有健康报告的人数
        $health_count = $mr_count - $have_report_num;
        //无绑定关系无健康报告的人数
        $no_health_count = $no_mr_count - $no_report_num;
        $data['bind_member'] = $bind_member;
        $data['have_report_num'] = $have_report_num;
        $data['no_report_num'] = $no_report_num;
        $data['no_bind_member'] = $no_bind_member;
        $data['health_count'] = $health_count;
        $data['no_health_count'] = $no_health_count;
        $this->assign('data',$data);
        return $this->fetch();
    }

    public function test(){
        
    }
}