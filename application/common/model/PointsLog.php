<?php
namespace app\common\model;

use Lib\Http;
use think\Exception;
use think\Hook;
use think\Model;
use think\Cache;
class PointsLog extends Model
{
    // 关闭自动时间格式化
    protected $createTime = false;
    protected $message_model;
    protected function initialize(){
        parent::initialize();
        $this->message_model = model('Message');
    }
    public function save_log($member_id,$member_mobile,$type,$points,$pl_desc,$pl_data = ""){
        $points_log = [
            'member_id'     => $member_id,
            'member_mobile' => $member_mobile,
            'type'          => $type,
            'points'        => $points,
            'add_time'      => time(),
            'pl_desc'       => $pl_desc,
            'pl_data'       => $pl_data
        ];

        $result = $this->create($points_log);
        if($result === false){
            return false;
        }
        if(!empty($points_log)){
            Hook::listen('create_points_log',$points_log);
        }
        return true;
    }

    /**
     * @param $member_id
     * @param $member_mobile
     * @param $points 创建订单所用积分
     * @param $points2 创建订单所得冻结积分
     * @param $pl_data 数据来源
     * @return bool
     */
    public function save_logs($member_id,$member_mobile,$points,$points2,$pl_data){
        $points_log = [];
        if($points > 0){
            $points_log[] =  ['member_id'=>$member_id,'member_mobile'=>$member_mobile,'type' => 'order_pay','points'=>$points,'add_time' => time(),'pl_desc' =>'用户支付订单','pl_data'=>''];
        }
        if($points2 > 0){
            $points_log[] = ['member_id'=>$member_id,'member_mobile'=>$member_mobile,'type' => 'order_freeze','points'=>$points2,'add_time' => time(),'pl_desc' =>'创建订单获得冻结积分','pl_data'=>$pl_data];
        }
        if(!empty($points_log)){
            $result = $this->saveAll($points_log);
            if($result === false){
                return false;
            }
        }
        if(!empty($points_log)){
            Hook::listen('create_points_log',$points_log);
        }
        return true;
    }

    //记录不同会员的两条日志
    public function save_points_log($member_id,$member_id2,$member_mobile,$member_mobile2,$points,$points2,$desc1,$desc2,$pl_data = ""){
        $points_log = [
            ['member_id'=>$member_id2,'member_mobile'=>$member_mobile2,'type' => 'order_freeze','points'=>$points2,'add_time' => time(),'pl_desc' =>$desc2,'pl_data'=>$pl_data],
            ['member_id'=>$member_id,'member_mobile'=>$member_mobile,'type' => 'order_return_parent','points'=>$points,'add_time' => time(),'pl_desc' =>$desc1,'pl_data'=>$pl_data],
        ];

        $result = $this->saveAll($points_log);
        if($result === false){
            return false;
        }
        if(!empty($points_log)){
            Hook::listen('create_points_log',$points_log);
        }
        return true;
    }

    /**
     *   获取分红奖励
     **/
    public function get_profit($member_id,$type,$page){
        //数据分页
        $count = $this->where(['member_id'=>$member_id])->count();
        $page_num = 5;
        $page_count = ceil($count / $page_num);
        $limit = 0;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;
        }else{
            $limit = 0;
        }

        $data = [];

        $pl_data = $this
            ->field('points,add_time,pl_desc,pl_data')
            ->where(['member_id'=>$member_id,'type'=>$type])
            ->whereTime('add_time','month')
            ->order('add_time desc')
            ->limit($limit,$page_num)
            ->select()->toArray();

        $data[$type] = [];
        if(!empty($pl_data)){
            foreach($pl_data as $pl_key=>$pl_val){
                $tmp = [];
                $add_time = date('m/d',$pl_val['add_time']);
                $json_data = json_decode($pl_val['pl_data'],true);
                $tmp['profit'] = '+'.$json_data['profit'];
                $tmp['grade_profit'] = '+'.$json_data['grade_profit'];
                $data[$type][] = [
                    'day_sum'  => '+'.$pl_val['points'],
                    'add_time' => $add_time,
                    'detail'   => $tmp,
                    'is_give'  => 1
                ];
            }
        }
        if($page == 1){
            $member_grade = model('Member')->where(['member_id'=>$member_id])->value('member_grade');
            $grade_info = Cache::get('grade');
            if(empty($grade_info)){
                $grade_info = model('Grade')->select()->toArray();
                $grade_info = Cache::set('grade',$grade_info);
            }
            if($member_grade > 1){
                //判断上月是否达标
                $last_consume = model('Order')
                    ->whereTime('payment_time','last month')
                    ->where(['order_state'=>['gt',10],'buyer_id'=>$member_id])
                    ->sum('order_amount');
                $last_consume = !empty($last_consume) ? $last_consume : 0;

                $is_reach = 0;
                foreach($grade_info as $gr_key=>$gr_val){
                    if($member_grade == $gr_val['grade']){
                        $is_reach = $last_consume >= $gr_val['grade_min_expend'] ? 1 : 0;
                        break;
                    }
                }

                if($is_reach > 0){
                    $today = $this->whereTime('add_time','today')
                        ->where(['member_id'=>$member_id,'type'=>$type])->find();
                    if(empty($today)){
                        $day_pl = [
                            'day_sum'  => '',
                            'add_time' => date('m/d'),
                            'detail'   => ['profit'=>'','grade_profit'=>''],
                            'is_give'  => 0,
                            'is_reach' => $is_reach
                        ];
                        if($type == 'week_profit'){
                            $days = date('t');
                            $day = date('d');
                            if(date('w') == 0){
                                $day_pl['add_time'] = date('m/d');
                            }else{
                                $week = 7 - date('w');
                                $num = $day + $week;
                                if($num < $days){
                                    $day_pl['add_time'] = date('m/').$num;
                                }else{
                                    $day_pl = [];
                                }
                            }
                        }elseif($type == 'day_profit'){
                            $day_pl['add_time'] = date('m/d');
                        }else{
                            $day_pl = [];
                        }
                        if(!empty($day_pl)){
                            array_unshift($data[$type],$day_pl);
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     *   获取月分红奖励
     **/
    public function month_profit($member_id,$type){
        //上月消费是否达标(本月)
        $member = model('Member')->where(['member_id'=>$member_id])->field('member_grade,member_time')->find();
        $member_grade = $member['member_grade'];
        $member_time = date('m',$member['member_time']);

        $data = ['the_month'=>[],'last_month'=>[]];
        $detail = ['profit'=>0,'grade_profit'=>0];

        $grade_info = Cache::get('grade');
        if(empty($grade_info)){
            $grade_info = model('Grade')->select()->toArray();
            $grade_info = Cache::set('grade',$grade_info);
        }

        $last_consume = model('Order')
            ->whereTime('payment_time','last month')
            ->where(['order_state'=>['gt',10],'buyer_id'=>$member_id])
            ->sum('order_amount');
        $last_consume = !empty($last_consume) ? $last_consume : 0;

        $is_reach = 0;
        if($member_grade > 1){
            foreach($grade_info as $gr_key=>$gr_val){
                if($member_grade == $gr_val['grade']){
                    $is_reach = $last_consume >= $gr_val['grade_min_expend'] ? 1 : 0;
                    break;
                }
            }
        }
        if($is_reach > 0) { //判断本月是否达标
            $has_info = $this->whereTime('add_time','month')->where(['member_id'=>$member_id,'type'=>$type])->order('add_time desc')->find();
            if(!empty($has_info)){
                $add_time = date('m/d',$has_info['add_time']);
                $json_data = json_decode($has_info['pl_data'],true);
                $detail['profit'] = '+'.$json_data['profit'];
                $detail['grade_profit'] = '+'.$json_data['grade_profit'];
                $day_sum = empty($has_info['points']) ? '' : '+'.$has_info['points'];
                $is_give = 1;
            }else{
                $detail = ['grade_profit'=>0,'profit'=>0];
                $add_time = 0;
                $day_sum = '';
                $is_give = 0;
            }
        }else{
            $is_reach = 0;
            $detail = ['grade_profit'=>0,'profit'=>0];
            $add_time = 0;
            $day_sum = '';
            $is_give = 0;
        }

        $data['the_month'] = [
            'is_reach'  => $is_reach,
            'detail'    => $detail,
            'day_sum'   => $day_sum,
            'add_time'  => $add_time,
            'is_give'   => $is_give,
            'is_new'    => 0
        ];

        //上月是否存在分红
        $last_month = $this->whereTime('add_time','last month')
            ->where(['member_id'=>$member_id,'type'=>$type])
            ->order('add_time desc')
            ->find();
        if(!empty($last_month)){
            $is_reach = 1;
            $add_time = date('m/d',$last_month['add_time']);
            $json_data = json_decode($last_month['pl_data'],true);
            $detail['profit'] = '+'.$json_data['profit'];
            $detail['grade_profit'] = '+'.$json_data['grade_profit'];
            $day_sum = empty($last_month['points']) ? '' : '+'.$last_month['points'];
            $is_give = 1;
        }else{
            $is_reach = 0;
            $detail = ['grade_profit'=>0,'profit'=>0];
            $add_time = 0;
            $day_sum = '';
            $is_give = 0;
        }

        $data['last_month'] = [
            'is_reach'  => $is_reach,
            'detail'    => $detail,
            'day_sum'   => $day_sum,
            'add_time'  => $add_time,
            'is_give'   => $is_give,
            'is_new'    => 0
        ];
        if($member_time == date('m')){
            $data['last_month']['is_new'] = 1;
        }

        return $data;
    }

    /**
     *   获取杰出贡献奖
     **/
    public function contribute_profit($member_id,$type){
        $member = model('Member')->where(['member_id'=>$member_id])->field('member_grade,member_time')->find();
        $member_grade = $member['member_grade'];
        $member_time = date('m',$member['member_time']);
        $data = ['min_expend'=>[],'the_month'=>[],'last_month'=>[]];
        $grade_info = Cache::get('grade');
        if(empty($grade_info)){
            $grade_info = model('Grade')->select()->toArray();
            $grade_info = Cache::set('grade',$grade_info);
        }
        //杰出贡献奖对应累加额度
        $plus = ['600','1000','1500','2000','2500','3000'];
        foreach($grade_info as $gr_key=>$gr_val){
            if($member_grade == $gr_val['grade']){
                foreach($plus as $key=>$val){
                    $plus[$key]+=$gr_val['grade_min_expend'];
                }
                break;
            }
        }
        $data['min_expend'] = $plus;

        $month_consume = model('Order')->whereTime('payment_time','month')->where(['order_state'=>['gt',10],'buyer_id'=>$member_id])->sum('order_amount');
        $month_consume = !empty($month_consume) ? $month_consume : 0;
        $data['month_consume'] = $month_consume;
        $is_reach = 0;
        foreach($grade_info as $gr_key=>$gr_val){
            if($member_grade == $gr_val['grade']){
                if($member_grade > 6){
                    $member_grade = $member_grade - 5;
                }

                if($month_consume >= $gr_val['grade_min_expend']+600){
                    $is_reach = 1;
                }
                break;
            }
        }

        //本月消费是否达标
        if($is_reach > 0) {
            $has_info = $this->whereTime('add_time','month')->order('add_time desc')->where(['member_id'=>$member_id,'type'=>$type])->find();
            if(!empty($has_info)){
                $add_time = date('m/d',$has_info['add_time']);
                $day_sum = empty($has_info['points']) ? '' : '+'.$has_info['points'];
                $is_give = 1;
            }else{
                $is_reach = 1;
                $add_time = 0;
                $day_sum = '';
                $is_give = 0;
            }
        }else{
            $is_reach = 0;
            $add_time = 0;
            $day_sum = '';
            $is_give = 0;
        }

        $data['the_month'] = [
            'is_reach'  => $is_reach,
            'day_sum'   => $day_sum,
            'add_time'  => $add_time,
            'is_give'   => $is_give,
            'is_new'    => 0
        ];
        //上月是否存在分红
        $last_month = $this->whereTime('add_time','last month')
            ->where(['member_id'=>$member_id,'type'=>$type])
            ->order('add_time desc')
            ->find();
        if(!empty($last_month)){
            $is_reach = 1;
            $add_time = date('m/d',$last_month['add_time']);
            $day_sum = empty($last_month['points']) ? '' : '+'.$last_month['points'];
            $is_give = 1;
        }else{
            $is_reach = 0;
            $add_time = 0;
            $day_sum = '';
            $is_give = 0;
        }

        $data['last_month'] = [
            'is_reach'  => $is_reach,
            'day_sum'   => $day_sum,
            'add_time'  => $add_time,
            'is_give'   => $is_give,
            'is_new'    => 0
        ];
        if($member_time == date('m')){
            $data['last_month']['is_new'] = 1;
        }

        return $data;
    }

    /**
     *   高管工资
     **/
    public function monitor_profit($member_id,$type){
        //个人消费额
        $self_amount = ['25000','30000','50000','100000'];
        //团队权值和
        $weight_plus = ['1000','4000','13000','40000'];
        //权值对应表
        $weight = ['1','5','8','12','17','23','70','210','630','1890','6000','18000'];

        $member_model = model('Member');
        $member = model('Member')->where(['member_id'=>$member_id])->field('member_grade,member_time')->find();
        $member_grade = $member['member_grade'];
        $member_time = date('m',$member['member_time']);

        $data = $pl_data = [];
        //个人消费
        $amount = model('Order')->whereTime('payment_time','month')->where(['buyer_id'=>$member_id,'order_state'=>['gt',10]])->sum('order_amount');
        $amount = empty($amount) ? 0 : $amount;

        //团队权值
        $total = 0;
        $team_info = $member_model->field('member_id,member_grade')->where(['parent_id'=>$member_id])->select()->toArray();
        for($i=1;$i<=12;$i++){
            $num = 0;
            foreach($team_info as $team_key=>$team_val){
                if($i == $team_val['member_grade']){
                    $num++;
                }
            }

            $total += $weight[$i-1] * $num;
        }

        $is_reach = $level = 0;
        $datas = ['the_month'=>[],'last_month'=>[]];

        if($amount >= 100000 && $total >= 40000){
            $is_reach = 1;
            $level = 4;
        }elseif($amount >= 50000 && $total >= 13000){
            $is_reach = 1;
            $level = 3;
        }elseif($amount >= 30000 && $total >= 4000){
            $is_reach = 1;
            $level = 2;
        }elseif($amount >= 25000 && $total >= 1000){
            $is_reach = 1;
            $level = 1;
        }

        //上月是否达标
        if($is_reach > 0) {
            $has_info = $this->whereTime('add_time','month')
                ->where(['member_id'=>$member_id,'type'=>$type])
                ->order('add_time desc')->find();
            if(!empty($has_info)){
                $add_time = date('m/d',$has_info['add_time']);
                $day_sum = empty($has_info['points']) ? '' : '+'.$has_info['points'];
                $is_give = 1;
            }else{
                $is_reach = 1;
                $add_time = 0;
                $day_sum = '';
                $is_give = 0;
            }
        }else{
            $is_reach = 0;
            $add_time = 0;
            $day_sum = '';
            $is_give = 0;
        }

        $datas['the_month'] = [
            'is_reach'  => $is_reach,
            'day_sum'   => $day_sum,
            'add_time'  => $add_time,
            'is_give'   => $is_give,
            'is_new'    => 0
        ];
        //上月是否存在分红
        $last_month =  $this->whereTime('add_time','last month')
            ->where(['member_id'=>$member_id,'type'=>$type])
            ->order('add_time desc')->find();

        if(!empty($last_month)){
            $is_reach = 1;
            $add_time = date('m/d',$last_month['add_time']);
            $day_sum = empty($last_month['points']) ? '' : '+'.$last_month['points'];
            $is_give = 1;
        }else{
            $is_reach = 0;
            $add_time = 0;
            $day_sum = '';
            $is_give = 0;
        }

        $datas['last_month'] = [
            'is_reach'  => $is_reach,
            'day_sum'   => $day_sum,
            'add_time'  => $add_time,
            'is_give'   => $is_give,
            'is_new'    => 0
        ];
        if($member_time == date('m')){
            $datas['last_month']['is_new'] = 1;
        }

        $diff_amount = $self_amount[$level] - $amount <= 0 ? 0 : $self_amount[$level] - $amount;
        $diff_weight = $weight_plus[$level] - $total <= 0 ? 0 : $weight_plus[$level] - $total;
        $data[$type] = [
            'level' => $level,
            'diff_amount' => $diff_amount,
            'self_amount' => $self_amount[$level],
            'diff_weight' => $diff_weight,
            'self_weight' => $weight_plus[$level],
            'detail' => $datas
        ];

        return $data;
    }

    /**
     * 用户支付成功增加冻结积分
     * @param $buyer_id
     * @param $points
     * @param array $goods_name
     * @return mixed
     */
    public function add_log_after_pay($buyer_id,$points,$goods_name=[]){
        if(empty($goods_name)){
            return false;
        }
        foreach($goods_name as $good){
            if(!$good['goods_present']){
                continue;
            }
            $pl_log = [
                'member_id' => $buyer_id,
                'points'    => $good['goods_present'],
                'add_time'  => time(),
                'pl_desc'   => '用户下单增加冻结积分',
                'type'      => 'order_freeze',
                'pl_data'   => json_encode(['goods_name'=>$good['goods_name']])
            ];
            $res = $this->create($pl_log);
            if(!$res){
                return false;
            }
        }
        return true;
    }
    public function add_parent_log_after_pay($parent_id,$points,$goods_name=[]){
        if(empty($goods_name)){
            return false;
        }
        foreach($goods_name as $good){
            if($good['goods_present'] == 0){
                continue;
            }
            $pl_log = [
                'member_id' => $parent_id,
                'points'    => $good['goods_present'],
                'add_time'  => time(),
                'pl_desc'   => '用户消费赠送上级冻结积分',
                'type'      => 'order_return_parent',
                'pl_data'   => json_encode(['goods_name'=>$good['goods_name']])
            ];
            if(!$this->create($pl_log)){
                return false;
            }
            $body = json_encode(['title'=>'积分奖励','points'=>'+'.$good['goods_parent_points']]);
            $message = [
                'from_member_id' => 0,
                'to_member_id'   => $parent_id,
                'message_title'  => '积分奖励',
                'message_time'   => time(),
                'message_state'  => 0,
                'message_type'   => 4,
                'is_more'        => 0,
                'is_push'        => 2,
                'message_body'   => $body,
                'push_title'     => '',
                'push_detail'    => '您的家族成员消费奖励您'.$good['goods_parent_points'].'积分'
            ];
            $res = $this->message_model->insertGetId($message);
            if(!$res){
                return false;
            }
        }
        return true;
    }
    /**
     * 获取合作伙伴用户总积分
     * @param $member_id
     * @return bool
     */
    public function get_parterner_user_points($member_id){
        $user = model('ThirdMember')->getByMemberId($member_id);
        //不是合作伙伴用户不用处理
        if(!$user){
            return false;
        }
        $parterner_id = model('ThirdMember')->where(['member_id'=>$member_id])->value('parterner_id');
        $parterner = model('ThirdAuthToken')->field('parterner_appid,parterner_public_key,points_get_url')->getById($parterner_id);
        $parterner_appid = $parterner['parterner_appid'];
        $parterner_public_key = $parterner['parterner_public_key'];
        $points_get_url = $parterner['points_get_url'];
        if(!$points_get_url){
            return false;
        }
        $data = [
            'appid'=>$parterner_appid,
            'data'=>[
                'mobile'=>$user->member_mobile,
                'timestamp'=>time(),
            ]
        ];
        $data = json_encode($data);
        $pu_key = openssl_pkey_get_public($parterner_public_key);//这个函数可用来判断公钥是否是可用的
        openssl_public_encrypt($data,$encrypted,$pu_key);//公钥加密
        $encrypted = base64_encode($encrypted);
        try{
            $res = Http::ihttp_post($points_get_url,['data'=>$encrypted]);
            $result = json_decode($res['content'],true);
            if ($result['code']!='200') {
                return false;
            }
            return $result['data'];
        }catch (\Exception $e){
            return false;
        }
    }

    /**
     * 合作伙伴用户和常仁用户总积分统一，记录差额积分变动
     * @param $member_id
     * @return bool
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function merge_parterner_user_points($member_id){
        $parterner_user_points = $this->get_parterner_user_points($member_id);
        if($parterner_user_points === false){
            return true;
        }
        $member_model = model('Member');
        $points = $member_model->where(['member_id'=>$member_id])->field('points,parterner_reserved_points')->find();
        $parterner_user_points_fixed = $parterner_user_points + $points['parterner_reserved_points'];
        $imbanlance_points = $parterner_user_points_fixed - $points['points'];
        $imbanlance_points_abs = abs($imbanlance_points);
        if($imbanlance_points_abs == 0){
           return true;
        }
        $this->startTrans();
        $member_model->startTrans();
        if($imbanlance_points > 0){
            $result = $member_model->where(['member_id'=>$member_id])->setInc('points',$imbanlance_points_abs);
            if(empty($result)){
                return false;
            }
            $data = [
                'member_id' => $member_id,
                'add_time' => time(),
                'pl_desc'  => '与合作伙伴的差额积分(用户总积分补差额)',
                'type'   =>'parterner_imbanlance_add',
                'points' => $imbanlance_points_abs
            ];
            $result = $this->create($data);
            if($result === false){
                $member_model->rollback();
                return false;
            }
        }
        if($imbanlance_points < 0){
            $result = $member_model->where(['member_id'=>$member_id])->setDec('points',$imbanlance_points_abs);
            if(empty($result)){
                return false;
            }
            $data = [
                'member_id' => $member_id,
                'add_time' => time(),
                'pl_desc'  => '与合作伙伴的差额积分(用户总积分减差额)',
                'type'   =>'parterner_imbanlance_reduce',
                'points' => $imbanlance_points_abs
            ];
            $result = $this->create($data);
            if($result === false){
                $member_model->rollback();
                return false;
            }
        }
        $member_model->commit();
        $this->commit();
        return true;
    }
}
