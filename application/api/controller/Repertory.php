<?php
namespace app\api\controller;
use JPush\Client;
use Lib\Express;
use think\Db;

class Repertory extends Apibase
{
	protected $express_model,$goods_model,$goodsLog_model,$gc_model,$appKey,$masterSecret;
	protected function _initialize(){
    	parent::_initialize();
    	$this->goods_model = model('Goods');
    	$this->goodsLog_model = model('GoodsLog');
    	$this->gc_model = model('GoodsCommon');
    	$this->express_model = model('express');
//        $this->appKey = '25b3a2a3df4d0d26b55ea031';
//        $this->masterSecret = '3efc28f3eb342950783754f2';
    }
   
	/**
    *	产品入库
	*/
	public function goods_repertory(){
		$goods_sn = input('goods_sn','','trim');
		if(empty($goods_sn) || !is_robotsn($goods_sn)){
			$this->ajax_return('10410','invalid goods_sn');
		}

		$has_info = $this->goods_model->where(['goods_sn'=>$goods_sn])->count();
		if($has_info > 0){
			$data = [
    			'goods_sn' => $goods_sn,
    			'is_success' => 2
    		];
			$this->ajax_return('10415','goods has been exist',$data);
		}
		//商品id
		$goods_commonid = input('goods_commonid','','intval');
        if(empty($goods_commonid)){
            $this->ajax_return('10411','invalid goods_commonid');
        }

		$this->goods_model->startTrans();
    	$this->goodsLog_model->startTrans();
    	$this->gc_model->startTrans();
    	$seller_id = model('StoreSeller')->where(['member_id'=>$this->member_info['member_id']])->value('seller_id');
    	$goods_data = [
    		'goods_commonid' => $goods_commonid,
    		'goods_sn' => $goods_sn,
    		'store_id' => 1,
    		'goods_time'=>time(),
    		'seller_id'=>$seller_id,
    		'upload_num'=> 1
    	];
    	$goods_result =  $this->goods_model->insertGetId($goods_data);
    	if($goods_result === false){
    		$this->ajax_return('10412','failed to add goods data');
    	}

    	//增加商品数量
    	$goods_num = $this->gc_model->where(['goods_commonid'=>$goods_commonid])->setInc('goods_storage');

    	if($goods_num === false){
    		$data = [
    			'goods_sn' => $goods_sn,
    			'is_sucess' => 0
    		];
    		$this->goods_model->rollback();
    		$this->ajax_return('10413','failed to add goods num',$data);
    	}

    	$member_name = model('member')->where(['member_id'=>$this->member_info['member_id']])->value('member_name');
    	$goods_log = [
    		'goods_id' => $goods_commonid,
    		'log_msg' => '库管员'.$member_name.'于'.date('Y-m-d H:i',time()).'扫码入库机器人 '.$goods_sn,
    		'log_time' => time(),
    		'log_role' => '库管员',
    		'log_user'=>$member_name,
    		'log_goodsstate' => 1
    	];

    	$log_result = $this->goodsLog_model->save($goods_log);
    	if($log_result === false){
    		$data = [
    			'goods_sn' => $goods_sn,
    			'is_sucess' => 0
    		];
    		$this->goods_model->rollback();
    		$this->gc_model->rollback();
    		$this->ajax_return('10414','failed to add log data',$data);
    	}

    	$this->goods_model->commit();
    	$this->goodsLog_model->commit();
    	$this->gc_model->commit();
    	$data = [
			'goods_sn' => $goods_sn,
			'is_sucess' => 1
    	];
    	$this->ajax_return('200','success',$data);
	}

	/**
    *	获取快递列表
	*/
	public function express_list(){
		$express_list = $this->express_model->get_express();
		$this->ajax_return('200', 'success',$express_list);
	}

	/**
    *	产品出库
	*/
	public function delivery(){
		//商品编码
		$goods_sn = input('goods_sn','','trim');

		if(empty($goods_sn)){
			$this->ajax_return('10430','invalid goods_sn');
		}
		//物流公司编码
		$company_id = input('post.company_id','','trim');
		if(empty($company_id)){
			$this->ajax_return('10431','invalid company_id');
		}
		$goods_sns = explode(',',$goods_sn);
        $goods_num = count($goods_sns);//出库商品的数量
		//物流单号
		$deli_code = input('post.deli_code','','trim');
		if(empty($deli_code)){
			$this->ajax_return('10432','invalid deli_code');
		}

		//订单编号
		$order_sn = input('post.order_sn','','trim');
		if(empty($order_sn)){
			$this->ajax_return('10433','invalid order_sn');
		}
		$order_id = model('order')->where(['order_sn'=>$order_sn])->value('order_id');
		if(empty($order_id)){
			$this->ajax_return('10434','invalid order_id');
		}
        $order_state = model('order')->where(['order_sn'=>$order_sn])->value('order_state');
        if($order_state !== 20){
            $this->ajax_return('11311','invalid order_state');
        }

		$goods_commonid = input('post.goods_commonid','','intval');
		if(empty($goods_commonid)){
			$this->ajax_return('10435','invalid goods_commonid');
		}

		$goods = [
			'goods_state' => 3,
            'order_sn'    => $order_sn
		];
		$this->goods_model->startTrans();
    	$this->goodsLog_model->startTrans();
    	$this->gc_model->startTrans();
		model('order')->startTrans();
		model('OrderLog')->startTrans();
        model('OrderCommon')->startTrans();
        model('Message')->startTrans();
    	$goods_result = $this->goods_model->save($goods,['goods_sn'=>['in',$goods_sn]]);//修改商品的状态出库
    	if($goods_result === false){
    		$this->ajax_return('10436','delivery is wrong');
    	}

    	$gc_result = $this->gc_model
            ->where(['goods_commonid'=>$goods_commonid])
            ->update([
                'goods_storage' => ['exp','goods_storage-'.$goods_num],
                'goods_salenum' => ['exp','goods_salenum+'.$goods_num]
            ]);
    	if($gc_result  === false){
    		$this->goods_model->rollback();
    		$this->ajax_return('10436','delivery is wrong');
    	}

    	$member_name = model('member')->where(['member_id'=>$this->member_info['member_id']])->value('member_name');
        $goods_log = [
    		'goods_id'       => $goods_commonid,
    		'log_msg'        => '库管员'.$member_name.'于'.date('Y-m-d H:i:s',time()).'扫码出库机器人 '.$goods_num.'件,机器人编号为'.$goods_sn,
    		'log_time'       => time(),
    		'log_role'       => '库管员',
    		'log_user'       => $member_name,
    		'log_goodsstate' => 1
    	];

    	$log_result = $this->goodsLog_model->save($goods_log);//记录操作日志
    	if($log_result === false){
    		$this->goods_model->rollback();
    		$this->gc_model->rollback();
    		$this->ajax_return('10436','delivery is wrong');
    	}

    	$company_code = model('Express')->get_express($company_id);//获取快递信息
        $result = $this->_test(trim($company_code['express_code']),$deli_code);//添加订阅信息
    	if($result === false){
    		$this->goods_model->rollback();
    		$this->gc_model->rollback();
    		$this->goodsLog_model->rollback();
    		$this->ajax_return('10437','failed to upload data');
    	}

    	$order_state = model('order')->where(['order_sn'=>$order_sn])->setField('order_state','30');//修改订单状态
    	if($order_state === false){
    		$this->goods_model->rollback();
    		$this->gc_model->rollback();
    		$this->goodsLog_model->rollback();
    		$this->ajax_return('10437','failed to upload data');
    	}

    	$order_log = [
    		'order_id' => $order_id,
    		'log_msg' => '库管员'.$member_name.'于'.date('Y-m-d H:i:s',time()).'出库订单成功,出库单号 : '.$order_sn,
    		'log_time' => time(),
    		'log_role'=>'库管员',
    		'log_user' => $member_name,
    		'log_orderstate' => '3'
    	];
    	$order_log = model('OrderLog')->save($order_log);//添加订单操作日志
    	if($order_log === false){
    		$this->goods_model->rollback();
    		$this->gc_model->rollback();
    		$this->goodsLog_model->rollback();
    		model('order')->rollback();
    		$this->ajax_return('10438','failed to add order_log');
    	}

    	//更改订单信息扩展表
    	$order_common_info = [
    		'shipping_express_id' => $company_id,
    		'shipping_code' => $deli_code,
    		'shipping_time' => time(),
    	];
    	$order_common = model('OrderCommon')->save($order_common_info,['order_id'=>$order_id]);//修改订单信息
    	if($order_common === false){
    		$this->goods_model->rollback();
    		$this->gc_model->rollback();
    		$this->goodsLog_model->rollback();
    		model('order')->rollback();
    		model('OrderLog')->rollback();
    		$this->ajax_return('10439','failed to edit order_common');
    	}
    	$order = model('Order')->where(['order_sn'=>$order_sn])->find();
//    	$member_mobile = model('Member')->where(['member_id'=>$order['buyer_id']])->value('member_mobile');
        $order_goods = model('OrderGoods')->where(['order_id'=>$order['order_id']])->find();
//        $client = new Client($this->appKey,$this->masterSecret);
//        $push = $client->push();
//        $cid = $push->getCid();
//        $cid = $cid['body']['cidlist'][0];
        $push_detail = '您的'.$order_sn.'订单已发货，请耐心等待哦！';
//        $res = $push->setCid($cid)
//            ->setPlatform(['android','ios'])
//            ->addAlias((string)$member_mobile)
//            ->iosNotification($push_detail,['title'=>'订单配送中'])
//            ->androidNotification($push_detail,['title'=>'订单配送中'])
//            ->send();
//        if($res['http_code'] == 200){
            $body = json_encode([
                'goods_name'  => $order_goods['goods_name'],
                'order_sn'    => $order_sn,
                'goods_image' => $this->base_url.'/uploads/product/'.$order_goods['goods_image'],
                'express'     => $company_code['express_name'],
                'title'       => '您的'.$order_goods['goods_name'].'订单已发货，请耐心等待哦！'
            ]);
            $message = [
                'from_member_id' => 0,
                'to_member_id'   => $order['buyer_id'],
                'message_title'  => '订单配送中',
                'message_time'   => time(),
                'message_state'  => 0,
                'message_type'   => 5,
                'is_more'        => 0,
                'is_push'        => 2,
                'message_body'   => $body,
                'push_detail'    => $push_detail,
                'push_title'     => '订单配送中',
                'push_member_id' => $order['buyer_id']
            ];
            $res = model('Message')->insertGetId($message);
            if($res === false){
                $this->goods_model->rollback();
                $this->gc_model->rollback();
                $this->goodsLog_model->rollback();
                model('order')->rollback();
                model('OrderLog')->rollback();
                model('OrderCommon')->rollback();
                $this->ajax_return('11840','failed to send message');
            }
//        }else{
//            $this->ajax_return('11910','message push false');
//        }
    	$this->goods_model->commit();
		$this->gc_model->commit();
		$this->goodsLog_model->commit();
		model('order')->commit();
		model('OrderLog')->commit();
        model('OrderCommon')->commit();
        model('Message')->commit();
    	$this->ajax_return('200','success');
	}

    /**
     * 衍生品出库
     */
    public function goods_delivery(){
        //出库商品的数量
        $goods_num = input('goods_num',0,'trim');
        $goods_num = (int)$goods_num;
        if(empty($goods_num) && $goods_num !== 0){
            $this->ajax_return('10490','invalid goods_num');
        }
        //物流公司编码
        $company_id = input('post.company_id','','trim');
        if(empty($company_id)){
            $this->ajax_return('10431','invalid company_id');
        }
        //物流单号
        $deli_code = input('post.deli_code','','trim');
        if(empty($deli_code)){
            $this->ajax_return('10432','invalid deli_code');
        }
        //订单编号
        $order_sn = input('post.order_sn','','trim');
        if(empty($order_sn)){
            $this->ajax_return('10433','invalid order_sn');
        }
        $order_state = model('order')->where(['order_sn'=>$order_sn])->value('order_state');//得到订单id号
        if($order_state !== 20){
            $this->ajax_return('11311','invalid order_state');
        }
        //判断是否存在该订单
        $order_id = model('order')->where(['order_sn'=>$order_sn])->value('order_id');
        if(empty($order_id)){
            $this->ajax_return('10434','invalid order_id');
        }

        $goods_commonid = input('post.goods_commonid','','intval');
        if(empty($goods_commonid)){
            $this->ajax_return('10435','invalid goods_commonid');
        }
        $this->goods_model->startTrans();
        $this->goodsLog_model->startTrans();
        $this->gc_model->startTrans();
        model('order')->startTrans();
        model('OrderLog')->startTrans();
        model('Message')->startTrans();
        //库存减少相应数量
        $gc_result = $this->gc_model->where(['goods_commonid'=>$goods_commonid])
            ->update([
                'goods_storage' => ['exp','goods_storage-'.$goods_num],
                'goods_salenum' => ['exp','goods_salenum+'.$goods_num]
            ]);
        if(!$gc_result){
            $this->goods_model->rollback();
            $this->ajax_return('10492','goods_storage is wrong');
        }
        $goods_info = $this->gc_model->where(['goods_commonid'=>$goods_commonid])->find();
        $member_name = model('member')->where(['member_id'=>$this->member_info['member_id']])->value('member_name');
        $goods_log = [
            'goods_id' => $goods_commonid,
            'log_msg' => '库管员'.$member_name.'于'.date('Y-m-d H:i:s',time()).'出库'.$goods_info['goods_name'].$goods_num.'件',
            'log_time' => time(),
            'log_role' => '库管员',
            'log_user'=>$member_name,
            'log_goodsstate' => 1
        ];
        //记录操作日志
        $log_result = $this->goodsLog_model->save($goods_log);
        if(!$log_result){
            $this->goods_model->startTrans();
            $this->goodsLog_model->startTrans();
            $this->ajax_return('10493','failed to update goodslog');
        }

        $company_code = model('express')->get_express($company_id);//获取快递信息
        $result = $this->_test(trim($company_code['express_code']),$deli_code);//添加订阅信息
        if(!$result){
            $this->goods_model->startTrans();
            $this->goodsLog_model->startTrans();
            $this->gc_model->startTrans();
            $this->ajax_return('10437','failed to update data');
        }
        //修改订单状态
        $order_state = model('order')->where(['order_sn'=>$order_sn])->setField('order_state','30');
        if($order_state === false){
            $this->goods_model->rollback();
            $this->gc_model->rollback();
            $this->goodsLog_model->rollback();
            $this->ajax_return('10493','failed to update order_state');
        }

        $order_log = [
            'order_id' => $order_id,
            'log_msg' => '库管员'.$member_name.'出库订单成功,出库单号'.$order_sn,
            'log_time' => time(),
            'log_role'=>'库管员',
            'log_user' => $member_name,
            'log_orderstate' => '3'
        ];
        $order_log = model('OrderLog')->save($order_log);//添加订单操作日志
        if($order_log === false){
            $this->goods_model->rollback();
            $this->gc_model->rollback();
            $this->goodsLog_model->rollback();
            model('order')->rollback();
            $this->ajax_return('10438','failed to add order_log');
        }

        //更改订单信息扩展表
        $order_common_info = [
            'shipping_express_id' => $company_id,
            'shipping_code' => $deli_code,
            'shipping_time' => time()
        ];
        //修改订单信息
        $order_common = model('OrderCommon')->save($order_common_info,['order_id'=>$order_id]);
        if($order_common === false){
            $this->goods_model->rollback();
            $this->gc_model->rollback();
            $this->goodsLog_model->rollback();
            model('order')->rollback();
            model('OrderLog')->rollback();
            $this->ajax_return('10439','failed to edit order_common');
        }
        $order = model('Order')->where(['order_sn'=>$order_sn])->find();
//        $member_mobile = model('Member')->where(['member_id'=>$order['buyer_id']])->value('member_mobile');
        $order_goods = model('OrderGoods')->where(['order_id'=>$order['order_id']])->find();
//        $client = new Client($this->appKey,$this->masterSecret);
//        $push = $client->push();
//        $cid = $push->getCid();
//        $cid = $cid['body']['cidlist'][0];
//        $push_detail = '您的'.$order_sn.'订单已发货，请耐心等待哦！';
//        $res = $push->setCid($cid)
//            ->setPlatform(['android','ios'])
//            ->addAlias((string)$member_mobile)
//            ->iosNotification($push_detail,['title'=>'订单配送中'])
//            ->androidNotification($push_detail,['title'=>'订单配送中'])
//            ->send();
//        if($res['http_code'] == 200){
            $body = json_encode([
                'goods_name'  => $order_goods['goods_name'],
                'order_sn'    => $order_sn,
                'goods_image' => $this->base_url.'/uploads/product/'.$order_goods['goods_image'],
                'express'     => $company_code['express_name'],
                'title'       => '您的'.$order_goods['goods_name'].'订单已发货，请耐心等待哦！'
            ]);
            $message = [
                'from_member_id' => 0,
                'to_member_id'   => $order['buyer_id'],
                'message_title'  => '订单配送中',
                'message_time'   => time(),
                'message_state'  => 0,
                'message_type'   => 5,
                'is_more'        => 0,
                'is_push'        => 2,
                'message_body'   => $body,
                'push_detail'    => '您的'.$order_goods['goods_name'].'订单已发货，请耐心等待哦！',
                'push_title'     => '订单配送中'
            ];
            $res = model('Message')->insertGetId($message);
            if($res === false){
                $this->goods_model->rollback();
                $this->gc_model->rollback();
                $this->goodsLog_model->rollback();
                model('order')->rollback();
                model('OrderLog')->rollback();
                model('OrderCommon')->rollback();
                $this->ajax_return('11840','failed to send message');
            }
//        }else{
//            $this->ajax_return('11910','message push false');
//        }

        $this->goods_model->commit();
        $this->gc_model->commit();
        $this->goodsLog_model->commit();
        model('order')->commit();
        model('OrderLog')->commit();
        model('Message')->commit();
        $this->ajax_return('200','success');
    }


	/**
	* 添加订阅信息
	*/
	private function _test($company_code,$deli_code){
        $appkey = '7ca0bfbc-fae6-435f-a391-6a3a12156aec';
        $appid = '1302427';
        $express = new Express($appkey,$appid);
        $result = $express->dist($company_code,$deli_code);
		return $result;
	}

	/**
	* 待发货接口
	*/
	public function wait_send(){
		$member_id = $this->member_info['member_id'];
		$wait_order = model('order')->where(['order_state'=>20,'lock_state'=>0])->order(['payment_time'=>'desc'])->field('order_amount,order_sn,order_id,create_time  payment_time')->select();
		if(empty($wait_order)){
			$this->ajax_return('10440','empty data');
		}
		$order_id_arr = array();
		foreach($wait_order as $wait_key=>$wait_val){
			$tmp = $wait_val['order_id'];
			$order_id_arr[] = $tmp;
		}

		$order_id_str = implode(',', $order_id_arr);
		$order_goods = model('OrderGoods')->where(['order_id'=>['in',$order_id_str]])->field('order_id,goods_name,goods_commonid,goods_num,gc_id,is_scan_code,goods_id,spec_value')->select();
		if(empty($order_goods)){
			$this->ajax_return('10441','empty order_goods');
		}

		foreach($wait_order as $wati_key=>$wait_val){
			foreach($order_goods as $goods_key=>$goods_val){
				if($wait_val['order_id'] == $goods_val['order_id']){
                    $wait_order[$wati_key]['goods_name'] = $goods_val['goods_name'];
                    $wait_order[$wati_key]['goods_commonid'] = $goods_val['goods_commonid'];
                    $wait_order[$wati_key]['goods_num'] = $goods_val['goods_num'];
                    $wait_order[$wati_key]['gc_id'] = $goods_val['gc_id'];
                    $wait_order[$wati_key]['is_scan_code'] = $goods_val['is_scan_code'];
                    break;
                }
			}
		}

		$this->ajax_return('200','success',$wait_order);
	}
	/**
	* 已发货接口
	*/
	public function waiten_send(){
		$member_id = $this->member_info['member_id'];
		$wait_order = model('order')->where(['order_state'=>30,'lock_state'=>0])->order('create_time desc')->field('order_amount,order_sn,order_id')->select()->toArray();

		if(empty($wait_order)){
			$this->ajax_return('10450','empty data');
		}
		$order_id_arr = array();
		foreach($wait_order as $wait_key=>$wait_val){
			$tmp = array();
			$tmp = $wait_val['order_id'];
			$order_id_arr[] = $tmp;
		}
		$order_id_str = implode(',', $order_id_arr);
		//订单产品
		$order_goods = Model('OrderGoods')->where(['order_id'=>['in',$order_id_str]])->field('order_id,goods_name')->select();

		if(empty($order_goods)){
			$this->ajax_return('10451','empty order_goods');
		}
		//订单信息扩展表
		$order_common = db('order_common')->where(['order_id'=>['in',$order_id_str]])->order('shipping_time desc')->field('order_id,shipping_express_id,shipping_time,shipping_code')->select();
       
		if(empty($order_common)){
			$this->ajax_return('10452','empty order_common');
		}

		foreach($order_common as $order_key => $order_val){
			$company_name = model('express')->get_express($order_val['shipping_express_id']);
			$order_common[$order_key]['express'] = $company_name;
		}
		
		//订单编号
		$order_sn = db('order')->where(['order_id'=>['in',$order_id_str]])->field('order_sn,order_id,create_time')->select();
		if(empty($order_sn)){
			$this->ajax_return('10453','empty order_sn');
		}
		//产品
		foreach($wait_order as $wati_key=>$wait_val){
			//商品名称
			foreach($order_goods as $goods_key=>$goods_val){
				if($wait_val['order_id'] == $goods_val['order_id']){
					$wait_order[$wati_key]['goods_name'] = $goods_val['goods_name'];
					break;
				}
			}

			foreach($order_common as $common_key=>$common_val){
				if($wait_val['order_id'] == $common_val['order_id']){
					$wait_order[$wati_key]['shipping_time'] = date('Y-m-d H:i',$common_val['shipping_time']);
					$wait_order[$wati_key]['express_name'] = $common_val['express']['express_name'];
					$wait_order[$wati_key]['shipping_code'] = $common_val['shipping_code'];
					break;
				}
			}

			//订单编号
			foreach($order_sn as $order_key=>$order_val){
				if($wait_val['order_id'] == $order_val['order_id']){
					$wait_order[$wati_key]['order_sn'] = $order_val['order_sn'];
					$wait_order[$wati_key]['create_time'] = date('Y-m-d H:i',$order_val['create_time']);
					break;
				}
			}
		}

        $wait_order = $this->_arraySequence($wait_order,'shipping_time');
		$this->ajax_return('200','success',$wait_order);
	}

    /**
     * 二维数组根据字段进行排序
     * @params array $array 需要排序的数组
     * @params string $field 排序的字段
     * @params string $sort 排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
     */
    private function _arraySequence($array, $field, $sort = 'SORT_DESC')
    {
        $arrSort = array();
        foreach ($array as $uniqid => $row) {
            foreach ($row as $key => $value) {
                $arrSort[$key][$uniqid] = $value;
            }
        }
        array_multisort($arrSort[$field], constant($sort), $array);
        return $array;
    }
    
	/**
	* 已发货详情接口
	*/
	public function has_send_detail(){
		$order_id = input('post.order_id','','intval');
        if(empty($order_id)){
            $this->ajax_return('10470','empty order_id');
        }

		//订单编号
		$order_common = model('OrderCommon')->where(['order_id'=>$order_id])->field('reciver_name,reciver_info,shipping_express_id,shipping_code')->find();
		if(empty($order_common)){
			$this->ajax_return('10471','empty order_common');
		}
		//收货人信息
		$reciver_info = json_decode($order_common['reciver_info'],true);
		$reciver = [
			'reciver_name' => $order_common['reciver_name'],
			'mobile' => $reciver_info['mobile'],
			'address' => $reciver_info['address'],
		];

		//配送信息
		$order_delivery =  array();
		
		$order_delivery['shipping_code'] = $order_common['shipping_code'];
		$shipping_name = model('express')->get_express($order_common['shipping_express_id']);
		$order_delivery['shipping_name'] = $shipping_name;

		//订单状态
		$order_info = model('Order')->where(['order_id'=>$order_id])->field('order_state,order_sn,create_time')->find();
		if(empty($order_info)){
			$this->ajax_return('10471','empty order_info');
		}
		//产品名称
		$goods = model('OrderGoods')->where(['order_id'=>$order_id])->field('goods_name,goods_num')->find();
		$order_delivery['order_state'] = $order_info['order_state'];
		$order_delivery['create_time'] = date('Y-m-d H:i',$order_info['create_time']);
		$order_delivery['goods_name'] = $goods['goods_name'];
        $order_delivery['goods_num'] = $goods['goods_num'];
		$reciver['order_sn'] = $order_info['order_sn'];

        //获取商品规格属性
        $spec = model('OrderGoods')->where(['order_id'=>$order_id])->field('spec_value,spec_name')->find();
        $reciver['spec_name'] = !empty($spec['spec_name']) ? explode('|',$spec['spec_name']) : [];
        if(!empty($spec['spec_value'])){
            $spec_value = json_decode($spec['spec_value'],true);
            foreach($spec_value as $v){
                $data['spec_value'][] = $v;
            }
        }
        $reciver['spec_value'] = !empty($data['spec_value']) ? $data['spec_value'] : [];
        $data = array();
        $data['delivery'] = $order_delivery;
        $data['reciver'] = $reciver;
		$this->ajax_return('200','success',$data);
	}

	/**
	* 未发货详情接口
	*/
	public function send_detail(){
		$order_id = input('post.order_id','','intval');
		if(empty($order_id)){
			$this->ajax_return('10470','empty order_id');
		}

		//订单编号
		$order_common = db('order_common')->where(['order_id'=>$order_id])->field('reciver_name,reciver_info')->find();
		if(empty($order_common)){
			$this->ajax_return('10481','empty order_common');
		}
		//收货人信息
		$reciver_info = json_decode($order_common['reciver_info'],true);
		$reciver = [
			'reciver_name' => $order_common['reciver_name'],
			'mobile' => $reciver_info['mobile'],
			'address' => $reciver_info['address'],
		];

		//订单状态
		$order_sn = db('order')->where(['order_id'=>$order_id])->field('order_sn,create_time')->find();
		if(empty($order_sn)){
			$this->ajax_return('10482','empty order_sn');
		}

		//产品名称
		$goods_name = db('order_goods')->where(['order_id'=>$order_id])->value('goods_name');

		$reciver['order_sn'] = $order_sn['order_sn'];
		$reciver['create_time'] = date('Y-m-d H:i',$order_sn['create_time']);
		$reciver['goods_name'] = $goods_name;
        //获取商品规格属性
        $spec = model('OrderGoods')->where(['order_id'=>$order_id])->field('spec_value,spec_name')->find();
        $reciver['spec_name'] = !empty($spec['spec_name']) ? explode('|',$spec['spec_name']) : [];
        if(!empty($spec['spec_value'])){
            $spec_value = json_decode($spec['spec_value'],true);
            foreach($spec_value as $v){
                $data['spec_value'][] = $v;
            }
        }
        $reciver['spec_value'] = !empty($data['spec_value']) ? $data['spec_value'] : [];
        $data = array();
        $data['reciver'] = $reciver;
		$this->ajax_return('200','success',$data);
	}

	/**
	* 衍生品入库
	*/
	public function set_num(){
		$goods_commonid = input('post.goods_commonid','','intval');
		if(empty($goods_commonid)){
			$this->ajax_return('10491','empty goods_commonid');
		}

		$goods_num = input('post.goods_num','','intval');
		if(empty($goods_num)){
			$this->ajax_return('10492','empty goods_num');
		}

		$this->goods_model->startTrans();
    	$this->goodsLog_model->startTrans();
    	$this->gc_model->startTrans();
    	$seller_id = db('store_seller')->where(['member_id'=>$this->member_info['member_id']])->value('seller_id');
    	$goods_data = [
    		'goods_commonid' => $goods_commonid,
    		'store_id' => 1,
    		'goods_time' => time(),
    		'seller_id' => $seller_id,
    		'upload_num' => $goods_num
    	];

    	$has_info = db('goods')->where(['goods_commonid'=>$goods_commonid,'seller_id'=>$seller_id])->count();
    	if($has_info > 0){
    		$goods_result =  $this->goods_model->where(['goods_commonid'=>$goods_commonid,'seller_id'=>$seller_id])->setInc('upload_num',$goods_num);
    	}else{
    		$goods_result =  $this->goods_model->insertGetId($goods_data);
    	}
    	
    	if($goods_result === false){
    		$this->ajax_return('10493','failed to add goods data');
    	}

    	//增加商品数量
    	$goods_num = $this->gc_model->where(['goods_commonid'=>$goods_commonid])->setInc('goods_storage',$goods_num);

    	if($goods_num === false){
    		$data = ['is_success' => 0];
    		$this->goods_model->rollback();
    		$this->ajax_return('10494','failed to add goods num',$data);
    	}

    	$member_name = model('member')->where(['member_id'=>$this->member_info['member_id']])->value('member_name');
    	$goods_log = [
    		'goods_id' => $goods_commonid,
    		'log_msg' => '库管员'.$member_name.'于'.date('Y-m-d H:i',time()).'添加衍生品 '.$goods_num,
    		'log_time' => time(),
    		'log_role' => '库管员',
    		'log_user'=>$member_name,
    		'log_goodsstate' => 1
    	];

    	$log_result = $this->goodsLog_model->save($goods_log);
    	if($log_result === false){
    		$data = ['is_sucess' => 0];
    		$this->goods_model->rollback();
    		$this->gc_model->rollback();
    		$this->ajax_return('10495','failed to add log data',$data);
    	}

    	$this->goods_model->commit();
    	$this->goodsLog_model->commit();
    	$this->gc_model->commit();
    	$data = ['is_sucess' => 1];
    	$this->ajax_return('200','success',$data);
	}
    /**
     * 待发货列表优化
     */
    public function pending_delivery_goods(){
        $member_id = $this->member_info['member_id'];
        $page = input('post.page',0,'intval');
        $query = model('Order')
            //->with('goodsInfos')
            ->where(['order_state'=>20,'lock_state'=>0])
            ->order(['payment_time'=>'desc'])
            ->field('order_amount,order_sn,order_id,create_time  payment_time');
        $page && $query->page($page)->limit(10);
        $wait_order = $query->select()->toArray();
        if(empty($wait_order)){
            $this->ajax_return('10440','empty data');
        }
        foreach($wait_order as &$order){
            $goods_info = model('OrderGoods')
                ->field('order_id,goods_commonid,goods_name,goods_price,goods_num,gc_id,goods_id,is_scan_code')
                ->where(['order_id'=>$order['order_id']])
                ->select()->toArray();
            if(!empty($goods_info)){
                foreach($goods_info as &$good){
                    $good_param = model('OrderGoods')->get_good_param($order['order_id'],$good['goods_commonid'],$good['goods_id']);
                    if(is_array($good_param)){
                        $good = array_merge($good,$good_param);
                    }
                    if($good['is_scan_code']){
                        $order['is_scan_code'] = 1;
                    }
                }
                unset($good);
                $order['goods_info'] = $goods_info;
            }else{
                $order['goods_info'] = [];
            }
        }
        $this->ajax_return('200','success',$wait_order);
    }

    /**
     * 待发货订单详情优化
     */
    public function pending_delivery_detail(){
        $order_id = input('post.order_id',0,'intval');
        if(empty($order_id)){
            $this->ajax_return('10470','empty order_id');
        }
        $data=[];
        //收货人信息
        $order_common = model('OrderCommon')->field('reciver_name,reciver_info')->getByOrderId($order_id);
        if(empty($order_common)){
            $this->ajax_return('10481','empty order_common');
        }
        $reciver_info = json_decode($order_common['reciver_info'],true);
        $reciver = [
            'reciver_name' => $order_common['reciver_name'],
            'mobile' => $reciver_info['mobile'],
            'address' => $reciver_info['address'],
        ];
        $data['reciver'] = $reciver;
        $order = model('Order')->field('order_sn,create_time')->getByOrderId($order_id);
        if(empty($order)){
            $this->ajax_return('10482','empty order_sn');
        }
        $data['order'] = $order;
        $order_goods = model('OrderGoods')
            ->field('goods_commonid,goods_id,goods_name,goods_price,goods_num,is_scan_code,spec_name,spec_value')
            ->where(['order_id'=>$order_id])
            ->select()
            ->toArray();
        $data['order_goods'] = [];
        if(!empty($order_goods)){
            foreach($order_goods as &$good){
                $good_param = model('OrderGoods')->get_good_param($order_id,$good['goods_commonid'],$good['goods_id']);
                $good['is_delivered'] = 0;
                if(model('OrderGoods')->order_good_delivered($order_id,$good['goods_commonid'],$good['goods_id'])){
                   $good['is_delivered'] = 1;
                }
                //配送信息
                $order_delivery =  model('OrderCommon')->order_goods_logistics($order_id,$good['goods_commonid'],$good['goods_id']);
                $good['delivery'] = $order_delivery;
                $good = array_merge($good,$good_param);
            }
            unset($good);
            $data['order_goods'] = $order_goods;
        }
        $this->ajax_return('200','success',$data);
    }
    /**
     * 商品发货优化
     */
    public function delivery_goods(){
        $order_sn = input('post.order_sn','','trim');
        if(empty($order_sn)){
            $this->ajax_return('10433','invalid order_sn');
        }
        $order = model('Order')->getByOrderSn($order_sn);
        if(empty($order['order_id'])){
            $this->ajax_return('10434','invalid order_id');
        }
        if($order['order_state'] !== 20){
            $this->ajax_return('11311','invalid order_state');
        }
        $delivery_goods = input('post.delivery_goods','','trim');
        $delivery_goods = json_decode($delivery_goods,true);
        if(empty($delivery_goods)){
            $this->ajax_return('12006','invalid delivery goods');
        }
        $seller_id = model('StoreSeller')->where(['member_id'=>$this->member_info['member_id']])->value('seller_id');
        if(is_null($seller_id)){
            $this->ajax_return('11420','invalid seller_info');
        }
        foreach($delivery_goods as $good){
            if(empty($good['goods_num'])){
                $this->ajax_return('11490','invalid goods_num');
            }
            $is_scan_code = model('GoodsCommon')->where(['goods_commonid'=>$good['goods_commonid']])->value('is_scan_code');
            if($is_scan_code){
                if(count($good['goods_sns']) != $good['goods_num']){
                    $this->ajax_return('11410','invalid goods_sn');
                }
                foreach($good['goods_sns'] as $goods_sn){
                    $bound_good = model('Goods')
                        ->where([
                            'goods_commonid'=>$good['goods_commonid'],
                            'goods_attr_id'=>$good['goods_id'],
                            'seller_id'=>$seller_id,
                            'goods_sn'=>$goods_sn
                        ])
                        ->find();
                    if(is_null($bound_good)){
                        $this->ajax_return('12010','bound good is not exist');
                    }
                }
            }else{
                $bound_good = model('Goods')
                    ->where([
                        'goods_commonid'=>$good['goods_commonid'],
                        'goods_attr_id'=>$good['goods_id'],
                        'seller_id'=>$seller_id
                    ])
                    ->find();
                if(is_null($bound_good)){
                    $this->ajax_return('12010','bound good is not exist');
                }
                if($bound_good['upload_num']< $good['goods_num']){
                    $this->ajax_return('12011','bound good is not enough');
                }
            }

        }
        if(!model('Order')->delivery($delivery_goods,$order['order_id'],$seller_id)){
            $this->ajax_return('12007','delivery failed');
        }
        $this->ajax_return('200','success');
    }

    /**
     * 已发货订单列表优化
     */
    public function delivered_goods_list(){
        $page = input('post.page',0,'intval');
        $query = model('order')
            ->where(['order_state'=>30,'lock_state'=>0])
            ->with('goodsInfos')
            ->with('orderCommons')
            ->order('create_time desc')
            ->field('order_amount,order_sn,order_id,payment_time');
        $page && $query->page($page)->limit(10);
        $orders = $query->select()->toArray();
        if(empty($orders)){
            $this->ajax_return('10450','empty data');
        }
        foreach($orders as &$order){
            $order['goods_info'] = $order['goods_infos']->toArray();
            unset($order['goods_infos']);
            $order['order_commons'] = $order['order_commons']->toArray();
            if(empty($order['goods_info'])){
                $this->ajax_return('10451','empty order_goods');
            }
            if(empty($order['order_commons'])){
                $this->ajax_return('10452','empty order_common');
            }
            foreach($order['order_commons'] as &$val){
                $val['express'] = model('express')->get_express($val['shipping_express_id']);
                $order_partition = model('OrderPartition')->field('good_common_id,good_attr_id')->getById($val['order_partition_id']);
                if(!empty($order_partition)){
                    $val['goods_commonid'] = $order_partition['good_common_id'];
                    $val['goods_id'] = $order_partition['good_attr_id'];
                }else{
                    $order_good = model('OrderGoods')->field('goods_id,goods_commonid')->where(['order_id'=>$val['order_id']])->find();
                    $val['goods_commonid'] = $order_good['goods_commonid'];
                    $val['goods_id'] = $order_good['goods_id'];
                }
                unset($val['order_partition_id']);
            }
            unset($val);
            foreach($order['goods_info'] as &$good){
                $good_param = model('OrderGoods')->get_good_param($order['order_id'],$good['goods_commonid'],$good['goods_id']);
                if(is_array($good_param)){
                    $good = array_merge($good,$good_param);
                }
                foreach($order['order_commons'] as $order_common){
                    if($good['goods_commonid'] == $order_common['goods_commonid'] && $good['goods_id'] == $order_common['goods_id']){
                        $good = array_merge($good,$order_common);
                        break;
                    }
                }
            }
            unset($good);
            unset($order['order_commons']);
        }
        unset($order);
        $this->ajax_return('200','success',$orders);
    }

    /**
     * 已发货订单详情优化
     */
    public function delivered_good_detail(){
        $order_id = input('post.order_id',0,'intval');
        if(empty($order_id)){
            $this->ajax_return('10470','empty order_id');
        }
        $data=[];
        //收货人信息
        $order_common = model('OrderCommon')->field('reciver_name,reciver_info')->getByOrderId($order_id);
        if(empty($order_common)){
            $this->ajax_return('10481','empty order_common');
        }
        $reciver_info = json_decode($order_common['reciver_info'],true);
        $reciver = [
            'reciver_name' => $order_common['reciver_name'],
            'mobile' => $reciver_info['mobile'],
            'address' => $reciver_info['address'],
        ];
        $data['reciver'] = $reciver;

        $order = model('Order')->field('order_sn,create_time')->getByOrderId($order_id);
        if(empty($order)){
            $this->ajax_return('10482','empty order_sn');
        }
        $data['order'] = $order;
        $order_goods = model('OrderGoods')
            ->field('goods_commonid,goods_id,goods_name,goods_price,goods_num,is_scan_code,spec_name,spec_value')
            ->where(['order_id'=>$order_id])
            ->select()
            ->toArray();
        $data['order_goods'] = [];
        if(!empty($order_goods)){
            foreach($order_goods as &$good){
                $good_param = model('OrderGoods')->get_good_param($order_id,$good['goods_commonid'],$good['goods_id']);
                if(is_array($good_param)){
                    $good = array_merge($good,$good_param);
                }
                //配送信息
                $order_delivery =  model('OrderCommon')->order_goods_logistics($order_id,$good['goods_commonid'],$good['goods_id']);
                $good['delivery'] = $order_delivery;
                if(model('OrderGoods')->order_good_delivered($order_id,$good['goods_commonid'],$good['goods_id'])){
                    $good['is_delivered'] = 1;
                }
            }
            unset($good);
            $data['order_goods'] = $order_goods;
        }
        $this->ajax_return('200','success',$data);
    }

    /**
     * 商品入库
     */
    public function inbound(){
        $goods_commonid = input('goods_commonid',0,'intval');
        if(empty($goods_commonid)){
            $this->ajax_return('10411','invalid goods_commonid');
        }
        $seller_id = model('StoreSeller')->where(['member_id'=>$this->member_info['member_id']])->value('seller_id');
        if(is_null($seller_id)){
            $this->ajax_return('11420','invalid seller_info');
        }
        $is_scan = model('GoodsCommon')->where(['goods_commonid'=>$goods_commonid])->value('is_scan_code');
        if(is_null($is_scan)){
            $this->ajax_return('10411','invalid goods_commonid');
        }
        if($is_scan){
            $goods_sns = input('goods_sns','[]','trim');
            $goods_sns = json_decode($goods_sns,true);
            if(empty($goods_sns)){
                $this->ajax_return('10410','invalid goods_sn');
            }

            foreach($goods_sns as $goods_sn){
                /*if(!is_robotsn($goods_sn)){
                    $this->ajax_return('10410','invalid goods_sn');
                }*/
                if(empty($goods_sns) || !is_array($goods_sn)){
                    $this->ajax_return('10410','invalid goods_sn');
                }
                $goods_info = model('GoodsCommon')
                    ->field('goods_commonid,goods_name,goods_price,goods_storage,goods_image,is_scan_code')
                    ->getByGoodsCommonid($goods_commonid);
                if(is_null($goods_info)){
                    $this->ajax_return('10641','empty goods_info');
                }
                $has_info = $this->goods_model
                    ->where(['goods_sn'=>$goods_sn['goods_sn'],'goods_commonid'=>$goods_commonid,'goods_attr_id'=>$goods_sn['goods_attr_id'],'seller_id'=>$seller_id])
                    ->count();
                if($has_info > 0){
                    $this->ajax_return('10415','goods has been exist');
                }
                if($this->goods_model->inbound_by_scan($this->member_info['member_id'],$goods_sn['goods_sn'],$goods_commonid,$goods_sn['goods_attr_id']) === false){
                    $this->ajax_return('12009','goods inbound fail');
                }
            }
            $data = [
                'goods_sns' => $goods_sns,
                'is_sucess' => 1
            ];
        }
        if(!$is_scan){
            $goods_attr_id = input('goods_attr_id',0,'intval');
            $goods_info = model('GoodsCommon')
                ->field('goods_commonid,goods_name,goods_price,goods_storage,goods_image,is_scan_code')
                ->getByGoodsCommonid($goods_commonid);
            if(is_null($goods_info)){
                $this->ajax_return('10641','empty goods_info');
            }
            $goods_num = input('post.goods_num',0,'intval');
            if(empty($goods_num)){
                $this->ajax_return('10492','empty goods_num');
            }
            if($this->goods_model->inbound_unscan($this->member_info['member_id'],$goods_num,$goods_commonid,$goods_attr_id) === false){
                $this->ajax_return('12009','goods inbound fail');
            }
            $data = [
                'is_sucess' => 1
            ];
        }
        $this->ajax_return('200','success',$data);
    }
    //已入库商品列表
    public function have_finished(){
        //库管员id
        $seller_info = model('StoreSeller')->where(['member_id'=>$this->member_info['member_id']])->field('seller_id,store_id,seller_role')->find();
        if(empty($seller_info)){
            $this->ajax_return('10420','invalid seller_info');
        }
        //判断是否为库管员
        $seller_role = ['2','3'];
        if(!in_array($seller_info['seller_role'], $seller_role)){
            $this->ajax_return('10421','the seller dose not manage');
        }
        $page = input('page/d',0,'intval');
        $begin_time = input('begin_time/d',0,'intval');
        $end_time = input('end_time/d',0,'intval');
        $goods_info = $this->goods_model->inbounded_goods($seller_info['seller_id'],$begin_time,$end_time,$page);
        if($goods_info === false){
            $this->ajax_return('11641','empty goods_info');
        }
        $this->ajax_return('200','success',$goods_info);
    }

}
