<?php

namespace app\common\model;

use think\Hook;
use think\Model;

class ThirdMember extends Model
{
    protected $member_model,$member_token_model,$member_info_model;
    protected function initialize(){
        parent::initialize();
        $this->member_model = model('Member');
        $this->member_token_model = model('MemberToken');
        $this->member_info_model = model('MemberInfo');
    }
    /**
     * 获取第三方用户token
     * @param $mobile
     * @return bool|mixed
     */
    public function get_token($mobile,$access_token){
        if(empty($mobile)){
            return false;
        }
        $token = $this->where(['member_mobile'=>$mobile])->value('token');
        if(!empty($token)){
            $member = $this->member_model->where(['member_mobile'=>$mobile])->select();
            if($member->isEmpty()){
                $this->where(['member_mobile'=>$mobile])->delete();
                return false;
            }
            $this->startTrans();
            $this->member_token_model->startTrans();
            $token = $this->member_token_model->create_token($mobile,'wap');
            if(is_null($token)){
                $this->rollback();
                return false;
            }
            $res = $this->where('member_mobile',$mobile)->setField('token',$token);
            if(!$res){
                $this->rollback();
                $this->member_token_model->rollback();
                return false;
            }
            $this->member_token_model->commit();
            $this->commit();
            return $token;
        }else{
            $member = $this->member_model->where(['member_mobile'=>$mobile])->find();
            if(empty($member)){
                return false;
            }
            $param = [
                "client_type"=>"wap",
                "member_mobile"=>$mobile,
                "member_avatar"=>$member->member_avatar,
                "member_height"=>$member->info->member_height,
                "member_weight"=>$member->info->member_weight,
                "birthday"=>date('Y-m-d',$member->info->birthday),
                "member_sex"=>$member->info->member_sex,
                "member_name"=>$member->member_name,
            ];
            return $this->add_member($param,$access_token);
        }
    }

    /**
     * 注册第三方用户
     * @param $param
     * @param $access_token
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add_member($param,$access_token){
        if(empty($param['member_mobile']) || empty($access_token)){
            return false;
        }
        $this->startTrans();
        $this->member_model->startTrans();
        $this->member_token_model->startTrans();
        $this->member_info_model->startTrans();
        $member_avatar = isset($param['member_avatar'])?$param['member_avatar']:'';
        $member_name = isset($param['member_name'])?$param['member_name']:'';
        //创建或更新会员
        $member = $this->member_model->where(['member_mobile' => $param['member_mobile']])->find();
        if($member){
            $data = [
                'member_avatar'=>$member_avatar,
                'member_name'=>$member_name,
                'login_num' => ['exp','login_num+1'],
                'login_time' => time(),
                'login_ip' => get_client_ip(0,true)
            ];
            $result = $this->member_model->where(['member_mobile' => $param['member_mobile']])->update($data);
            if (!$result) {
                return false;
            }
            $member_id = $member->member_id;
            $birthday = isset($param['birthday'])?$param['birthday']:'1987-12-05';
            $birthday = strtotime($birthday);
            $bir_year = date('Y',$birthday);
            $year = date('Y',time());
            $age = $year - $bir_year;
            $member_info = [
                'member_id'=>$member_id ,
                'member_height' => isset($param['member_height'])?$param['member_height']:175,
                'member_weight' => isset($param['member_weight'])?$param['member_weight']:70,
                'birthday' => $birthday,
                'member_age' => $age,
                'member_sex' => isset($param['member_sex'])?$param['member_sex']:1,
            ];
            $res = $this->member_info_model->where(['member_id'=>$member_id])->update($member_info);
            if($res === false){
                $this->member_model->rollback();
                return false;
            }
        }
        if(!$member){
            $data = ['member_avatar'=>$member_avatar,'member_name'=>$member_name, 'member_mobile' => $param['member_mobile'],'login_num' => 1,'member_time' => time(),'login_time' => time(),'login_ip' => get_client_ip(0,true)];
            $result = $this->member_model->create($data);
            if (!$result) {
                return false;
            }
            $member_id = $result->member_id;
            //创建用户详细信息
            $birthday = isset($param['birthday'])?$param['birthday']:'1987-12-05';
            $birthday = strtotime($birthday);
            $bir_year = date('Y',$birthday);
            $year = date('Y',time());
            $age = $year - $bir_year;
            $member_info = [
                'member_id'=>$member_id ,
                'member_height' => isset($param['member_height'])?$param['member_height']:175,
                'member_weight' => isset($param['member_weight'])?$param['member_weight']:70,
                'birthday' => $birthday,
                'member_age' => $age,
                'member_sex' => isset($param['member_sex'])?$param['member_sex']:1,
            ];
            $res = $this->member_info_model->create($member_info);
            if(!$res){
                $this->rollback();
                $this->member_model->rollback();
                return false;
            }
        }
        //若原来表中有记录则先删去 防止插入数据时报重复主键
        $third_member = $this->where('member_id ='.$member_id.' or member_mobile='.$param['member_mobile'])->select();
        if(!$third_member->isEmpty()){
            $this->where('member_id ='.$member_id.' or member_mobile='.$param['member_mobile'])->delete();
        }
        //创建新token
        $token = $this->member_token_model->create_token($param['member_mobile'],'wap');
        if (is_null($token)) {
            $this->member_model->rollback();
            $this->member_info_model->rollback();
            return false;
        }
        $parterner_id = model('ThirdAuthToken')->where(['access_token'=>$access_token])->value('id');
        //创建第三方用户信息
        $third_member_info = array_merge([
                'member_mobile' => $param['member_mobile'],
                'member_name'=>$member_name,
                'member_avatar'=>$member_avatar,
                'token'=>$token,
                'parterner_id'=>$parterner_id
            ],$member_info);
        $res = $this->save($third_member_info);
        if(!$res){
            $this->member_model->rollback();
            $this->member_token_model->rollback();
            $this->member_info_model->rollback();
            return false;
        }
        $this->commit();
        $this->member_model->commit();
        $this->member_token_model->commit();
        $this->member_info_model->commit();
        //推送初始购物积分给合作伙伴用户
        $points = model('Member')->where(['member_id'=>$member_id])->value('points');
        if($points > 0){
            $point_log = [
                'member_id' => $member_id,
                'add_time' => time(),
                'pl_desc'  => '常仁用户初始购物积分',
                'type'   =>'parterner_shop_points',
                'points' => $points
            ];
            Hook::listen('create_points_log',$point_log);
        }
        return $token;
    }
}
