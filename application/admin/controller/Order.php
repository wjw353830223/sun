<?php
namespace app\admin\controller;
use think\Db;
class Order extends Adminbase
{
    protected $order_model,$gi_model;
    public function _initialize() {
        parent::_initialize();
        $this->order_model = model('Order');
        $this->gi_model = model('GoodsImages');
    }

    //订单列表
    public function index(){
        $where = $param = array();
        if(request()->isGet()){

            $order_sn = input('get.order_sn','','trim');
            if(!empty($order_sn)){
                $where['order_sn'] = $order_sn;
            }

            $param['order_state'] = input('get.order_state','','intval');

            $start_time = input('get.start_time');
            if(!empty($start_time)){
                $start_time = strtotime($start_time);
                $where['create_time'] = ['egt',$start_time];
            }

            $end_time = input('get.end_time');
            if(!empty($end_time)){
                $end_time = strtotime($end_time);
                $where['create_time'] = ['lt',$end_time];
            }

            $end_time = input('get.end_time');
            if(!empty($start_time) && !empty($end_time)){
                $over_time = strtotime($end_time);
                $where['create_time'] = array(array('egt',$start_time),array('lt',$over_time));
            }

            $s_name = input('get.seller_name','','trim');
            if(!empty($s_name)){
                $where['seller_name'] = $s_name;
            }
        }
        // $order_model = db('order');
        $count = $this->order_model->where($where)->count();
        $where['order_type'] = 1;
        $where['lock_state'] = 0;
        $lists = $this->order_model->where($where)->field('order_sn,seller_name,order_amount,order_state,create_time')->paginate(20,$count);
        $page = $lists->render();
        $this->assign('page',$page);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    //交易订单列表
    public function trade(){
        $where = [];
        $query = [];
        $order_sn = input('get.order_sn','','trim');
        if(!empty($order_sn)){
            $query['order_sn'] = $order_sn;
            $where['order_sn'] = $order_sn;
        }

        $order_state = input('get.order_state','','intval');
        if(!empty($order_state) && strlen($order_state) > 0){
            $query['order_state'] = $order_state;
            $where['order_state'] = $order_state;
        }
        $start_time = input('get.start_time');
        if(!empty($start_time)){
            $query['start_time'] = $start_time;
            $begin_time = strtotime($start_time);
            $where['create_time'] = ['egt',$begin_time];
        }

        $end_time = input('get.end_time');
        if(!empty($end_time)){
            $query['end_time'] = $end_time;
            $over_time = strtotime($end_time);
            $where['create_time'] = ['lt',$over_time];
        }

        if(!empty($start_time) && !empty($end_time)){
            $query['start_time'] = $start_time;
            $query['end_time'] = $end_time;
            $begin_time = strtotime($start_time);
            $over_time = strtotime($end_time);
            $where['create_time'] = [['egt',$begin_time],['lt',$over_time]];
        }

        $buyer_mobile = input('get.buyer_mobile','','trim');
        if(!empty($buyer_mobile)){
            $query['buyer_mobile'] = $buyer_mobile;
            $where['buyer_mobile'] = $buyer_mobile;
        }

        $order_model = $this->order_model;
        $count = $order_model->where($where)->count();
        $where['order_type'] = 1;
        $where['lock_state'] = 0;

        $lists = $order_model->where($where)
            ->field('order_sn,buyer_name,order_amount,buyer_mobile,order_state,create_time,order_id,payment_code')
            ->order('order_id desc')->paginate(15,$count,['query'=>$query]);

        $page = $lists->render();
        $this->assign('page',$page);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    //交易订单详情
    public function order_detail(){
        $order_id = input('get.order_id',0,'intval');
        $order_info = model('Order')->where(['order_id'=>$order_id])->field('order_id,order_sn,pay_sn,order_state,order_amount,shipping_fee,buyer_id,buyer_name,payment_code,create_time')->find();
        /***收货人信息***/
        $buyer_info = model('OrderCommon')->where(['order_id'=>$order_info['order_id']])->find();

        $reciver_info = json_decode($buyer_info['reciver_info'],true);
        $address = model('area')->get_name($buyer_info['reciver_area_id']);
        $reciver_info['address'] = $address.$reciver_info['address'];
        $buyer_info['reciver_info'] = $reciver_info;
        $order_info['buyer_info'] = $buyer_info;
        $member_mobile = model('Member')->where(['member_id'=>$order_info['buyer_id']])->value('member_mobile');
        $order_info['member_mobile'] = $member_mobile;
        //订单商品详情
        $order_goods = model('OrderGoods')->where(['order_id'=>$order_info['order_id']])->field('goods_id,goods_commonid,goods_name,goods_price,goods_num,goods_image,spec_value')->select();
        $pic_base_url = config('qiniu.buckets')['images']['domain'];
        $order_partition_sn_nums = 0;
        foreach($order_goods as &$order_good){
            $image = $order_good['goods_image'];
            $order_good['goods_image']= config('qiniu.buckets')['images']['domain'] . '/uploads/product/'.str_replace(strrchr($image,"."),"",$image).'_140x140.'.substr(strrchr($image, '.'), 1);
            $order_good['goods_amount'] = $order_good['goods_price'] * $order_good['goods_num'];
            $spec_value = json_decode($order_good['spec_value'],true);
            $value = [];
            if(!empty($spec_value)){
                foreach($spec_value as $v){
                    $value[] = $v;
                }
                $order_good['spec_value'] = count($value) > 0 ? implode(' ',$value) : '默认规格';
            }else{
                $order_good['spec_value'] = '默认规格';
            }
            $order_partition_sn = model('OrderPartition')
                ->where(['order_sn'=>$order_info['order_sn'],'good_common_id'=>$order_good['goods_commonid'],'good_attr_id'=>$order_good['goods_id']])
                ->value('order_partition_sn');
            if($order_partition_sn){
                $order_good['order_partition_sn'] = $order_partition_sn;
                $order_partition_sn_nums +=1;
            }else{
                $order_good['order_partition_sn'] = '';
            }
        }
        unset($order_good);
        $order_info['goods_info'] = $order_goods;
        switch ($order_info['payment_code']) {
            case 'weixin':
                $order_info['payment_code'] = '微信支付';
                break;
            case 'alipay':
                $order_info['payment_code'] = '支付宝支付';
                break;
            case 'offline':
                $order_info['payment_code'] = '线下自提';
                break;
            case 'predeposit':
                $order_info['payment_code'] = '站内余额支付';
                break;
            case 'mixed':
                $order_info['payment_code'] = '混合支付';
                break;
            case 'points':
                $order_info['payment_code'] = '站内积分支付';
                break;
            case 'unipay':
                $order_info['payment_code'] = '银行卡支付';
                break;
            default:
                # code...
                break;
        }

        //操作记录
        $order_log = model('OrderLog')->where(['order_id'=>$order_info['order_id']])->order(['log_time'=>'desc'])->select();
        $order_info['order_log'] = $order_log;
        $this->assign('order_info',$order_info);
        $this->assign('order_partition_sn_nums',$order_partition_sn_nums);
        return $this->fetch();
    }

    //订单编辑
    public function edit(){
        return $this->fetch();
    }

    //订单编辑提交
    public function edit_post(){

    }
}