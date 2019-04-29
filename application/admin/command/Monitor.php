<?php
namespace app\admin\Command;  

use think\console\Command;  
use think\console\Input;  
use think\console\Output;  
use think\Db;
use think\Hook;
use think\Log;
use think\Request;

class Monitor extends Command  
{  
	protected function configure(){  
		$this->setName('monitor')->setDescription('Command Monitor');
	}  
  
	protected function execute(Input $input, Output $output){
        $request = Request::instance([]);
        $request->module("common");
		$this->_monitor_profit();
	}

	/**
	 *	高管工资
	 */
	private function _monitor_profit(){
        $first = mktime(0, 0, 0, date('m'), 1, date('y'));
        $last = mktime(23, 59, 59, date('m'), date('t'), date('y'));
		//总利润
		$monitor_global = db('Profit')
            ->where(['type'=>'day','add_time'=>['egt',$first]])
            ->where(['add_time'=>['lt',$last]])
            ->sum('profit');
		if($monitor_global <= 0){
            Log::write(date('y').'年'.date('m').'月利润为0，消费商奖不发放');
        }else{
            $result = $this->_reach();
            $type = ['one'=>'0.1','two'=>'0.15','three'=>'0.3','four'=>'0.45'];
            foreach($result as $re_key=>$re_val){
                if(empty($re_val)){
                    continue;
                }else{
                    $points = (int)($monitor_global *0.1 * $type[$re_key] / $re_val[$re_key.'_num']);
                    if($points <= 0){
                        continue;
                    }
                    $data = [];
                    foreach($re_val['tmp'] as $key=>$val){
                        $data[] = [
                            'member_id' => $val,
                            'add_time' => time(),
                            'points' => $points,
                            'pl_desc'  => '高管工资',
                            'type'   =>'monitor_profit',
                        ];
                    }
                    $member_model = model('Member');
                    $pl_model = model('PointsLog');

                    $pl_model->startTrans();
                    $member_model->startTrans();
                    $result = $member_model->where(['member_id'=>['in',$re_val['tmp']]])->setInc('points',$points);
                    if(empty($result)){
                        continue;
                    }
                    $res = $pl_model->insertAll($data);
                    if(empty($res)){
                        $member_model->rollback();
                        continue;
                    }
                    $pl_model->commit();
                    $member_model->commit();
                    if(!empty($data)){
                        Hook::listen('create_points_log',$data);
                    }
                }
            }
        }
	}

	/**
	 *	高管工资达标人数
	 */
	private function _reach(){
        $member_model = model('Member');
        //权值对应表
        $weight = ['1','5','8','12','17','23','70','210','630','1890','6000','18000'];
        $one_num = $two_num = $three_num = $four_num = 0;

		$member_info = $member_model->field('member_id')->where(['member_grade'=>['gt',6]])->select();

		$data = ['one'=>[],'two'=>[],'three'=>[],'four'=>[]];
        $first = mktime(0, 0, 0, date('m'), 1, date('y'));
        $last = mktime(23, 59, 59, date('m'), date('t'), date('y'));
		foreach($member_info as $mem_key=>$mem_val){
			//个人消费
			$amount = model('Order')
                ->where(['payment_time'=>['lt',$last]])
                ->where(['order_state'=>['gt',10],'buyer_id'=>$mem_val['member_id'],'payment_time'=>['egt',$first]])
                ->sum('order_amount');
			$amount = empty($amount) ? 0 : $amount;

			//团队权值
	        $total = 0;
	        $team_info = $member_model->field('member_id,member_grade')->where(['parent_id'=>$mem_val['member_id']])->select();

	        for($i=1;$i<=12;$i++){
	            $num = 0;
	            foreach($team_info as $team_key=>$team_val){
	                if($i == $team_val['member_grade']){
	                    $num++;
	                }
	            }

	            $total += $weight[$i-1] * $num;
	        }

	        if($amount >= 100000 && $total >= 40000){
	        	$four_num++;
	        	$four_tmp[] = $mem_val['member_id'];
	        	$data['four'] = ['four_num'=>$four_num,'tmp'=>$four_tmp];
	        }elseif($amount >= 50000 && $total >= 13000){
	        	$three_num++;
	        	$three_tmp[] = $mem_val['member_id'];
	        	$data['three'] = ['three_num'=>$three_num,'tmp'=>$three_tmp];
	        }elseif($amount >= 30000 && $total >= 4000){
	        	$two_num++;
	        	$two_tmp[] = $mem_val['member_id'];
	        	$data['two'] = ['two_num'=>$two_num,'tmp'=>$two_tmp];
	        }elseif($amount >= 25000 && $total >= 1000){
	        	$one_num++;
	        	$one_tmp[] = $mem_val['member_id'];
	        	$data['one'] = ['one_num'=>$one_num,'tmp'=>$one_tmp];
	        }else{
	        	continue;
	        }
		}
		return $data;
	}
}  



