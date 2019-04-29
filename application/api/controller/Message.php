<?php
namespace app\api\controller;
use Lib\Sms;
use think\Cache;
use Lib\Http;
use Lib\Watch;
class Message extends Apibase
{
    protected $message_model,$article_model;
	protected function _initialize(){
    	parent::_initialize();
    	$this->message_model = model('Message');
    	$this->article_model = model('Article');
    }
   
	/**
    *	获取消息
	*/
	public function get_message(){
        $data = $this->message_list();
		$cash_num = 0;
		$express_num = 0;
		foreach($data['system_info'] as $k=>$v){
		    if($data['system_info'][$k]['message_type'] == 4){
                $data['cash_info'][] = $data['system_info'][$k];
                if($data['system_info'][$k]['notice'] == 0){
                    $cash_num++;
                }
                unset($data['system_info'][$k]);
                continue;
            }
            if($data['system_info'][$k]['message_type'] == 5){
                $data['express_info'][] = $data['system_info'][$k];
                if($data['system_info'][$k]['notice'] == 0){
                    $express_num++;
                }
                unset($data['system_info'][$k]);
                continue;
            }
        }

        if(empty($data['cash_info'])){
            $data['cash_info'] = [];
        }else{
            foreach($data['cash_info'] as $k=>$v){
                $message_body = json_decode($v['message_body'],true);
                $data['cash_info'][$k]['title'] = $message_body['title'];
                $data['cash_info'][$k]['points'] = $message_body['points'];
            }
            $cash_time = [];
            foreach($data['cash_info'] as $k=>$v){
                $data['cash_info'][$k]['message_body'] = $data['cash_info'][$k]['push_detail'];
                $cash_time[] = $v['message_time'];
                unset($data['cash_info'][$k]['push_detail']);
            }
            array_multisort($cash_time, SORT_DESC, $data['cash_info']);
        }
        if(empty($data['express_info'])){
            $data['express_info'] = [];
        }else{
            foreach($data['express_info'] as $k=>$v){
                $message_body = json_decode($v['message_body'],true);
                $data['express_info'][$k]['goods_name'] = $message_body['goods_name'];
                $data['express_info'][$k]['order_sn'] = $message_body['order_sn'];
                $data['express_info'][$k]['goods_image'] = $message_body['goods_image'];
                $data['express_info'][$k]['express'] = $message_body['express'];
                $data['express_info'][$k]['title'] = $message_body['title'];
                $data['express_info'][$k]['message_body'] = $data['express_info'][$k]['push_detail'];
                unset($data['express_info'][$k]['push_detail']);
            }
            $exp_time = [];
            foreach($data['express_info'] as $k=>$v){
                $exp_time[] = $v['message_time'];
                unset($data['express_info'][$k]['push_detail']);
            }
            array_multisort($exp_time, SORT_DESC, $data['express_info']);
        }
        if(empty($data['family_info'])){
            $data['system_info'] = array_merge($data['system_info'],[]);
        } else{
            $time = [];
            $data['system_info'] = array_merge($data['system_info'],$data['family_info']);
            foreach($data['system_info'] as $k=>$v){
                $time[] = $v['message_time'];
                unset($data['system_info'][$k]['push_detail']);
            }
            array_multisort($time, SORT_DESC, $data['system_info']);
        }
        unset($data['family_info']);

        $data['cash_num'] = $cash_num;
        $data['express_num'] = $express_num;

		$this->ajax_return('200','success',$data);
	}

	private function message_list(){
        //获取家庭消息
        $member_id = $this->member_info['member_id'];
        $register_time = model('Member')->where(['member_id'=>$member_id])->value('member_time');
        $family_info = $this->message_model
            ->where('message_type=2 and del_member_id is null and to_member_id = '.$member_id)
            ->order(['message_time'=>'DESC'])
            ->field('message_type,message_id,message_title,message_body,message_time,read_member_id,return_member_id,push_detail')
            ->select()->toArray();

        //获取系统消息、实名认证、机器人报警、用户升级、
        $system_info = $this->message_model
            ->where(['message_type'=>['in',[1,4,5,6,7,8,9,3,0]],'is_push'=>['in',[0,2]],'message_time'=>['egt',$register_time]])
            ->order(['message_time'=>'DESC'])
            ->field('message_type,message_id,to_member_id,message_title,message_body,message_time,read_member_id,del_member_id,return_member_id,push_detail')
            ->select()->toArray();

        if(empty($family_info) && empty($system_info)){
            $this->ajax_return('10080','empty data');
        }

        $data = array();
        $system_ids = $ids = [];
        foreach ($family_info as $key => $value) {
            if(empty($value['return_member_id'])){
                //已读 1 未读 0
                if(empty($value['read_member_id'])){
                    $data['family_info'][$key]['message_id'] = (string)$value['message_id'];
                    $data['family_info'][$key]['message_type'] = (string)$value['message_type'];
                    $data['family_info'][$key]['message_title'] = !empty($value['message_title']) ? $value['message_title'] : "";
                    $message_body = json_decode($value['message_body'],true);
                    $data['family_info'][$key]['is_invite'] = $message_body['is_invite'];
                    $data['family_info'][$key]['is_join'] = $message_body['is_join'];
                    $data['family_info'][$key]['notice'] = "0";
                    $data['family_info'][$key]['message_time'] = $value['message_time'];
                    if($message_body['is_invite'] == 1){
                        $data['family_info'][$key]['message_body'] = $message_body['member_name'].'邀请您加入'.$message_body['family_name'].'家庭';
                    }elseif($message_body['is_invite'] == 2){
                        $data['family_info'][$key]['message_body'] = $message_body['member_name'].'申请加入'.$message_body['family_name'].'家庭';
                    }
                }else{
                    $data['family_info'][$key]['message_id'] = (string)$value['message_id'];
                    $data['family_info'][$key]['message_type'] = (string)$value['message_type'];
                    $data['family_info'][$key]['message_title'] = !empty($value['message_title']) ? $value['message_title'] : "";
                    $message_body = json_decode($value['message_body'],true);
                    $data['family_info'][$key]['is_invite'] = $message_body['is_invite'];
                    $data['family_info'][$key]['is_join'] = $message_body['is_join'];
                    $data['family_info'][$key]['notice'] = "1";
                    $data['family_info'][$key]['message_time'] = $value['message_time'];
                    if($message_body['is_invite'] == 1){
                        $data['family_info'][$key]['message_body'] = $message_body['member_name'].'邀请您加入'.$message_body['family_name'].'家庭';
                    }elseif($message_body['is_invite'] == 2){
                        $data['family_info'][$key]['message_body'] = $message_body['member_name'].'申请加入'.$message_body['family_name'].'家庭';
                    }
                }
            }
            $ids[] = $value['message_id'];
        }
        foreach ($system_info as $key => $value) {
            $return_member_id = explode(',',$value['return_member_id']);
            if(!in_array($member_id,$return_member_id)){
                $del_member_id = explode(',',$value['del_member_id']);
                $read_member_id = explode(',',$value['read_member_id']);
                if($value['to_member_id'] == 'all'){
                    if(!in_array($member_id, $read_member_id) && !in_array($member_id, $del_member_id)){
                        //未读过该消息的成员
                        $data['system_info'][$value['message_id']]['message_id'] = (string)$value['message_id'];
                        $data['system_info'][$value['message_id']]['message_type'] = (string)$value['message_type'];
                        $data['system_info'][$value['message_id']]['message_title'] = !empty($value['message_title']) ? $value['message_title'] : "";
                        $data['system_info'][$value['message_id']]['notice'] = "0";
                        $data['system_info'][$value['message_id']]['message_time'] = $value['message_time'];
                        $data['system_info'][$value['message_id']]['message_body'] = $value['message_body'];
                        $data['system_info'][$value['message_id']]['push_detail'] = $value['push_detail'];
                    }
                    if(in_array($member_id, $read_member_id) && !in_array($member_id, $del_member_id)){
                        $data['system_info'][$value['message_id']]['message_id'] = (string)$value['message_id'];
                        $data['system_info'][$value['message_id']]['message_type'] = (string)$value['message_type'];
                        $data['system_info'][$value['message_id']]['message_title'] = !empty($value['message_title']) ? $value['message_title'] : "";
                        $data['system_info'][$value['message_id']]['notice'] = "1";
                        $data['system_info'][$value['message_id']]['message_time'] = $value['message_time'];
                        $data['system_info'][$value['message_id']]['message_body'] = $value['message_body'];
                        $data['system_info'][$value['message_id']]['push_detail'] = $value['push_detail'];
                    }
                    $system_ids[] = $value['message_id'];
                }else{
                    $to_member_id = explode(',',$value['to_member_id']);
                    if(in_array($member_id, $to_member_id) && !in_array($member_id, $read_member_id) && !in_array($member_id, $del_member_id)){
                        $data['system_info'][$value['message_id']]['message_id'] = (string)$value['message_id'];
                        $data['system_info'][$value['message_id']]['message_type'] = (string)$value['message_type'];
                        $data['system_info'][$value['message_id']]['message_title'] = !empty($value['message_title']) ? $value['message_title'] : "";
                        $data['system_info'][$value['message_id']]['notice'] = "0";
                        $data['system_info'][$value['message_id']]['message_time'] = $value['message_time'];
                        $data['system_info'][$value['message_id']]['message_body'] = $value['message_body'];
                        $data['system_info'][$value['message_id']]['push_detail'] = $value['push_detail'];
                    }
                    if(in_array($member_id, $to_member_id) && in_array($member_id, $read_member_id) && !in_array($member_id, $del_member_id)){
                        $data['system_info'][$value['message_id']]['message_id'] = (string)$value['message_id'];
                        $data['system_info'][$value['message_id']]['message_type'] = (string)$value['message_type'];
                        $data['system_info'][$value['message_id']]['message_title'] = !empty($value['message_title']) ? $value['message_title'] : "";
                        $data['system_info'][$value['message_id']]['notice'] = "1";
                        $data['system_info'][$value['message_id']]['message_time'] = $value['message_time'];
                        $data['system_info'][$value['message_id']]['message_body'] = $value['message_body'];
                        $data['system_info'][$value['message_id']]['push_detail'] = $value['push_detail'];
                    }
                    if(count($to_member_id) > 1){
                        if(in_array($member_id, $to_member_id) && !in_array($member_id, $read_member_id) && !in_array($member_id, $del_member_id)){
                            $system_ids[] = $value['message_id'];
                        }
                        if(in_array($member_id, $to_member_id) && in_array($member_id, $read_member_id) && !in_array($member_id, $del_member_id)){
                            $system_ids[] = $value['message_id'];
                        }
                    }else{
                        if(in_array($member_id, $to_member_id) && !in_array($member_id, $read_member_id) && !in_array($member_id, $del_member_id)){
                            $ids[] = $value['message_id'];
                        }
                        if(in_array($member_id, $to_member_id) && in_array($member_id, $read_member_id) && !in_array($member_id, $del_member_id)){
                            $ids[] = $value['message_id'];
                        }
                    }
                }
            }
        }
        model('Message')->where(['message_id'=>['in',$ids]])
            ->update(['return_member_id'=>$member_id]);
        $message = model('Message')->where(['message_id'=>['in',$system_ids]])
            ->field('message_id,return_member_id')->select();
        foreach ($message as $k=>$v){
            model('Message')->where(['message_id'=>$v['message_id']])
                ->update(['return_member_id'=>$v['return_member_id'].','.$member_id]);
        }
        if(empty($data['system_info'])){
            $data['system_info'] = [];
        }
        if(empty($data['family_info'])){
            $data['family_info'] = [];
        }
        return $data;
    }

	/**
    *	删除消息
	*/
	public function delete_info(){
		$member_id = $this->member_info['member_id'];
		$param = input("param.");
        if(empty($param['message_id']) || count($param['message_type']) == 0){
            $this->ajax_return('10100','invalid param');
        }
		$message_id = intval($param['message_id']);
		$message_type = intval($param['message_type']);

		$has_info = $this->message_model->where(['message_id'=>$message_id])->find();
		if(empty($has_info)){
			$this->ajax_return('10101','illegal handle');
		}

		//删除家庭消息
		if($message_type == 2){
			$info_array = ['1 , 2'];
			if($has_info['del_member_id'] == $member_id || in_array($has_info['message_state'],$info_array)){
				$this->ajax_return('10102','have been deleted');
			}

			$del_data = ['del_member_id'=>$member_id,'message_state'=>2];

			$del_result = $this->message_model->save($del_data,['message_id'=>$message_id]);
		}else{
            if(empty($has_info['del_member_id'])){
                $del_member_id = $member_id;
            }else{
                $del_member_id = explode(',',$has_info['del_member_id']);
                if(!empty($has_info['del_member_id']) && in_array($member_id,$del_member_id)){
                    $this->ajax_return('10102','have been deleted');
                }

                array_push($del_member_id,$member_id);
                $del_member_id = implode(',',$del_member_id);
            }
            $del_data = ['del_member_id'=>$del_member_id];
            $del_result = $this->message_model->save($del_data,['message_id'=>$message_id]);
        }
		if(!$del_result){
			$this->ajax_return('10103','failed to delete data');
		}
		$this->ajax_return('200','success','');
	}

	/**
    *	读取消息
	*/
	public function read_info(){
		$member_id = $this->member_info['member_id'];
        $message_id = input("post.message_id",0,"intval");
        $message_type = input("post.message_type",0,"intval");
        $ids = explode(',',$message_id);
		if(count($ids) > 1){
            model('Message')->where(['message_id'=>['in',$ids]])->update(['read_member_id'=>$member_id]);
            $this->ajax_return('200','success',[]);
        }else{
            $has_info = $this->message_model->where(['message_id'=>$message_id,'message_type'=>$message_type])->find();
            $data  = array();
            //读取家庭消息
            if($message_type == 2){
                if($member_id == $has_info['del_member_id']){
                    $this->ajax_return('10110','have been deleted');
                }
                if($member_id != $has_info['read_member_id']){
                    $read_member_id = $member_id;
                    $result = $this->message_model->save(['read_member_id'=>$read_member_id],['message_id'=>$message_id]);
                    if(!$result){
                        $this->ajax_return('10111','failed to save data');
                    }
                }
            }else{
                $del_member_id = explode(',',$has_info['del_member_id']);

                if(in_array($member_id, $del_member_id)){
                    $this->ajax_return('10110','have been deleted');
                }

                $read_member_id = explode(',',$has_info['read_member_id']);

                if(!in_array($member_id, $read_member_id)){
                    if(empty($read_member_id)){
                        $read_member_id = $member_id;
                    }else{
                        array_push($read_member_id,$member_id);
                        $read_member_id = implode(',',$read_member_id);
                    }
                    $result = $this->message_model->save(['read_member_id'=>$read_member_id],['message_id'=>$message_id]);

                    if(!$result){
                        $this->ajax_return('10111','failed to save data');
                    }
                }
            }
            $data['message_title'] = !empty($has_info['message_title']) ? $has_info['message_title'] : "";
            $data['message_body'] = $has_info['message_body'];
            $data['message_time'] = $has_info['message_time'];
            $data['notice'] = "1";
            $this->ajax_return('200','success',$data);
        }
	}
    

    /**
    * 同意加入家庭
    */
    public function join_family(){
        $message_id = input('post.message_id',0,'intval');
        $member_id = $this->member_info['member_id'];
        $message_info = $this->message_model->where(array('message_id' => $message_id,'to_member_id' => $member_id,'message_type' => 2))->field('message_body,from_member_id')->find(); 
        if (empty($message_info)) {
            $this->ajax_return('11200','empty data');
        }
        $message_body = json_decode($message_info['message_body'],true);

        $family_member_id = model('family')->where(array('family_id' => $message_body['family_id'],'state' => 1))->value('member_id');

        if (empty($family_member_id)) {
            $this->ajax_return('11201','family state is unusual');
        }

        if ($message_body['is_invite'] < 1 || $message_body['is_invite'] > 2) {
            $this->ajax_return('11202','illegal handle');
        }

        //邀请加入家庭信息
        if ($message_body['is_invite'] == 1) {
            if ($family_member_id == $member_id) {
                $this->ajax_return('10203','family owner dose join');
            }
            
            $has_join = model('family_member')->where(array('member_id' => $member_id,'family_id' => $message_body['family_id']))->count();

            if ($has_join > 0) {
                $this->ajax_return('11204','have been join');
            }

            $true_id = $member_id;
        }

        //申请加入家庭信息
        if ($message_body['is_invite'] == 2) {
            if ($family_member_id != $member_id ) {
                $this->ajax_return('11205','is not family owner');
            }

            $has_join = model('family_member')->where(array('member_id' => $message_info['from_member_id'],'family_id' => $message_body['family_id']))->count();

            if ($has_join > 0) {
                //已加入家庭，请勿重复操作
                $this->ajax_return('11203','have been join');
            }

            $true_id = $message_info['from_member_id'];
        }

        //已加入家庭
        $message_body['is_join'] = 1;
        $array = json_encode($message_body);
        $family_model = model('family');
        $member_model = model('family_member');
        $message_model = model('message');
        $family_model->startTrans();
        $member_model->startTrans();
        $message_model->startTrans();

        $watch_imei = model('member_info')->where(['member_id'=>$family_member_id])->value('watch_imei');
        if(!empty($watch_imei)){
            $member_info = model('member')->field('member_mobile,member_avatar,member_name')->where(['member_id'=>$true_id])->find();
            if(empty($member_info)){
                $this->ajax_return('11200','empty data');
            }
        }
        $relative_id = !empty($result['relative_id']) ? $result['relative_id'] : '';
        $result = $member_model->save(['relative_id'=>$relative_id,'member_id' => $true_id,'family_id' => $message_body['family_id'],'is_member' => 1,'create_time' => time()]);
        if ($result === false) {
            //加入失败
            $this->ajax_return('11206','handle of join wrong');
        }

        $message_result = $message_model->save(['read_member_id'=>$member_id,'update_time' => time(),'message_body'=>$array],['message_id' => $message_id]);
        if ($message_result === false) {  
            $family_model->rollback();
            $this->ajax_return('11206','handle of join wrong');
        }

        $family_result = $family_model->where(['family_id' => $message_body['family_id']])->setInc('member_count'); 
        if ($family_result === false) {
            $family_model->rollback();
            $this->ajax_return('11206','handle of join wrong');
        }

        $family_model->commit();
        $member_model->commit();
        $message_model->commit();

        $this->ajax_return('200','success','');
    }

	
}
