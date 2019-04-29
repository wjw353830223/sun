<?php

namespace app\admin\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
class Standard extends Command
{
    protected function configure(){
        $this->setName('standard')->setDescription('Command Standard');
    }

    protected function execute(Input $input, Output $output){
        $this->_up_to_standard();
    }

    /**
     * 每月1号10：00满足参与奖励且奖励和大于0的用户进行推送
     */
    private function _up_to_standard(){
        $amount = ['100','150','300','300','500','100','100','150','300','300','500'];
        $member_model = db('Member');
        $order_model = db('Order');
        $member = $member_model->where(['member_grade'=>['gt',1]])->select();
        $to_member_id = [];
        foreach($member as $key=>$val){
            $order_amount = $order_model
                ->where(['buyer_id'=>$val['member_id'],'order_state'=>['gt',10]])
                ->whereTime('payment_time','last month')
                ->sum('order_amount');
            $grade_amount = $amount[$val['member_grade']-2];
            if($order_amount > $grade_amount){
                $to_member_id[] = $val['member_id'];
            }
        }
        $member_id = implode(',',$to_member_id);
        $message = [
            'from_member_id' => 0,
            'to_member_id'   => $member_id,
            'message_title'  => '',
            'message_time'   => time(),
            'message_state'  => 0,
            'message_type'   => 4,
            'is_more'        => 0,
            'is_push'        => 1,
            'message_body'   => '您上月已完成最低消费额，本月每日都会给您发放奖励。',
            'push_detail'    => '您上月已完成最低消费额，本月每日都会给您发放奖励。',
            'push_title'     => ''
        ];
        db('Message')->insertGetId($message);
    }
}