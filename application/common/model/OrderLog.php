<?php
namespace app\common\model;

use think\Model;

class OrderLog extends Model
{
    protected $member_model;
    protected function initialize(){
        parent::initialize();
        $this->member_model = model('Member');
    }
    public function save_log($member_name,$points,$av_predeposit,$order_sn,$order_id){
		$points = empty($points) ? 0 : $points;
		$av_predeposit = empty($av_predeposit) ? 0 : $av_predeposit;
    	$pd_log = [
	        'order_id' => $order_id,
	        'log_msg' => $member_name.'通过使用'.$points.'个积分'.$av_predeposit.'余额支付订单编号为'.$order_sn.'的订单',
	        'log_time' => time(),
	        'log_user' => $member_name,
	        'log_orderstate' => '20',
	    ];

        $result = $this->save($pd_log);
        if($result === false){
        	return false;
        }
        return true;
    }
    public function add_log_after_pay($member_name,$pay_sn,$order_id,$pos_amount,$pd_amount,$pay_amount,$third_pay_type = 'unipay'){
        $log_msg = $member_name;
        if($pos_amount > 0 || $pd_amount > 0){
            $log_msg .= '通过使用' . $pos_amount . '个积分' . $pd_amount .'余额下单,并';
        }
        switch ($third_pay_type){
            case 'unipay':
                $log_msg .= '通过银行卡支付订单成功,支付金额'. $pay_amount .'元，支付单号：'.$pay_sn;
                break;
            case 'weixin':
                $log_msg .= '通过微信支付订单成功,支付金额'. $pay_amount .'元，支付单号：'.$pay_sn;
                break;
            case 'alipay':
                $log_msg .= '通过支付宝支付订单成功,支付金额'. $pay_amount .'元，支付单号：'.$pay_sn;
                break;
            default:
                break;
        }
        $log_data = [
            'order_id' => $order_id,
            'log_msg' => $log_msg,
            'log_time' => time(),
            'log_role' => '系统',
            'log_user' => 'admin',
            'log_orderstate' => '20'
        ];
        return $this->insert($log_data);
    }
}