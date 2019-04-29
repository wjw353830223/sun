<?php
namespace app\common\model;

use think\Model;

class PdLog extends Model
{
	// 关闭自动时间格式化
    protected $createTime = false;

    public function save_log($member_id,$member_mobile,$av_predeposit){
    	$pd_log = [
	        'member_id' => $member_id,
	        'member_mobile' => $member_mobile,
	        'type' => 'order_pay',
	        'av_amount' => $av_predeposit,
	        'add_time' => time(),
	        'log_desc' => '用户支付订单'
	    ];

        $result = $this->save($pd_log);
        if($result === false){
        	return false;
        }
        return true;
    }

    /**
     * @param $data
     * @return bool
     */
    public function pdlog_add($data){
        $res = db('PdLog')->insert($data);
       if(!empty($res)){
           return true;
       }
       return false;
    }
}