<?php

namespace app\open\controller;

use think\Controller;
use think\Db;

class Message extends Controller
{
    public function _initialize()
    {
        parent::_initialize();
    }

    public function leave_message(){
        if(request()->isAjax()){
            $message_question = input('post.message_question','','trim');
            if(empty($message_question)){
                $this->ajax_return('','问题不能为空');
            }
            if(words_length($message_question) > 40){
                $this->ajax_return('','问题不能多于40个字符');
            }
            $message_answer = input('post.message_answer','','trim');
            if(!empty($message_answer) && words_length($message_answer) > 40){
                $this->ajax_return('','回答不能多于40个字符');
            }
            $ip = get_client_ip(0,true);
            $data = [
                'message_question' => $message_question,
                'message_answer'   => $message_answer,
                'message_ip'       => $ip,
                'message_time'     => time()
            ];
            $res = DB::name('leave_message')->insert($data);
            if($res === false){
                $this->ajax_return('','提交失败');
            }
            $this->ajax_return('200','提交成功');
        }
    }

    /**
     * 全局中断输出
     * @param $code string 响应码
     * @param $msg string 简要描述
     * @param $result array 返回数据
     */
    public function ajax_return($code = '200',$msg = '',$result = array()){
        $data = array(
            'code'   => (string)$code,
            'msg'    =>  $msg,
            'result' => $result
        );
        ajax_return($data);
    }
}