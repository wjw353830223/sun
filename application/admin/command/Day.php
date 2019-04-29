<?php
namespace app\admin\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Cache;
use think\Hook;
use think\Request;

class Day extends Command
{
    protected function configure(){
        $this->setName('day')->setDescription('Command Day');
    }

    protected function execute(Input $input, Output $output){
        $request = Request::instance([]);
        $request->module("common");
        $this->_calculate_profit();
    }

    /**
     *	计算每日利润
     */
    private function _calculate_profit(){
        $order_data = model('Order')->whereTime('payment_time','today')->field('order_id')->where(['order_state'=>['gt',10]])->select();
        $tmp = [];
        if(!empty($order_data)){
            foreach($order_data as $or_key=>$or_val){
                $tmp[] = $or_val['order_id'];
            }

            $string = implode(',',$tmp);

            $og_data = model('OrderGoods')->field('order_id,goods_commonid,goods_num,goods_price,cost_price')->where(['order_id'=>['in',$string]])->select();
            $goods_profit = 0;
            if(!empty($og_data)){
                foreach($og_data as $og_key=>$og_val){
                    $profit = ($og_val['goods_price'] - $og_val['cost_price']) * $og_val['goods_num'];
                    $profit = (int)$profit;
                    $goods_profit += $profit;
                }
            }

            $data = [
                'profit' => $goods_profit,
                'type' => 'day',
                'add_time' => time()
            ];

            $result = db('Profit')->insert($data);

            if(!empty($result)){
                $this->_member_profit($goods_profit);
            }
        }
    }

    /**
     *	每日分红奖励(会员)
     */
    private function _member_profit($goods_profit){
        $day_global = $goods_profit;
        $total_num = $this->_reach_total();
        //普通会员[2-6]满足达标分红
        $amount = ['100','150','300','300','500'];
        $rate = ['0.16','0.18','0.2','0.22','0.24'];
        for($i=2 ; $i <= 6 ; $i++){
            $result = $this->_reach_standard($i,$amount[$i-2]);
            if($result['num'] > 0){
                //日分红
                $average = $result['num'] == 0 ? 0 : (int)($day_global * 0.1 * 0.05 / $total_num);
                //等级分红
                $grade_average = $result['num'] == 0 ? 0 : (int)($day_global * 0.1 * 0.1 * $rate[$i-2] / $result['num']);
                $pl_data = json_encode(['profit'=>$average,'grade_profit'=>$grade_average]);
                $points = $average + $grade_average;
                if($points <= 0){
                    continue;
                }
                $pl_log = [];
                foreach($result['member'] as $mem_key=>$mem_val){
                    $pl_log[] = [
                        'member_id' => $mem_val,
                        'add_time'  => time(),
                        'points'    => $points,
                        'pl_desc'   => '每日分红奖励',
                        'type'      =>'day_profit',
                        'pl_data'   => $pl_data
                    ];
                }
                $member_model = model('Member');
                $pl_model = model('PointsLog');

                $pl_model->startTrans();
                $member_model->startTrans();

                $res = $member_model->where(['member_id'=>['in',$result['member']]])->setInc('points',$points);
                if($res === false){
                    continue;
                }

                $result = $pl_model->insertAll($pl_log);

                if($result === false){
                    $member_model->rollback();
                    continue;
                }
                $pl_model->commit();
                $member_model->commit();
                if(!empty($pl_log)){
                    Hook::listen('create_points_log',$pl_log);
                }
            }
        }
    }

    /**
     *	达标人数
     */
    private function _reach_standard($member_grade,$amount){
        $member = model('Member')->field('member_id')->where(['member_grade'=>['eq',$member_grade]])->select();
        $num = $grade_num = 0;
        if(date('m') == 1){
            $first = mktime(0, 0, 0, 12, 1, date('y')-1);
            $last = mktime(23, 59, 59, 12, date('t',$first), date('y')-1);
        }else{
            $first = mktime(0, 0, 0, date('m')-1, 1, date('y'));
            $last = mktime(23, 59, 59, date('m')-1, date('t',$first), date('y'));
        }
        $tmp = [];
        if(!empty($member)){
            foreach($member as $mem_key=>$mem_val){
                $self_amount = model('Order')
                    ->where(['buyer_id'=>$mem_val['member_id'],'payment_time'=>['egt',$first]])
                    ->where(['payment_time'=>['elt',$last]])
                    ->sum('order_amount');

                if($self_amount < $amount){
                    continue;
                }

                $tmp[] = $mem_val['member_id'];
                $num++;
            }
        }
        $data = ['member'=>$tmp,'num'=>$num];
        return $data;
    }

    /**
     *	达标总人数
     */
    private function _reach_total(){
        $amount = ['100','150','300','300','500','100','100','150','300','300','500'];
        if(date('m') == 1){
            $first = mktime(0, 0, 0, 12, 1, date('y')-1);
            $last = mktime(23, 59, 59, 12, date('t',$first), date('y')-1);
        }else{
            $first = mktime(0, 0, 0, date('m')-1, 1, date('y'));
            $last = mktime(23, 59, 59, date('m')-1, date('t',$first), date('y'));
        }
        $num = 0;
        //每一级 判断该级人满足最低消费所达人数
        for($i=2;$i<=6;$i++){
            $member = model('Member')->field('member_id')->where(['member_grade'=>['eq',$i]])->select();
            $tmp = [];
            if(!empty($member)){
                foreach($member as $mem_key=>$mem_val){
                    $self_amount = db('Order')
                        ->where(['buyer_id'=>$mem_val['member_id'],'payment_time'=>['egt',$first]])
                        ->where(['payment_time'=>['elt',$last]])
                        ->sum('order_amount');

                    if($self_amount < $amount[$i-2]){
                        continue;
                    }

                    $tmp[] = $mem_val['member_id'];
                    $num++;
                }
            }
        }
        return $num;
    }
}