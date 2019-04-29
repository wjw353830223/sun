<?php
namespace app\common\model;

use think\Model;

class OrderCommon extends Model
{
    /**
     * 获取订单下商品的物流信息
     * @param $order_id
     * @param $goods_commonid
     * @param int $goods_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function order_goods_logistics($order_id,$goods_commonid,$goods_id=0){
        $order_sn = model('Order')->where(['order_id'=>$order_id])->value('order_sn');
        $order_partition_id = model('OrderPartition')->where(['order_sn'=>$order_sn,'good_common_id'=>$goods_commonid,'good_attr_id'=>$goods_id])
            ->value('id');
        if(!is_null($order_partition_id)){
            $order_common = $this->field('shipping_code,shipping_express_id')->where(['order_id'=>$order_id,'order_partition_id'=>$order_partition_id])->find();
        }else{
            $order_common = $this->field('shipping_code,shipping_express_id')->where(['order_id'=>$order_id])->find();
        }
        //配送信息
        $order_delivery =  [];
        $order_delivery['shipping_code'] = $order_common['shipping_code'];
        $shipping_name = model('Express')->get_express($order_common['shipping_express_id']);
        $order_delivery['shipping_name'] = [
            'express_id'=> '',
            'express_name'=>'',
            'express_state'=>'',
            'express_code'=>'',
            'express_order'=>''
        ];
        if(isset($shipping_name['express_id']) && isset($shipping_name['express_name'])){
            $order_delivery['shipping_name'] = $shipping_name;
        }
        return $order_delivery;
    }
}