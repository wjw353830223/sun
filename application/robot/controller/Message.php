<?php
namespace app\robot\controller;
class Message extends Robotbase
{
    protected function _initialize()
    {
        parent::_initialize();
    }

    /**
    * 获取信息列表
    */
    public function message_list(){
        $data = [];
        for ($i=1; $i < 6; $i++) { 
            $tmp = [
                'message_id' => $i,
                'message_title' => '测试信息'.$i,
                'message_content' => '测试信息内容'.$i,
                'create_time' => time(),
            ];
            $data[] = $tmp;
        }
        $this->ajax_return('200','Success',$data);
    }

}