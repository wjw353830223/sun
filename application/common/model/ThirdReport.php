<?php

namespace app\common\model;

use think\Model;

class ThirdReport extends Model
{
    /**
     * @param $auth_token_id
     * @param $file_path
     * @param $upload_ip
     * @param $member_health_id
     * @param $create_time
     * @return int|string
     */
    public function add_report($auth_token_id, $member_health_id, $create_time){
        $access_key = $this->gen_access_key($auth_token_id);
        $data = [
            'auth_token_id'=>$auth_token_id,
            'member_health_id'=>$member_health_id,
            'create_time'=>$create_time,
            'access_key'=>$access_key,
        ];
        if(!$this->insert($data)){
            return false;
        }
        return $access_key;
    }
    public function save_report($auth_token_id, $file_path, $upload_ip, $member_health_id, $time){
        $data = [
            'file_path'=>$file_path,
            'upload_ip'=>$upload_ip,
            'create_time'=>$time
        ];
        return $this->where([ 'auth_token_id'=>$auth_token_id, 'member_health_id'=>$member_health_id])->update($data);
    }
    /**
     * @param $member_health_id
     * @return array|bool|false|\PDOStatement|string|Model
     */
    public function get_report($access_key){
        if(empty($access_key)){
            return false;
        }
        return $this->where(['access_key'=>$access_key])->find();
    }

    /**
     * 生成用户报告访问key
     * @param $auth_token_id
     * @return string
     */
    public function gen_access_key($auth_token_id){
        $access_key = md5(md5($auth_token_id) . time() . random_string(12));
        $res = $this->where(['access_key'=>$access_key])->find();
        if($res){
            $access_key = $this->gen_access_key($auth_token_id);
        }
        return $access_key;
    }
}
