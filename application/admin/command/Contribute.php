<?php
namespace app\admin\Command;  

use think\console\Command;  
use think\console\Input;  
use think\console\Output;  
use think\Db;
use think\Hook;
use think\Log;
use think\Request;

class Contribute extends Command  
{  
	protected function configure(){  
		$this->setName('contribute')->setDescription('Command Contribute');  
	}  
  
	protected function execute(Input $input, Output $output){
        $request = Request::instance([]);
        $request->module("common");
        $first = mktime(0, 0, 0, date('m'), 1, date('y'));
        $last = mktime(23, 59, 59, date('m'), date('t'), date('y'));
        //总利润
        $contribute_global = db('Profit')
            ->where(['type'=>'day','add_time'=>['egt',$first]])
            ->where(['add_time'=>['lt',$last]])
            ->sum('profit');
        if($contribute_global > 0){
            $rate = ['one'=>'0.05','two'=>'0.08','three'=>'0.12','four'=>'0.18','five'=>'0.25','six'=>'0.32'];
            $this->_member_profit($contribute_global,$rate);
            $this->_seller_profit($contribute_global,$rate);
        }else{
            Log::write(date('y').'年'.date('m').'月利润为0，杰出贡献奖不发放');
        }
	}

    private function _member_profit($contribute_global,$rate){
        $member_model = model('Member');
        $pl_model = model('PointsLog');
        $result = $this->_reach_num();
        foreach($result as $re_key=>$re_val){
            if(empty($re_val)){
                continue;
            }else{
                $profit = (int)($contribute_global * 0.1 * 0.45 * $rate[$re_key] / $re_val['num']);
                if($profit <= 0){
                    continue;
                }
                $data = [];
                foreach($re_val['tmp'] as $val){
                    $data[] = [
                        'member_id' => $val,
                        'add_time' => time(),
                        'pl_desc'  => '杰出贡献奖励',
                        'type'   =>'contribute_profit',
                        'points' => $profit
                    ];
                }
                $pl_model->startTrans();
                $member_model->startTrans();
                $result = $member_model->where(['member_id'=>['in',$re_val['tmp']]])->setInc('points',$profit);
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


	private function _seller_profit($contribute_global,$rate){
		$result = $this->_seller_num();
		
		foreach($result as $re_key=>$re_val){
			if(empty($re_val)){
				continue;
			}else{
				$profit = $re_val['num'] == 0 ? 0 : (int)($contribute_global * 0.25 * 0.45 * $rate[$re_key] / $re_val['num']);
				if($profit <= 0){
				    continue;
                }
                $data = [];
				foreach($re_val['tmp'] as $key=>$val){
                    $data[] = [
                        'member_id' => $val,
                        'add_time' => time(),
                        'pl_desc'  => '杰出贡献奖励',
                        'type'   =>'contribute_profit',
                        'points' => $profit
                    ];
				}
                $member_model = model('Member');
                $pl_model = model('PointsLog');

                $pl_model->startTrans();
                $member_model->startTrans();

                $result = $member_model->where(['member_id'=>['in',$re_val['tmp']]])->setInc('points',$profit);
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

    private function _reach_num(){
        $member_model = model('Member');
        $first = mktime(0, 0, 0, date('m'), 1, date('y'));
        $last = mktime(23, 59, 59, date('m'), date('t'), date('y'));
        //杰出贡献奖对应累加额度
        $amount = ['0','100','150','300','300','500','100','100','150','300','300','500'];
        $one_num = $two_num = $three_num = $four_num = $five_num = $six_num = 0;
        $data = ['one'=>[],'two'=>[],'three'=>[],'four'=>[],'five'=>[],'six'=>[]];
        $order_info = model('Order')
            ->field('buyer_id,sum(order_amount) as order_amount')
            ->where(['payment_time'=>['lt',$last]])
            ->where(['order_state'=>['gt',10],'payment_time'=>['egt',$first]])
            ->group('buyer_id')->select();
        if(!empty($order_info)){
            foreach($order_info as $or_key=>$or_val){
                $member_grade = $member_model->where(['member_id'=>$or_val['buyer_id']])->value('member_grade');
                if($member_grade < 7){
                    $diff_amount = $or_val['order_amount'] - $amount[$member_grade-1];
                    if($diff_amount >= 600 && $diff_amount < 1000){
                        $one_num++;
                        $one_tmp[] = $or_val['buyer_id'];
                        $data['one'] = ['num'=>$one_num,'tmp'=>$one_tmp];
                    }elseif($diff_amount >= 1000 && $diff_amount < 1500){
                        $two_num++;
                        $two_tmp[] = $or_val['buyer_id'];
                        $data['two'] = ['num'=>$two_num,'tmp'=>$two_tmp];
                    }elseif($diff_amount >= 1500 && $diff_amount < 2000){
                        $three_num++;
                        $three_tmp[] = $or_val['buyer_id'];
                        $data['three'] = ['num'=>$three_num,'tmp'=>$three_tmp];
                    }elseif($diff_amount >= 2000 && $diff_amount < 2500){
                        $four_num++;
                        $four_tmp[] = $or_val['buyer_id'];
                        $data['four'] = ['num'=>$four_num,'tmp'=>$four_tmp];
                    }elseif($diff_amount >= 2500 && $diff_amount < 3000){
                        $five_num++;
                        $five_tmp[] = $or_val['buyer_id'];
                        $data['five'] = ['num'=>$five_num,'tmp'=>$five_tmp];
                    }elseif($diff_amount >= 3000){
                        $six_num++;
                        $six_tmp[] = $or_val['buyer_id'];
                        $data['six'] = ['num'=>$six_num,'tmp'=>$six_tmp];
                    }else{
                        continue;
                    }
                }
            }
        }
        return $data;
    }


	private function _seller_num(){
		$member_model = model('Member');
		//杰出贡献奖对应累加额度
		$amount = ['0','100','150','300','300','500','100','100','150','300','300','500'];
		$one_num = $two_num = $three_num = $four_num = $five_num = $six_num = 0;
		$data = ['one'=>[],'two'=>[],'three'=>[],'four'=>[],'five'=>[],'six'=>[]];
        $first = mktime(0, 0, 0, date('m'), 1, date('y'));
        $last = mktime(23, 59, 59, date('m'), date('t'), date('y'));
		$order_info = db('Order')
            ->field('buyer_id,sum(order_amount) as order_amount')
            ->where(['payment_time'=>['lt',$last]])
            ->where(['order_state'=>['gt',10],'payment_time'=>['egt',$first]])
            ->group('buyer_id')->select();
		if(!empty($order_info)){
			foreach($order_info as $or_key=>$or_val){
				$member_grade = $member_model->where(['member_id'=>$or_val['buyer_id']])->value('member_grade');
				if($member_grade >= 7){
					$diff_amount = $or_val['order_amount'] - $amount[$member_grade-1];
					if($diff_amount >= 3000){
                        $six_num++;
                        $six_tmp[] = $or_val['buyer_id'];
                        $data['six'] = ['num'=>$six_num,'tmp'=>$six_tmp];
					}elseif($diff_amount >= 2500){
						$five_num++;
			        	$five_tmp[] = $or_val['buyer_id'];
			        	$data['five'] = ['num'=>$five_num,'tmp'=>$five_tmp];
					}elseif($diff_amount >= 2000){
						$four_num++;
			        	$four_tmp[] = $or_val['buyer_id'];
			        	$data['four'] = ['num'=>$four_num,'tmp'=>$four_tmp];
					}elseif($diff_amount >= 1500){
						$three_num++;
			        	$three_tmp[] = $or_val['buyer_id'];
			        	$data['three'] = ['num'=>$three_num,'tmp'=>$three_tmp];
					}elseif($diff_amount >= 1000){
						$two_num++;
			        	$two_tmp[] = $or_val['buyer_id'];
			        	$data['two'] = ['num'=>$two_num,'tmp'=>$two_tmp];
					}elseif($diff_amount >= 600){
						$one_num++;
			        	$one_tmp[] = $or_val['buyer_id'];
			        	$data['one'] = ['num'=>$one_num,'tmp'=>$one_tmp];
					}else{
						continue;
					}
				}
			}
		}
		return $data;
	}
}