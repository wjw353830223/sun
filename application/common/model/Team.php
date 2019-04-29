<?php
namespace app\common\model;

use think\Model;

class Team extends Model
{
    protected $createTime = false;

    /**
     *	团队总业绩
     */
    public function team_perform($member_id){
        $team_info = model('Member')->where(['parent_id'=>$member_id])->select()->toArray();
        if(!empty($team_info)){
            $tmp = [];
            foreach($team_info as $ti_key=>$ti_val){
                $tmp[] = $ti_val['member_id'];
            }
            $order_amount = model('Order')->where(['order_state'=>50,'buyer_id'=>['in',$tmp]])->sum('order_amount');
          
        }

        $order_amount = isset($order_amount) && !empty($order_amount) ? $order_amount : 0;

        return $order_amount;
    }

    /**
     *	团队业绩统计
     */
    public function team_mdperform($date_type,$member_id){
        $type = '';
        switch ($date_type) {
            case 'month':
                $type = 'month';
                break;
            case 'day':
                $type = 'today';
                break;
        }
        $tmp = [];
        $team_info = model('Member')->where(['parent_id'=>$member_id])->select()->toArray();
        if(!empty($team_info)){
            foreach($team_info as $ti_key=>$ti_val){
                $tmp[] = $ti_val['member_id'];
            }
            $query = ['buyer_id'=>['in',$tmp],'order_state'=>50];

            $order_amount = model('Order')->whereTime('finnshed_time',$type)
                ->where($query)->sum('order_amount');

        }
        $order_amount = isset($order_amount) && !empty($order_amount) ? $order_amount : 0;

        return $order_amount;
    }

    /**
     *	团队业绩详情
     */
    public function perform_detail($member_id,$page,$type){
        $member_model = model('Member');
        $count = $member_model ->where(['parent_id'=>$member_id])->count();
        $page_num = 5;
        $page_count = ceil($count / $page_num);
        $limit = 0;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;
        }else{
            $limit = 0;
        }

        $grade_array = ['7'=>'F','8'=>'E','9'=>'D','10'=>'C','11'=>'B','12'=>'A'];

        $team_info = $member_model->where(['parent_id'=>$member_id])->limit($limit,$page_num)->select()->toArray();
        switch ($type) {
            case 'month':
                $type = 'month';
                break;
            case 'day':
                $type = 'today';
                break;
        }
        $data = [];
        if(!empty($team_info)){
            $tmp = [];
            foreach($team_info as $ti_key=>$ti_val){
                $tmp[] = $ti_val['member_id'];
            }
            $query = ['buyer_id'=>['in',$tmp],'order_state'=>50];
            if($type == 'total'){
                $order_info = model('Order')->field('buyer_id,sum(order_amount) as order_amount')->where($query)->group('buyer_id')->order('order_amount')->select()->toArray();
            }else{
                $order_info = model('Order')->whereTime('finnshed_time',$type)->field('buyer_id,sum(order_amount) as order_amount')->where($query)->group('buyer_id')->order('order_amount')->select()->toArray();
            }

            foreach($team_info as $ti_key=>$ti_val){
                $tmp = [];
                $tmp['member_name'] = $ti_val['member_name'];
                $tmp['member_grade'] = $ti_val['member_grade'];
                if($ti_val['member_grade'] > 6){
                    $tmp['grade_name'] = $grade_array[$ti_val['member_grade']].'级消费商';
                }else{
                    $tmp['grade_name'] = 'lv.'.$ti_val['member_grade'];
                }

                $tmp['member_avatar'] = $ti_val['member_avatar'];
                if(!empty($order_info)){
                    foreach($order_info as $oi_key=>$oi_val){
                        if($ti_val['member_id'] == $oi_val['buyer_id']){
                            $tmp['order_amount'] = $oi_val['order_amount'];
                            break;
                        }else{
                            $tmp['order_amount'] = 0;
                        }
                    }
                }else{
                    $tmp['order_amount'] = 0;
                }
               
                $data[] = $tmp;
            }
        }
        return $data;
    }
}