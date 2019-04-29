<?php

namespace app\api\controller;

use think\Request;

class CarOffLine extends Apibase
{
    protected $shop_car_model;
    protected function _initialize(){
        parent::_initialize();
        $this->shop_car_model = model('ShopCar');
    }
    //离线购物车推荐商品
    public function recommend(){
        $page = input('post.page/d',0,'intval');
        $car_good_common_ids = input('post.car_good_common_ids/s','[]','trim');
        $car_good_common_ids = json_decode($car_good_common_ids,true);
        $recommend_goods = $this->shop_car_model->car_off_line_commend_products($page,$car_good_common_ids);
        $this->ajax_return('200','success',$recommend_goods);
    }

    /**
     * 购物车获取商品规格属性  公共方法
     */
    public function goods_attribute(){
        $good_common_id = input('post.goods_commonid/d',0,'trim');
        if(empty($good_common_id)){
            $this->ajax_return('11300','invalid goods_commonid');
        }
        $result = model('GoodsCommon')->get_good_attributions($good_common_id);
        if(empty($result)){
            $this->ajax_return('11300','invalid goods_commonid');
        }
        list($goods_attributions,$spec_values,$spec_names) = $result;
        $data = [
            'goods_commonid' => $good_common_id,
            'spec_value'     => $spec_values,
            'spec_name'      => $spec_names,
            'attr'           => $goods_attributions
        ];
        $this->ajax_return('200','success',$data);
    }
    public function parse_goods_attribution(){
        $goods_attribution = model('GoodsAttribute')->select();
        foreach($goods_attribution as $attr){
            $spec_name = !empty($attr['spec_name'])?explode('|',$attr['spec_name']):[];
            $spec_name = array_flip(array_flip(array_filter($spec_name)));
            $spec_name = implode('|',$spec_name);
            var_dump($attr->where(['goods_id'=>$attr['goods_id']])->update(['spec_name'=>$spec_name]));
        }
    }
}
