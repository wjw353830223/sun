<?php

namespace app\api\controller;

use think\Request;

class Car extends Apibase
{
    protected $shop_car_model;
    protected function _initialize(){
        parent::_initialize();
        $this->shop_car_model = model('ShopCar');
    }
    //购物车初始化 合并登录前后购物车数据
    public function init(){
        $buyer_id = $this->member_info['member_id'];
        $page = input('post.page/d',0,'intval');
        $car_goods = input('post.car_goods/s','[]','trim');
        $car_goods = json_decode($car_goods,true);
        $car_goods = $this->shop_car_model->car_products_init($buyer_id, $car_goods,$page);
        if($car_goods === false){
            $this->ajax_return('12003','initialize shop car fail');
        }
        $this->ajax_return('200','success',$car_goods);
    }
    //添加商品到购物车 修改商品数量
    public function add()
    {
        $good_common_id = input('post.good_common_id/d',0,'trim');
        if(empty($good_common_id)){
            $this->ajax_return('11300','invalid goods_commonid');
        }
        $good_nums = input('post.good_nums/d',0,'trim');
        if(empty($good_nums)){
            $this->ajax_return('11490','invalid goods_num');
        }
        $good_attr_id = input('post.good_attr_id/d',0,'trim');
        $is_check = input('post.is_check/d',0,'trim');
        $buyer_id = $this->member_info['member_id'];
        $good_param = model('GoodsCommon')->get_good_param($good_common_id,$good_attr_id);
        if($good_param === false){
            $this->ajax_return('12012','good is put off sale');
        }
        if($this->shop_car_model->add_product($buyer_id,$good_common_id,$good_nums, $good_attr_id, $is_check) === false){
            $this->ajax_return('12000','add product in shop car fail');
        }
        $this->ajax_return('200','success',[]);
    }
    //修改商品属性和数量
    public function edit(){
        $car_id = input('post.car_id/d',0,'trim');
        $car_good = $this->shop_car_model->getById($car_id);
        if(empty($car_id) || empty($car_good)){
            $this->ajax_return('12004','invalid shop car id');
        }
        $good_nums = input('post.good_nums/d',0,'trim');
        if(empty($good_nums)){
            $this->ajax_return('11490','invalid goods_num');
        }
        $good_attr_id = input('post.good_attr_id/d',0,'trim');
        $good_common_id = model('ShopCar')->where(['id'=>$car_id])->value('good_common_id');
        $good_param = model('GoodsCommon')->get_good_param($good_common_id,$good_attr_id);
        if($good_param === false){
            $this->ajax_return('12012','good is put off sale');
        }
        $is_check = input('post.is_check/d',0,'trim');
        $buyer_id = $this->member_info['member_id'];
        if($this->shop_car_model->edit_product($car_id,$buyer_id,$good_nums, $good_attr_id, $is_check) === false){
            $this->ajax_return('12005','edit product in shop car fail');
        }
        $this->ajax_return('200','success',[]);
    }
    //删除购物车商品
    public function delete(){
        $car_ids = input('post.car_ids/s','[]','trim');
        $car_ids = json_decode($car_ids);
        if(empty($car_ids)){
            $this->ajax_return('12004','invalid shop car id');
        }
        foreach($car_ids as $car_id){
            $car_good = $this->shop_car_model->getById($car_id);
            if(empty($car_good)){
                $this->ajax_return('12004','invalid shop car id');
            }
        }
        $buyer_id = $this->member_info['member_id'];
        if(!$this->shop_car_model->delete_products($buyer_id, $car_ids)){
            $this->ajax_return('12001','delete products in shop car fail');
        }
        $this->ajax_return('200','success',[]);
    }
    //清空用户购物车
    public function clear(){
        $buyer_id = $this->member_info['member_id'];
        if(!$this->shop_car_model->clear_products($buyer_id)){
            $this->ajax_return('12002','clear products in shop car fail');
        }
        $this->ajax_return('200','success',[]);
    }
    //购物车推荐商品
    public function recommend(){
        $buyer_id = $this->member_info['member_id'];
        $page = input('post.page',0,'intval');
        $recommend_goods = $this->shop_car_model->car_commend_products($buyer_id,$page);
        $this->ajax_return('200','success',$recommend_goods);
    }
    //购物车中商品总数量
    public function products_nums(){
        $buyer_id = $this->member_info['member_id'];
        $total_nums = $this->shop_car_model->car_pruducts_nums($buyer_id);
        $this->ajax_return('200','success',['total_nums'=>$total_nums]);
    }
    //获取购物车商品选中状态
    public function products_check_states(){
        $car_ids = input('post.car_ids/s','[]','trim');
        $car_ids = json_decode($car_ids);
        if(empty($car_ids)){
            $this->ajax_return('12004','invalid shop car id');
        }
        foreach($car_ids as $car_id){
            $car_good = $this->shop_car_model->getById($car_id);
            if(empty($car_good)){
                $this->ajax_return('12004','invalid shop car id');
            }
        }
        $is_check = input('post.is_check/d',0,'intval');
        $buyer_id = $this->member_info['member_id'];
        if(!$this->shop_car_model->products_check_all($buyer_id, $car_ids, $is_check)){
            $this->ajax_return('12008','check products in shop car fail');
        }
        $this->ajax_return('200','success',[]);
    }
}
