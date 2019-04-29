<?php
namespace app\common\model;
use think\Model;
class Guestbook extends Model
{
	/**
	* 会员详细关联
	*/
    public function member(){
        return $this->hasOne('Member','member_id','member_id')->field('member_id,member_mobile,member_name');
    }
}