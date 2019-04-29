<?php
/**
* 商家中心类
*
*/
namespace app\seller\controller;
class SellerCenter extends Sellerbase
{
	protected function _initialize(){
        parent::_initialize();
    }

    public function index(){
    	return $this->fetch();
    }
	
	//库存查看
	public function kc_search(){
		return $this->fetch();
	}
	
	//进货申请
	public function kc_apply(){
		return $this->fetch();
	}
	
	//查看详情
	public function kc_explicit(){
		return $this->fetch();
	}
	public function kc_output(){
		return $this->fetch();
	}
	
	//消息反馈
	public function kc_refuse(){
		return $this->fetch();
	}
	
	//交易列表
	public function order_search(){
		return $this->fetch();
	}
	
	//订单详情
	public function order_detail(){
		return $this->fetch();
	}
	
	//进货列表
	public function order_stock(){
		return $this->fetch();
	}
	
	//进货详情
	public function order_stocked(){
		return $this->fetch();
	}

	//进货设置
    	public function order_setting(){
    		return $this->fetch();
    	}

    //物流设置
        	public function order_logistics(){
        		return $this->fetch();
        	}

    //客户查看
            	public function custom_search(){
            		return $this->fetch();
            	}

    //客户详情
                	public function custom_explicit(){
                		return $this->fetch();
                	}

    //公司业绩详情
                    	public function achievement_search(){
                    		return $this->fetch();
                    	}

    //个人业绩列表
                        	public function achievement_person(){
                        		return $this->fetch();
                        	}

    //个人业绩详情
                      	public function achievement_explicit(){
                                return $this->fetch();
                             }

     //员工管理
                          	public function person_search(){
                                    return $this->fetch();
                                 }
}