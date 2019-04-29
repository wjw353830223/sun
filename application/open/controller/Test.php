<?php
/*
 * 第三方检测机构
 */
namespace app\open\controller;

use Qiniu\Auth;
use think\Cache;
use think\Controller;

class Test extends Controller {

    public function _initialize() {
        parent::_initialize();
	}
	//模拟推送积分到合作伙伴
    public function index(){
        $param = input('param.','','trim');
        file_put_contents('third.txt',json_encode($param).PHP_EOL,FILE_APPEND);
    }
    //模拟从合作伙伴得到总积分
    public function get_points(){
        $member_mobile = input('param.member_mobile','','trim');
        $points = model('Member')->where(['member_mobile'=>$member_mobile])->value('points');
        $points_arr = [0,100,200,300,400,500];
        $data = [
            'code' => 200,
            'points' => $points + $points_arr[array_rand($points_arr)],
        ];
        ajax_return($data);
    }
}