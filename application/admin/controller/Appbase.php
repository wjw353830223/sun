<?php
/**
* 基础公用类
*
*/
namespace app\admin\controller;
use think\Controller;
use think\View;
use think\Config;
class Appbase extends Controller{

	public $view;
	protected function _initialize(){
    	$this->view = new View([],Config::get('view_replace_str'));
    }
}