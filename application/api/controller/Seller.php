<?php
namespace app\api\controller;
use Lib\Qrcode;
use think\Cache;
class Seller extends Apibase
{
    protected $store_seller,$report_model,$mq_model,$member_model,$order_model,$tr_model,$team_model;
    protected $pl_model,$seller_model,$sm_model,$grade_model;
	protected function _initialize(){
    	parent::_initialize();
    	$this->seller_model = model('StoreSeller');
        $this->sm_model = model('SellerMember');
        $this->mq_model = model('MemberQrcode');
        $this->member_model = model('Member');
        $this->order_model = model('Order');
        $this->grade_model = model('Grade');
        $this->tr_model = model('TeamReward');
        $this->team_model = model('Team');
        $this->pl_model = model('PointsLog');
    }

    /**
    * 生成二维码
    */
    public function create_qrcode(){
        //获取二维码
        $has_info = $this->mq_model->where(['member_id'=>$this->member_info['member_id']])->find();

        $member_avatar = $this->member_model->where(['member_id'=>$this->member_info['member_id']])->value('member_avatar');
        $member_avatar = strexists($member_avatar,'http') ? $member_avatar : $this->base_url.'/uploads/avatar/'.$member_avatar;
        $data = [
            'member_id' => $this->member_info['member_id'],
            'member_mobile' => $this->member_info['member_mobile'],
            'member_avatar' => $member_avatar,
            'member_name'   => $this->member_info['member_name']
        ];

        if(count($has_info) < 1){
            $result = $this->mq_model->create_qrcode($data,1);
        }else{
            $last_time = time() - $has_info['add_time'];
            if($last_time > 5184000){
                $result = $this->mq_model->create_qrcode($data,0,$has_info['qrcode']);
            }
        }

        if(isset($result) && empty($result)){
            $this->ajax_return('11710','failed to create qrcode');
        }

        $qrcode = !empty($result) ? $this->base_url.'/qrcode/'.$result : $this->base_url.'/qrcode/'.$has_info['qrcode'];
        $data['qrcode'] = $qrcode;

        $seller_data = [
            'seller_name' => '',
            'sell_amount' => '0.00',
            'income' => '0.00'
        ];
        $seller_state = ['1','3'];
        $member_id = $this->member_info['member_id'];
        $seller_info = $this->seller_model->where(['member_id'=>$member_id,'seller_state'=>2])->field('seller_name,seller_role,invite_code,qrcode')->find();

        if(!empty($seller_info)){
            //销售业绩
            $sell_amount = model('order')->where(['referee_id'=>$member_id,'order_state'=>['egt',40]])->sum('order_amount');
            //指定会员(18917890618)刘建刚 业绩收入
            $sell_amount = $this->member_info['member_id'] == 3 ? '3605880' : $sell_amount;
            $income = $this->member_info['member_id'] == 3 ? '824000' : 0;
            $seller_data = [
                'seller_name' => $seller_info['seller_name'],
                'sell_amount' => $sell_amount,
                'income' => $income
            ];
        }
        
        $seller_data['qrcode'] = $qrcode;
        $this->ajax_return('200','success',$seller_data);
    }


    
    /**
    * 申请成为销售商
    */
    public function apply_seller(){
        $true_name = input('post.true_name','','trim');
        if(empty($true_name) || words_length($true_name) < 2 || words_length($true_name) > 8){
            $this->ajax_return('10510','invalid true_name');
        }
        
        $member_id = $this->member_info['member_id'];
        $mobile = model('member')->where(['member_id'=>$member_id])->value('member_mobile');

        $has_info = $this->seller_model->where(['member_id'=>$member_id])->count();
        if($has_info > 0){
            $this->ajax_return('10511','seller has been exist');
        }

        $id_card = input('post.id_card','','trim');
        if(empty($id_card) || !validation_filter_id_card($id_card)){
            $this->ajax_return('10512','invalid id_card');
        }

        $has_info = model('StoreSeller')->where(['id_card'=>$id_card])->count();
        if($has_info > 0){
            $this->ajax_return('10517','id_card has been exist');
        }
        
        $seller_data = array();
        $invite_code = input('post.invite_code','','trim');
        if(!empty($invite_code)){
            //邀请码为当前会员
            if($invite_code == $this->member_info['member_mobile']){
                $this->ajax_return('10518','invalid invite_code');
            }

            $seller_id = $this->seller_model->where('invite_code|seller_mobile','eq',$invite_code)->value('seller_id');
            if(empty($seller_id)){
                $this->ajax_return('10519','the seller is empty');
            }
            $seller_data['parent_id'] = $seller_id;
        }

        $files = request()->file();
        if(empty($files) || count($files) != 2){
            $this->ajax_return('10514','exist id_card is empty');
        }

        
        foreach($files as $file_key=>$file_val){
            // 移动到框架应用根目录/public/uploads/ 目录下
            $save_path = 'public/uploads/seller';
            $info = $file_val->rule('uniqid')->validate(['size'=>1048576,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.$save_path.'/',true,false);
            if(!$info){
                $this->ajax_return('10515',$file_val->getError());
            }

            $seller_data[$file_key] = $info->getFilename();
        }

        $seller_data['seller_name'] = $true_name;
        $seller_data['seller_mobile'] = $mobile;
        $seller_data['id_card'] = $id_card;
        $seller_data['member_id'] = $member_id;
        $seller_data['store_id'] = 1;
        $seller_data['seller_state'] = 1;
        $seller_data['apply_time'] = time();

        $result = $this->seller_model->save($seller_data);
        if($result === false){
            $this->ajax_return('10516','failed to save data');
        }
        
        $this->ajax_return('200','success');
    }

   

    /**
    * 潜在客户报备
    */
    public function add_custom(){
        $member_id = $this->member_info['member_id'];
        $seller_info = $this->seller_model->where(['member_id'=>$member_id,'seller_state'=>2])->field('seller_name,seller_id,seller_mobile')->find();
        if(empty($seller_info)){
            $this->ajax_return('10520','memeber is not seller or state wrong');
        }

        $customer_name = strip_tags(input('post.customer_name','','trim'));
        if(empty($customer_name) || words_length($customer_name) < 2 || words_length($customer_name) > 8){
            $this->ajax_return('10521','invalid customer_name');
        }

        $customer_mobile = input('post.customer_mobile','','trim');
        if(empty($customer_mobile) || !is_mobile($customer_mobile)){
            $this->ajax_return('10522','invalid customer_mobile');
        }    

        if($customer_mobile == $seller_info['seller_mobile']){
            $this->ajax_return('10523','invalid mobile');
        }
        // $has_info = $this->sm_model->where(['customer_mobile'=>$customer_mobile])->value('customer_state');
        // $customer_state = ['1','2'];
        // if(!empty($has_info) && in_array($has_info, $customer_state)){
        //     $this->ajax_return('10523','member has been binded');
        // }
        $has_info = $this->sm_model->where(['customer_mobile'=>$customer_mobile])->field('customer_state,add_time')->find();
        $time_30 = (time()-$has_info['add_time']) > 2592000 ? 1 : 0;
        if(!empty($has_info) && $time_30){
            $set_state = $this->sm_model->where(['customer_mobile'=>$customer_mobile])->setField('customer_state',0);
            if(!$set_state){
                $this->ajax_return('10527','member state set wrong');
            }
        }

        if(!empty($has_info) && $time_30 < 1){
            $this->ajax_return('10528','member has been reported');
        }

        $product_intent = strip_tags(input('post.product_intent','','trim'));
        if(words_length($product_intent) < 2 || words_length($product_intent) > 20){
            $this->ajax_return('10524','invalid product_intent');
        }

        $remarks = strip_tags(input('post.remarks','','trim'));
        if(!empty($remarks)){
            if(words_length($remarks) < 2 || words_length($remarks) > 20){
                $this->ajax_return('10525','invalid remarks');
            }
        }

        //判断报备用户是否已存在
        $is_has = model('member')->where(['member_mobile'=>$customer_mobile])->value('member_id');
        $sm_data = [
            'seller_id' => $seller_info['seller_id'],
            'customer_name' => $customer_name,
            'customer_mobile' => $customer_mobile,
            'product_intent' => $product_intent,
            'remarks' => $remarks,
            'is_member' => !empty($is_has) ? 1 : 0,
            'member_id' => !empty($is_has) ? $is_has : 0,
            'customer_state' => 1,
            'add_time' => time()
        ];

        $sm_result = $this->sm_model->insertGetId($sm_data);
        if($sm_result === false){
            $this->ajax_return('10526','failed to add data');
        }

        $lg_data = [
            'lg_sm_id' => $sm_result,
            'lg_type' => 'customer_report',
            'lg_add_time' => time(),
            'lg_desc' => '报备：销售员'.$seller_info['seller_name'].'与用户'.$customer_name.',已报备,'.date('Y-m-d H:i:s',time())
        ];

        $lg_result = model('SellerLog')->save($lg_data);
        if($lg_result === false){
            $this->ajax_return('10526','failed to add data');
        }

        $this->ajax_return('200','success');
    }

    /**
    * 客户列表
    */
    public function custom_list(){
        $member_id = $this->member_info['member_id'];
        $seller_id = $this->seller_model->where(['member_id'=>$member_id,'seller_state'=>2])->value('seller_id');
        if(empty($seller_id)){
            $this->ajax_return('10530','invalid seller_id or state wrong');
        }

        $customer_state = ['1','2'];
        $sm_data = $this->sm_model->where(['seller_id'=>$seller_id,'customer_state'=>['in',$customer_state]])->field('is_member,customer_name,sm_id,customer_state')->select();
        if(empty($sm_data)){
            $this->ajax_return('10531','empty sm_data');
        }

        $this->ajax_return('200','success',$sm_data);
    }

    /**
    * 潜在客户列表
    */
    public function potential_custom_list(){
        $member_id = $this->member_info['member_id'];
        $seller_id = $this->seller_model->where(['member_id'=>$member_id,'seller_state'=>2])->value('seller_id');
        if(empty($seller_id)){
            $this->ajax_return('10540','invalid seller_id or state wrong');
        }

        $sm_data = $this->sm_model->where(['seller_id'=>$seller_id,'customer_state'=>1])->field('is_member,customer_name,sm_id,customer_state')->select();
        if(empty($sm_data)){
            $this->ajax_return('10541','empty sm_data');
        }

        $this->ajax_return('200','success',$sm_data);
    }

     /**
    * 客户详情
    */
    public function custom_detail(){
        $sm_id = input('post.sm_id',0,'intval');
        $customer_state = input('post.customer_state',0,'intval');
        if(empty($sm_id)){
            $this->ajax_return('10550','invalid sm_id');
        }

        $customer_info = $this->sm_model->where(['sm_id'=>$sm_id])->field('member_id,customer_name,customer_mobile,product_intent,remarks,is_member')->find();
        if(empty($customer_info)){
            $this->ajax_return('10551','empty customer_info');
        }

        //报备客户
        if($customer_state == 1){
            $customer_data = [
                'customer_name' => $customer_info['customer_name'],
                'customer_mobile' => $customer_info['customer_mobile'],
                'product_intent' => $customer_info['product_intent'],
                'remarks' => $customer_info['remarks'],
                'is_member' => 0,
                'money_amount' => 0
            ];
        }

        //绑定客户
        if($customer_state == 2){
            // $member_data = model('member')->where(['member_id'=>$customer_info['member_id']])->field('member_name,member_mobile')->find();
            // if(empty($member_data)){
            //     $this->ajax_return('10552','empty member_data');
            // }
            $order_state = ['40','50'];
            $money_amount = model('order')->where(['buyer_id'=>$customer_info['member_id'],'order_state'=>['in',$order_state]])->sum('order_amount');
            $customer_data = [
                'customer_name' => $customer_info['customer_name'],
                'customer_mobile' => $customer_info['customer_mobile'],
                'product_intent' => '',
                'remarks' => '',
                'is_member' => 1,
                'money_amount' => !empty($money_amount) ? $money_amount : 0
            ];
        }

        $this->ajax_return('200','success',$customer_data);
    }

    /**
    * 团队详情
    */
    public function custom_team(){
        $member_id = $this->member_info['member_id'];
        $seller_info = $this->seller_model->where(['member_id'=>$member_id,'seller_state'=>2])->field('seller_id,invite_code,seller_name')->find();
        if(empty($seller_info)){
            $this->ajax_return('10560','invalid seller or state wrong');
        }

        $seller_data = array();

        //团队人数
        $team_num = $this->seller_model->where(['parent_id'=>$seller_info['seller_id'],'seller_state'=>2])->count();

        //团队成员信息
        $team_member_info = $this->seller_model->where(['parent_id'=>$seller_info['seller_id'],'seller_state'=>2])->field('member_id,seller_name')->select()->toArray();
        
        if(!empty($team_member_info)){
            $team_member_id = array();
            foreach($team_member_info as $info_key=>$info_val){
                $tmp = array();
                $team_member_id[] = $info_val['member_id'];
            }
            // $team_member_id = array_merge($team_member_id,$seller_info['seller_id']);
            $team_member_arr = implode(',',$team_member_id);

            $order_state = ['40','50'];
            $order_amount = model('order')->where(['referee_id'=>['in',$team_member_arr],'order_state'=>['gt',30]])->field('referee_id,sum(order_amount) as order_amount')->group('referee_id')->select()->toArray();
            if(!empty($order_amount)){
                foreach ($team_member_info as $info_key => $info_val) {
                    foreach ($order_amount as $amount_key => $amount_val) {
                        if($info_val['member_id'] == $amount_val['referee_id']){
                            $team_member_info[$info_key]['order_amount'] = $amount_val['order_amount'];
                            break;
                        }else{
                            $team_member_info[$info_key]['order_amount'] = '';
                        }
                    }
                }

                $tmp = array();
                foreach($team_member_info as $mi_key=>$mi_val){
                    $tmp[] = $mi_val['order_amount'];
                }
            }else{
                foreach ($team_member_info as $info_key => $info_val) {
                    $team_member_info[$info_key]['order_amount'] = 0;
                }
            }
        }
        $tmp =  !empty($tmp) ? array_sum($tmp) : 0;
        $seller_data = [
            'seller_name' => $seller_info['seller_name'],
            'total_amount' => $tmp,
            'identify' => $tmp > 10000000 ? '全职': '兼职',
            'team_num' => $team_num,
            'invite_code' => $seller_info['invite_code'],
            'team_member_info' => $team_member_info,
        ];
        
        $this->ajax_return('200','success',$seller_data);
    }

    /**
    * 团队信息(销售商)
    */
    public function team_seller(){
        $page = input('param.page',1,'intval');

        //数据分页
        $count = $this->member_model ->where(['parent_id'=>$this->member_info['member_id']])->count();
        $page_num = 5;
        $page_count = ceil($count / $page_num);
        $limit = 0;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;  
        }else{
            $limit = 0;
        }

        $member_data = $this->member_model->where(['parent_id'=>$this->member_info['member_id']])->limit($limit,$page_num)->select()->toArray();
     
        //获取等级数据
        $team_number = $this->team_model->where(['member_id'=>$this->member_info['member_id']])->value('team_number');
        $tr_data = $this->tr_model->where(['reward_state'=>1])->order('team_number ASC')->select()->toArray();
        foreach($tr_data as $tr_key=>$tr_val){
            if($team_number < $tr_val['team_number']){
                $last_number = $tr_val['team_number'] - $team_number;
                $points = $tr_val['fixed_refund_points'];
                break;
            }

            if($team_number == $tr_val['team_number']){
                $last_number = $tr_data[$tr_key+1]['team_number'];
                $points = $tr_data[$tr_key+1]['fixed_refund_points'];
                break;
            }
        }

        $team_info = ['member_count'=>$count,'last_number'=>$last_number,'points'=>$points,'seller_info'=>[]];

        if(!empty($member_data)){
            $tmp = [];
            foreach($member_data as $md_key =>$md_val){
                $tmp[] = $md_val['member_id'];
            }
            $tmp = implode(',',$tmp);

            $order_info = $this->order_model->where(['buyer_id'=>['in',$tmp],'order_state'=>50])->field('buyer_id,sum(order_amount) as order_amount')->group('buyer_id')->select()->toArray();
            
            $data = [];
            foreach($member_data as $md_key => $md_val){
                $member_detail = [];
                $member_detail['member_name'] = !empty($md_val['member_name']) ? $md_val['member_name'] : '匿名';
                $member_detail['member_mobile'] = $md_val['member_mobile'];
                $member_detail['member_grade'] = $md_val['member_grade'];
                $member_detail['member_avatar'] = strexists($md_val['member_avatar'],'http') ? $md_val['member_avatar'] : $this->base_url . '/uploads/avatar/' . $md_val['member_avatar'];
                $member_detail['order_amount'] = 0;
                foreach ($order_info as $or_key => $or_val) {
                    if($md_val['member_id'] == $or_val['buyer_id']){
                        $member_detail['order_amount'] = $or_val['order_amount'];
                        break;
                    }
                }
                $data[] = $member_detail;
                unset($member_detail);
            }

            $grade_array = ['7'=>'F','8'=>'E','9'=>'D','10'=>'C','11'=>'B','12'=>'A'];
            foreach($data as $da_key => $da_val){
                if($da_val['member_grade'] > 6){
                    $da_val['grade_name'] = $grade_array[$da_val['member_grade']].'级销费商';
                    $team_info['seller_info'][] = $da_val;
                }
            }
        }
        
        $this->ajax_return('200','msg',$team_info);
    }

    /**
    * 团队信息(会员)
    */
    public function team_member(){
        $page = input('param.page',1,'intval');

        //数据分页
        $count = $this->member_model ->where(['parent_id'=>$this->member_info['member_id']])->count();
        $page_num = 5;
        $page_count = ceil($count / $page_num);
        $limit = 0;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;  
        }else{
            $limit = 0;
        }

        $member_data = $this->member_model->where(['parent_id'=>$this->member_info['member_id']])->limit($limit,$page_num)->select()->toArray();
        $team_info = ['oridinary_info'=>[]];

        if(!empty($member_data)){
            $team_info = ['ordinary_info'=>[]];
            $tmp = [];
            foreach($member_data as $md_key =>$md_val){
                $tmp[] = $md_val['member_id'];
            }
            $tmp = implode(',',$tmp);

            $order_info = $this->order_model->where(['buyer_id'=>['in',$tmp],'order_state'=>50])->field('buyer_id,sum(order_amount) as order_amount')->group('buyer_id')->select()->toArray();
            
            $data = [];
            foreach($member_data as $md_key => $md_val){
                $member_detail = [];
                $member_detail['member_name'] = !empty($md_val['member_name']) ? $md_val['member_name'] : '匿名';
                $member_detail['member_mobile'] = $md_val['member_mobile'];
                $member_detail['member_grade'] = $md_val['member_grade'];
                $member_detail['member_avatar'] = strexists($md_val['member_avatar'],'http') ? $md_val['member_avatar'] : $this->base_url . '/uploads/avatar/' . $md_val['member_avatar'];
                $member_detail['order_amount'] = 0;
                foreach ($order_info as $or_key => $or_val) {
                    if($md_val['member_id'] == $or_val['buyer_id']){
                        $member_detail['order_amount'] = $or_val['order_amount'];
                        break;
                    }
                }
                $data[] = $member_detail;
                unset($member_detail);
            }

            foreach($data as $da_key => $da_val){
                if($da_val['member_grade'] <= 6){
                    $da_val['grade_name'] = 'Lv.'.$da_val['member_grade'].'会员';
                    $team_info['ordinary_info'][] = $da_val;
                }
            }
        }
        
        $this->ajax_return('200','msg',$team_info);
    }

    /**
    * 销费商中心
    */
    public function seller_center(){
        $member_data = $this->member_model->basic_info($this->member_info['member_id']);

        $member_avatar = strexists($member_data['member_avatar'],'http') ? $member_data['member_avatar'] : $this->base_url . '/uploads/avatar/' . $member_data['member_avatar'];

        $data = ['member_name'=>$member_data['member_name'],'member_grade'=>$member_data['member_grade'],'grade_name'=>$member_data['grade_name'],'member_avatar'=>$member_avatar,'income_info'=>[]];
        $pl_type = ['month_refund_points','fixed_refund_points','grade_return_present','order_add','admin_add','order_parent_present','team_perform_reward'];
        $query = [
            'member_id'=>$this->member_info['member_id'],
            'type'=>['in',$pl_type]
        ];
        //总收益
        $pl_amount = $this->pl_model->where($query)->sum('points');
        // echo $this->pl_model->getLastsql();die;
        $pl_amount = !empty($pl_amount) ? $pl_amount : 0;

        $year = date("Y",time());
        $month = date("m",time());
        $day = date('t');       
        $start_time = mktime(0,0,0,$month,1,$year);        
        $end_time = mktime(23,59,59,$month,$day,$year); 
    
        //当月收益
        $query['add_time'] = [['egt',$start_time],['elt',$end_time]];
        $query['pl_state'] = 1;
        $pl_month_amount = $this->pl_model->where($query)->sum('points');
        $pl_month_amount = !empty($pl_month_amount) ? $pl_month_amount : 0;

        //个人业绩总计
        $person_income = $this->order_model->where(['buyer_id'=>$this->member_info['member_id'],'order_state'=>50])->sum('order_amount');
        $person_income = !empty($person_income) ? $person_income : 0;

        //团队总业绩
        $team_data = $this->team_model->team_perform($this->member_info['member_id']);
        $team_data = !empty($team_data) ? $team_data : 0;

        $data['income_info'] = [
            'total_income' => $pl_amount,
            'month_income' => $pl_month_amount,
            'person_performance' => $person_income,
            'total_performance'=>$team_data
        ];
        $this->ajax_return('200','msg',$data);
    }

    /**
    * 团队推广奖励
    */
    public function team_popularize(){
        $page = input('param.page',1,'intval');

        //数据分页
        $count = $this->pl_model ->where(['member_id'=>$this->member_info['member_id']])->count();
        $page_num = 5;
        $page_count = ceil($count / $page_num);
        $limit = 0;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;  
        }else{
            $limit = 0;
        }

        $tr_data = $this->pl_model->where(['member_id'=>$this->member_info['member_id'],'type'=>'fixed_refund_points','pl_state'=>1])->limit($limit,$page_num)->select()->toArray();

        $data = [];
        if(!empty($tr_data)){
            foreach($tr_data as $tr_key => $tr_val){
               $tmp = [];
               $tmp['points'] = !empty($tr_val['points']) ? '+'.$tr_val['points'] : 0;
               $tmp['add_time'] = $tr_val['add_time'];  
               $data_json = json_decode($tr_val['pl_data'],true);
               $tmp['up_num'] = $data_json['up_num'];
               $data[] = $tmp;
            }
        }
        
        $this->ajax_return('200','success',$data);
    }

    /**
    * 团队管理奖励
    */
    public function team_manage(){
        $page = input('param.page',1,'intval');

        //数据分页
        $count = $this->pl_model ->where(['member_id'=>$this->member_info['member_id']])->count();
        $page_num = 5;
        $page_count = ceil($count / $page_num);
        $limit = 0;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;  
        }else{
            $limit = 0;
        }

        $team_info = $this->team_model->where(['member_id'=>$this->member_info['member_id'],'team_status'=>1])->find();
        $tr_data = $this->tr_model->where(['reward_state'=>1])->order('team_number ASC')->select()->toArray();

        foreach($tr_data as $ti_key=>$ti_val){
            if($team_info['team_number'] == $ti_val['team_number']){
                if(isset($ti_val[$ti_key+1])){
                    $last_number = $ti_val[$ti_key+1]['team_number'];
                    $month_refund = $ti_val[$ti_key+1]['mouth_refund_points'];
                }
                
            }
            if($team_info['team_number'] < $ti_val['team_number']){
                $last_number = $ti_val['team_number'];
                $month_refund = $ti_val['mouth_refund_points'];
                break;
            }

            if($team_info['team_number'] >= $ti_val['team_number']){

            }
        }

        $pl_data = $this->pl_model->where(['member_id'=>$this->member_info['member_id'],'type'=>'month_refund_points'])->limit($limit,$page_num)->select()->toArray();

        $data = ['member_count'=>$team_info['team_number'],'last_number'=>$last_number,'month_refund'=>$month_refund,'team_reward'=>[]];
        if(!empty($pl_data)){
           foreach($pl_data as $pl_key=>$pl_val){
                $tmp = [];
                $json_decode = json_decode($pl_val['pl_data'],true);
                $tmp['reward_number'] = $json_decode['reward_number'];
                $tmp['points'] = '+'.$pl_val['points'];
                $tmp['add_time'] = $pl_val['add_time'];
                $tmp['status'] = $pl_val['pl_state'];
                $data['team_reward'][] = $tmp;
            }
        }

        $this->ajax_return('200','success',$data);
    }

     /**
    * 分享积分奖励
    */
     public function share_info(){
        $page = input('param.page',1,'intval');

        //数据分页
        $count = $this->pl_model ->where(['member_id'=>$this->member_info['member_id']])->count();
        $page_num = 5;
        $page_count = ceil($count / $page_num);
        $limit = 0;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;  
        }else{
            $limit = 0;
        }

        $type = ['grade_return_present','order_parent_present'];
        $query = ['member_id'=>$this->member_info['member_id'],'type'=>['in',$type]];
        $total_points = $this->pl_model->where($query)->sum('points');
        $total_points = !empty($total_points) ? $total_points : 0;

        $data = ['total'=>$total_points,'points_detail'=>[]];

        $pl_data = $this->pl_model->where($query)->order('add_time desc')->limit($limit,$page_num)->select()->toArray();
        
        if(!empty($pl_data)){
            foreach($pl_data as $pl_key=>$pl_val){
                $tmp = [];
                $tmp['points'] = '+'.$pl_val['points'];
                $decode_data = json_decode($pl_val['pl_data'],true);
                $tmp['customer'] = $decode_data['customer'];
                $tmp['add_time'] = $pl_val['add_time'];
                if($pl_val['type'] == 'grade_return_present'){
                    $tmp['type'] = 'upgrade';
                    $tmp['consume_grade'] = $decode_data['upgrade'];
                }else{
                    $tmp['type'] = 'consume';
                    $tmp['consume_grade'] = $decode_data['consume_num'];
                }

                $data['points_detail'][] = $tmp;
            }
        }

        $this->ajax_return('200','success',$data);
    }

     /**
    * 团队业绩(顶部)
    */
    public function team_perform(){
        $member_info = $this->member_model->basic_info($this->member_info['member_id']);
        if(!empty($member_info['member_avatar'])){
            $member_info['member_avatar'] = strexists($member_info['member_avatar'],'http') ? $member_info['member_avatar'] : $this->base_url . '/uploads/avatar/' . $member_info['member_avatar'];
        }else{
            $member_info['member_avatar'] = '';
        }
        $total_perform = $this->team_model->team_perform($this->member_info['member_id']);
        $month_perform = $this->team_model->team_mdperform('month',$this->member_info['member_id']);
        $day_perform = $this->team_model->team_mdperform('day',$this->member_info['member_id']);

        $data = ['member_info'=>$member_info,'total_perform'=>$total_perform,'month_perform'=>$month_perform,'day_perform'=>$day_perform];

        $this->ajax_return('200','success',$data);
    }

     /**
    * 团队业绩详细
    */
    public function perform_detail(){
        $page = input('post.page',1,'intval');

        $per_type = input('post.type','total','trim');
        $type = ['total','month','day'];
        if(!in_array($per_type,$type)){
            $this->ajax_return('11770','type is not exist');
        }

        if($per_type == 'total'){
            $data = $this->team_model->perform_detail($this->member_info['member_id'],$page,$per_type);
        }

        if($per_type == 'month'){
            $data = $this->team_model->perform_detail($this->member_info['member_id'],$page,$per_type);
        }

        if($per_type == 'day'){
            $data = $this->team_model->perform_detail($this->member_info['member_id'],$page,$per_type);
        }
        if(!empty($data)){
            foreach($data as $da_key=>$da_val){
                if(!empty($da_val['member_avatar'])){
                    $data[$da_key]['member_avatar'] = strexists($da_val['member_avatar'],'http')
                        ? $da_val['member_avatar']
                        : $this->base_url . '/uploads/avatar/' . $da_val['member_avatar'];
                }else{
                    $data[$da_key]['member_avatar'] = '';
                }
                
                $info[] = $da_val['order_amount'];
            }
            //SORT_ASC 返回一个降序排列的数组  SORT_DESC 返回一个升序排列的数组
            array_multisort($info, SORT_DESC, $data);
        }

        $this->ajax_return('200','success',$data);
    }

    /**
    * 业绩奖励
    */
    public function perform_reward(){
        $member_info = $this->member_model->basic_info($this->member_info['member_id']);

        $grade_info = Cache::get('grade');
        if(!empty($grade_info)){
            $grade_info = $this->grade_model->order(array("grade" => "ASC"))->select()->toArray();
            Cache::set('grade',$grade_info);
        }
        $tmp = [];

        foreach($grade_info as $gi_key=>$gi_val){
            if($member_info['member_grade'] == $gi_val['grade']){
                $tmp['grade_min_expend'] = $gi_val['grade_min_expend'];
                $tmp['team_min_amount'] = $gi_val['team_min_amount'];
                $tmp['team_present_points'] = $gi_val['team_present_points'];
            }
        }
        
        //个人消费额
        $self_amount = $this->order_model->where(['buyer_id'=>$this->member_info['member_id'],'order_state'=>50])->sum('order_amount');
        $self_amount = !empty($self_amount) ? $self_amount : 0;
        $self_last = ($tmp['grade_min_expend'] - $self_amount) > 0 ? $tmp['grade_min_expend'] - $self_amount : 0;
        $is_run = $tmp['grade_min_expend'] > $self_amount ? 1 : 0;

        //团队消费
        $team_info = $this->member_model->where(['parent_id'=>$this->member_info['member_id']])->select()->toArray();
        $tmps = [];
        foreach($team_info as $ti_key=>$ti_val){
            $tmps['member_id'] = $ti_val['member_id'];
        }
      
        if(isset($tmps) && !empty($tmps)){
            $tmps = implode(',', $tmps);
            $order_amount = model('Order')->where(['buyer_id'=>['in',$tmps],'order_state'=>50])->sum('order_amount');
            $order_amount = !empty($order_amount) ? $order_amount : 0;
        }else{
            $order_amount = 0;
        }

        $team_last = ($tmp['team_min_amount'] - $order_amount) > 0 ? $tmp['team_min_amount'] - $order_amount : 0;
        $tis_run = $tmp['team_min_amount'] > $order_amount ? 1 : 0;

        $data = ['member_info'=>$member_info,'month_record'=>[],'perform_log'=>[]];
        $data['month_record'] = [
            'get_points' => $tmp['team_present_points'],
            'self_amount' =>  $tmp['grade_min_expend'],
            'fis_run' =>$is_run,
            'self_last' => $self_last,
            'team_amount' => $tmp['team_min_amount'],
            'tis_run' =>$tis_run,
            'team_last' => $team_last
        ];

        $grade_array = ['7'=>'F','8'=>'E','9'=>'D','10'=>'C','11'=>'B','12'=>'A'];
        $page = input('post.page',1,'intval');
        //数据分页
        $count = $this->pl_model ->where(['member_id'=>$this->member_info['member_id']])->count();
        $page_num = 5;
        $page_count = ceil($count / $page_num);
        $limit = 0;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;  
        }else{
            $limit = 0;
        }
        //团队业绩奖励明细
        $pl_data = $this->pl_model->where(['type'=>'team_perform_reward','member_id'=>$this->member_info['member_id']])->limit($limit,$page_num)->select()->toArray();
        if(!empty($pl_data)){
            foreach($pl_data as $pl_key=>$pl_val){
                $decode_data = json_decode($pl_val['pl_data'],true);
                $info['points'] = '+'.$pl_val['points'];
                $info['add_time'] = $pl_val['add_time'];
                $info['is_get'] = $decode_data['is_get'];
                $info['member_grade'] = $decode_data['member_grade'];
                $info['grade_name'] = $grade_array[$decode_data['member_grade']].'级消费商';
                $data['perform_log'][] = $info;
            }
        }
        $this->ajax_return('200','success',$data);
    }
}