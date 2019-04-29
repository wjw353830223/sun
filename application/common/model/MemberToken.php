<?php
namespace app\common\model;
use think\Model;
class MemberToken extends Model
{
    protected $autoWriteTimestamp = true;
    protected $updateTime = false;
    /**
	* 角色表关联
	*/
    public function member(){
        return $this->hasOne('Member','member_id','member_id')->field('member_id,member_mobile,member_name,member_avatar,member_state');
    }

    public function create_token($mobile,$client_type){
    	$member_info = db('member')->where('member_mobile',$mobile)->find();
        if (empty($member_info)) {
            return null;
        }
        //生成新的token
        $token = md5($mobile . strval(time()) . strval(rand(0,999999)));
        $user_token = $this->where(['mobile' => $mobile,'client_type' => $client_type])->find();
        //不存在token记录则创建
        if(empty($user_token)){
            $data = [
                'member_id'=>$member_info['member_id'],
                'mobile'=>$mobile,
                'token'=>$token,
                'client_type'=>$client_type
            ];
            $res = $this->data($data)->save();
            if($res === false){
                return null;
            }
        }
        //同步所有客户端的token (wap wehchat android ios)
        $res = $this->where(['mobile'=>$mobile])->update(['token'=>$token]);
        if($res === false){
            return null;
        }
        return $token;
    }
}