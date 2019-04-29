<?php
/**
 * 快递鸟物流异步通知处理
 */
namespace app\open\controller;
use think\Controller;
use Lib\Express;
use think\Log;
class ExpressNotify extends Controller {

	protected $orp_model,$order_model,$org_model,$orm_model,$sm_model;
	public function _initialize() {
		$this->orp_model = model('OrderPay');
		$this->order_model = model('Order');
		$this->org_model = model('OrderLog');
		$this->orm_model = model('OrderCommon');
		$this->sm_model = model('SellerMember');
	}

	public function index(){
		$param = input('param.');
		$appkey = '7ca0bfbc-fae6-435f-a391-6a3a12156aec';
		$appid = '1302427';
		$express = new Express($appkey,$appid);

		//验证推送消息的正确性
		$check_result = $express->check($param);
		if (!$check_result) {
			$express->reply_notify(false);
		}
		
		$request_data = json_decode($param['RequestData'],true);

		$failed_times = 0;
		foreach ($request_data['Data'] as $key => $value) {

			//只处理已签收状态
			if ($value['State'] != 3) {
				break;
			}
			$shipping_code = $value['LogisticCode'];
			$om_info = $this->orm_model->where('shipping_code',$shipping_code)->find();
			if (is_null($om_info)) {
				Log::write('物流推送签收成功，但查无订单数据，物流单号:'.$shipping_code,'notice');
				break;
			}
			$om_info = $om_info->toArray();

			//已处理订单忽略
			$order_state = $this->order_model->where('order_id',$om_info['order_id'])->value('order_state');
			if ($order_state >= 40) {
				break;
			}

			$param = [
				'order_state' => '40',
				'sign_time' => time()
			];
			$order_result = $this->order_model->save($param,['order_id'=>$om_info['order_id']]);
			if (empty($order_result)) {
				$failed_times++;
				break;
			}

			//写入订单日志
			$log_data = [
				'order_id' => $om_info['order_id'],
				'log_msg' => '客户签收成功,快递单号：'.$shipping_code,
				'log_time' => time(),
				'log_role' => '系统',
				'log_user' => 'admin',
				'log_orderstate' => '40'
			];

			$org_result = $this->org_model->insert($log_data);

			if (empty($org_result)) {
				$failed_times++;
				break;
			}

			// $buyer_info = model('order')->where(['order_id'=>$om_info['order_id']])->field('buyer_mobile,buyer_name,buyer_id,referee_id')->find();
			// if($buyer_info['buyer_id'] == $buyer_id['referee_id']){
			// 	$has_info = $this->sm_model->where(['member_id'=>$buyer_info['buyer_id'],'customer_state'=>2])->find();

			// 	if(!empty($has_info)){
			// 		$result = $this->sm_model->where(['member_id'=>$buyer_info['buyer_id']])->setField('customer_state',2);
			// 	}
			// }

			// if($buyer_info['buyer_id'] !== $buyer_id['referee_id']){
			// 	//销售员信息
			// 	$seller_id = model('StoreSeller')->where(['member_id'=>$buyer_info['referee_id']])->value('seller_id');
			// 	$has_info = $this->sm_model->where(['member_id'=>$buyer_info['buyer_id'],'customer_state'=>2])->field('seller_id,member_id')->find();
				
			// 	$sm_data = [
			// 		'seller_id' => $has_info['seller_id'],
			// 		'customer_name' => $buyer_info['buyer_name'],
			// 		'customer_mobile' => $buyer_info['buyer_mobile'],
			// 		'is_member' => 1,
			// 		'member_id' => $buyer_info['buyer_id'],
			// 		'customer_state' => 2,
			// 		'add_time' => time()
			// 	];
			// 	if(empty($has_info)){
			// 		$result = $this->sm_model->save($sm_data);
			// 	}else{
			// 		//判断是否为当前销售员
			// 		if($seller_id == $has_info['seller_id']){
			// 			$result = $this->sm_model->where(['member_id'=>$buyer_info['buyer_id']])->setField('customer_state',2);
			// 		}else{
			// 			$this->sm_model->where(['member_id'=>$buyer_info['buyer_id']])->setField('customer_state',0);
			// 			$result = $this->sm_model->save($sm_data);
			// 		}

			// 	}
			// }

			// if (empty($result)) {
			// 	$failed_times++;
			// 	break;
			// }
			
		}

		if ($failed_times > 0) {
			$express->reply_notify(false);
		}else{
			$express->reply_notify();
		}
	}


	
}