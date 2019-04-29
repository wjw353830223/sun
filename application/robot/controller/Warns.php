<?php
namespace app\robot\controller;
use JPush\Client;
use JPush\Exceptions\APIRequestException;
use think\Controller;

class Warns extends Robotbase
{
    protected $family_model,$warns_model,$family_member_model;
    protected function _initialize(){
        parent::_initialize();
        $this->family_model = model('family');
        $this->warns_model = model('warns');
        $this->family_member_model = model('family_member');
    }

    /**
     * 机器人推送消息
     */
    public function send_warns(){
        $robot_imei = input('post.robot_sn','','trim');
        $content = input('post.content','','trim');
        if(empty($content)){
            $this->ajax_return('100020','content is empty');
        }

        $warns_data = array();
        $warns_data['warns_content'] = strip_tags($content);

        $family_info = $this->family_model->where(['robot_imei'=>$robot_imei])->field('family_id,state')->find();
        if(empty($family_info)){
            $this->ajax_return('100021','family dose not exist');
        }

        if($family_info['state'] == 0){
            $this->ajax_return('100022','family has been locked');
        }

        $family_member_info = $this->family_member_model->where(['family_id'=>$family_info['family_id']])->field('id,info_id,member_id')->select()->toArray();
        
        $member_info = $notmember = array();
        if(!empty($family_member_info)){
            foreach($family_member_info as $fm_key=>$fm_val){
                if($fm_val['info_id'] == 0){
                    $member_info[] = $fm_val['member_id'];
                }else{
                    $notmember[] = $fm_val['info_id'];
                }
            }
        }
     
        $is_member_str = implode(',', $member_info);
        $is_not_member_str = implode(',', $notmember);

        //注册家庭成员信息
        $is_member_info = model('member')->where(['member_id'=>['in',$is_member_str]])->field('member_avatar,member_name')->select()->toArray();
        foreach($is_member_info as $is_key=>$is_val){
            $is_member_info[$is_key]['member_avatar'] = !empty($is_val['member_avatar']) ? $this->base_url.'/uploads/avatar/'.$is_val['member_avatar'] : '';
        }

        $is_not_member_info = model('FamilyMemberInfo')->where(['info_id'=>['in',$is_not_member_str]])->field('avatar as member_avatar,nickname as member_name')->select()->toArray();
        foreach($is_not_member_info as $ins_key=>$ins_val){
            $is_not_member_info[$ins_key]['member_avatar'] = !empty($ins_val['member_avatar']) ? $this->base_url.'/uploads/avatar/'.$ins_val['member_avatar'] : '';
        }
        
        $member_info = array_merge($is_member_info,$is_not_member_info);
        $warns_data['warner'] = 3;
        $warns_data['warns_type'] = 2;
        $warns_data['family_id'] = $family_info['family_id'];
        $warns_data['create_time'] = time();

        $result = $this->warns_model->save($warns_data);
        if($result === false){
            $this->ajax_return('100023','failed to add data');
        }

        $this->ajax_return('200','success',$member_info);
    }

    /**
     * 机器人报警推送消息
     */
    public function robot_warn_send(){
        $robot_imei = input('post.robot_sn','','trim');
        $family_info = $this->family_model->where(['robot_imei'=>$robot_imei])->field('family_id,state,family_name')->find();
        if(empty($family_info)){
            $this->ajax_return('100021','family dose not exist');
        }

        if($family_info['state'] == 0){
            $this->ajax_return('100022','family has been locked');
        }
        $family_member_info = $this->family_member_model
            ->where(['family_id'=>$family_info['family_id']])
            ->field('id,info_id,member_id')->select();

        $member_info = [];
        if(!empty($family_member_info)){
            foreach($family_member_info as $fm_key=>$fm_val){
                if($fm_val['info_id'] == 0){
                    $member_info[] = $fm_val['member_id'];
                }
            }
        }
        $client = new Client(config('jpush')['app_key'],config('jpush')['master_secret']);
        $push = $client->push();
        $getCid = $push->getCid(count($member_info));
        $cids = $getCid['body']['cidlist'];
        $member_mobile = model('Member')->where(['member_id'=>['in',$member_info]])->column('member_mobile');
        $mobiles = [];
        $push_detail = '您的'.$family_info['family_name'].'家庭机器人发出了报警信息，请立即和家人联系';
        foreach($member_mobile as $k=>$v){
            try{
                $alias = $push->setCid($cids[$k])
                    ->options(['apns_production'=>true])
                    ->setPlatform(['android','ios'])
                    ->addAlias((string)$v)
                    ->iosNotification($push_detail,['title'=>'机器人报警'])
                    ->androidNotification($push_detail,['title'=>'机器人报警'])
                    ->send();
            }catch(APIRequestException $e){
                continue;
            }
            if($alias['http_code'] == 200){
                $mobiles[] = $v;
            }
        }
        $ids = model('Member')->where(['member_mobile'=>['in',$mobiles]])->column('member_id');
        $ids = implode(',',$ids);

        $member_str = implode(',',$member_info);
        $message = [
            'from_member_id' => 0,
            'to_member_id'   => $member_str,
            'message_title'  => '机器人报警',
            'message_time'   => time(),
            'message_state'  => 0,
            'message_type'   => 7,
            'is_more'        => 1,
            'is_push'        => 2,
            'message_body'   => $push_detail,
            'push_detail'    => $push_detail,
            'push_title'     => '机器人报警',
            'push_member_id' => $ids
        ];
        $res = model('Message')->save($message);
        if($res === false){
            $this->ajax_return('100120','the robot alarm failed');
        }
        $this->ajax_return('200','success','');
    }

    /**
     * 验证提交信息
     */
    protected function check($param)
    {
        
    }

    
}