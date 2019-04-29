<?php
namespace app\seller\controller;
class Index extends Sellerbase
{
	protected function _initialize(){
        parent::_initialize();
    }
    
    public function index()
    {
    	$this->view->engine->layout(false);
		return $this->view->fetch();
    }

}
