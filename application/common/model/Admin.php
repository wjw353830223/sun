<?php
namespace app\common\model;

use think\Model;

class Admin extends Model
{

    /**
	* 角色表关联
	*/
    public function role(){
        return $this->hasOne('AuthRole','role_id','role_id')->field('role_id,name');
    }

    //验证action是否重复添加
    public function checkNameUpdate($data) {
        //检查是否重复添加
        $id=$data['id'];
        unset($data['id']);
        $find = $this->field('admin_id')->where($data)->find();
        if (isset($find['admin_id']) && $find['admin_id']!=$id) {
            return false;
        }
        return true;
    }
}