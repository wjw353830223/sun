<?php
namespace app\api\controller;
use app\common\model\MemberAuth;
use think\Cache;
use think\Hook;
use think\View;

class Member extends Apibase
{
    protected $member_model, $token_model, $info_model,$area_model,$seller_model,$sm_model,$mq_model;
    protected $pl_model,$grade_model,$order_model,$member_auth,$cash_model,$pd_model;
    protected function _initialize() {
        parent::_initialize();
        $this->member_model = model('Member');
        $this->token_model = model('MemberToken');
        $this->info_model = model('MemberInfo');
        $this->area_model = model('Area');
        $this->seller_model = model('StoreSeller');
        $this->sm_model = model('SellerMember');
        $this->mq_model = model('MemberQrcode');
        $this->pl_model = model('PointsLog');
        $this->grade_model = model('Grade');
        $this->order_model = model('Order');
        $this->member_auth = model('MemberAuth');
        $this->cash_model = model('PdCash');
        $this->pd_model = model('PdLog');
    }

    /**
     * 会员资料编辑
     */
    public function edit_info() {
        $param = input('param.');
        $host_data = $this->member_model->_initMemberInfo($param,$this->member_info);
        if(isset($host_data['code'])  && isset($host_data['msg'])){
            $this->ajax_return($host_data['code'],$host_data['msg']);
        }
        $sex = input('post.sex','','intval');
        $file = request()->file('avatar');
        if (!is_null($file)) {
            $info = $file->rule('uniqid')->validate(['size' => 156780, 'ext' => 'jpg,png,gif,jpeg'])
                ->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'avatar');
            if ($info) {
                $host_data['member_avatar'] = $info->getFilename();
                $bucket = config('qiniu.buckets')['images']['name'];
                $key = 'uploads/avatar/'. $info->getSaveName();
                $path = './' . $key;
                add_file_to_qiniu($key,$path,$bucket);
            } else {
                $this->ajax_return('10043',$file->getError());
            }
        }

        //返回提交数据
        $domain = config('qiniu.buckets')['images']['domain'];
        if (isset($host_data['member_avatar'])) {
            $host_data['member_avatar'] =  $domain.'/uploads/avatar/' . $host_data['member_avatar'];
        }

        $birthday = strtotime($param['birthday']);
        $bir_year = date('Y',$birthday);
        $year = date('Y');
        $age = $year - $bir_year;

        $info_data = [
            'member_height' => $param['member_height'],
            'member_weight' => $param['member_weight'],
            'birthday' => $birthday,
            'member_age' => $age,
            'member_sex' => $sex,
        ];

        $res = $this->member_model->update_member_info($host_data,$info_data,$this->member_info['member_id']);
        if($res === false){
            $this->ajax_return('10044', 'failed to save data');
        }
        $host_data['mobile'] = $param['mobile'];
        $this->ajax_return('200', 'success',$host_data);
    }

    /**
     * 会员资料获取
     */
    public function get_info() {
        $member_info = $this->member_info;
        unset($member_info['member_state']);
        if (!empty($member_info['member_avatar'])) {
            $member_info['member_avatar'] = strexists($member_info['member_avatar'],'http')
                ? $member_info['member_avatar']
                : config('qiniu.buckets')['images']['domain'] . '/uploads/avatar/' . $member_info['member_avatar'];
        }else{
            $member_info['member_avatar'] = '';
        }
        //获取会员积分/余额
        $member_id = $this->member_info['member_id'];
        //常仁用户积分和合作伙伴用户积分统一
        Hook::listen('get_user_info_before',$member_id);
        $cash_info = $this->member_model->where(['member_id'=>$member_id])
            ->field('av_predeposit,points,freeze_points,member_grade,experience')->find();
        $member_info['av_predeposit'] = $cash_info['av_predeposit'];
        $member_info['freeze_points'] = $cash_info['freeze_points'];
        $member_info['points'] = !empty($cash_info['points']) ? $cash_info['points'] : 0;
        $member_info['points_money'] = $cash_info['points'];
        $member_info['member_grade'] = $cash_info['member_grade'];
        $member_info['experience'] = $cash_info['experience'];
        //默认积分关闭 余额关闭
        $member_info['is_points'] = 0;
        $member_info['is_predeposit'] = 0;
        //获取会员身高、体重、生日
        $member_info_data = model('MemberInfo')->where(['member_id'=>$member_id])->field('member_height,member_weight,birthday,member_sex')->find();
        if(empty($member_info_data)){
            $this->ajax_return('10045', 'member_info is empty');
        }
        $member_info_data = $member_info_data->toArray();
        $member_info_data['birthday'] = date('Y-m-d',$member_info_data['birthday']);

        $member_info = array_merge($member_info,$member_info_data);

        $where = [];
        $data = [];
        //判断当前用户是否为销售员、库管员
        $seller = model('StoreSeller')
            ->where(['member_id'=>$member_id,'seller_state'=>['in',[2,3]]])
            ->find();
        $member = $this->member_model->where(['member_id'=>$member_id])->find();
        if($member['member_grade'] < 7){
            //普通会员
            $where['grade_type'] = 1;
            $data['grade_type'] = 1;
            $data['grade'] = $member_info['member_grade'];
            //等级状态开启
            $where['grade_state'] = 1;
            $data['grade_state'] = 1;

            //当前等级所需积分数
            $grade = model('Grade')->where($data)
                ->field('grade_points,grade_type,grade')
                ->find();
            //大于当前经验的等级
            $where['grade_points'] = ['gt',$cash_info['experience']];

            $grade_info = model('Grade')->where($where)
                ->field('grade_points,grade_type,grade')
                ->order('grade_points asc')->find();
            //获取升级所需经验值以及会员角色
            if(!$grade_info){
                $member_info['grade_type'] = $grade['grade_type'];
                $member_info['grade_points'] = $grade['grade_points'];
                $member_info['grade'] = $grade['grade'];
            }else{
                $member_info['grade_type'] = $grade_info['grade_type'];
                $member_info['grade_points'] = $grade_info['grade_points'];
                $member_info['grade'] = $grade_info['grade'];
            }
        }else{
            $member_info['grade_type'] = 2;
            $member_info['grade'] = $cash_info['member_grade'];
        }
        if($seller && $seller['seller_role'] == 2 || $seller['seller_role'] == 3){
            $member_info['is_repertory'] = 1;
        }else{
            $member_info['is_repertory'] = 0;
        }
        if($seller){
            $member_info['seller_state'] = $seller['seller_state'];
        }
        $member_info['is_auth'] = $this->member_auth($member_id);
        $auth = model('MemberAuth')
            ->where(['member_id'=>$member_id,'auth_state'=>1])
            ->field('auth_name,id_card')->find();
        if(!empty($auth)){
            $member_info['auth_name'] = $auth['auth_name'];
            $member_info['id_card'] = $auth['id_card'];
        }else{
            $member_info['auth_name'] = '';
            $member_info['id_card'] = '';
        }
        $this->ajax_return('200', 'success', $member_info);
    }
    /**
     * 会员获取地址
     */
    public function get_area() {
        $area = Cache::get('area');
        if (empty($area)) {
            $area = $this->area_model->order('area_id ASC')->select();
            Cache::set('area', $area);
        }
        $parent_id = input('post.parent_id', 0, 'intval');
        $data = array();
        foreach ($area as $key => $value) {
            $tmp = array();
            if ($value['area_parent_id'] == $parent_id) {
                $tmp['area_id'] = $value['area_id'];
                $tmp['area_name'] = $value['area_name'];
                $tmp['parent_id'] = $value['area_parent_id'];
                $data[] = $tmp;
            }
        }
        if (empty($data)) {
            $this->ajax_return('10030', 'invalid deep');
        } else {
            $this->ajax_return('200', 'success', $data);
        }
    }

    /**
     *	编辑健康档案基本资料
     */
    public function basic_info(){
        $param = input("param.");
        //真实姓名
        $true_name = strip_tags($param['true_name']);

        if(empty($true_name)){
            $this->ajax_return('10130', 'invalid true_name');
        }
        //性别
        $sex_array = ['1', '2'];
        if (empty($param['member_sex']) || !in_array($param['member_sex'], $sex_array)) {
            $this->ajax_return('10131', 'invalid member_sex');
        }
        //年龄
        if (empty($param['member_age']) || intval($param['member_age']) > 200) {
            $this->ajax_return('10132', 'invalid member_age');
        }
        //身高
        if (empty($param['member_height']) || intval($param['member_height']) > 250) {
            $this->ajax_return('10133', 'invalid member_height');
        }
        //体重
        if (empty($param['member_weight']) || intval($param['member_weight']) > 250) {
            $this->ajax_return('10134', 'invalid member_weight');
        }
        //职业类型
        $pro_type = ['0','1','2','3','4','5','6','7','8','9','10','11'];
        if(!in_array($param['profession'], $pro_type)){
            $this->ajax_return('10135', 'invalid profession');
        }
        //医保类型
        $medical_array = ['0' , '1' , '2' , '3'];
        if(!in_array($param['medical_type'], $medical_array)){
            $this->ajax_return('10136', 'invalid medical_type');
        }
        //组合详情表数据
        $info_data = ['true_name' => $true_name,'member_sex' => $param['member_sex'],'member_age' =>$param['member_age'],'member_height'=>$param['member_height'],'member_weight'=>$param['member_weight']];
        $info_data['profession'] = intval($param['profession']);
        $info_data['medical_type'] = intval($param['medical_type']);

        $has_info = $this->info_model->where(['member_id' => $this->member_info['member_id']])->count();
        if ($has_info > 0) {
            $info_result = $this->info_model->save($info_data, ['member_id' => $this->member_info['member_id']]);
        } else {
            $info_data['member_id'] = $this->member_info['member_id'];
            $info_result = $this->info_model->save($info_data);
        }
        if ($info_result === false) {
            $this->ajax_return('10137', 'failed to save data');
        }

        $info_data['profession'] = $param['profession'];
        $info_data['medical_type'] = $param['medical_type'];
        $this->ajax_return("200","success",$info_data);
    }

    /**
     *	获取健康档案基本资料
     */
    public function get_basic_info(){
        $member_id = $this->member_info['member_id'];
        $member_info = $this->info_model->where(['member_id'=>$member_id])->field('true_name,member_sex,member_age,member_height,member_weight,profession,medical_type')->find();
        if(empty($member_info)){
            $this->ajax_return('10140','empty data');
        }
        $member_info['member_sex'] = (string)$member_info['member_sex'];
        $member_info['member_age'] = (string)$member_info['member_age'];
        $member_info['member_height'] = (string)$member_info['member_height'];
        $member_info['member_weight'] = (string)$member_info['member_weight'];
        $member_info['profession'] = (string)$member_info['profession'];
        $member_info['medical_type'] = (string)$member_info['medical_type'];
        $this->ajax_return('200','success',$member_info);
    }

    /**
     *	判断用户权限
     */
    public function member_power(){
        $member_id = $this->member_info['member_id'];
        $has_info = $this->member_model->where(['member_id'=>$member_id])->find();
        if(empty($has_info)){
            $this->ajax_return('10470','member dose not exsit');
        }

        $seller_info = db('store_seller')->where(['member_id'=>$member_id])->field('seller_role,seller_state')->find();
        if(empty($seller_info)){
            $seller_role['seller_role'] = '0';
            $seller_role['seller_state'] = '';
        }else{
            $seller_role['seller_role'] = $seller_info['seller_role'];
            $seller_role['seller_state'] = $seller_info['seller_state'];
        }

        $this->ajax_return('200','success',$seller_role);
    }


    /**
     *	获取/生成二维码
     */
    public function create_qrcode(){
        $member_avatar = $this->member_model
            ->where(['member_id'=>$this->member_info['member_id']])
            ->value('member_avatar');
        $member_avatar = strexists($member_avatar,'http')
            ? $member_avatar
            : config('qiniu.buckets')['images']['domain'].'/uploads/avatar/'.$member_avatar;

        $data = [
            'member_id' => $this->member_info['member_id'],
            'member_avatar' => $member_avatar,
            'member_name'   => $this->member_info['member_name'],
        ];
        $domain = config('qiniu.buckets')['images']['domain'];
        $has_info = $this->mq_model->where(['member_id'=>$this->member_info['member_id']])->find();
        //会员二维码信息不存在
        if(empty($has_info) || (!empty($has_info) && empty($has_info['qrcode']))){
            if(!empty($has_info)){
                $this->mq_model->where(['member_id'=>$this->member_info['member_id']])->delete();
            }
            $data['member_mobile'] = $this->member_info['member_mobile']; 
            $result = $this->mq_model->create_qrcode($data,1);
            if(isset($result) && empty($result)){
                $this->ajax_return('11710','failed to create qrcode');
            }
            $data['qrcode'] = $domain. '/qrcode/'.$result;
        }else{
            $data['qrcode'] = $domain.'/qrcode/'.$has_info['qrcode'];
        }
        $this->ajax_return('200','success',$data);
    }

    /**
     *	会员中心
     */
    public function member_center(){
        $seller_state = ['2','3'];
        $where = [
            'member_id'    => $this->member_info['member_id'],
            'seller_state' => ['in',$seller_state],
            'seller_role'  => ['in',[1,3]]
        ];
        $seller_info = $this->seller_model->where($where)->find();

        $member_info = [
            'ordinary_info' => [],
            'seller_info' => []
        ];
        if(empty($seller_info)){
            if(!empty($this->member_info['member_avatar'])){
                $member_avatar = strexists($this->member_info['member_avatar'],'http') ? $this->member_info['member_avatar'] : config('qiniu.buckets')['images']['domain'] . '/uploads/avatar/'.$this->member_info['member_avatar'];
            }else{
                $member_avatar = '';
            }

            //判断当前用户可升至等级
            $grade_info = Cache::get('grade');
            if(empty($grade_info)){
                $grade_info = $this->grade_model->where(['grade_type'=>1,'grade_state'=>1])->order('grade asc')->select();
                Cache::set('grade', $grade_info);
            }

            $member_data = $this->member_model->where(['member_id'=>$this->member_info['member_id']])->field('experience,member_grade')->find();
            $last_num = $grade_experience = 0;
            if($member_data['member_grade'] == 6){
                $next_num = $this->member_model->where(['parent_id'=>$this->member_info['member_id'],'member_grade'=>['egt',6]])->count();
                foreach($grade_info as $gr_key=>$gr_val){
                    if(6 == $gr_val['grade']){
                        $grade_experience = $gr_val['grade_points'];
                        break;
                    }
                }
                $grade = 'F级消费商';
                if($next_num >= 3){
                    $last_num =  0;
                }else{
                    $last_num =  3 - $next_num;
                }
            }else{
                foreach($grade_info as $gr_key=>$gr_val){
                    if($member_data['experience'] < $gr_val['grade_points']){
                        $grade = $gr_val['grade'];
                        $grade_experience = $gr_val['grade_points'];
                        break;
                    }else{
                        $grade = $member_data['member_grade'];
                        $grade_experience = $gr_val['grade_points'];
                    }
                }
            }

            //购买积分奖励
            $query_condition = [
                'member_id'=>$this->member_info['member_id'],
                'type' => 'order_add'
            ];
            $buy_award = $this->pl_model->whereTime('add_time','month')->where($query_condition)->sum('points');
            $buy_points = !empty($buy_award) ? '+'.$buy_award : 0;

            //等级积分奖励(上一月)
            $query = [
                'member_id'=>$this->member_info['member_id'],
                'type' => 'system_points_award'
            ];
            $grade_points = $this->pl_model->whereTime('add_time','last month')->where($query)->value('points');
            if(empty($grade_points)){
                //获取等级数据

                $member_grade = $this->member_model->where(['member_id'=>$this->member_info['member_id']])->value('member_grade');
                foreach($grade_info as $gr_key=>$gr_val){
                    if($member_grade == $gr_val['grade']){
                        $to_grade_points = $gr_val['grade_expend_present'];
                        break;
                    }
                }

                $to_grade_points = !empty($to_grade_points) ? '-'.$to_grade_points : 0;
            }
            $grade_points = !empty($grade_points) ? '+'.$grade_points : $to_grade_points;

            //分享积分奖励
            $query_condition['type'] = ['in',['grade_return_present','order_parent_present']];
            $share_award = $this->pl_model->whereTime('add_time','month')->where($query_condition)->sum('points');
            $share_points = !empty($share_award) ? '+'.$share_award : 0;

            //用户当前是否具备申请成为消费商的资格
            $member_count = $this->member_model->where(['parent_id'=>$this->member_info['member_id'],'member_grade'=>6])->count();
            $is_apply = $member_data['member_grade'] >= 6 && $member_count >=3 ? 1 : 0;

            $member_info['ordinary_info'] = [
                'member_avatar' => $member_avatar,
                'member_name' => $this->member_info['member_name'],
                'experience' => $member_data['experience'],
                'member_grade' => $member_data['member_grade'],
                'to_grade' => $grade,
                'to_experience' => $grade_experience,
                'last_num' => $last_num,
                'buy_points' => $buy_points,
                'grade_points' => $grade_points,
                'is_apply' => $is_apply,
                'share_points' => $share_points
            ];
        }

        $this->ajax_return('200','success',$member_info);
    }


    /**
     *	积分奖励
     */
    public function integral_award(){
        $type = input('post.type','','trim');
        $page = input('post.page',0,'intval');

        $integral_type = ['buy','share','grade'];
        if(!in_array($type,$integral_type)){
            $this->ajax_return('11760','type is not exist');
        }

        //数据分页
        $count = $this->pl_model ->where(['type'=>$type])->count();
        $page_num = 5;
        $page_count = ceil($count / $page_num);
        $limit = 0;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;
        }else{
            $limit = 0;
        }
        $data = [];
        //购买积分奖励明细
        if($type == 'buy'){
            $buy_data = $this->pl_model->where(['member_id'=>$this->member_info['member_id'],'type'=>'order_add'])->field('pl_data,add_time,points')->limit($limit,$page_num)->select()->toArray();
            $data['buy_info'] = [];
            if(!empty($buy_data)){
                foreach($buy_data as $buy_key=>$buy_val){
                    $buy_data = json_decode($buy_val['pl_data'],true);
                    $data['buy_info'][] = [
                        'goods_name'  => $buy_data['goods_name'],
                        'goods_price' => $buy_data['goods_price'],
                        'goods_pic'   => config('qiniu.buckets')['images']['domain'] . $buy_data['goods_pic'],
                        'points_award'=> '+'.$buy_val['points'],
                        'add_time'    => $buy_val['add_time']
                    ];
                }
            }
        }

        //等级积分奖励明细
        if($type == 'grade'){
            $member_grade = $this->member_model->where(['member_id'=>$this->member_info['member_id']])->value('member_grade');
            //用户当月消费额度
            $query = [
                'buyer_id'  => $this->member_info['member_id'],
                'order_state' => '50',
            ];
            $order_amount = $this->order_model->whereTime('finnshed_time','month')->where($query)->sum('order_amount');
            $order_amount = !empty($order_amount) ? $order_amount : 0;

            //获取等级数据
            $grade_info = Cache::get('grade');
            if(empty($grade_info)){
                $grade_info = $this->grade_model->where(['grade_type'=>1,'grade_state'=>1])->order('grade ASC')->select();
                Cache::set('grade', $grade_info);
            }
            //当月等级积分进度
            foreach($grade_info as $gr_key=>$gr_val){
                if($member_grade == $gr_val['grade']){
                    $is_run = $order_amount < $gr_val['grade_min_expend'] ? 1 : 0;
                    $min_expend = $gr_val['grade_min_expend'];
                    $grade_points = $gr_val['grade_expend_present'];
                    break;
                }
            }

            //既往月份等级积分明细
            $grade_month = $this->pl_model
                ->where(['member_id'=>$this->member_info['member_id'],'type'=>'system_points_award'])
                ->field('add_time,pl_data,points')
                ->limit($limit,$page_num)
                ->select()->toArray();

            $data['grade_info'] = [];

            if(!empty($grade_month)){
                foreach($grade_month as $gm_key=>$gm_val){
                    $tmp = [];
                    $grade_month = json_decode($gm_val['pl_data'],true);
                    $tmp['points'] = !empty($gm_val['points']) ? '+'.$gm_val['points'] : 0;
                    $tmp['time_grade'] = $grade_month['time_grade'];
                    $tmp['add_time'] = date('Y/m',$gm_val['add_time']);
                    $data['grade_info'][] = $tmp;
                }
            }

            $data['member_grade'] = $member_grade;
            $data['last_total'] = $min_expend > $order_amount ? $min_expend - $order_amount : 0;
            $data['is_run'] = $is_run;
            $data['min_expend'] = $min_expend;
            $data['month'] = date('m',time());
            $data['grade_points'] = $grade_points;
        }

        //分享积分奖励明细
        if($type == 'share'){
            $type = ['order_parent_present','grade_return_present'];
            //升级积分奖励
            $upgrade = $this->pl_model->where(['member_id'=>$this->member_info['member_id'],'type'=>['in',$type]])->field('pl_data,add_time,type,points')->order('add_time desc')->limit($limit,$page_num)->select()->toArray();

            $data['share_info'] = [];

            //判断用户总数
            $member_count = $this->member_model->where(['parent_id'=>$this->member_info['member_id']])->count();
            //累计积分
            $points_sum = $this->pl_model->where(['member_id'=>$this->member_info['member_id'],'type'=>['in',$type]])->sum('points');
            if(!empty($upgrade)){
                foreach($upgrade as $up_key=>$up_val){
                    $tmp = [];
                    $upgrade_data = json_decode($up_val['pl_data'],true);
                    if($up_val['type'] == 'grade_return_present'){
                        $tmp = [
                            'up_grade' => !empty($upgrade_data['upgrade']) ? $upgrade_data['upgrade'] : 0,
                            'grade_return' => !empty($upgrade_data['grade_return']) ? '+'.$upgrade_data['grade_return'] : 0,
                            'parent_points' => !empty($up_val['points']) ? '+'.$up_val['points'] : 0,
                            'consume_num' => !empty($upgrade_data['consume_num']) ? $upgrade_data['consume_num'] : 0,
                            'customer' => !empty($upgrade_data['customer']) ? $upgrade_data['customer'] : '',
                            'add_time' => $up_val['add_time'],
                            'type' => 'upgrade'
                        ];
                    }

                    if($up_val['type'] == 'order_parent_present'){
                        $tmp = [
                            'up_grade' => !empty($upgrade_data['upgrade']) ? $upgrade_data['upgrade'] : 0,
                            'grade_return' => !empty($upgrade_data['grade_return']) ? '+'.$upgrade_data['grade_return'] : 0,
                            'parent_points' => !empty($up_val['points']) ? '+'.$up_val['points'] : 0,
                            'consume_num' => !empty($upgrade_data['consume_num']) ? $upgrade_data['consume_num'] : 0,
                            'customer' => !empty($upgrade_data['customer']) ? $upgrade_data['customer'] : '',
                            'add_time' => $up_val['add_time'],
                            'type' => 'consume'
                        ];
                    }
                    $data['share_info'][] = $tmp;
                }
            }

            $data['member_count'] = $member_count;
            $data['points_sum']  = !empty($points_sum) ? $points_sum : $points_sum;
        }

        $this->ajax_return('200','success',$data);
    }

    /**
     * 绑定用户列表
     */
    public function bind_member_list(){
        $page = input('post.page',1,'intval');
        $member_id = $this->member_info['member_id'];
        //数据分页
        $count = model('Member')
            ->where(['parent_id'=>$member_id])->count();
        $page_num = 5;
        $page_count = ceil($count / $page_num);
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;
        }else{
            $limit = 0;
        }
        $member = model('Member')
            ->where(['parent_id'=>$member_id])
            ->field('member_id,member_avatar,member_grade,member_name,member_mobile')
            ->order('member_id desc')
            ->limit($limit,$page_num)
            ->select();
        foreach($member as $k=>$v){
            if(!empty($v['member_avatar'])){
                $v['member_avatar'] = strexists($v['member_avatar'],'http')
                    ? $v['member_avatar']
                    : config('qiniu.buckets')['images']['domain'] . '/uploads/avatar/'.$v['member_avatar'];
            }
        }
        $ids = [];
        foreach($member as $key=>$vo){
            $ids[] = $vo['member_id'];
        }
        $order = model('Order')
            ->where(['order_state'=>50,'buyer_id'=>['in',$ids]])
            ->field('buyer_id,sum(order_amount) as order_amount')
            ->group('buyer_id')
            ->select()->toArray();
        //消费金额总计
        if(!$order){
            foreach($member as $k=>$v){
                $member[$k]['order_amount'] = 0;
            }
        }else {
            foreach ($member as $k => $v) {
                foreach ($order as $key => $val) {
                    if ($order[$key]['buyer_id'] == $member[$k]['member_id']) {
                        $member[$k]['order_amount'] = $order[$key]['order_amount'];
                    }
                }
            }
            foreach($member as $key => $val){
                if(!isset($val['order_amount'])){
                    $member[$key]['order_amount'] = 0;
                }
            }
        }
        //奖励积分总计
        $type = ['order_parent_present','grade_return_present'];
        $points = model('PointsLog')
            ->where(['member_id'=>$this->member_info['member_id'],'type'=>['in',$type]])
            ->field('member_id,pl_data,points')
            ->select()->toArray();
        $data = [];
        foreach($points as $key=>$val){
            $pl_data = json_decode($val['pl_data'],true);
            $pl_data['points'] = $val['points'];
            $data[] = $pl_data;
        }
        foreach($member as $k=>$v){
            if(!isset($member[$k]['points'])){
                $member[$k]['points'] = 0;
            }
            foreach($data as $key=>$val){
                if($v['member_id'] == $val['from_member']){
                    $member[$k]['points'] += $data[$key]['points'];
                }
            }
        }

        $this->ajax_return('200','success',$member);
    }

    /**
     * 实名认证
     */
    public function auth(){
        $auth_name = input('post.auth_name','','trim');
        if(empty($auth_name) || words_length($auth_name) < 2 || words_length($auth_name) > 8){
            $this->ajax_return('11810','invalid auth_name');
        }
        $member_id = $this->member_info['member_id'];

        $has_one = $this->member_auth->where(['auth_state'=>1,'member_id'=>$member_id])->count();
        if($has_one > 0){
            $this->ajax_return('11811','the member is auth');
        }
        $id_card = input('post.id_card','','trim');
        if(empty($id_card) || !validation_filter_id_card($id_card)){
            $this->ajax_return('11812','invalid id_card');
        }
        $has_info = $this->member_auth->where(['id_card'=>$id_card,'auth_state'=>1])->count();
        if($has_info > 0){
            $this->ajax_return('11813','id_card has been exist');
        }
        //判断当前会员实名认证是否在审核 这块逻辑错误 如果有两条auth_member记录 $auth_state === 0 限制不了
        $auth_state = $this->member_auth->where(['member_id'=>$member_id,'id_card'=>$id_card])->value('auth_state');
        if($auth_state === 0){
            $this->ajax_return('11817','the member is reviewing');
        }
        $files = request()->file();
        if(empty($files) || count($files) != 3){
            $this->ajax_return('11814','exist id_card is empty');
        }

        $auth = [];
        foreach($files as $file_key=>$file_val){
            // 移动到框架应用根目录/public/uploads/ 目录下
            $save_path = 'public/uploads/seller';
            $info = $file_val->rule('uniqid')->validate(['size'=>1048576,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.$save_path.'/',true,false);
            if(!$info){
                $this->ajax_return('11815',$file_val->getError());
            }

            $auth[$file_key] = $info->getFilename();
        }
        $auth['member_id']  = $member_id;
        $auth['auth_name']  = $auth_name;
        $auth['id_card']    = $id_card;
        $auth['apply_time'] = time();
        $auth['auth_state'] = 0;
        $res = $this->member_auth->save($auth);
        if($res === false){
            $this->ajax_return('11816','the member is auth_false');
        }
        $this->ajax_return('200','success',[]);
    }

    /**
     * 实名认证 阿里云接口认证(主) + 后台人工审核认证（辅助认证）
     */
    public function auth_verify(){
        $auth_name = input('post.auth_name','','trim');
        if(empty($auth_name) || words_length($auth_name) < 2 || words_length($auth_name) > 8){
            $this->ajax_return('11810','invalid auth_name');
        }
        $member_id = $this->member_info['member_id'];
        if($this->member_auth->member_auth_judge($member_id)){
            $this->ajax_return('11811','the member is auth');
        }
        $id_card = input('post.id_card','','trim');
        if(empty($id_card) || !validation_filter_id_card($id_card)){
            $this->ajax_return('11812','invalid id_card');
        }
        if(!empty($this->member_auth->member_get_by_idcard($id_card))){
            $this->ajax_return('11813','id_card has been exist');
        }
        //判断当前会员最近一次实名认证是否在审核
        if($this->member_auth->member_last_auth_state($member_id,$id_card) === MemberAuth::AUTH_STATE_DEFAULT){
            $this->ajax_return('11817','the member is reviewing');
        }
        $bank_no = input('post.bank_no','','trim');
        if(empty($bank_no)){
            $this->ajax_return('11821','invalid bank_no');
        }
        $auth['member_id']  = $member_id;
        $auth['auth_name']  = $auth_name;
        $auth['id_card']    = $id_card;
        $auth['apply_time'] = time();
        $auth['bank_no'] = $bank_no;
        $files = request()->file();
        if(empty($files) || count($files) != 3){
            $this->ajax_return('11814','exist id_card is empty');
        }
        $ma_id = $this->member_auth->auth_verify($member_id, $bank_no,$auth_name,$id_card);
        if($ma_id === false){
            $this->ajax_return('11816','the member is auth_false');
        }
        //添加身份证图片
        foreach($files as $file_key=>$file_val){
            $save_path = 'public/uploads/seller';
            $info = $file_val->rule('uniqid')->validate(['size'=>1048576,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.$save_path.'/',true,false);
            if(!$info){
                $this->ajax_return('11815',$file_val->getError());
            }
            $auth[$file_key] = $info->getFilename();
        }
        if(!$this->member_auth->add_auth_card($ma_id,$auth['card_face'],$auth['card_back'],$auth['card_hand'])){
            $this->ajax_return('11818',' identification card images add false');
        }
        $this->ajax_return('200','success',[]);
    }
     /**
     * 提成奖励
     */
    public function deduct(){
        $type = input('post.type','','trim');
        $page = input('post.page',1,'intval');

        $deduct_type = ['self','team'];
        if(!in_array($type,$deduct_type)){
            $this->ajax_return('11780','type is not exist');
        }

        //数据分页
        $count = $this->pl_model->where(['type'=>$type])->count();
        $page_num = 15;
        $page_count = ceil($count / $page_num);
        $limit = 0;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;
        }else{
            $limit = 0;
        }

        $data = [];
        //个人
        if($type == 'self'){
            $data['self'] = [];
            $where = [
                'type'      =>  'order_freeze',
                'member_id' =>  $this->member_info['member_id']
            ];
            $self_data = $this->pl_model->where($where)->order('add_time desc')->limit($limit,$page_num)->select()->toArray();
            if(!empty($self_data)){
                foreach($self_data as $self_key=>$self_val){
                    $tmp = [];
                    $data_decode = json_decode($self_val['pl_data'],true);
                    $tmp['goods_name'] = $data_decode['goods_name'];
                    $tmp['points'] = '+'.$self_val['points'];
                    $tmp['add_time'] = $self_val['add_time'];
                    $days_7 = 7*24*3600;
                    $time = $self_val['add_time'] + $days_7;
                    $hour_24 = 24*3600;
                    $day_time = $time - time();

                    if($day_time < 0){
                        $day_time = 0;
                    }elseif($day_time > $hour_24){
                        $day_time =  $day_time/24/3600 > 0 ? ceil($day_time/24/3600) : 0;
                    }else{
                        $day_time = 1;
                    }

                    $tmp['day_time'] = $day_time;
                    $data['self'][] = $tmp;
                }
            }
        }

        //团队
        if($type == 'team'){
            $data['team'] = [];
            $where = [
                'type'      =>  'order_return_parent',
                'member_id' =>  $this->member_info['member_id']
            ];
            $team_data = $this->pl_model->where($where)->order('add_time desc')->limit($limit,$page_num)->select()->toArray();
            if(!empty($team_data)) {
                foreach ($team_data as $te_key => $te_val) {
                    $tmp = [];
                    $data_decode = json_decode($te_val['pl_data'], true);
                    $tmp['goods_name'] = $data_decode['goods_name'];
                    $tmp['points'] = '+' . $te_val['points'];
                    $tmp['add_time'] = $te_val['add_time'];
                    $days_7 = 7 * 24 * 3600;
                    $time = $te_val['add_time'] + $days_7;
                    $hour_24 = 24 * 3600;
                    $day_time = $time - time();

                    if ($day_time < 0) {
                        $day_time = 0;
                    } elseif ($day_time > $hour_24) {
                        $day_time = $day_time / 24 / 3600 > 0 ? ceil($day_time / 24 / 3600) : 0;
                    } else {
                        $day_time = 1;
                    }

                    $tmp['day_time'] = $day_time;
                    $data['team'][] = $tmp;
                }
            }
        }
        
        $this->ajax_return('200','success',$data);
    }

    /**
     * 权益中心
     */
    public function rights_center(){
        $member_id = $this->member_info['member_id'];
        $data = [];
        $member = $this->member_model
            ->where(['member_id'=>$member_id])
            ->field('member_grade,experience')
            ->find();
        $member_grade = $member['member_grade'];
        $experience = $member['experience'];
        $grade = $this->grade_model->where(['grade'=>$member_grade])->find();
        if($member['member_grade'] < 6){
            if($member_grade == 6){
                $data['grade_experience'] = $grade['grade_points'];
                $data['to_experience'] = $grade['grade_points'];
                $data['between_experience'] = 0;
                $data['to_grade_name'] = $grade['grade_name'];
            }else{
                $to_grade = $this->grade_model
                    ->where(['grade_points'=>['gt',$experience]])
                    ->order('grade_points asc')
                    ->find();
                $data['grade_experience'] = $grade['grade_points'];
                $data['to_experience'] = $to_grade['grade_points'];
                $data['between_experience'] = $to_grade['grade_points'] - $experience;
                $data['to_grade_name'] = $to_grade['grade_name'];
            }
            $data['experience'] = $experience;
            $data['is_seller'] = 0;
        }else{
            if($member['member_grade'] == 6){
                $data['member_grade'] = $member['member_grade'];
            }
            $count = $this->member_model->where(['parent_id'=>$member_id,'member_grade'=>$member_grade])->count();
            $data['count'] = $count ? 3 - $count : 3;
            $data['is_seller'] = 1;
        }
        $grade_img = '';
        if(!empty($grade['grade_img'])){
            $grade_img = strexists($grade['grade_img'],'http')
                ? $grade['grade_img']
                : config('qiniu.buckets')['images']['domain'] . '/uploads/bank/' . $grade['grade_img'];
        }
        $data['grade_img'] = $grade_img;
        $data['grade_name'] = $grade['grade_name'];
        $data['is_alert'] = $this->member_model->where(['member_id'=>$member_id])->value('is_alert');
        if($data['is_alert'] == 1){
            $this->member_model
                ->where(['member_id'=>$this->member_info['member_id']])
                ->update(['is_alert'=>0]);
        }
        $this->ajax_return('200','success',$data);
    }

    /**
    *   我的团队
    **/
    public function my_team(){
        $member_id = $this->member_info['member_id'];
        $page = input('post.page',1,'intval');
        //数据分页
        $page_num = 10;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;
        }else{
            $limit = 0;
        }
        
        $member_data = $this->member_model
            ->with('info')
            ->where(['parent_id'=>$member_id])
            ->field('member_name,member_avatar,member_mobile,member_grade')
            ->limit($limit,$page_num)
            ->select()->toArray();

        $team = [];
        if(!empty($member_data)){
            foreach($member_data as $mem_key=>$mem_val){
                $tmp = [];
                $tmp['member_name'] = $mem_val['member_name'];
                if(!empty($mem_val['member_avatar'])){
                    $member_avatar = strexists($mem_val['member_avatar'],'http') ? $mem_val['member_avatar'] : config('qiniu.buckets')['images']['domain'].'/uploads/avatar/'.$mem_val['member_avatar'];
                }else{
                    $member_avatar = '';
                }
                $tmp['member_avatar'] = $member_avatar;
                $tmp['member_mobile'] = $mem_val['member_mobile'];
                $tmp['member_grade'] = $mem_val['member_grade'];
                $team[] = $tmp;
            }
        }
        //权值对应表
        $weight = ['1','5','8','12','17','23','70','210','630','1890','6000','18000'];
        //权值
        $member_info = $this->member_model->where(['parent_id'=>$member_id])->select()->toArray();
        //团队权值
        $total = 0;
        foreach($member_info as $mem_key=>$mem_val) {
            $total += $weight[$mem_val['member_grade'] - 1];
        }
        $data['total'] = $total;
        $data['team'] = $team;
        $this->ajax_return('200','success',$data);
    }

    /**
     *   商城返利顶部
     **/
    public function return_profit_top(){
        //会员等级
        $member_grade = $this->member_model->where(['member_id'=>$this->member_info['member_id']])->value('member_grade');

        //当月总收益
        $income_type = ['day_profit','week_profit','month_profit','contribute_profit','monitor_profit'];
        $total_income = $this->pl_model->whereTime('add_time','month')->where(['type'=>['in',$income_type],'member_id'=>$this->member_info['member_id']])->sum('points');
        $month_income = !empty($total_income) ? $total_income : 0;

        //当月所需最低消费
        $month_expend = $this->order_model->whereTime('payment_time','month')->where(['buyer_id'=>$this->member_info['member_id'],'order_state'=>['egt',20]])->sum('order_amount');
        $month_expend = !empty($month_expend) ? $month_expend : 0;
        $min_expend = 0;
        $amount = ['100','150','300','300','500','100','100','150','300','300','500'];
        if($member_grade < 2){
            $is_reach = 0;
            $member_amount = 0;
        }else{
            $member_amount = $amount[$member_grade-2];
            $is_reach = $month_expend - $member_amount > 0 ? 1 : 0;
        }
        

        $top_data = [
            'member_grade' => $member_grade,
            'month_income' => $month_income,
            'min_expend' => $min_expend,
            'max_expend' => $member_amount,
            'month_expend' => $month_expend,
            'is_reach' => $is_reach
        ];

        $this->ajax_return('200','success',$top_data);
    }

    /**
     *   分红返利
     **/
    public function return_profit(){
        $page = input('post.page',1,'intval');
        $type = input('post.type','','trim');
        $profit_type = ['day','week','month','contribute','monitor'];

        if(!in_array($type, $profit_type)){
            $this->ajax_return('11800','type is not exist');
        }
        $data = [];
        switch($type) {
            case 'day':
                $data = $this->pl_model->get_profit($this->member_info['member_id'],'day_profit',$page);
                break;
            case 'week':
                $data = $this->pl_model->get_profit($this->member_info['member_id'],'week_profit',$page);
                break;
            case 'month':
                $data = $this->pl_model->month_profit($this->member_info['member_id'],'month_profit');
                break;
            case 'contribute':
                $data = $this->pl_model->contribute_profit($this->member_info['member_id'],'contribute_profit');
                break;
            case 'monitor':
                $member_grade = $this->member_model->where(['member_id'=>$this->member_info['member_id']])->value('member_grade');
                if($member_grade < 7){
                    $this->ajax_return('11900','invalid member');
                }
                $data = $this->pl_model->monitor_profit($this->member_info['member_id'],'monitor_profit');
                break;
            default:
                break;
        }
        $this->ajax_return('200','success',$data);
    }

    /**
     * 资金管理  提现管理数据返回
     */
    public function cash_management_view(){
        $member_id = $this->member_info['member_id'];
        $data = [];
        $auth_name = $this->member_auth->where(['member_id'=>$member_id,'auth_state'=>1])->value('auth_name');
        $data['is_auth'] = $this->member_auth($member_id);
        //消费者和消费商每月只能进行一次提现
        $pdc_payment_state = $this->cash_model
            ->where(['pdc_member_id'=>$member_id])
            ->whereTime('pdc_add_time','month')
            ->value('pdc_payment_state');
        $data['is_withdrawn'] = strlen($pdc_payment_state) > 0 ? $pdc_payment_state : 3;
        $data['auth_name'] = isset($auth_name) ? $auth_name : '';
        $this->ajax_return('200','success',$data);
    }

    /**
     * 资金管理  提现管理
     */
    public function cash_management(){
        $member_id = $this->member_info['member_id'];
        $member = $this->member_model->where(['member_id'=>$member_id])->find();
        if(!$member['is_auth']){
            $this->ajax_return('11827','the member is not auth');
        }
        //消费者和消费商每月只能进行一次提现
       $pdc_add_time = $this->cash_model
            ->where(['pdc_member_id'=>$member_id,'pdc_payment_state'=>['in',[0,1]]])
            ->whereTime('pdc_add_time','month')
            ->value('pdc_add_time');
        if(!empty($pdc_add_time)){
            $this->ajax_return('11828','the member has been withdrawn this month');
        }
        $bank_name = input('post.bank_name');
        if(empty($bank_name)){
            $this->ajax_return('11820','empty bank_name');
        }
        $bank_no = input('post.bank_no');
        if(empty($bank_no)){
            $this->ajax_return('11821','invalid bank_no');
        }
        $bank_user = input('post.bank_user');
        if(empty($bank_user) || words_length($bank_user) < 2){
            $this->ajax_return('11822','invalid bank_user');
        }
        //判断持卡人与实名认证的姓名是否一致
        $auth_name = $this->member_auth->where(['member_id'=>$member_id,'auth_state'=>1])->value('auth_name');
        if($bank_user !== $auth_name){
            $this->ajax_return('11829','the bank_user is unequal to auth_name');
        }
        $amount = input('post.amount');
        if(empty($amount)){
            $this->ajax_return('11823','invalid amount');
        }
        if($amount < 10){
            $this->ajax_return('11836','amount must be greater than 10.');
        }
        if($amount > $member['av_predeposit']){
            $this->ajax_return('11824','amount is more than av_predeposit');
        }
        //根据扣税规则计算扣除个人所得税
        if($amount - 3500 <= 1500 && $amount - 3500 > 0){
            $dec_amount = ($amount - 3500)*0.03;
        }elseif($amount - 3500 <= 4500 && $amount - 3500 > 1500){
            $dec_amount = ($amount - 3500)*0.1 - 105;
        }elseif($amount - 3500 > 4500 && $amount - 3500 <= 9000){
            $dec_amount = ($amount - 3500)*0.2 - 555;
        }elseif($amount - 3500 > 9000 && $amount - 3500 <= 35000){
            $dec_amount = ($amount - 3500)*0.25 - 1005;
        }elseif($amount - 3500 > 35000 && $amount - 3500 <= 55000){
            $dec_amount = ($amount - 3500)*0.3 - 2755;
        }elseif($amount - 3500 > 55000 && $amount - 3500 <= 80000){
            $dec_amount = ($amount - 3500)*0.35 - 5505;
        }elseif($amount - 3500 > 80000){
            $dec_amount = ($amount - 3500)*0.45 - 13505;
        }else{
            $dec_amount = 0;
        }
        if($dec_amount !== 0){
            $dec_amount_exp = explode('.',$dec_amount);
            if(count($dec_amount_exp) == 2){
                $dec_amount_exp[1] = substr($dec_amount_exp[1],0,2);
            }
            $dec_amount = implode('.',$dec_amount_exp);
        }
        $dec_amount_get = input('post.dec_amount');
        $dec_amount_get = $dec_amount_get ? $dec_amount_get : 0;
        //两个个税值进行比较，增加准确度（两位小数）
        if(round($dec_amount_get,2) !== round($dec_amount,2)){
            $this->ajax_return('11835','dec_amount false');
        }
        //实际提现金额
        $pdc_amount = $amount - $dec_amount;
        $this->member_model->startTrans();
        $this->cash_model->startTrans();
        //在可用余额中扣除提现金额
        $res = $this->member_model->where(['member_id'=>$member_id])->setDec('av_predeposit',$amount);
        if($res === false){
            $this->ajax_return('11825','av_predeposit dec false');
        }
        $pdc_sn = $this->order_model->make_paysn($member_id);
        $pl_data = [
            'dec_amount' => $dec_amount,
            'amount'     => $amount,
            'pdc_amount' => $pdc_amount
        ];
        //记录提现日志
        $data = [
            'pdc_sn'            => $pdc_sn,
            'pdc_member_id'     => $member_id,
            'pdc_member_name'   => $member['member_name'],
            'pdc_amount'        => $pdc_amount,
            'pdc_bank_name'     => $bank_name,
            'pdc_bank_no'       => $bank_no,
            'pdc_bank_user'     => $bank_user,
            'pdc_add_time'      => time(),
            'pdc_desc'          => '余额转出',
            'pdc_data'          => json_encode($pl_data)
        ];
        $cash = $this->cash_model->save($data);
        if($cash === false){
            $this->member_model->rollback();
            $this->ajax_return('11826','cash log false');
        }
        $this->member_model->commit();
        $this->cash_model->commit();
        $this->ajax_return('200','success',[]);
    }

    /**
     * 提现前获取认证信息
     */
    public function get_auth_bank(){
        $member_id = $this->member_info['member_id'];
        $member = model('Member')->member_get_info_by_id($member_id);
        if(!$member['is_auth']){
            $this->ajax_return('11827','the member is not auth');
        }
        $member_auth = $this->member_auth->member_auth_last_verify($member_id);
        $data = [
            'bank_name'=>$member_auth['auth_name'],
            'bank_no' => $member_auth['bank_no'],
        ];
        $this->ajax_return('200','success',$data);
    }
    /**
     * 提现优化
     */
    public function withdraw(){
        $member_id = $this->member_info['member_id'];
        $member = model('Member')->member_get_info_by_id($member_id);
        if(!$member['is_auth']){
            $this->ajax_return('11827','the member is not auth');
        }
        /*$pdc_withdraw_times = $this->cash_model->cash_withdraw_month_count($member_id);
        if($pdc_withdraw_times){
            $this->ajax_return('11828','the member has been withdrawn this month');
        }*/
        $bank_name = input('post.bank_name');
        if(empty($bank_name)){
            $this->ajax_return('11820','empty bank_name');
        }
        $member_auth = $this->member_auth->member_auth_last_verify($member_id);
        $bank_no = input('post.bank_no');
        if(empty($bank_no)){
            $this->ajax_return('11821','invalid bank_no');
        }
        $bank_user = input('post.bank_user');
        if(empty($bank_user) || words_length($bank_user) < 2){
            $this->ajax_return('11822','invalid bank_user');
        }
        //判断持卡人与实名认证的姓名是否一致
        if($bank_user !== $member_auth['auth_name']){
            $this->ajax_return('11829','the bank_user is unequal to auth_name');
        }
        $amount = input('post.amount');
        if(empty($amount)){
            $this->ajax_return('11823','invalid amount');
        }
        /*if($amount < 10){
            $this->ajax_return('11836','amount must be greater than 10.');
        }*/
        if($amount > $member['av_predeposit']){
            $this->ajax_return('11824','amount is more than av_predeposit');
        }
        $dec_amount = $this->cash_model->personal_income_tax($amount);
        $dec_amount_get = input('post.dec_amount');
        $dec_amount_get = $dec_amount_get ? $dec_amount_get : 0;
        //两个个税值进行比较，增加准确度（两位小数）
        if(round($dec_amount_get,2) !== round($dec_amount,2)){
            $this->ajax_return('11835','dec_amount false');
        }
        if(!$this->cash_model->withdraw_update($member_id,$amount,$dec_amount,$bank_name,$bank_no,$bank_user)){
            $this->ajax_return('11826','cash log false');
        }
        $this->ajax_return('200','success',[]);
    }
    /**
     * 资金管理  消费商积分转余额页面返回
     */
    public function points_to_cash_view(){
        $member_id = $this->member_info['member_id'];
        $data = [];
        $member = $this->member_model
            ->where(['member_id'=>$member_id])
            ->field('is_auth,points,av_predeposit,member_grade')
            ->find();
        //判断当前会员身份  消费商/消费者
        $seller_role = model('StoreSeller')
            ->where(['member_id'=>$member_id,'seller_state'=>['in',[2,3]]])
            ->value('seller_role');
        if(empty($seller_role) || $member['member_grade'] < 7 || $seller_role == 2){
            $data['is_seller'] = 0;
        }else{
            $data['is_seller'] = 1;
        }
        //累计转出积分
        $points = model('PointsLog')
            ->where(['type'=>'points_to_cash','member_id'=>$member_id])
            ->whereTime('add_time','month')
            ->sum('points');
        $data['points'] = !empty($points) ? $points : 0;
        $this->ajax_return('200','success',$data);
    }

    /**
     * 资金管理  消费商积分转余额 提交
     */
    public function points_to_cash(){
        $member_id = $this->member_info['member_id'];
        $member = $this->member_model
            ->where(['member_id'=>$member_id])
            ->field('is_auth,points,av_predeposit,member_grade')
            ->find();
        //判断当前会员身份  消费商/消费者
        $seller_role = model('StoreSeller')
            ->where(['member_id'=>$member_id,'seller_state'=>['in',[2,3]]])
            ->value('seller_role');
        if(empty($seller_role) || $member['member_grade'] < 7 || $seller_role == 2){
            $this->ajax_return('11830','the member is not seller');
        }
        $points = input('post.points');
        if(empty((int)$points)){
            $this->ajax_return('11834','invalid points');
        }
        $this->member_model->startTrans();
        model('PointsLog')->startTrans();
        model('PdLog')->startTrans();
        $data = [
            'av_predeposit' => ['exp','av_predeposit+'.$points],
            'points'        => ['exp','points-'.$points]
        ];
        $member_data = $this->member_model->where(['member_id'=>$member_id])->update($data);
        if($member_data === false){
            $this->ajax_return('11831','points is modify false');
        }
        $log = [
            'member_id'     => $member_id,
            'member_mobile' => $this->member_info['member_mobile'],
            'points'        => $points,
            'add_time'      => time(),
            'type'          => 'points_to_cash',
            'pl_desc'       => '积分转余额'
        ];
        $points_log = model('PointsLog')->save($log);
        if($points_log === false){
            $this->member_model->rollback();
            $this->ajax_return('11832','points log false');
        }
        $pd_log = [
            'member_id'     => $member_id,
            'member_mobile' => $this->member_info['member_mobile'],
            'av_amount'     => $points,
            'type'          => 'points_to_cash',
            'add_time'      => time(),
            'log_desc'      => '积分转余额'
        ];
        $pd = model('PdLog')->save($pd_log);
        if($pd === false){
            $this->member_model->rollback();
            model('PointsLog')->rollback();
            $this->ajax_return('11833','PdLog false');
        }
        $this->member_model->commit();
        model('PointsLog')->commit();
        model('PdLog')->commit();
        if(!empty($log)){
            Hook::listen('create_points_log',$log);
        }
        $this->ajax_return('200','success',[]);
    }

    /**
     * 资金明细
     */
    public function cash_detail(){
        $page = input('post.page',1,'intval');
        $type = input('post.type','','trim');
        //数据分页
        $page_num = 10;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;
        }else{
            $limit = 0;
        }
        $member_id = $this->member_info['member_id'];
        $data = [];
        if($type == 'cash'){
            $cash = $this->cash_model
                ->where(['pdc_member_id'=>$member_id])
                ->order('pdc_add_time desc')
                ->field('pdc_add_time,pdc_amount,pdc_desc,pdc_payment_state,pdc_data')
                ->limit($limit,$page_num)
                ->select()->toArray();
            foreach($cash as $k=>$val){
                $pdc_data = json_decode($cash[$k]['pdc_data'],true);
                $cash[$k]['pdc_add_time'] = date('Y/m/d H:i',$cash[$k]['pdc_add_time']);
                $cash[$k]['amount'] = $pdc_data['amount'];
                $cash[$k]['dec_amount'] = $pdc_data['dec_amount'];
                $cash[$k]['pdc_amount'] = $pdc_data['pdc_amount'];
                unset($cash[$k]['pdc_data']);
            }
            $data['cash'] = $cash;
        }
        if($type == 'points'){
            $points = $this->pl_model
                ->where(['type'=>'points_to_cash','member_id'=>$member_id])
                ->field('pl_desc,add_time,points')
                ->order('add_time desc')
                ->limit($limit,$page_num)
                ->select();
            foreach($points as $k=>$val){
                $points[$k]['add_time'] = date('Y/m/d H:i',$points[$k]['add_time']);
            }
            $data['points'] = $points;
        }
        $this->ajax_return('200','success',$data);
    }
    /**
     * 判断当前会员是否实名认证
     */
    private function member_auth($member_id){
        $is_auth = $this->member_model->where(['member_id'=>$member_id])->value('is_auth');
        if($is_auth == 1){
            $auth = 1;
        }else{
            $auth_state = $this->member_auth
                ->where(['member_id'=>$this->member_info['member_id']])
                ->order('apply_time desc')->value('auth_state');
            if($auth_state === 0){
                $auth = 0;
            }elseif($auth_state === 2){
                $auth = 2;
            }else{
                $auth = 3;
            }
        }
        return $auth;
    }

    /**
     * 修改密码
     */
    public function change_password(){
        $mobile = $this->member_info['member_mobile'];
        $code = input('post.code','','trim');
        if(empty($code)){
            $this->ajax_return('12050','empty code');
        }
        //验证验证码
//         if($mobile == '13088888888'){
//         	Cache::set('sms'.$mobile,'888888',300);
//         }
//         $cache_code = Cache::pull('sms'.$mobile);
//         if (is_null($cache_code) || $cache_code != $code) {
//         	$this->ajax_return('12057','invalid code');
//         }
        $new_password = input('post.new_password','','trim');
        if(empty($new_password) || strlen($new_password) < 6 || strlen($new_password) > 20){
            $this->ajax_return('12051','invalid new_password');
        }
        $re_password = input('post.re_password','','trim');
        if(empty($re_password) || strlen($re_password) < 6 || strlen($re_password) > 20){
            $this->ajax_return('12052','invalid re_password');
        }
        if($new_password !== $re_password){
            $this->ajax_return('12053','re_password is different with new_password');
        }
        $member_info = $this->member_model
            ->where(['member_mobile'=>$mobile])
            ->field('member_password,encrypt')->find();
        $salt = $member_info['encrypt'];
        if(empty($salt)){
            $salt = random_string(6,5);
        }
        $new_pwd = sp_password($new_password,$salt);
        //判断新旧密码是否一样
        if($member_info['member_password'] == $new_pwd){
            $this->ajax_return('12055','new_password is not different with old_password');
        }
        $res = $this->member_model
            ->where(['member_mobile'=>$this->member_info['member_mobile']])
            ->update(['member_password'=>$new_pwd,'encrypt'=>$salt]);
        if($res === false){
            $this->ajax_return('12056','failed to change password');
        }
        $this->ajax_return('200','success','');
    }
}
