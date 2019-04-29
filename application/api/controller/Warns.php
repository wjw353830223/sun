<?php
namespace app\api\controller;
use Lib\Sms;
use think\Cache;
use Lib\Http;
use Lib\Watch;
class Warns extends Apibase
{
    protected $warns_model;
	protected function _initialize(){
    	parent::_initialize();
    	$this->warns_model = model('warns');
    }
   
	/**
    *	获取家庭提醒列表
	*/
	public function get_family_list(){
		$family_id = input('post.family_id','','intval');
		if(empty($family_id)){
			$this->ajax_return('10193','invalid family_id');
		}

		$warns_info = $this->warns_model->where(['family_id'=>$family_id,'warns_state'=>1,'warner'=>['neq',1]])->order(['create_time'=>'DESC'])->field('warner,warns_id,warns_content,send_member,accept_member')->select();

		if(empty($warns_info->toArray())){
			$this->ajax_return('10194','empty data');
		}
        $tmp = [];
		//获取发送会员头像
		foreach($warns_info as $k=>$v){
			$tmp[$k] = $v['send_member'];
		}
		$send_member_arr = implode(',',$tmp);
		$family_member_arr = model('family_member')->where(['id'=>['in',$send_member_arr]])->field('member_id,id')->select();

		$member_arr = array();
		foreach($family_member_arr as $kk=>$vv){
			$member_arr[] = $vv['member_id'];
		}
		$member_id_arr = implode(',', $member_arr);
		$member_info_arr = model('member')->where(['member_id'=>['in',$member_id_arr]])->field('member_id,member_avatar')->select();
		$temp = array();
		foreach($family_member_arr as $k=>$v){
            foreach ($member_info_arr as $kk => $vv) {
                if($v['member_id'] == $vv['member_id']){
                    $temp[$v['id']] = $vv['member_avatar'];
                }
            }
        }

		$data = array();
		
		foreach ($warns_info as $key => $value) {
			foreach ($temp as $kk => $vv) {
                if($kk == $value['send_member']){
                    $data[$key]['send_member_avatar'] = !empty($vv) ? $this->base_url.'/uploads/avatar/'.$vv : '';
                }
            }
			$data[$key]['warns_id'] = $value['warns_id'];
			$data[$key]['warns_content'] = $value['warns_content'];
			$data[$key]['warner'] = $value['warner'];
			$data[$key]['send_member'] = $value['send_member'];
			$data[$key]['accept_member'] = $value['accept_member'];
		}
		$this->ajax_return('200','success',$data);
	}

	/**
    *	获取个人提醒列表
	*/
	public function get_self_list(){
		$family_id = input('post.family_id','0','intval');
		if(empty($family_id)){
			$this->ajax_return('10500','invalid family_id');
		}

		$family_member_id = model('family_member')->where(['member_id'=>$this->member_info['member_id'],'family_id'=>$family_id])->value('id');
		if(empty($family_member_id)){
			$this->ajax_return('10501','invalid family_member_id');
		}

		$warns_info = $this->warns_model->where(['send_member'=>['eq',$family_member_id]])->select();

		if(empty($warns_info->toArray())){
			$this->ajax_return('10502','empty data');
		}

		//获取接收会员姓名
		foreach($warns_info as $k=>$v){
			$tmp[$k] = $v['accept_member'];
		}
		
		$accept_member_arr = implode(',',array_unique($tmp));
		$family_member_arr = model('family_member')->where(['id'=>['in',$accept_member_arr]])->field('member_id,id')->select()->toArray();
		
		$member_arr = array();
		foreach($family_member_arr as $kk=>$vv){
			$member_arr[] = $vv['member_id'];
		}
		$member_id_arr = implode(',', $member_arr);
		$member_info_arr = model('member')->where(['member_id'=>['in',$member_id_arr]])->field('member_id,member_name')->select()->toArray();
		
		$member_info = array();
		foreach($family_member_arr as $k=>$v){
			$tmp = array();
			foreach ($member_info_arr as $kk => $vv) {
				if($v['member_id'] == $vv['member_id']){
					$family_member_arr[$k]['member_name'] = $vv['member_name'];
					break;
				}
			}
		}
		
		$warns_data = array();
		foreach($warns_info as $k=>$v){
			$tmp = array();
			$tmp['warns_id'] = $v['warns_id'];
			$tmp['family_id'] = $family_id;
			$tmp['send_time'] = date('H:i',$v['send_time']);
			$tmp['warns_state'] = $v['warns_state'];
			$tmp['warns_content'] = $v['warns_content'];
			foreach ($family_member_arr as $kk => $vv) {
				if($vv['id'] == $v['accept_member']){
					$tmp['accept_member_name'] = !empty($vv['member_name']) ? $vv['member_name'] : '';
					break;
				}
			}
			
			if(!empty($v['repeat_time'])){
				$repeat_time = explode(',',$v['repeat_time']);

				$sun_array = ['1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六','7'=>'周日'];
				$sun_data = array();
				foreach($repeat_time as $kk=>$vv){
					$sun_data[$kk] = $sun_array[$vv];
				}
				$repeat_time = implode(',',$sun_data);
				$tmp['repeat_time'] = $repeat_time;
			}else{
				$tmp['repeat_time'] = '';
			}
			$tmp['warner'] =  $v['warner'];
			$tmp['accept_member'] = $v['accept_member'];
			$tmp['send_member'] = $v['send_member'];
			$warns_data[] = $tmp;
		}

		$this->ajax_return('200','success',$warns_data);
	}

	/**
    *	添加事项提醒
	*/
	public function add_thing_warns(){
		$param = input("param.");
		if(empty($param['family_id'])){
			$this->ajax_return('10400','invalid family_id');
		}

		$family_member_model = model('family_member');
		$family_member_id = $family_member_model->where(['member_id'=>$this->member_info['member_id'],'family_id'=>$param['family_id']])->value('id');
		if(empty($family_member_id)){
			$this->ajax_return('10401','invalid family_member_id');
		}
		//提醒类别
		$warns_content = strip_tags($param['warns_content']);
		if(empty($param['warns_content'])){
			$this->ajax_return('10402','invalid warns_content');
		}
		//判断输入提醒字数不超过30
		$warns_content_len = strlen($warns_content);
		$i = $count = 0;
		while($i < $warns_content_len){
			$chr = ord($warns_content[$i]);
			$count++;$i++;
			if($i >= $warns_content_len){
				break;
			}
			if ($chr & 0x80){
				$chr <<= 1;
				while ($chr & 0x80){
					$i++;
               		$chr <<= 1;
				}
			}
		}
		if($count > 30){
			$this->ajax_return('10403','out of content length');
		}
		$thing_data['warns_content'] = $warns_content;
		
		//时间 默认为即刻推送
		if(!empty($param['send_time'])){
			$thing_data['send_time'] = strtotime($param['send_time']);
		}else{
			$thing_data['send_time'] = strtotime(date('H:i',time()));
		}

		//重复 默认为 永不
		if(!empty($param['repeat_time'])){
			$data = array();
			$sun_array = ['1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六','7'=>'周日'];
			$repeat_time = explode(',',$param['repeat_time']);
			foreach ($repeat_time as $k => $v) {
				foreach ($sun_array as $kk => $vv) {
					if(!in_array($v,$sun_array)){
						$this->ajax_return('10406','invalid repeat_time');
					}
					if($v == $vv){
						$data[$k] = $kk;
					}
				}
			}

			$thing_data['repeat_time'] = implode(',', $data);
		}

		

		//提醒对象  -2:机器人 -3:家庭
		if($param['warner'] < 0) {
			$warner_array = ['-2','-3'];
			if(!in_array($param['warner'], $warner_array)){
				$this->ajax_return('10404','invalid warner');
			}
		}
		
		if($param['warner'] > 0){
			$family_member = model('family_member')->where(['family_id'=>$param['family_id'],'id'=>$param['warner'],'is_member'=>1])->find();
			if(empty($family_member->toArray())){
				$this->ajax_return('10404','invalid warner');
			}
		}
		
		//数据同步至手表
		if($param['is_step'] == 1){
			$watch = new watch();
			$watch_imei = model('member_info')->where(['member_id'=>$this->member_info['member_id']])->value('watch_imei');
			if(empty($watch_imei)){
				$this->ajax_return('10407','member dose not bind');
			}
			$sun_array = ['1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六','7'=>'周日'];
			$array1 = ['1'=>'mon','2'=>'tues','3'=>'wed','4'=>'thur','5'=>'fri','6'=>'sat','7'=>'sun'];
			$array2 = ['mon'=>'0','tues'=>'0','wed'=>'0','thur'=>'0','fri'=>'0','sat'=>'0','sun'=>'0'];
			$repeat_time = explode(',',$param['repeat_time']);
			foreach ($repeat_time as $k => $v) {
				foreach ($sun_array as $kk => $vv) {
					if ($v == $vv) {
						$array2[$array1[$kk]] = '1';
						break;
					}
				}
			}
			$array2['remContext'] = $warns_content;
			$post_data = [
	        	'device_imei' => $watch_imei,
	        	'operType' => '1',
				'time' => $param['send_time'],
        	];
        	$param_data = array_merge($post_data,$array2);
        	$result = $watch->send_request($param_data,'10122');
        	
	        if(!$result){
				$this->ajax_return('10408',$watch->get_error());
			}
			$thing_data['return_id'] = $result['id'];
		}

		$thing_data['warner'] = $param['warner'] < 0 ? -$param['warner'] : 4;
		$thing_data['create_time'] = time();
		$thing_data['family_id'] = $param['family_id'];
		$thing_data['send_member'] = $family_member_id;
		$thing_data['accept_member'] = $param['warner'] > 0 ? $param['warner'] : '';
		$thing_result = $this->warns_model->save($thing_data);
		if($thing_result === false){
			$this->ajax_return('10405','failed to save data');
		}
		
		$this->ajax_return('200','success','');  
	}

	/**
    *	修改事项提醒
	*/
	public function edit_thing_warns(){
		$param = input("param.");
		
		//提醒id
		$warns_id = intval($param['warns_id']);
		if(empty($warns_id)){
			$this->ajax_return('10600','invalid warns_id');
		}
        
        if(empty($param['family_id'])){
			$this->ajax_return('10605','invalid family_id');
		}
		$family_member_id = model('family_member')->where(['member_id'=>$this->member_info['member_id'],'family_id'=>$param['family_id']])->value('id');
		if(empty($family_member_id)){
			$this->ajax_return('10606','invalid family_member_id');
		}

		//提醒内容
		$warns_content = strip_tags($param['warns_content']);
		if(empty($warns_content)){
			$this->ajax_return('10601','invalid warns_content');
		}
		
		//判断输入提醒字数不超过30
		$warns_content_len = strlen($warns_content);
		$i = $count = 0;
		while($i < $warns_content_len){
			$chr = ord($warns_content[$i]);
			$count++;$i++;
			if($i >= $warns_content_len){
				break;
			}
			if ($chr & 0x80){
				$chr <<= 1;
				while ($chr & 0x80){
					$i++;
               		$chr <<= 1;
				}
			}
		}
		if($count > 30){
			$this->ajax_return('10602','out of content length');
		}
		$thing_data['warns_content'] = $warns_content;

		//时间 默认为即刻推送
		if(!empty($param['send_time'])){
			$thing_data['send_time'] = strtotime($param['send_time']);
		}else{
			$thing_data['send_time'] = strtotime(date('H:i',time()));
		}

		//重复 默认为 永不
		if(!empty($param['repeat_time'])){
			$data = array();
			$sun_array = ['1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六','7'=>'周日'];
			$repeat_time = explode(',',$param['repeat_time']);
			foreach ($repeat_time as $k => $v) {
				foreach ($sun_array as $kk => $vv) {
					if($v == $vv){
						$data[$k] = $kk;
						break;
					}
				}
			}

			$thing_data['repeat_time'] = implode(',', $data);
		}

		//提醒对象  -2:机器人 -3:家庭
		if($param['warner'] < 0) {
			$warner_array = ['-2','-3'];
			if(!in_array($param['warner'], $warner_array)){
				$this->ajax_return('10607','invalid warner');
			}
		}

		if($param['warner'] > 0){
			$family_member = model('family_member')->where(['family_id'=>$param['family_id'],'id'=>$param['warner'],'is_member'=>1])->find();
			if(empty($family_member)){
				$this->ajax_return('10404','invalid warner');
			}
		}
		
		//数据同步至手表
		if($param['is_step'] == 1){
			$watch = new watch();
			$watch_imei = model('member_info')->where(['member_id'=>$this->member_info['member_id']])->value('watch_imei');
			if(empty($watch_imei)){
				$this->ajax_return('10406','invalid watch_imei');
			}

			$return_id = $this->warns_model->where(['warns_id'=>$warns_id])->value('return_id');
			if(empty($return_id)){
				$this->ajax_return('10407','invalid return_id');
			}
			$sun_array = ['1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六','7'=>'周日'];
			$array1 = ['1'=>'mon','2'=>'tues','3'=>'wed','4'=>'thur','5'=>'fri','6'=>'sat','7'=>'sun'];
			$array2 = ['mon'=>'0','tues'=>'0','wed'=>'0','thur'=>'0','fri'=>'0','sat'=>'0','sun'=>'0'];
			$repeat_time = explode(',',$param['repeat_time']);
			foreach ($repeat_time as $k => $v) {
				foreach ($sun_array as $kk => $vv) {
					if ($v == $vv) {
						$array2[$array1[$kk]] = '1';
					}
				}
			}
			$array2['remContext'] = $warns_content;
			$post_data = [
	        	'device_imei' => $watch_imei,
	        	'remind_id' => $return_id,
	        	'operType' => '2',
				'time' => $param['send_time'],
        	];
        	$param_data = array_merge($post_data,$array2);
        	$result = $watch->send_request($param_data,'10122');
	        if(!$result){
				$this->ajax_return('10407',$watch->get_error());
			}
		}

		$thing_data['warner'] = $param['warner'] < 0 ? -$param['warner'] : 4;
		$thing_data['accept_member'] = $param['warner'] > 0 ? $param['warner'] : '';

		$thing_result = $this->warns_model->save($thing_data,['warns_id' => $warns_id]);
		
		if($thing_result === false){
			$this->ajax_return('10604','failed to save data');
		}
		$this->ajax_return('200','success','');
	}

	/**
    *	开启/关闭提醒
	*/
	public function close_open_warns(){

		$warns_id = input('post.warns_id','0','intval');
		if(empty($warns_id)){
			$this->ajax_return('10700','invalid warns_id');
		}
        
        $family_id = input('post.family_id','0','intval');
        if(empty($family_id)){
			$this->ajax_return('10704','invalid family_id');
		}
		$family_member_id = model('family_member')->where(['member_id'=>$this->member_info['member_id'],'family_id'=>$family_id])->value('id');
		if(empty($family_member_id)){
			$this->ajax_return('10705','invalid family_member_id');
		}

		$warns_state = input('post.warns_state','0','intval');
		$state_array = ['0','1'];
		if(!in_array($warns_state,$state_array)){
			$this->ajax_return('10701','invalid warns_state');
		}

		$warns_info = $this->warns_model->where(['warns_id'=>$warns_id])->find();
		if(empty($warns_info)){
			$this->ajax_return('10706','dose not exist warns');
		}
		
		$result = $this->warns_model->save(['warns_state'=>$warns_state],['warns_id'=>$warns_id]);
		if($result === false){
			$this->ajax_return('10703','failed to save data');
		}

		$this->ajax_return('200','success','');
	}

	/**
    *	删除提醒
	*/
	public function delete_warns(){
		$warns_id = input('post.warns_id','0','intval');
		if(empty($warns_id)){
			$this->ajax_return('10800','invalid warns_id');
		}

		$family_id = input('post.family_id','0','intval');
        if(empty($family_id)){
			$this->ajax_return('10803','invalid family_id');
		}
		//接收消息家庭成员id
		$accept_member_id = input('post.accept_member','0','intval');
        if(empty($family_id)){
			$this->ajax_return('10807','invalid accept_member');
		}
		$family_member_id = model('family_member')->where(['member_id'=>$this->member_info['member_id'],'family_id'=>$family_id])->value('id');
		if(empty($family_member_id)){
			$this->ajax_return('10804','invalid family_member_id');
		}

		$warns_info = $this->warns_model->where(['warns_id'=>$warns_id])->find();
		if(empty($warns_info)){
			$this->ajax_return('10801','have been deleted');
		}

		//判断是否与提醒表中家庭会员ID一致
		if($family_member_id != $warns_info['send_member']){
			$this->ajax_return('10804','invalid family_member_id');
		}

		if($accept_member_id == $family_member_id){
			$watch = new Watch();
			$return_id = $this->warns_model->where(['warns_id'=>$warns_id])->value('return_id');
			if(empty($return_id)){
				$this->ajax_return('10805','invalid return_id');
			}

			$watch_imei = model('member_info')->where(['member_id'=>$this->member_info['member_id']])->value('watch_imei');
			$sun_array = ['1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六','7'=>'周日'];
			$array1 = ['1'=>'mon','2'=>'tues','3'=>'wed','4'=>'thur','5'=>'fri','6'=>'sat','7'=>'sun'];
			$array2 = ['mon'=>'0','tues'=>'0','wed'=>'0','thur'=>'0','fri'=>'0','sat'=>'0','sun'=>'0'];
			$array2['remContext'] = $warns_info['warns_content'];
			$post_data = [
	        	'device_imei' => $watch_imei,
	        	'remind_id' => $return_id,
	        	'operType' => '3',
				'time' => $warns_info['send_time'],
	    	];
	    	$param_data = array_merge($post_data,$array2);
	    	$result = $watch->send_request($param_data,'10122');
	        if(!$result){
				$this->ajax_return('10806',$watch->get_error());
			}
		}
		

		$result = $this->warns_model->where(['warns_id'=>$warns_id])->delete();


		if($result === false){
			$this->ajax_return('10802','failed to delete data');
		}

		$this->ajax_return('200','success','');
	}

	/**
    *	添加吃药提醒
	*/
	public function add_pill_warns(){
		$param = input("param.");

		//提醒类别
		$warns_array = ['1', '2'];
		if(empty($param['warns_type']) || !in_array($param['warns_type'], $warns_array)){
			$this->ajax_return('10200','invalid medical_type');
		}
		$medical_data = ['warns_type' => intval($param['warns_type'])];

		//药品名称
		$pill_name = strip_tags($param['pill_name']);
		
		if(empty($pill_name)){
			$this->ajax_return('10201','invalid pill_name');
		}else{
			$badwords = array("\\",'&',' ',"'",'"','/','*',',','<','>',"\r","\t","\n","#","@","!","$","%","^","+","、",".","，");
		    foreach($badwords as $value){
		        if(strpos($pill_name, $value) !== false) {
		            $this->ajax_return('10201','invalid pill_name');
		        }
		    }
		}
		$medical_data = ['pill_name' => $pill_name];

		//吃药时间
		if(empty($param['taking_time'])){
			$this->ajax_return('10202','invalid taking_time');
		}
		$taking_time = explode(',',$param['taking_time']);
		$count = count($taking_time);
		if($count > 4){
			$this->ajax_return('10202','invalid taking_time');
		} 
		$time_format = ['first_time','second_time','third_time','fourth_time'];
		$taking = array();
		for($i=0;$i<$count;$i++){
			$taking[$time_format[$i]] = $taking_time[$i];
		}
		$medical_data['taking_time'] = json_encode($taking);
		//重复时间
		if(!isset($param['repeat_time'])){
			$medical_data['repeat_time'] = NULL;
		}else{
			$data = array();
			$sun_array = ['1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六','7'=>'周日'];
			$repeat_time = explode(',',$param['repeat_time']);
			foreach ($repeat_time as $k => $v) {
				foreach ($sun_array as $kk => $vv) {
					if($v == $vv){
						$data[$k] = $kk;
					}
				}
			}

			$medical_data['repeat_time'] = implode(',', $data);
		}
		//剂量
		if(empty($param['pill_dose'])){
			$this->ajax_return('10203','invalid pill_dose');
		}
		$medical_data['pill_dose'] = $param['pill_dose'];
		//提醒对象
		$warner_array = ['1' , '2'];
		if(!in_array($param['warner'],$warner_array)){
			$this->ajax_return('10204','invalid warner');
		}
		$medical_data['warner'] = intval($param['warner']);
		$medical_data['create_time'] = time();
		$medical_result = $this->warns_model->save($medical_data);
		if($medical_result === false){
			$this->ajax_return('10205','failed to save data');
		}
		$this->ajax_return('200','success','');
	}

	/**
    *	修改吃药提醒
	*/
	public function edit_pill_warns(){
		$param = input("param.");
		//提醒类别
		// $warns_array = ['1', '2'];
		// if(empty($param['warns_type']) || !in_array($param['warns_type'], $warns_array)){
		// 	$this->ajax_return('10200','invalid medical_type');
		// }

		//提醒id
		$warns_id = intval($param['warns_id']);
		if(empty($warns_id)){
			$this->ajax_return('10300','invalid warns_id');
		}

		//药品名称
		$pill_name = strip_tags($param['pill_name']);
		if(empty($pill_name)){
			$this->ajax_return('10301','invalid pill_name');
		}else{
			$badwords = array("\\",'&',' ',"'",'"','/','*',',','<','>',"\r","\t","\n","#","@","!","$","%","^","+","、",".","，");
		    foreach($badwords as $value){
		        if(strpos($pill_name, $value) !== false) {
		            $this->ajax_return('10201','invalid pill_name');
		        }
		    }
		}
		$medical_data = ['pill_name' => $pill_name];

		//吃药时间
		if(empty($param['taking_time'])){
			$this->ajax_return('10302','invalid taking_time');
		}
		$taking_time = explode(',',$param['taking_time']);
		$count = count($taking_time);
		if($count > 4){
			$this->ajax_return('10302','invalid taking_time');
		} 
		$time_format = ['first_time','second_time','third_time','fourth_time'];
		$taking = array();
		for($i=0;$i<$count;$i++){
			$taking[$time_format[$i]] = $taking_time[$i];
		}
		$medical_data['taking_time'] = json_encode($taking);
	    
		//重复时间
		if(!isset($param['repeat_time'])){
			$medical_data['repeat_time'] = NULL;
		}else{
			$data = array();
			$sun_array = ['1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六','7'=>'周日'];
			$repeat_time = explode(',',$param['repeat_time']);
			foreach ($repeat_time as $k => $v) {
				foreach ($sun_array as $kk => $vv) {
					if($v == $vv){
						$data[$k] = $kk;
					}
				}
			}

			$medical_data['repeat_time'] = implode(',', $data);
		}
		
		//剂量
		if(empty($param['pill_dose'])){
			$this->ajax_return('10303','invalid pill_dose');
		}
		$medical_data['pill_dose'] = $param['pill_dose'];

		//提醒对象
		$warner_array = ['1' , '2'];
		if(!in_array($param['warner'],$warner_array)){
			$this->ajax_return('10304','invalid warner');
		}
		$medical_result = $this->warns_model->save($medical_data,['warns_id'=>$warns_id]);
		if($medical_result === false){
			$this->ajax_return('10305','failed to save data');
		}
		$this->ajax_return('200','success','');
	}

	

	

	

	/**
    *	获取提醒内容
	*/
	public function get_warns_content(){
		$warns_id = input('post.warns_id','0','intval');
		if(empty($warns_id)){
			$this->ajax_return('10600','invalid warns_id');
		}
		$warns_info = $this->warns_model->where(['article_id'=>$warns_id])->find();

		if(empty($warns_info)){
			$this->ajax_return('10601','empty data');
		}

		$warns_data = array();
		$warns_taking_time = json_decode($warns_info['taking_time'],true);
		foreach ($warns_taking_time as $key => $value) {
			$warns_info['taking_time'] = implode(',',$value);
		}
	}
}
