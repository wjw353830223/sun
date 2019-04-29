<?php
namespace app\common\model;

use JPush\Client;
use JPush\Exceptions\JPushException;
use think\Model;

class Message extends Model
{
    /**
     * @param $alias
     * @param $push_detail 推送详细
     * @param $title 推送标题
     * @param $message 数据库保存信息
     * @return bool
     */
    public function send_message($alias,$push_detail,$title,$message){
        $appKey = config('jpush.app_key');
        $masterSecret = config('jpush.master_secret');
        $client = new Client($appKey,$masterSecret);
        $push = $client->push();
        $cid = $push->getCid();
        $cid = $cid['body']['cidlist'][0];
        try{
            $res = $push->setCid($cid)
                ->options(['apns_production'=>true])
                ->setPlatform(['android','ios'])
                ->addAlias((string)$alias)
                ->iosNotification($push_detail,['title'=>$title])
                ->androidNotification($push_detail,['title'=>$title])
                ->send();
        } catch (JPushException $e){
            return false;
        }
        if(!$res['http_code'] == 200){
           return false;
        }
        $message_base = [
            'from_member_id' =>  0,
            'to_member_id'   => 0,
            'message_title'  => '',
            'message_body'   => '',
            'message_time'   => time(),
            'message_state'  => 0,
            'message_type'   => 0,
            'is_more'        => 0,
            'push_title'     => '',
            'push_detail'    => '',
            'read_member_id' => null,
            'del_member_id' => null,
            'update_time' => time()
        ];
        $save_message = array_merge($message_base, $message);
        $res = $this->insert($save_message);
        if(!empty($res)){
            return true;
        }
        return false;
    }

    /**
     * 获取推送消息列表
     * @param int $member_id
     * @return array $data
     */
    public function get_message($member_id){
        $message = $this
            ->where(['is_push'=>['in',[1,2]]])
            ->order(['message_time'=>'DESC'])
            ->field('message_type,message_id,to_member_id,message_title,message_body,message_time,read_member_id,del_member_id,	push_member_id,push_detail,push_title')
            ->select()->toArray();
        $data = [];
        if(count($message) > 0){
            foreach($message as $key=>$val){
                $read_member_id = explode(',',$val['read_member_id']);
                $del_member_id = explode(',',$val['del_member_id']);
                $push_member_id = explode(',',$val['push_member_id']);
                if($val['to_member_id'] == 'all'){
                    if(!in_array($member_id,$read_member_id) &&
                        !in_array($member_id,$push_member_id) &&
                        !in_array($member_id,$del_member_id)){
                        $data['more'][] = $val;
                    }
                }else{
                    $to_member_id = explode(',',$val['to_member_id']);
                    if(in_array($member_id,$to_member_id) &&
                        !in_array($member_id,$read_member_id) &&
                        !in_array($member_id,$push_member_id) &&
                        !in_array($member_id,$del_member_id)){
                        if(count($to_member_id) > 1){
                            $data['more'][] = $val;
                        }else{
                            $data['one'][] = $val;
                        }
                    }
                }
            }
        }
        return $data;
    }
}