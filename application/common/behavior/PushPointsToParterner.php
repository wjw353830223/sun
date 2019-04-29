<?php
/**
 * 行为类：把积分变动加入任务队列
 * 任务队列：把积分变动推送给合作伙伴
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/25
 * Time: 11:44
 */
namespace app\common\behavior;
use think\Queue;
use think\Request;
class PushPointsToParterner
{
    public function run(&$logs){
        if(empty($logs)){
            return false;
        }
        $res = true;
        foreach($logs as $log){
            if(is_array($log)){
                $res = $this->join_tasks($log);
            }else{
                $res = $this->join_tasks($logs);
                break;
            }
        }
        return $res;
    }

    /**
     * 发布任务到任务队列:通知合作商户总积分记录变动
     * @param $log
     * @return bool
     */
    public function join_tasks($log){
        $request = Request::instance([]);
        $request->module("common");
        $user = model('ThirdMember')->getByMemberId($log['member_id']);
        //不是合作伙伴用户不用处理
        if(!$user){
            return true;
        }
        //会引起合作伙伴总积分增加的状态
        $add_points_types = ['day_profit','month_profit','week_profit','monitor_profit','contribute_profit',
            'order_add','order_refund','order_cancel','admin_add','order_parent_present','parterner_shop_points'];
        //会引起合作伙伴总积分减少的状态
        $reduce_points_types = ['order_pay','points_to_cash','admin_del'];
        $types = array_merge($add_points_types,$reduce_points_types);
        if(!in_array($log['type'],$types)){
            return true;
        }
        try{
            if(!isset($log['type']) || !isset($log['points'])){
                return false;
            }
            if(!isset($log['pl_desc'])){
                $log['pl_desc'] = '';
            }
            $operate = 1;
            if(in_array($log['type'],$reduce_points_types)){
                $operate = 0;
            }
            $parterner_id = model('ThirdMember')->where(['member_id'=>$log['member_id']])->value('parterner_id');
            $member_mobile = 0;
            if(isset($log['member_mobile'])){
                $member_mobile = $log['member_mobile'];
            }else{
                if(isset($user->member_mobile)){
                    $member_mobile = $user->member_mobile;
                }
            }
            //返回当前总积分，防止因网络或队列延时返回原因，合作伙伴来不及及时变更积分，用户用原总积分支付
            $total_points  = model('Member')->where(['member_id'=>$log['member_id']])->value('points');
            $data = [
                'parterner_id'=>$parterner_id,
                'member_id'=>$log['member_id'],
                'score_sign'=>$log['type'],
                'total_shop_points'=>$total_points,
                'score'=>$log['points'],
                'type'=>$operate,
                'mobile'=>$member_mobile,
                'explain'=>$log['pl_desc'],
                'timestamp'=> time(),
            ];
            $res = Queue::push('app\admin\job\PushPoints@fire',json_encode($data),'points');
            if(false === $res){
                return false;
            }
            return true;
        }catch(\Exception $e){
            return false;
        }
    }
}