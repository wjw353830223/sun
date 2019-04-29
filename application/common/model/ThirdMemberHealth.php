<?php

namespace app\common\model;

use Lib\Http;
use think\Model;

class ThirdMemberHealth extends Model
{
    /**
     * 保存第三方用户信息
     * @param $member_name
     * @param $member_height
     * @param $member_weight
     * @param $member_age
     * @param $member_sex
     */
    public function add_member_health($auth_token_id,$member_name,$member_height,$member_weight,$member_age,$member_sex){
        $data = [
            'auth_token_id'=>$auth_token_id,
            'member_name'=>$member_name,
            'member_height'=>$member_height,
            'member_weight'=>$member_weight,
            'member_age'=>$member_age,
            'member_sex'=>$member_sex
        ];
        $member_healthy_id =  $this->insertGetId($data);
        if(!$member_healthy_id){
            return false;
        }
        $access_key = model('ThirdReport')->add_report($auth_token_id, $member_healthy_id, time());
        if(!$access_key){
            return false;
        }
        return [
            'member_healthy_id'=>$member_healthy_id,
            'access_key'=>$access_key
        ];
    }
}
