<?php
namespace app\index\controller;

class Open extends Indexbase
{
    protected $order_model,$pay_model,$member_model,$log_model,$payment_model;
    protected function _initialize(){
        parent::_initialize();
        $this->order_model = model('Order');
        $this->pay_model = model('OrderPay');
        $this->member_model = model('Member');
        $this->log_model = model('OrderLog');
        $this->payment_model = model('Payment');
    }
    public function index(){
        return $this->fetch();
    }
    /**
     * 支付返回的地址
     */
    public function pay_return(){
        return redirect($this->base_url.'/h5/#/pages/order/orderList?refresh=true');
    }
}