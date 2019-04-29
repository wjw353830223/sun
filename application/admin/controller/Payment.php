<?php
namespace app\admin\controller;

class Payment extends Adminbase
{
    protected $payment_model;
    protected function _initialize() {
        parent::_initialize();
        $this->payment_model = model('Payment');
    }


    /**
    * 支付设置列表
    */
    public function index(){
    	$lists = $this->payment_model->select()->toArray();
    	$this->assign('lists',$lists);
    	return $this->fetch();
    }


    /**
     * 支付方式关闭
     *
     */
	public function lock(){
		$payment_id = input("get.id",0,"intval");
		if(!empty($payment_id)){
			$result = $this->payment_model->save(['payment_state'=>0],['payment_id'=>$payment_id]);
	        if ($result !== false) {
	    		$this->success("支付方式关闭成功",url("payment/index"));
	    	}else{
	    		$this->error("支付方式关闭失败！");
	    	}
		}else{
			$this->error("传入数据有误!");
		}
		
	}

	/**
     * 支付方式开启
     *
     */
	public function unlock(){
		$payment_id = input("get.id",0,"intval");
		if(!empty($payment_id)){
			$result = $this->payment_model->save(['payment_state'=>1],['payment_id'=>$payment_id]);
	        if ($result !== false) {
	    		$this->success("支付方式开启成功",url("payment/index"));
	    	}else{
	    		$this->error("支付方式开启失败！");
	    	}
    	}else{
    		$this->error("传入数据有误!");
    	}
	}
}