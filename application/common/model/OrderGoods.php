<?php
namespace app\common\model;

use think\Model;

class OrderGoods extends Model
{

    protected $goods_images_model,$order_partition_model,$express_model;
    protected function initialize(){
        parent::initialize();
        $this->goods_images_model = model('GoodsImages');
        $this->order_partition_model = model('OrderPartition');
        $this->express_model = model('Express');
    }

    //获取订单下商品的总积分 总经验 总父级积分
    public function order_products_amount($order_id){
        if(empty($order_id)){
            return false;
        }
        $order_goods = $this->where(['order_id'=>$order_id])->select();
        if(empty($order_goods)){
            return false;
        }
        $points = 0;
        $experiences = 0;
        $parent_points = 0;
        $goods_name = [];
        foreach($order_goods as $key =>$good){
            $goods_name[$key]['goods_name'] = $good['goods_name'];
            $goods_name[$key]['goods_present'] = $good['goods_points'];
            $goods_name[$key]['goods_experience'] = $good['goods_experience'];
            $goods_name[$key]['goods_parent_points'] = $good['goods_parent_points'];
            $points += $good['goods_points'];
            $experiences  += $good['goods_experience'];
            $parent_points += $good['goods_parent_points'];
        }
        return [
            'goods_present' => $points,
            'goods_experience' => $experiences,
            'goods_parent_points' => $parent_points,
            'goods_name' => $goods_name
        ];
    }

    /**
     * 获取订单下的产品信息
     * @param $order_id
     * @param $order_sn
     * @param int $order_state
     * @param bool $car_order
     * @return array|bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_order_products($order_id,$order_sn,$order_state = 20,$car_order=false){
        $goods_info = $this->where(['order_id'=>$order_id])
            ->field('spec_value,spec_name',true)
            ->select()->toArray();
        if (is_null($goods_info)) {
            return false;
        }
        foreach($goods_info as &$good){
            $good['partition_order_sn'] = $order_sn;
            if($car_order){
                $partition_order_sn = $this->order_partition_model
                    ->where(['order_sn'=>$order_sn, 'good_common_id'=>$good['goods_commonid'],'good_attr_id'=>$good['goods_id']])
                    ->value('order_partition_sn');
                $good['partition_order_sn'] = !empty($partition_order_sn)?$partition_order_sn:'';
            }
            $good_param = $this->get_good_param($order_id,$good['goods_commonid'],$good['goods_id']);
            if(is_array($good_param)){
                $good = array_merge($good,$good_param);
            }
            $order_partition_id = model('OrderPartition')->where(['order_sn'=>$order_sn,'good_common_id'=>$good['goods_commonid'],'good_attr_id'=>$good['goods_id']])
                ->value('id');
            if(!is_null($order_partition_id)){
                $order_common = model('OrderCommon')->where(['order_id'=>$order_id,'order_partition_id'=>$order_partition_id])
                    ->find();
            }else{
                $order_common = model('OrderCommon')->where(['order_id'=>$order_id])
                    ->find();
            }
            $order_states = ['30','40','50'];
            if(in_array($order_state,$order_states)){
                $express_info = $this->express_model->get_express($order_common['shipping_express_id']);
                if(isset($express_info['express_name']) && isset($express_info['express_code'])){
                    $good['express'] = [
                        'express_name' => $express_info['express_name'],
                        'express_code' => $express_info['express_code'],
                        'shipping_time' => $order_common['shipping_time'],
                        'shipping_code' => $order_common['shipping_code']
                    ];
                }
            }
        }
        unset($good);
        return $goods_info;
    }

    /**
     * 获取商品属性参数 库存 图片
     * @param $order_id
     * @param $goods_commonid
     * @param int $goods_id
     * @return array
     */
    public function get_good_param($order_id,$goods_commonid,$goods_id = 0){
        $good_common = model('GoodsCommon')->field('goods_storage')
            ->getByGoodsCommonid($goods_commonid);
        if(is_null($good_common)){
            $storage = 0;
        }else{
            $storage = $good_common['goods_storage'];
        }
        $order_good = $this->where(['order_id'=>$order_id,'goods_commonid'=>$goods_commonid,'goods_id'=>$goods_id])->find();
        $image = $order_good['goods_image'];
        if($goods_id > 0){
            $good_attribution = model('GoodsAttribute')
                ->field('goods_storage')
                ->getByGoodsId($goods_id);
            if(is_null($good_attribution)){
                $storage = 0;
            }else{
                $storage = $good_attribution['goods_storage'];
            }

        }
        $spec_value = json_decode($order_good['spec_value'],true);
        $spec_name = $order_good['spec_name'];
        $data = [];
        $value = [];
        if(!empty($spec_value)){
            foreach($spec_value as $v){
                $value[] = $v;
            }
            $data['spec_value'] = count($value) > 0 ? $value : [];
        }else{
            $data['spec_value'] = [];
        }
        $data['goods_image'] = !empty($image) ? config('qiniu.buckets')['images']['domain'] . '/uploads/product/'.str_replace(strrchr($image,"."),"",$image).'_140x140.'.substr(strrchr($image, '.'), 1) : '';
        $data['spec_name'] = !empty($spec_name) && $spec_name !== '[]' ? explode('|',$spec_name) : [];
        $data['goods_storage'] = $storage;
        return $data;
    }

    /**
     * 判断商品是否已发货
     * @param $buyer_id
     * @param $order_id
     * @param $goods_commonid
     * @param int $goods_id
     * @return bool
     */
    public function order_good_delivered($order_id,$goods_commonid,$goods_id = 0){
        $order = model('OrderCommon')->alias('oc')->join('__ORDER_PARTITION__ op','oc.order_partition_id = op.id')
            ->field('oc.order_id,op.order_sn,op.order_partition_sn,oc.shipping_express_id,oc.shipping_code')
            ->where(['oc.order_id'=>$order_id,'op.good_common_id'=>$goods_commonid,'op.good_attr_id'=>$goods_id])
            ->find();
        if(!is_null($order)){
            if(!empty($order['shipping_express_id']) && !empty($order['shipping_code'])){
                return true;
            }
        }else{
            $order = model('OrderCommon')->field('shipping_express_id,shipping_code')
                ->where(['order_id'=>$order_id])
                ->find();
            if(!empty($order['shipping_express_id']) && !empty($order['shipping_code'])){
                return true;
            }
        }
        return false;
    }
}