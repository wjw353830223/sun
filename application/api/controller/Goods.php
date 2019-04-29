<?php
namespace app\api\controller;
use think\Cache;
use think\Db;
class Goods extends Apibase
{
	protected function _initialize(){
    	parent::_initialize();
    }
   
	/**
    *	获取产品分类详情
	*/
	public function cate_list(){
		$cate_id = input('post.cate_id',0,'intval');
		$has_info = db('goods_class')->where(['gc_id'=>$cate_id])->find();
		if(empty($has_info)){
			$this->ajax_return('11280','category dose not exsit');
		}
		//当前分类产品
		$goods_list = db('goods_common')->where(['gc_id'=>$cate_id])->field('goods_commonid,goods_name,goods_image,gc_id,goods_price,goods_version,market_price,goods_storage')->order(['goods_addtime'=>'desc'])->select();
		if(empty($goods_list)){
			$this->error('11281','empty data');
		}

		$goods_data = array();
		foreach($goods_list as $goods_key=>$goods_val){
			$tmp = array();
			$tmp['goods_commonid'] = $goods_val['goods_commonid'];
			$tmp['goods_name'] = $goods_val['goods_name'];
			$tmp['goods_price'] = $goods_val['goods_price'];
			$tmp['goods_version'] = $goods_val['goods_version'];
			$tmp['goods_image'] = !empty($goods_val['goods_image']) ? config('qiniu.buckets')['images']['domain'] . '/uploads/article/'.$goods_val['goods_image'] : '';
			$tmp['gc_id'] = $goods_val['gc_id'];
			$tmp['goods_storage'] = $goods_val['goods_storage'];
			$tmp['goods_mprice'] = $goods_val['market_price'];
			$goods_data[] = $tmp;
		}

		$this->ajax_return('200','success',$goods_data);
	}

	
}
