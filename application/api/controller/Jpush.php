<?php

namespace app\api\controller;

use JPush\Client;
use JPush\Exceptions\APIRequestException;
use JPush\Exceptions\JPushException;

class Jpush extends Apibase
{
    private $message_model,$member_model;
    public function _initialize()
    {
        parent::_initialize();
        $this->message_model = model('Message');
        $this->member_model = model('Member');
    }

    public function message_push(){
        $member_id = $this->member_info['member_id'];
        $member_mobile = $this->member_info['member_mobile'];
        $data = $this->message_model->get_message($member_id);
        if(!empty($data)){
            $client = new Client(config('jpush')['app_key'],config('jpush')['master_secret']);
            $push = $client->push();
            if(isset($data['one']) && !empty($data['one'])){
                $message = $data['one'];
                $ids = [];
                $num = 0;
                if(count($message) > 0){
                    $getCid = $push->getCid(count($message));
                    $cids = $getCid['body']['cidlist'];
                    //个人消息
                    foreach($message as $k=>$v){
                        try{
                            $alias = $push->setCid($cids[$num])
                                ->options(['apns_production'=>true])
                                ->setPlatform(['android','ios'])
                                ->addAlias((string)$member_mobile);
                            if($v['message_type'] == 0){
                                $notification = $alias
                                    ->iosNotification($v['message_body'],['title'=>$v['push_title']])
                                    ->androidNotification($v['message_body'],['title'=>$v['push_title']]);
                            }else{
                                $notification = $alias
                                    ->iosNotification($v['push_detail'],['title'=>$v['push_title']])
                                    ->androidNotification($v['push_detail'],['title'=>$v['push_title']]);
                            }
                            $res = $notification->send();
                        }catch (JPushException $e){
                            continue;
                        }
                        if($res['http_code'] !== 200){
                            $this->ajax_return('11910','message push false');
                        }
                        $ids[] = $v['message_id'];
                        $num++;
                    }
                    $this->message_model->where(['message_id'=>['in',$ids]])->update(['push_member_id'=>$member_id]);
                }
            }
            if(isset($data['more']) && !empty($data['more'])){
                $more = [];
                $msg = $data['more'];
                $number = 0;
                if(count($msg) > 0){
                    $getCids = $push->getCid(count($msg));
                    $scid = $getCids['body']['cidlist'];
                    //多人接收的消息
                    foreach($msg as $k=>$v){
                        try{
                            $alias = $push->setCid($scid[$number])
                                ->options(['apns_production'=>true])
                                ->setPlatform(['android','ios'])
                                ->addAlias((string)$member_mobile);
                            if($v['message_type'] == 0){
                                $notification = $alias
                                    ->iosNotification($v['message_body'],['title'=>$v['push_title']])
                                    ->androidNotification($v['message_body'],['title'=>$v['push_title']]);
                            }else{
                                $notification = $alias
                                    ->iosNotification($v['push_detail'],['title'=>$v['push_title']])
                                    ->androidNotification($v['push_detail'],['title'=>$v['push_title']]);
                            }
                            $res = $notification->send();
                        }catch(JPushException $e){
                            continue;
                        }
                        if($res['http_code'] !== 200){
                            $this->ajax_return('11910','message push false');
                        }
                        $more[] = $v['message_id'];
                        $number++;
                    }
                    $datas = $this->message_model
                        ->where(['message_id'=>['in',$more]])
                        ->field('message_id,push_member_id')->select();
                    foreach($datas as $v){
                        $this->message_model->where(['message_id'=>$v['message_id']])
                            ->update(['push_member_id'=>$v['push_member_id'].','.$member_id]);
                    }
                }
            }
        }
        $this->ajax_return('200','success','');
    }
}