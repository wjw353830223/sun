<?php
namespace app\admin\Command;  

use think\console\Command;  
use think\console\Input;  
use think\console\Output;  
use think\Db;
use think\Hook;
use think\Log;
use think\Request;

class Month extends Command  
{  
	protected function configure(){  
		$this->setName('month')->setDescription('Command Month');
	}
  
	protected function execute(Input $input, Output $output){
        $request = Request::instance([]);
        $request->module("common");//绑定当前模块为common
		$amount = ['100','150','300','300','500','100','100','150','300','300','500'];
        $first = mktime(0, 0, 0, date('m'), 1, date('y'));
        $last = mktime(23, 59, 59, date('m'), date('t'), date('y'));
        //总利润
        $month_global = db('Profit')
            ->where(['type'=>'day','add_time'=>['egt',$first]])
            ->where(['add_time'=>['lt',$last]])
            ->sum('profit');
        $month_global = empty($month_global) ? 0 : $month_global;
        $res = db('Profit')->insert(['profit'=>$month_global,'type'=>'month','add_time'=>time()]);
        if($res === false){
            Log::write(date('y').'年'.date('m').'月利润写入数据库失败');
        }else{
            if($month_global > 0){
                $total_num = $this->_reach_total($amount);
                if(count($total_num['member_num']) <= 0){
                    Log::write(date('y').'年'.date('m').'月达标消费者人数为0');
                }else{
                    $this->_member_profit($month_global,$total_num['member_num'],$amount);
                }
                if(count($total_num['seller_num']) <= 0){
                    Log::write(date('y').'年'.date('m').'月达标消费商人数为0');
                }else{
                    $this->_seller_profit($month_global,$total_num['seller_num']);
                }
            }else{
                Log::write(date('y').'年'.date('m').'月利润为0，每月分红不发放');
            }
        }
	}
	/**
	 *	每月分红奖励(会员)
	 */
	private function _member_profit($month_global,$total_num,$amount){
		//普通会员[2-6]满足达标分红
		$rate = ['0.16','0.18','0.2','0.22','0.24'];
		for($i=2 ; $i <= 6 ; $i++){
			$result = $this->_reach_standard($i,$amount[$i-2]);
			if($result['num'] > 0){
				//日分红
                $average = $result['num'] == 0 ? 0 : (int)($month_global * 0.1 * 0.1 / $total_num);
                //等级分红
                $grade_average = $result['num'] == 0 ? 0 : (int)($month_global * 0.1 * 0.15 * $rate[$i-2] / $result['num']);
                $pl_data = json_encode(['profit'=>$average,'grade_profit'=>$grade_average]);
                $points = $average + $grade_average;
                if($points <= 0){
                    continue;
                }
                $data = [];
				foreach($result['member'] as $mem_key=>$mem_val){
                    $data[] = [
                        'member_id' => $mem_val,
                        'add_time' => time(),
                        'points' => $points,
                        'pl_desc'  => '每月分红奖励',
                        'type'   =>'month_profit',
                        'pl_data' => $pl_data
                    ];
                }

                $member_model = model('Member');
                $pl_model = model('PointsLog');

                $pl_model->startTrans();
                $member_model->startTrans();
                $result = $member_model->where(['member_id'=>['in',$result['member']]])->setInc('points',$points);
                if(empty($result)){
                    Log::write('每月分红奖励积分下发失败');
                    continue;
                }
                Log::write('每月分红奖励积分下发成功');
                $res = $pl_model->insertAll($data);
                if(empty($res)){
                    $member_model->rollback();
                    Log::write('每月分红奖励积分日志记录失败');
                    continue;
                }
                Log::write('每月分红奖励积分日志记录成功');
                $pl_model->commit();
                $member_model->commit();
                if(!empty($data)){
                    Hook::listen('create_points_log',$data);
                }
			}
		}
	}

	/**
	 *	每月分红奖励(消费商)
	 */
	private function _seller_profit($month_global,$total_num){
		$amount = ['100','100','150','300','300','500'];
		$rate = ['0.25','0.2','0.17','0.15','0.13','0.1'];
		for($i=7 ; $i <= 12 ; $i++){
			$result = $this->_reach_standard($i,$amount[$i-7]);
			if($result['num'] > 0){
				//日分红
				$average = $result['num'] == 0 ? 0 : (int)($month_global * 0.25 * 0.1 / $total_num);
				//等级分红 
				$grade_average = $result['num'] == 0 ? 0 : (int)($month_global * 0.25 * 0.25 * $rate[$i-7] / $result['num']);
                $pl_data = json_encode(['profit'=>$average,'grade_profit'=>$grade_average]);
                $points = $average + $grade_average;
                if($points <= 0){
                    continue;
                }
                $data = [];
				foreach($result['member'] as $mem_key=>$mem_val){
                    $data[] = [
                        'member_id' => $mem_val,
                        'add_time' => time(),
                        'points' => $points,
                        'pl_desc'  => '每月分红奖励',
                        'type'   =>'month_profit',
                        'pl_data' => $pl_data
                    ];
				}
                $member_model = model('Member');
                $pl_model = model('PointsLog');

                $pl_model->startTrans();
                $member_model->startTrans();
                $res = $member_model->where(['member_id'=>['in',$result['member']]])->setInc('points',$points);
                if(empty($res)){
                    Log::write('每月分红奖励积分下发失败');
                    continue;
                }
                Log::write('每月分红奖励积分下发成功');
                $result = $pl_model->insertAll($data);
                if(empty($result)){
                    $member_model->rollback();
                    Log::write('每月分红奖励积分日志记录失败');
                    continue;
                }
                Log::write('每月分红奖励积分日志记录成功');
                $pl_model->commit();
                $member_model->commit();
                if(!empty($data)){
                    Hook::listen('create_points_log',$data);
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
	 *	达标总人数(会员、消费商)
     */
    private function _reach_total($amount){
        $member_num = $seller_num = 0;
        //每一级 判断该级人满足最低消费所达人数
        if(date('m') == 1){
            $first = mktime(0, 0, 0, 12, 1, date('y')-1);
            $last = mktime(23, 59, 59, 12, date('t',$first), date('y')-1);
        }else{
            $first = mktime(0, 0, 0, date('m')-1, 1, date('y'));
            $last = mktime(23, 59, 59, date('m')-1, date('t',$first), date('y'));
        }
        for($i=2;$i<=12;$i++){
            $member = model('Member')->field('member_id,member_grade')->where(['member_grade'=>['eq',$i]])->select();
            if(!empty($member)){
                foreach($member as $mem_key=>$mem_val){
                    $self_amount = model('Order')
                        ->where(['buyer_id'=>$mem_val['member_id'],'payment_time'=>['egt',$first]])
                        ->where(['payment_time'=>['elt',$last]])
                        ->sum('order_amount');
                    if($self_amount < $amount[$i-2]){
                        continue;
                    }
                    $mem_val['member_grade'] > 6 ? $seller_num++ : $member_num++;
                }
            }
        }
        $data = ['member_num'=>$member_num,'seller_num'=>$seller_num];
        return $data;
    }
}  



