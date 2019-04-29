<?php
namespace app\robot\controller;
use think\Controller;
class Robot extends Robotbase
{
    protected $family_model;
    protected function _initialize()
    {
        parent::_initialize();
        $this->family_model = model('Family');
    }

    /**
     * 机器人解绑
     */
    public function robot_unbind()
    {
        $robot_sn = $this->token_info['robot_sn'];
        $family_info = $this->family_model->where('robot_imei',$robot_sn)->find();
        if (is_null($family_info)) {
            $this->ajax_return('200','Success');
        }

        $family_info = $family_info->toArray();
        $result = $this->family_model->where('family_id',$family_info['family_id'])->setField('robot_imei','');
        if ($result === false) {
            $this->ajax_return('100050','Failed to update data');
        }else{
            $this->ajax_return('200','Success');
        }
    }

    
    /**
     * 机器人绑定查询
     */
    public function bind_info()
    {
        $robot_sn = $this->token_info['robot_sn'];
        $family_info = $this->family_model->where('robot_imei',$robot_sn)->find();
        if (is_null($family_info)) {
            $this->ajax_return('100060','Invalid robot SN');
        }

        $family_info = $family_info->toArray();
        $this->ajax_return('200','Success',['family_name' => $family_info['family_name']]);
    }
    
}