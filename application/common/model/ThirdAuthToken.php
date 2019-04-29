<?php

namespace app\common\model;

use think\Model;

class ThirdAuthToken extends Model
{
    const ACCESS_TOKRN_EXPIRE = 7200;
    /**
     * 生成随机字符串
     * @param int $length
     * @return string
     */
    public function generate_random_string($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {
            $str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }

    /**
     * 生成唯一的secret
     * @return string
     */
    public function generate_secret(){
        $random_str = $this->generate_random_string(32);
        $res = $this->where(['app_secret'=>$random_str])->find();
        if(!empty($res)){
            $secret = $this->generate_secret();
        }else{
            $secret = strtoupper($random_str);
        }
        return $secret;
    }

    /**
     * 生成唯一的appid
     * @return int|mixed
     */
    public function generate_app_id(){
        $app_id = $this->order(['app_id'=>'DESC'])->limit(1)->value('app_id');
        $app_id = !empty($app_id)? (int)$app_id + 1 : 1000000000;
        return $app_id;
    }

    /**
     * 获取第三方机构
     * @param $app_id
     * @param $secret
     */
    public function get_merchant($app_id,$secret){
        return $this->where(['app_id'=>$app_id, 'app_secret'=>$secret])->find();
    }

    /**
     * 生成access_token
     * @return array|bool
     */
    public function generate_access_token($auth_token_id){
        $random_str = $this->generate_random_string(64);
        $res = $this->where(['access_token'=>base64_encode($random_str)])->find();
        if(empty($res)){
           $access_token = base64_encode($random_str);
           $time = time() + ThirdAuthToken::ACCESS_TOKRN_EXPIRE;
           $data = [
               'access_token'=>$access_token,
               'token_expired'=>$time,
           ];
           if(!$this->where(['id'=>$auth_token_id])->update($data)){
               return false;
           }
        }else{
            $data = $this->generate_access_token($auth_token_id);
        }
        return $data;
    }
    /**
     * 刷新access_token
     * @param $access_token
     * @return array|bool
     */
    public function refresh_access_token($access_token){
        if(empty($access_token)){
            return false;
        }
        $random_str = $this->generate_random_string(64);
        $res = $this->where(['access_token'=>base64_encode($random_str)])->find();
        if(empty($res)){
            $new_access_token = base64_encode($random_str);
            $time = time() + ThirdAuthToken::ACCESS_TOKRN_EXPIRE;
            $data = [
                'access_token'=>$new_access_token,
                'token_expired'=>$time,
            ];
            if(!$this->where(['access_token'=>$access_token])->update($data)){
                return false;
            }
        }else{
            $data = $this->refresh_access_token($access_token);
        }
        return $data;
    }

    /**
     * 验证access_token合法性
     * @param $access_token
     * @return bool
     */
    public function validate_access_token($access_token){
        if(empty($access_token)){
            return false;
        }
        $res = $this->where(['access_token'=>$access_token])->find();
        if(empty($res)){
            return false;
        }
        $time = time();
        if($res['token_expired'] < $time){
            return false;
        }
        return true;
    }

    /**
     * 通过access_token获取第三方认证信息
     * @param $access_token
     * @return array|bool|false|\PDOStatement|string|Model
     */
    public function get_auth_token_by_access_token($access_token){
        if(empty($access_token)){
            return false;
        }
        return $this->where(['access_token'=>$access_token])->find();
    }
}
