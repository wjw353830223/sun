<?php
namespace app\api\controller;
use Lib\Sms;
use think\Cache;

class Achieve extends Apibase
{
	protected function _initialize(){
    	parent::_initialize();
    	$this->order_model = model('order');
    }
   
	/**
    *	获取已完成订单列表
	*/
	public function order_list(){
		$page = input('param.page',1,'intval');
 
		//已完成包含：待返利、已返利
		$order_state = ['40','50'];
		//数据分页
		$count = $this->order_model ->where(['order_state'=>['in',$order_state],'delete_state'=>0,'lock_state'=>0])->count();
		$page_num = 5;
		$page_count = ceil($count / $page_num);
		$limit = 0;
		if ($page > 0) {
			$limit = ($page - 1) * $page_num;  
		}else{
			$limit = 0;
		}

		//判断是否为销售员
		$member_id = $this->member_info['member_id'];
		$seller_role = db('store_seller')->where(['member_id'=>$member_id])->value('seller_role');
		$seller_role_arr = ['1','3'];
		if(!in_array($seller_role,$seller_role_arr)){
			$this->ajax_return('10380', 'the member is not seller');
		}

		//订单列表
		$order_info = $this->order_model->where(['order_state'=>['in',$order_state],'delete_state'=>0,'lock_state'=>0,'referee_id'=>$member_id])->field('order_sn,order_id,payment_time,order_state,order_amount')->order(['finnshed_time'=>'DESC'])->limit($limit,$page_num)->select()->toArray();
		
		if (empty($order_info)) {
			$this->ajax_return('10381', 'empty data');
		}

		$order_id = array();
		foreach($order_info as $order_key=>$order_val){
			$tmp = array();
			$tmp = $order_val['order_id'];
			$order_id[] = $tmp;
		}
		$order_id_arr = implode(',', $order_id);
		$order_goods = model('OrderGoods')->where(['order_id'=>['in',$order_id_arr]])->field('order_id,goods_name,goods_price')->select();
		if(empty($order_goods)){
			$this->ajax_return('10381', 'empty data');
		}

		$order_data = array();
		foreach($order_info as $order_key => $order_val){
			
			foreach($order_goods as $goods_key => $goods_val){
				$tmp = array();
				if($order_val['order_id'] == $goods_val['order_id']){
					$tmp['order_sn'] = $order_val['order_sn'];
					$tmp['order_id'] = $order_val['order_id'];
					$tmp['payment_time'] = $order_val['payment_time'];
					$tmp['order_state'] = $order_val['order_state'];
					$tmp['order_amount'] = $order_val['order_amount'];
					$tmp['goods_name'] = $goods_val['goods_name'];
					break;
				}
			}
			$order_data['order_info'][] = $tmp;
		}

		//订单总数及总额
		$order_num_total = $this->order_model->where(['order_state'=>['in',$order_state],'delete_state'=>0,'lock_state'=>0,'referee_id'=>$member_id])->field('count(order_id) as order_num,sum(order_amount) as order_total')->group('referee_id')->select()->toArray();

		if(empty($order_num_total)){
			$this->ajax_return('10382', 'unpay order is empty');
		}
		$order_data['order_num'] = $order_num_total[0]['order_num'];
		$order_data['order_total'] = $order_num_total[0]['order_total'];
		
		$this->ajax_return('200','success',$order_data);
	}

	/**
    *	获取未完成订单列表
	*/
	public function unorder_list(){
		$page = input('param.page',1,'intval');

		//订单状态：0(已取消)10(默认):未付款;20:已付款;30:已发货;40:已收货;50:已完成    未完成包含：待付款10、待发货20、待收货30
		$order_state = ['0','10','20','30'];
		//数据分页
		$count = $this->order_model ->where(['order_state'=>['in',$order_state],'delete_state'=>0,'lock_state'=>0])->count();
		$page_num = 5;
		$page_count = ceil($count / $page_num);
		$limit = 0;
		if ($page > 0) {
			$limit = ($page - 1) * $page_num;  
		}else{
			$limit = 0;
		}

		//判断是否为销售员
		$member_id = $this->member_info['member_id'];
		$seller_role = db('store_seller')->where(['member_id'=>$member_id])->value('seller_role');
		$seller_role_arr = ['1','3'];
		if(!in_array($seller_role,$seller_role_arr)){
			$this->ajax_return('10390', 'the member is not seller');
		}

		$order_info = $this->order_model->where(['order_state'=>['in',$order_state],'delete_state'=>0,'lock_state'=>0,'referee_id'=>$member_id])->field('order_sn,order_id,create_time,order_state,order_amount')->group('order_id')->order(['create_time'=>'DESC'])->limit($limit,$page_num)->select()->toArray();
		if (empty($order_info)) {
			$this->ajax_return('10391', 'empty data');
		}

		$order_id = array();
		foreach($order_info as $order_key=>$order_val){
			$tmp = array();
			$tmp = $order_val['order_id'];
			$order_id[] = $tmp;
		}
		$order_id_arr = implode(',', $order_id);
		$order_goods = model('OrderGoods')->where(['order_id'=>['in',$order_id_arr]])->field('order_id,goods_name,goods_price')->select();
		if(empty($order_goods)){
			$this->ajax_return('10390', 'empty data');
		}

		foreach($order_info as $order_key => $order_val){
			foreach($order_goods as $goods_key => $goods_val){
				if($order_val['order_id'] == $goods_val['order_id']){
					$order_info[$order_key]['goods_name'] = $goods_val['goods_name'];
				}
			}
		}
		
		$this->ajax_return('200','success',$order_info);
	}

	//订单详情
	public function order_detail(){
		$order_id = input('post.order_id','','intval');
		if(empty($order_id)){
			$this->ajax_return('10400', 'empty order_id');
		}

		//订单信息
		$order_info = $this->order_model->where(['order_id'=>$order_id])->field('order_sn,business_type,payment_code,create_time,order_amount,referee_id,buyer_name,buyer_mobile')->find();

		if(empty($order_info)){
			$this->ajax_return('10401', 'order dose not exist');
		}
		$order_info['create_time'] = date('Y-m-d H:i',$order_info['create_time']);
		
		//产品信息(产品型号 编码)
		$goods_info = db('order_goods')->where(['order_id'=>$order_id])->field('goods_name,goods_seri')->find();
		//出货公司
		$store_id = db('store_seller')->where(['member_id'=>$order_info['referee_id']])->value('store_id');
		if(empty($store_id)){
			$this->ajax_return('10402', 'the member is not seller');
		}
		$store_name = db('store')->where(['store_id'=>$store_id])->value('store_name');
		if(empty($store_name)){
			$this->ajax_return('10403', 'store_name dose not exist');
		}
		//收货地址
		$reciver_info = db('order_common')->where(['order_id'=>$order_id])->field('reciver_info')->find();
		if(empty($reciver_info)){
			$this->ajax_return('10404', 'reciver_info is empty');
		}
		$reciver_info = json_decode($reciver_info['reciver_info'],true);
		//整合数据
		$order_info['goods_name'] = $goods_info['goods_name'];
		$order_info['goods_seri'] = $goods_info['goods_seri'];
		$order_info['store_name'] = $store_name;
		$order_info['address'] = $reciver_info['address'];

		$this->ajax_return('200','success',$order_info);
	}
}
