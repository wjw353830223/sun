<?php
namespace app\api\controller;

use JPush\Client;
use Lib\Express;
use think\Hook;

class Order extends Apibase
{
    protected $order_model,$member_model,$pay_model,
        $og_model,$goods_model,$adress_model,$shop_car_model,
        $om_model,$express_model,$pd_model,$points_model,$ol_model,$ex_model,
        $appKey,$masterSecret,$order_common_model,$order_partition_model;

    protected function _initialize(){
        parent::_initialize();
        $this->order_model = model('Order');
        $this->pay_model = model('OrderPay');
        $this->og_model = model('OrderGoods');
        $this->member_model = model('Member');
        $this->goods_model = model('GoodsCommon');
        $this->adress_model = model('Address');
        $this->om_model = model('OrderCommon');
        $this->express_model = model('Express');
        $this->pd_model = model('PdLog');
        $this->points_model = model('PointsLog');
        $this->ol_model = model("OrderLog");
        $this->ex_model = model("ExperienceLog");
        $this->appKey = '25b3a2a3df4d0d26b55ea031';
        $this->masterSecret = '3efc28f3eb342950783754f2';
        $this->shop_car_model = model('ShopCar');
        $this->order_common_model = model('OrderCommon');
        $this->order_partition_model = model('OrderPartition');
    }

    /**
     * 获取订单列表
     */
    public function get_list(){
        $member_id = $this->member_info['member_id'];
        $page = input('param.page',1,'intval');
        //数据分页
        $count = $this->order_model->where(['buyer_id' => $member_id,'order_type' => 1,'delete_state' => 0])->count();
        if ($count < 1) {
            $this->ajax_return('10310','empty data');
        }

        $page_num = 5;
        $page_count = ceil($count / $page_num);
        $limit = 0;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;
        }else{
            $limit = 0;
        }
        $where = ['buyer_id' => $member_id,'order_type' => 1,'delete_state' => 0];
        $order_state = input('param.order_state',0,'intval');
        if($order_state !== 0 && !empty($order_state)){
            if(!in_array($order_state,[10,20,30,40,50])){
                $this->ajax_return('10311','order_state false');
            }
            $where['order_state'] = $order_state;
        }

        $order_list = $this->order_model
            ->with('goodsInfo')
            ->where($where)
            ->field('order_id,order_sn,pay_sn,goods_amount,order_amount,order_state')
            ->order('create_time desc')
            ->limit($limit,$page_num)
            ->select()->toArray();

        if($order_list){
            $goods_id = [];
            foreach($order_list as $or_key => $or_val){
                $goods_id[] = $or_val['goods_info']['goods_commonid'];
            }
            $goods_id_str = implode(',',array_unique($goods_id));
            unset($goods_id);
            $image_arr = model('goods_images')->where(['goods_commonid'=>['in',$goods_id_str],'is_default'=>1])->field('goods_commonid,goods_image')->select()->toArray();

            foreach ($order_list as $or_key => $or_val) {
                foreach($image_arr as $img_key => $img_val){
                    if($or_val['goods_info']['goods_commonid'] == $img_val['goods_commonid']){
                        $order_list[$or_key]['goods_info']['goods_image'] = !empty($img_val['goods_image']) ? config('qiniu.buckets')['images']['domain'].'/uploads/product/'.str_replace(strrchr($img_val['goods_image'],"."),"",$img_val['goods_image']).'_140x140.'.substr(strrchr($img_val['goods_image'], '.'), 1) : '';
                        break;
                    }
                    $order_list[$or_key]['goods_info']['goods_image'] = '';
                }
            }
        }
        $this->ajax_return('200','success',$order_list);
    }
    /**
     * 获取订单信息
     */
    public function get_info(){
        $order_sn = input('post.order_sn','','trim');
        $order_info = $this->order_model->with('goodsInfo')->where('order_sn',$order_sn)->find();

        if (is_null($order_info)) {
            $this->ajax_return('10320','invalid order_sn');
        }
        $order_info = $order_info->toArray();

        //提取商品信息
        $goods_info = $order_info['goods_info'];
        $goods_image = model('goods_images')->where(['goods_commonid'=>$order_info['goods_info']['goods_commonid'],'is_default'=>1])->value('goods_image');
        $goods_info['goods_image'] = !empty($goods_image) ? config('qiniu.buckets')['images']['domain'] . '/uploads/product/'.str_replace(strrchr($goods_image,"."),"",$goods_image).'_140x140.'.substr(strrchr($goods_image, '.'), 1) : '';
        $order_id = $goods_info['order_id'];

        unset($order_info['goods_info']);
        unset($goods_info['order_id']);

        // if ($order_info['order_state'] == 0) {
        // 	$this->ajax_return('10320','order is closed');
        // }

        $data = array();
        $state_arr = ['0','10','20','40','50'];
        if (in_array($order_info['order_state'],$state_arr)) {
            //订单信息
            $order = [
                'order_sn' => $order_info['order_sn'],
                'order_amount' => $order_info['order_amount'],
                'order_state' => $order_info['order_state'],
                'pay_sn' =>   $order_info['pay_sn'],
                'stroe_name' => '常仁总部',
                'business_type' => $order_info['business_type'],
                'pay_type' => 'online',
                'remaining_time' => $order_info['create_time'] + 252000 -time()
            ];
            $data['order'] = $order;

            $order_state = ['10','20','40','50'];
            if(in_array($order_info['order_state'],$order_state)){
                $order_id = $this->order_model->where(['order_sn'=>$order_sn])->value('order_id');
                $order_common_info = $this->om_model->where(['order_id'=>$order_id])
                    ->field('reciver_name,reciver_info,reciver_province_id,reciver_city_id,reciver_area_id')
                    ->find();
                $area_info = model('area')->get_name($order_common_info['reciver_area_id']);
                $reciver_info = json_decode($order_common_info['reciver_info'],true);
                $default_address = [
                    'true_name' => $order_common_info['reciver_name'],
                    'mob_phone' => $reciver_info['mobile'],
                    'area_info' => $area_info,
                    'address' => $reciver_info['address'],
                ];
            }else{
                //地址信息
                $default_address = $this->adress_model->where(['member_id' => $this->member_info['member_id'],'is_default' => 1])->field('address_id,true_name,mob_phone,area_info,address')->find();
            }

            if (!is_null($default_address)) {
                $data['address'] = $default_address;
            }else{
                $data['address'] = [];
            }

            //商品信息
            $data['goods'] = $goods_info;
        }

        //已发货
        if ($order_info['order_state'] == 30) {

            $om_info = $this->om_model->where('order_id',$order_info['order_id'])->find();
            if (is_null($om_info)) {
                $this->ajax_return('10321','invalid order');
            }
            $om_info = $om_info->toArray();
            $express_info = $this->express_model->get_express($om_info['shipping_express_id']);
            if (empty($express_info)) {
                $this->ajax_return('10322','invalid express');
            }

            //订单信息
            $order = [
                'order_sn'      => $order_info['order_sn'],
                'order_amount'  => $order_info['order_amount'],
                'order_state'   => $order_info['order_state'],
                // 'create_time'   => $order_info['create_time'],
                'stroe_name'    => '常仁总部',
                'business_type' => $order_info['business_type'],
                'pay_type'      => 'online'
            ];
            $data['order'] = $order;

            //快递信息
            $data['express'] = [
                'express_name' => $express_info['express_name'],
                'express_code' => $express_info['express_code'],
                'shipping_time' => $om_info['shipping_time'],
                'shipping_code' => $om_info['shipping_code']
            ];

            //地址信息
            $order_id = $this->order_model->where(['order_sn'=>$order_sn])->value('order_id');
            $order_common_info = $this->om_model->where(['order_id'=>$order_id])
                ->field('reciver_name,reciver_info,reciver_province_id,reciver_city_id,reciver_area_id')->find();
            $area_info = model('area')->get_name($order_common_info['reciver_area_id']);
            $reciver_info = json_decode($order_common_info['reciver_info'],true);
            $address = [
                'true_name' => $order_common_info['reciver_name'],
                'mob_phone' => $reciver_info['mobile'],
                'area_info' => $area_info,
                'address' => $reciver_info['address'],
            ];
            $data['address'] = $address;
            //商品信息
            $data['goods'] = $goods_info;
        }

        //销售顾问信息
        $adviser_info = array();

        if(empty($order_info['referee_id'])){
            $adviser_info['true_name'] = '';
            $adviser_info['mobile'] = '';
        }else{
            $seller_name = model('StoreSeller')->where(['member_id'=>$order_info['referee_id']])->value('seller_name');
            $adviser_info['mobile'] = $this->member_model->where('member_id',$order_info['referee_id'])->value('member_mobile');
            $adviser_info['true_name'] = $seller_name;
        }

        //获取用户余额/积分信息
        $pd_pos = $this->member_model->where(['member_id'=>$order_info['buyer_id']])->field('av_predeposit,points')->find();
        $data['av_predeposit'] = $pd_pos['av_predeposit'];
        $data['points'] = $pd_pos['points'];
        $data['points_money'] = $pd_pos['points'];

        //默认积分开启 余额关闭
        $data['is_points'] = 0;
        $data['is_predeposit'] = 0;
        $data['order']['create_time'] = $order_info['create_time'];
        $data['order']['sign_time'] = empty($order_info['sign_time']) ? '' : $order_info['sign_time'];
        $data['order']['payment_time'] = empty($order_info['payment_time']) ? '' : $order_info['payment_time'];
        $data['pd_amount'] = $order_info['pd_amount'];
        $data['pos_amount'] = $order_info['pos_amount'];

        //获取赠送经验
        $og_info = model('OrderGoods')
            ->where(['order_id'=>$order_info['order_id']])
            ->field('goods_points,goods_experience')->find();
        $data['goods_points'] = $og_info['goods_points'];
        $data['goods_experience'] = $og_info['goods_experience'];
        //健康顾问信息
        $data['adviser'] = $adviser_info;
        //获取商品规格属性
        $spec = model('OrderGoods')->where(['order_id'=>$order_id])->field('spec_value,spec_name')->find();
        $data['spec_name'] = !empty($spec['spec_name']) ? explode('|',$spec['spec_name']) : [];
        if(!empty($spec['spec_value'])){
            $spec_value = json_decode($spec['spec_value'],true);
            foreach($spec_value as $v){
                $data['spec_value'][] = $v;
            }
        }
        $data['spec_value'] = !empty($data['spec_value']) ? $data['spec_value'] : [];
        $this->ajax_return('200','success',$data);
    }
    /**
     * 创建订单
     */
    public function create_order(){
        $goods_commonid = input('post.goods_commonid',0,'intval');
        if (empty($goods_commonid)) {
            $this->ajax_return('10300','invalid goods_commonid');
        }

        $goods_num = input('post.goods_num',1,'intval');

        $true_name = input('post.true_name',1,'trim');
        if (empty($true_name) || words_length($true_name) < 2) {
            $this->ajax_return('10301','invalid true_name');
        }

        $mobile = input('post.mobile','','trim');
        if (empty($mobile) || !is_mobile($mobile)) {
            $this->ajax_return('10302','invalid mobile');
        }

        $card_no = input('post.card_no','','trim');
        if (!empty($card_no) && !validation_filter_id_card($card_no)) {
            $this->ajax_return('10303','invalid card_no');
        }

        $business_type = input('post.business_type',1,'intval');
        if ($business_type < 0 || $business_type > 2) {
            $this->ajax_return('10304','invalid business_type');
        }

        $member_info = $this->member_model->where('member_mobile',$mobile)->find();
        if (is_null($member_info) || $member_info['member_id'] == $this->member_info['member_id']) {
            $this->ajax_return('10305','invalid member');
        }

        //比较信息与当前销售员信息是否一致
        $store_seller = model('StoreSeller')->where(['member_id'=>$this->member_info['member_id']])->field('id_card,seller_mobile')->find();
        if($mobile == $store_seller['seller_mobile'] || $card_no == $store_seller['id_card']){
            $this->ajax_return('10305','invalid member');
        }
        //查询商品基本信息
        $goods_common = $this->goods_model->where('goods_commonid',$goods_commonid)->find();
        if (is_null($goods_common)) {
            $this->ajax_return('10306','invalid goods');
        }

        $this->pay_model->startTrans();
        $this->order_model->startTrans();
        $this->og_model->startTrans();
        //生成支付信息
        $paysn = $this->order_model->make_paysn($member_info['member_id']);
        $pay_result = $this->pay_model->save(['pay_sn' => $paysn,'buyer_id' => $member_info['member_id']]);
        if (empty($pay_result)) {
            $this->ajax_return('10307','create order failed');
        }
        $pay_id = $this->pay_model->pay_id;

        //生成订单信息
        $order_sn = $this->order_model->make_ordersn($pay_id);
        $order_data = [
            'order_sn'     => $order_sn,
            'pay_sn'       => $paysn,
            'order_type'   => 1,
            'business_type'=> $business_type,
            'buyer_id'     => $member_info['member_id'],
            'buyer_name'   => $true_name,
            'buyer_mobile' => $mobile,
            'buyer_idcard' => !empty($card_no) ? $card_no : '',
            'referee_id'   => $this->member_info['member_id'],
            'create_time'  => time(),
            'goods_amount' => $goods_common['goods_price'],
            'order_amount' => round($goods_common['goods_price'] * $goods_num,2)
        ];
        $order_result = $this->order_model->save($order_data);
        if (empty($order_result)) {
            $this->pay_model->rollback();
            $this->ajax_return('10307','create order failed');
        }
        $order_id = $this->order_model->order_id;

        //生成订单商品信息
        $og_data = [
            'order_id'       => $order_id,
            'goods_commonid' => $goods_commonid,
            'gc_id'          => $goods_common['gc_id'],
            'goods_name'     => $goods_common['goods_name'],
            'goods_image'    => $goods_common['goods_image'],
            'goods_price'    => $goods_common['goods_price'],
            'goods_num'      => $goods_num
        ];
        $og_result = $this->og_model->save($og_data);
        if (empty($og_result)) {
            $this->pay_model->rollback();
            $this->order_model->rollback();
            $this->ajax_return('10307','create order failed');
        }
        $this->pay_model->commit();
        $this->order_model->commit();
        $this->og_model->commit();
        $this->ajax_return('200','success');
    }

    /**
     * 订单扩展接口
     */
    public function confirm_order(){
        //订单SN码
        $order_sn = input('post.order_sn','','trim');
        if(empty($order_sn)){
            $this->ajax_return('11370','invalid order_sn');
        }
        $order_info = $this->order_model->where(['order_sn'=>$order_sn])
            ->field('order_id,pd_amount,pos_amount,order_amount')->find();
        if(empty($order_info)){
            $this->ajax_return('11371','order_id dose not exist');
        }
        //判断是否需要拉起支付
        $has_pay = round($order_info['pd_amount'] + $order_info['pos_amount'],2);
        $is_pay = $order_info['order_amount'] == $has_pay ? 0 : 1;
        $data = ['is_pay'=>$is_pay];
        $this->ajax_return('200','success',$data);
    }

    /**
     * 待支付状态下修改收货地址
     */
    public function update_address(){
        $member_id = $this->member_info['member_id'];
        $order_sn = input('post.order_sn','','trim');
        if(empty($order_sn)){
            $this->ajax_return('11370','invalid order_sn');
        }
        $order = model('Order')->where(['order_sn'=>$order_sn,'buyer_id'=>$member_id])->find();
        if(!$order){
            $this->ajax_return('11371','order_id dose not exist');
        }
        if($order['order_state'] !== 10){
            $this->ajax_return('11311','order_state false');
        }
        $address_id = input('post.address_id');
        $address = model('Address')->where(['member_id'=>$member_id,'address_id'=>$address_id])->find();
        if(!$address){
            $this->ajax_return('11372','invalid address_id');
        }
        $data = [
            'reciver_province_id' => $address['province_id'],
            'reciver_area_id'     => $address['area_id'],
            'reciver_city_id'     => $address['city_id'],
            'reciver_name'        => $address['true_name'],
        ];
        $reciver_info = [
            'mobile'    => $address['mob_phone'],
            'address'   => $address['address'],
        ];
        $data['reciver_info'] = json_encode($reciver_info);

        $has_info = $this->om_model->where(['order_id'=>$order['order_id']])->count();
        if(!$has_info){
            $this->ajax_return('11371','order_id dose not exist');
        }
        $result = $this->om_model->where(['order_id'=>$order['order_id']])->update($data);
        if($result === false){
            $this->ajax_return('11374','failed to add data');
        }
        $this->ajax_return('200','success',[]);
    }
    /**
     * 用户创建订单--优化
     */
    public function custom_create_order(){
        $goods_commonid = input('post.goods_commonid',0,'intval');
        if (empty($goods_commonid)) {
            $this->ajax_return('11460','invalid goods_commonid');
        }
        $goods_num = input('post.goods_num',1,'intval');
        $true_name = input('post.true_name',1,'trim');
        if (empty($true_name) || words_length($true_name) < 2) {
            $this->ajax_return('11461','invalid true_name');
        }
        $mobile = input('post.mobile','','trim');
        if (empty($mobile) || !is_mobile($mobile)) {
            $this->ajax_return('11462','invalid mobile');
        }
        $card_no = input('post.card_no','','trim');
        if (!empty($card_no) && !validation_filter_id_card($card_no)) {
            $this->ajax_return('11463','invalid card_no');
        }
        $business_type = input('post.business_type',1,'intval');
        if ($business_type < 0 || $business_type > 2) {
            $this->ajax_return('11464','invalid business_type');
        }
        $address_id = input('post.address_id',0,'intval');
        if (empty($address_id)) {
            $this->ajax_return('11464','invalid address_id');
        }
        $goods_id = input('post.goods_id',0,'intval');
        //库存规格判断
        $good_param = model('GoodsCommon')->get_good_param($goods_commonid,$goods_id);
        if($good_param === false){
            $this->ajax_return('12012','good is put off sale');
        }
        if($good_param['goods_storage'] < $goods_num){
            $this->ajax_return('12013','good is not enough');
        }
        //判断是否使用积分及余额
        $is_points = input('post.is_points','','intval');
        $points = input('post.points','','intval');
        $is_predeposit = input('post.is_predeposit',0,'intval');
        $predeposit = input('post.predeposit');
        if($goods_id > 0){
            $ga_data = model('GoodsAttribute')
                ->where(['goods_commonid'=>$goods_commonid,'goods_id'=>$goods_id])
                ->find();
            if(empty($ga_data)){
                $this->ajax_return('','invalid goods_id');
            }
        }else{
            $ga_data = model('GoodsCommon')
                ->where(['goods_commonid'=>$goods_commonid])
                ->field('cost_price,goods_price,goods_name')->find();
        }
        //当前用户信息
        $member_info = $this->member_model->member_get_info_by_id($this->member_info['member_id']);
        $buy_info = [
            'business_type'=>$business_type,
            'true_name'=>$true_name,
            'mobile'=>$mobile,
            'address_id'=>$address_id,
            'goods_num'=>$goods_num,
            'goods_commonid'=>$goods_commonid,
            'card_no'=>$card_no,
            'goods_id'=>$goods_id,
            'ga_data'=>$ga_data
        ];
        //四舍五入  订单总价
        $order_amount = round($ga_data['goods_price'] * $goods_num,2);
        if($is_points > 0 ){
            if(empty($points)){
                $this->ajax_return('11469','empty points');
            }
            if($member_info['points'] - $points < 0){
                $this->ajax_return('10376','the points is not enough');
            }
            $is_enough = round($points - $order_amount,2) >= 0 ? 1 : 0;
            if(!$is_enough && $is_predeposit > 0){
                if(empty($member_info['av_predeposit'])){
                    $this->ajax_return('10377','av_predeposit is empty');
                }
                if($member_info['av_predeposit'] - $predeposit < 0){
                    $this->ajax_return('10378','av_predeposit is not enough');
                }
            }
        }
        if($is_points <= 0 && $is_predeposit > 0){
            if(empty($member_info['av_predeposit'])){
                $this->ajax_return('10377','av_predeposit is empty');
            }
            if($member_info['av_predeposit'] - $predeposit < 0){
                $this->ajax_return('10378','av_predeposit is not enough');
            }
        }
        $pay_type = $this->order_model->order_pay_type($points, $predeposit, $order_amount, $this->member_info['member_id'], $is_points, $is_predeposit);
        if(($data = $this->order_model->create_order($member_info,$buy_info,$predeposit, $points, $pay_type))==false){
            $this->ajax_return('11467','create order failed');
        }
        $this->ajax_return('200','success',$data);
    }
    public function custom_create_order_pay(){
        $this->custom_create_order();
    }
    //购物车结算
    public function custom_create_car_order(){
        $goods_commonid = input('post.goods_commonid/d',0,'intval');
        if(!empty($goods_commonid)){
            $this->custom_create_order();
        }
        $car_ids = input('post.car_ids/s','[]','trim');
        $car_ids = json_decode($car_ids,true);
        if (empty($car_ids) || !is_array($car_ids)) {
            $this->ajax_return('12004','invalid shop car id');
        }
        foreach($car_ids as $car_id){
           $car_good = $this->shop_car_model->getById($car_id);
           if(empty($car_good)){
               $this->ajax_return('12004','invalid shop car id');
           }
           //库存规格判断
           $good_param = model('GoodsCommon')->get_good_param($car_good['good_common_id'],$car_good['good_attr_id']);
           if($good_param === false){
               $this->ajax_return('12012','good is put off sale');
           }
           if($good_param['goods_storage'] < $car_good['good_nums']){
               $this->ajax_return('12013','good is not enough');
           }
        }
        $true_name = input('post.true_name','','trim');
        if (empty($true_name) || words_length($true_name) < 2) {
            $this->ajax_return('11461','invalid true_name');
        }
        $mobile = input('post.mobile','','trim');
        if (empty($mobile) || !is_mobile($mobile)) {
            $this->ajax_return('11462','invalid mobile');
        }
        $card_no = input('post.card_no','','trim');
        /*if (!empty($card_no) && !validation_filter_id_card($card_no)) {
            $this->ajax_return('11463','invalid card_no');
        }*/
        $business_type = input('post.business_type',1,'intval');
        if ($business_type < 0 || $business_type > 2) {
            $this->ajax_return('11464','invalid business_type');
        }
        $address_id = input('post.address_id',0,'intval');
        $address_info = model('Address')->where(['address_id'=>$address_id])->find();
        if (empty($address_id) || is_null($address_info)) {
            $this->ajax_return('11464','invalid address_id');
        }
        //判断是否使用积分及余额
        $is_points = input('post.is_points','','intval');
        $points = input('post.points','','intval');
        $is_predeposit = input('post.is_predeposit',0,'intval');
        $predeposit = input('post.predeposit');
        //当前用户信息
        $member_info = $this->member_model->getByMemberId($this->member_info['member_id']);
        //购物车下单商品总价
        $order_amount = $this->shop_car_model->car_products_amount($car_ids);
        if($is_points > 0 ){
            if(empty($points)){
                $this->ajax_return('11469','empty points');
            }
            if($member_info['points'] - $points < 0){
                $this->ajax_return('10376','the points is not enough');
            }
            $is_enough = round($points - $order_amount,2) >= 0 ? 1 : 0;
            if(!$is_enough && $is_predeposit > 0){
                if(empty($member_info['av_predeposit'])){
                    $this->ajax_return('10377','av_predeposit is empty');
                }
                if($member_info['av_predeposit'] - $predeposit < 0){
                    $this->ajax_return('10378','av_predeposit is not enough');
                }
            }
        }
        if($is_points <= 0 && $is_predeposit > 0){
            if(empty($member_info['av_predeposit'])){
                $this->ajax_return('10377','av_predeposit is empty');
            }
            if($member_info['av_predeposit'] - $predeposit < 0){
                $this->ajax_return('10378','av_predeposit is not enough');
            }
        }
        $pay_type = $this->order_model->order_pay_type($points, $predeposit, $order_amount, $this->member_info['member_id'], $is_points, $is_predeposit);
        $buy_info = [
            'business_type'=>$business_type,
            'true_name'=>$true_name,
            'mobile'=>$mobile,
            'address_id'=>$address_id,
            'card_no'=>$card_no,
            'car_ids'=>$car_ids
        ];
        if(($data = $this->order_model->create_car_order($member_info,$buy_info,$predeposit, $points, $pay_type))==false){
            $this->ajax_return('11467','create order failed');
        }
        $this->ajax_return('200','success',$data);
    }

    /**
     * 未支付订单
     */
    public function unorder_member(){
        $member_id = $this->member_info['member_id'];

        $order_sn = $this->order_model->where(['buyer_id'=>$member_id,'order_state'=>10,'delete_state'=>0])
            ->order('create_time desc')->value('order_sn');
        if(empty($order_sn)){
            $this->ajax_return('10480','unpay order is empty');
        }

        $data = [
            'order_sn' =>  $order_sn
        ];

        $this->ajax_return('200','success',$data);
    }

    /**
     * 订单删除
     */
    public function del_order(){
        $param = input("param.");
        $member_id = $this->member_info['member_id'];
        $order_id = $param['order_id'];
        if(empty($order_id)){
            $this->ajax_return('10580','empty order_id');
        }
        $order = $this->order_model->where(['order_id'=>$order_id,'buyer_id'=>$member_id])->find();
        if(empty($order)){
            $this->ajax_return('10581','invalid order');//订单不存在
        }
        $this->order_model->startTrans();
        model('OrderLog')->startTrans();

        $del_order = $this->order_model->where(['order_id'=>$order_id,'buyer_id'=>$member_id])->update(['delete_state'=>'1']);
        if(!$del_order){
            $this->ajax_return('10582','order_model del fail');
        }

        $member_name = model('Member')->where(['member_id'=>$member_id])->value('member_name');

        $log = [
            'order_id' =>  $order_id,
            'log_msg'  =>  $member_name.'在'.date('Y-m-d H:i:s').'删除订单编号为'.$order['order_sn'].'的订单',
            'log_time' =>  time(),
            'log_user' =>  $member_name,
            'log_orderstate' => $order['order_state']
        ];

        $order_log = model('OrderLog')->insert($log);
        if(!$order_log){
            $this->order_model->rollback();
            $this->ajax_return('10585','order_log del fail');
        }

        $this->order_model->commit();
        model('OrderLog')->commit();
        $this->ajax_return('200','success');
    }

    /**
     * 取消订单
     */
    public function cancel_order(){
        $member_id = $this->member_info['member_id'];
        $order_id = input('param.order_id','','int');
        if(!$order_id){
            $this->ajax_return('11600','empty order_id');
        }
        $order = $this->order_model->where(['order_id'=>$order_id,'buyer_id'=>$member_id])->find();
        if(empty($order)){
            $this->ajax_return('11601','invalid order');//订单不存在
        }
        if($order['order_state'] == 0){
            $this->ajax_return('11602','order_state is 0');//该订单已经被取消
        }
        if(in_array($order['order_state'],[20,30,40,50,60])){
            $this->ajax_return('11605','order can not cancel');//该订单不可以取消
        }
        $this->order_model->startTrans();
        model('OrderLog')->startTrans();
        model('Member')->startTrans();
        $this->points_model->startTrans();
        $pay_pd = $this->order_model
            ->where(['order_id'=>$order_id,'buyer_id'=>$member_id])
            ->field("pd_amount,pos_amount")
            ->find();
        $member_info = model('Member')->where(['member_id'=>$member_id])->find();
        $log_msg = $member_info['member_name'].'在'.date('Y-m-d H:i:s').'取消订单编号为'.$order['order_sn'].'的订单';
        $pd_log = [
            'member_id' => $member_id,
            'type'      => 'order_cancel',
            'add_time'  => time(),
            'log_desc'  => '用户取消订单返还账户余额'
        ];
        //仅使用余额支付
        if(!empty(ceil($pay_pd['pd_amount'])) && $pay_pd['pos_amount'] == 0){
            $pd_amount = model('Member')->where(['member_id'=>$member_id])
                ->setInc('av_predeposit',$pay_pd['pd_amount']);
            if(!$pd_amount){
                $this->ajax_return('11606','av_predeposit false');
            }
            $pd_log['av_amount'] = $pay_pd['pd_amount'];
            $log_msg .= "退回余额￥".$pay_pd['pd_amount'].'元';
            $pd_res = model('PdLog')->insertGetId($pd_log);
            if($pd_res == false){
                model('Member')->rollback();
                $this->ajax_return('11604','log update fail');
            }
        }
        //仅使用积分支付
        if(empty(ceil($pay_pd['pd_amount'])) && !empty($pay_pd['pos_amount'])){
            $pos_amount = model('Member')->where(['member_id'=>$member_id])->setInc('points',$pay_pd['pos_amount']);
            if(!$pos_amount){
                $this->ajax_return('11607','points false');
            }
            $log_msg .= "退回积分".$pay_pd['pos_amount'];
            $points_log = [
                'member_id'     => $member_id,
                'member_mobile' => $member_info['member_mobile'],
                'add_time'      => time(),
                'pl_desc'       => '取消订单返回支付使用积分',
                'type'          => 'order_cancel',
                'points'        => $pay_pd['pos_amount']
            ];
            $p_log = $this->points_model->save($points_log);
            if($p_log === false) {
                model('Member')->rollback();
                $this->ajax_return('11604', 'log update fail');
            }
        }
        //使用积分和余额同时支付
        if(!empty(ceil($pay_pd['pd_amount'])) && !empty($pay_pd['pos_amount'])){
            $pos_amount = model('Member')
                ->where(['member_id'=>$member_id])
                ->setInc('points',$pay_pd['pos_amount']);
            $pd_amount = model('Member')
                ->where(['member_id'=>$member_id])
                ->setInc('av_predeposit',$pay_pd['pd_amount']);
            if(!$pos_amount || !$pd_amount){
                $this->ajax_return('11608','av_predeposit false or points false');
            }
            $pd_log['av_amount'] = $pay_pd['pd_amount'];
            $pd_res = model('PdLog')->insertGetId($pd_log);
            if($pd_res == false){
                model('Member')->rollback();
                $this->ajax_return('11604','log update fail');
            }
            $log_msg .= "退回余额￥".$pay_pd['pd_amount']."元,退回积分".$pay_pd['pos_amount'];
            $points_log = [
                'member_id'     => $member_id,
                'member_mobile' => $member_info['member_mobile'],
                'add_time'      => time(),
                'pl_desc'       => '取消订单返回支付使用积分',
                'type'          => 'order_cancel',
                'points'        => $pay_pd['pos_amount']
            ];
            $p_log = $this->points_model->save($points_log);
            if($p_log === false){
                model('Member')->rollback();
                $this->ajax_return('11604','log update fail');
            }
        }

        $order_state = $this->order_model->where(['order_id'=>$order_id,'buyer_id'=>$member_id])->update(['order_state'=>'0']);
        if(!$order_state){
            model('Member')->rollback();
            $this->ajax_return('11603','order_state update fail');
        }
        $log = [
            'order_id' =>  $order_id,
            'log_msg'  =>  $log_msg,
            'log_time' =>  time(),
            'log_user' =>  $member_info['member_name'],
            'log_orderstate' => $order['order_state']
        ];
        $order_log = model('OrderLog')->insert($log);
        if(!$order_log){
            $this->order_model->rollback();
            model('Member')->rollback();
            $this->ajax_return('11604','log update fail');
        }

        model('Member')->commit();
        $this->order_model->commit();
        model('OrderLog')->commit();
        $this->points_model->commit();
        if(!empty($points_log)){
            Hook::listen('create_points_log',$points_log);
        }
        $this->ajax_return('200','success');
    }
    //获取订单列表--优化
    public function get_order_list(){
        $member_id = $this->member_info['member_id'];
        $page = input('param.page',1,'intval');
        //数据分页
        $count = $this->order_model->where(['buyer_id' => $member_id,'order_type' => 1,'delete_state' => 0])->count();
        if ($count < 1) {
            $this->ajax_return('10310','empty data');
        }

        $page_num = 5;
        $page_count = ceil($count / $page_num);
        $limit = 0;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;
        }else{
            $limit = 0;
        }
        $where = ['buyer_id' => $member_id,'order_type' => 1,'delete_state' => 0];
        $order_state = input('param.order_state',0,'intval');
        if($order_state !== 0 && !empty($order_state)){
            if(!in_array($order_state,[10,20,30,40,50])){
                $this->ajax_return('10311','order_state false');
            }
            $where['order_state'] = $order_state;
        }

        $order_list = $this->order_model
            ->where($where)
            ->field('order_id,order_sn,pay_sn,order_amount,order_state')
            ->order('create_time desc')
            ->limit($limit,$page_num)
            ->select()->toArray();
        if($order_list){
            foreach($order_list as &$order){
                $order_goods = $this->order_model->get_order_info($order['order_id'],$order['order_sn'],$member_id);
                $order['goods_info'] = $order_goods['goods'];
            }
            unset($order);
        }
        $this->ajax_return('200','success',$order_list);
    }
    //获取订单详情--优化
    public function get_order_info(){
        $order_sn = input('post.order_sn','','trim');
        $order_info = $this->order_model->getByOrderSn($order_sn);
        if (is_null($order_info)) {
            $this->ajax_return('10320','invalid order_sn');
        }
        if($order_info['order_state'] == 30){
            $order_common_info = $this->order_common_model->getByOrderId($order_info['order_id']);
            if (is_null($order_common_info)) {
                $this->ajax_return('10321','invalid order');
            }
            $express_info = $this->express_model->get_express($order_common_info['shipping_express_id']);
            if (empty($express_info)) {
                $this->ajax_return('10322','invalid express');
            }
        }
        $data = $this->order_model->get_order_info($order_info['order_id'],$order_sn,$this->member_info['member_id']);
        //获取用户余额/积分信息
        $pd_pos = $this->member_model->field('av_predeposit,points')->getByMemberId($order_info['buyer_id']);
        $data['av_predeposit'] = $pd_pos['av_predeposit'];
        $data['points'] = $pd_pos['points'];
        $data['points_money'] = $pd_pos['points'];
        //默认积分开启 余额关闭
        $data['is_points'] = 0;
        $data['is_predeposit'] = 0;
        $data['pd_amount'] = $order_info['pd_amount'];
        $data['pos_amount'] = $order_info['pos_amount'];
        //健康顾问信息
        $adviser_info =[
            'true_name' => '',
            'mobile' => ''
        ];
        if(!empty($order_info['referee_id'])){
            $seller_name = model('StoreSeller')->where(['member_id'=>$order_info['referee_id']])->value('seller_name');
            $adviser_info['mobile'] = $this->member_model->where('member_id',$order_info['referee_id'])->value('member_mobile');
            $adviser_info['true_name'] = $seller_name;
        }
        $data['adviser'] = $adviser_info;
        $this->ajax_return('200','success',$data);
    }
    //删除订单--优化
    public function order_delete(){
        $param = input("param.");
        $member_id = $this->member_info['member_id'];
        $order_id = $param['order_id'];
        if(empty($order_id)){
            $this->ajax_return('10580','empty order_id');
        }
        if($this->order_model->order_delete($order_id,$member_id) === false){
            $this->ajax_return('10582','order_model del fail');
        }
        $this->ajax_return('200','success');
    }
    //取消订单--优化
    public function order_cancel(){
        $order_id = input('param.order_id','','int');
        if(!$order_id){
            $this->ajax_return('11600','empty order_id');
        }
        $order = $this->order_model->where(['order_id'=>$order_id,'buyer_id'=>$this->member_info['member_id']])->find();
        if(empty($order)){
            $this->ajax_return('11601','invalid order');//订单不存在
        }
        if($order['order_state'] == 0){
            $this->ajax_return('11602','order_state is 0');//该订单已经被取消
        }
        if(in_array($order['order_state'],[20,30,40,50,60])){
            $this->ajax_return('11605','order can not cancel');//该订单不可以取消
        }
        $pay_sn = model('Order')->where(['order_id'=>$order_id])->value('pay_sn');
        $pay_info = model('OrderPay')->field('buyer_id,api_pay_state,pay_type,pay_time')->getByPaySn($pay_sn);
        //订单已支付但订单状态还未改变，仍然是未支付状态（例如：第三方回调不成功时）不能取消该订单，需要调用第三方支付查询接口排查
        if(($pay_info['pay_type']==1 && model('OrderPay')->order_query($pay_sn,$pay_info['pay_time'],$pay_info['pay_type'],[$this,'wechat_query']))
        || ($pay_info['pay_type']==2 && model('OrderPay')->order_query($pay_sn,$pay_info['pay_time'],$pay_info['pay_type'],[$this,'alipay_query']))
        || ($pay_info['pay_type']==3 && model('OrderPay')->order_query($pay_sn,$pay_info['pay_time'],$pay_info['pay_type'],[$this,'unipay_query']))
        ){
            $this->ajax_return('11363','order has been pay');
        }
        if(!$this->order_model->order_cancel($order_id,$this->member_info['member_id'])){
            $this->ajax_return('11608','av_predeposit false or points false');
        }
        $this->ajax_return('200','success');
    }

    /**
     * 微信支付结果查询
     * @param $out_trade_no
     * @return bool
     */
    public function wechat_query($out_trade_no){
        $resp_data = model('OrderPay')->wechat_pay_query($out_trade_no);
        if($resp_data === false || !isset($resp_data['return_code'])){
            return false;
        }
        if($resp_data['return_code']==='SUCCESS' && $resp_data['trade_state'] === 'SUCCESS'){
            return $resp_data;
        }
        return false;
    }

    /**
     * 银行卡支付结果查询
     * @param $pdc_sn
     * @param $txn_time
     * @return bool
     */
    public function unipay_query($pdc_sn,$txn_time){
        $resp_data = model('OrderPay')->pay_query($pdc_sn, $txn_time);
        if($resp_data === false || !isset($resp_data['respCode'])){
            return false;
        }
        if($resp_data['respCode']==='00' && $resp_data['origRespCode'] === '00'){
            return $resp_data;
        }
        return false;
    }

    /**
     * 支付宝支付结果查询
     * @param $out_trade_no
     * @param $trade_no
     * @return bool|mixed
     */
    public function alipay_query($out_trade_no,$trade_no){
        $resp_data = model('OrderPay')->ali_pay_query($out_trade_no,$trade_no);
        $resp_data = json_decode(json_encode($resp_data),true);
        if(!isset($resp_data['alipay_trade_query_response']) || empty($resp_data['alipay_trade_query_response'])){
            return false;
        }
        $resp_data = $resp_data['alipay_trade_query_response'];
        if($resp_data === false || !isset($resp_data['code'])){
            return false;
        }
        if($resp_data['code'] =='10000' && $resp_data['trade_status'] == 'TRADE_SUCCESS'){
            return $resp_data;
        }
        return false;
    }
}