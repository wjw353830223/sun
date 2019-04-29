<?php
namespace app\api\controller;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16
 * Time: 11:21
 */
class Signature{
    public function get_sign(){
        $param = $_GET;
        ksort($param);
        unset($param['signature']);
        $sort_str = http_build_query($param);
        $signature = sha1($sort_str);
        $param['signature'] = $signature;
        echo  json_encode($param);
    }
    public function get_sign_new(){
        $param = $_POST;
        ksort($param);
        unset($param['signature']);
        $sort_str = http_build_query($param);
        $signature = sha1($sort_str);
        $param['signature'] = $signature;
        echo  json_encode($param);
    }
}
