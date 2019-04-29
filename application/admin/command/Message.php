<?php

namespace app\admin\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
class Message extends Command
{
    protected function configure(){
        $this->setName('message')->setDescription('Command Message');
    }

    protected function execute(Input $input, Output $output){
        $this->_send_message();
    }

    /**
     * 每月20号10：00对未达标用户进行推送
     */
    private function _send_message(){
        $amount = ['100','150','300','300','500','100','100','150','300','300','500'];
        $member_model = db('Member');
        $order_model = db('Order');
        $member = $member_model->where(['member_grade'=>['gt',1]])->select();
        foreach($member as $key=>$val){
            $order_amount = $order_model
                ->where(['buyer_id'=>$val['member_id'],'order_state'=>['gt',10]])
                ->whereTime('payment_time','month')
                ->sum('order_amount');
            $grade_amount = $amount[$val['member_grade']-2];
            if($order_amount < $grade_amount){
                $dec_amount = $grade_amount - $order_amount;
                $message = [
                    'from_member_id' => 0,
                    'to_member_id'   => $val['member_id'],
                    'message_title'  => '',
                    'message_time'   => time(),
                    'message_state'  => 0,
                    'message_type'   => 4,
                    'is_more'        => 0,
                    'is_push'        => 1,
                    'message_body'   => '您还差'.$dec_amount.'元即可达到本月最低消费额，下月即可享受整月奖励哦~',
                    'push_detail'    => '您还差'.$dec_amount.'元即可达到本月最低消费额，下月即可享受整月奖励哦~',
                    'push_title'     => ''
                ];
                $res = db('Message')->insertGetId($message);
                if($res === false){
                    continue;
                }
            }
        }
    }
}