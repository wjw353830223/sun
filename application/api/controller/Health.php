<?php
namespace app\api\controller;
use Lib\Sms;
use think\Cache;
use Lib\Http;
class Health extends Apibase
{
	protected function _initialize(){
    	parent::_initialize();
    	$this->info_model = model('MemberInfo');
    }
   
	/**
    *	编辑健康档案基本资料
	*/
	public function basic_info(){
		$param = input("param.");
		//真实姓名
		if(empty($param['true_name']) || mb_strlen($param['true_name'],'utf8') > 4){
			$this->ajax_return('10080', 'invalid true_name');
		}
		//性别
		$sex_array = ['1', '2'];
		if (empty($param['member_sex']) || !in_array($param['member_sex'], $sex_array)) {
			$this->ajax_return('10081', 'invalid member_sex');
		}
		//年龄
		if (empty($param['member_age']) || intval($param['member_age']) > 200) {
			$this->ajax_return('10082', 'invalid member_age');
		}
		//身高
		if (empty($param['member_height']) || intval($param['member_height']) > 250) {
			$this->ajax_return('10083', 'invalid member_height');
		}
		//体重
		if (empty($param['member_weight']) || intval($param['member_weight']) > 250) {
			$this->ajax_return('10084', 'invalid member_weight');
		}
		
		//组合详情表数据
		$info_data = ['true_name' => $param['true_name'],'member_sex' => $param['member_sex'],'member_age' =>$param['member_age'],'member_height'=>$param['member_height'],'member_weight'=>$param['member_weight']];
		$info_data['profession'] = empty($param['profession']) ? 0 : intval($param['profession']);
		$info_data['medical_type'] = empty($param['medical_type']) ? 0 : intval($param['medical_type']);

		$has_info = $this->info_model->where(['member_id' => $this->member_info['member_id']])->count();
		if ($has_info > 0) {
			$info_result = $this->info_model->save($info_data, ['member_id' => $this->member_info['member_id']]);
		} else {
			$info_data['member_id'] = $this->member_info['member_id'];
			$info_result = $this->info_model->save($info_data);
		}
		if ($info_result === false) {
			$this->ajax_return('10085', 'failed to save data');
		}
		
		$info_data['profession'] = $param['profession'];
		$info_data['medical_type'] = $param['medical_type'];
		$this->ajax_return("200","success",$info_data);
	}

	/**
    *	获取健康档案基本资料
	*/
	public function get_basic_info(){
		$member_id = $this->member_info['member_id'];
		$member_info = $this->info_model->where(['member_id'=>$member_id])->field('true_name,member_sex,member_age,member_height,member_weight,profession,medical_type')->find();
		$member_info['member_sex'] = (string)$member_info['member_sex'];
		$member_info['member_age'] = (string)$member_info['member_age'];
		$member_info['member_height'] = (string)$member_info['member_height'];
		$member_info['member_weight'] = (string)$member_info['member_weight'];
		$member_info['profession'] = (string)$member_info['profession'];
		$member_info['medical_type'] = (string)$member_info['medical_type'];
		$this->ajax_return('200','success',$member_info);
	}
}

