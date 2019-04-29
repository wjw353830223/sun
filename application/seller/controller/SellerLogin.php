<?php
/**
* 商家登录类
*
*/
namespace app\seller\controller;
class SellerLogin extends Sellerbase
{
	protected function _initialize(){
        parent::_initialize();
    }

    /**
    * 经销商登录
    */
    public function index(){
    	$this->view->engine->layout(false);
    	return $this->fetch();
    }
}