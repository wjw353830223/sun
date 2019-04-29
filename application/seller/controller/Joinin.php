<?php
/**
* 商家入驻申请类
*
*/
namespace app\seller\controller;
class Joinin extends Sellerbase
{
    protected function _initialize(){
        parent::_initialize();
    }
    public function index()
    {
		$this->redirect('Joinin/step1');
    }

    public function step1(){
    	$agreement = '使用本公司服务所须遵守的条款和条件。
    	1.用户资格本公司的服务仅向适用法律下能够签订具有法律约束力的合同的个人提供并仅由其使用。在不限制前述规定的前提下，本公司的服务不向18周岁以下或被临时或无限期中止的用户提供。如您不合资格，请勿使用本公司的服务。此外，您的帐户（包括信用评价）和用户名不得向其他方转让或出售。另外，本公司保留根据其意愿中止或终止您的帐户的权利。';
    	$this->assign('agreement',$agreement);
    	$this->assign('step',1);
    	$this->view->engine->layout('joinin/joinin_apply');
    	return $this->view->fetch('joinin/joinin_apply_step1');
    }
    public function step2(){
    	$this->assign('step',2);
    	$this->view->engine->layout('joinin/joinin_apply');
    	return $this->view->fetch('joinin/joinin_apply_step2');
    }
    public function step3(){
    	$this->assign('step',3);
    	$this->view->engine->layout('joinin/joinin_apply');
    	return $this->view->fetch('joinin/joinin_apply_step3');
    }
    public function step4(){
    	$this->assign('step',4);
    	$this->view->engine->layout('joinin/joinin_apply');
    	return $this->view->fetch('joinin/joinin_apply_step4');
    }
    public function step5(){
    	$this->assign('step',5);
    	$this->view->engine->layout('joinin/joinin_apply');
    	return $this->view->fetch('joinin/joinin_apply_step5');
    }

    public function pay(){
    	$this->assign('step',5);
    	$this->view->engine->layout('joinin/joinin_apply');
    	return $this->view->fetch('joinin/joinin_apply_pay');
    }

}