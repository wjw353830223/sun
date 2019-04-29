<?php
namespace app\common\model;

use think\Model;

class AdvertPosition extends Model
{
	public function get_advert($position_id){
		if (empty($position_id)) {
			return NULL;
		}

		$list  = $this->get($position_id)->toArray();
		if (empty($list) || $list['is_use'] < 1) {
			return NULL;
		}

		$now_time = time();
		$condition = array();
		$condition['start_time'] = ['ELT',$now_time];
		$condition['end_time'] = ['EGT',$now_time];
		$condition['position_id'] = ['EQ',$position_id];
		$data_list = [];

		//区分多广告或单广告展示
		if ($list['display'] == 1) {
			$data_list = model('Advert')->where($condition)->field('title,content')->order('slide_sort ASC')->select();
		}else{
			$data_list = model('Advert')->where($condition)->field('title,content')->order('slide_sort ASC')->limit(1)->select();
		}
		$data = [];
		$data['position_name'] = $list['name'];
		$data['position_intro'] = $list['intro'];
		if (!empty($data_list)) {
			$data['advert'] = $data_list->toArray();
		}
		return $data;
	}
}