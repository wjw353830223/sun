<?php
namespace app\common\model;

use think\Model;

class ExperienceLog  extends Model
{
    /**
     * 用户下单获取经验值
     * @param $member_id
     * @param $member_mobile
     * @param $experience
     * @return bool
     */
    public function save_log($member_id,$member_mobile,$experience){
        $experience_log = [
            'member_id' => $member_id,
            'member_mobile' => $member_mobile,
            'type' => 'order_pay',
            'experience' => $experience,
            'add_time' => time(),
            'ex_desc' => '用户下单增加经验值'
        ];
        
        $result = $this->save($experience_log);
        if($result === false){
            return false;
        }
        return true;
    }
}