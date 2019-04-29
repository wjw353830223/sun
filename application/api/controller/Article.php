<?php
namespace app\api\controller;
use app\common\model\Wechat;
use Lib\Sms;
use think\Cache;
use Lib\Http;
class Article extends Apibase
{
	protected $catid,$help_catid,$article_model;
	protected function _initialize(){
    	parent::_initialize();
    	$this->article_model = model('article');
    	$this->catid = 3;      //资讯列表id
    	$this->help_catid = 5;  //帮助列表id
    }
   
	/**
    *	获取资讯列表
	*/
	public function get_list(){
		$page = input('param.page',1,'intval');

		//数据分页
		$count = $this->article_model ->where(['cate_id'=>$this->catid,'status'=>1])->count();
		$page_num = 5;
		$page_count = ceil($count / $page_num);
		$limit = 0;
		if ($page > 0) {
			$limit = ($page - 1) * $page_num;  
		}else{
			$limit = 0;
		}
		$article_info = $this->article_model->where(['cate_id'=>$this->catid,'status'=>1])->field('article_id,title,thumb,view_nums,uniqid_code')->order(['create_time'=>'DESC'])->limit($limit,$page_num)->select()->toArray();
		if (empty($article_info)) {
			$this->ajax_return('10150', 'empty data');
		}

		foreach($article_info as $key => $value){
			$article_info[$key]['thumb'] = !empty($value['thumb']) ? config('qiniu.buckets')['images']['domain'] . '/uploads/article/'.$value['thumb'] : '';
			$article_info[$key]['url'] = !empty($value['uniqid_code']) ? config('qiniu.buckets')['images']['domain'] . '/article/'.$value['uniqid_code'].'.html' : '';
			unset($article_info[$key]['uniqid_code']);
		}
		$this->ajax_return('200', 'success', $article_info);
	}

	/**
    *	获取帮助列表
	*/
	public function get_help_list(){
		$help_info = $this->article_model->where(['cate_id'=>$this->help_catid,'status'=>1])->select()->toArray();
		$data = array();
		foreach($help_info as $key => $value){
			$data[$key]['title'] = $value['title'];
			$data[$key]['url'] = !empty($value['uniqid_code']) ? config('qiniu.buckets')['images']['domain'] . '/article/'.$value['uniqid_code'].'.html' : '';
		}
		if (empty($data)) {
			$this->ajax_return('10060', 'empty data');
		} else {
			$this->ajax_return('200', 'success', $data);
		}
	}

	/**
    *	获取食材分类
	*/
	public function get_food_cate(){
		$food_cate = model('category')->where(['parent_id'=>6])->field('category_id,catname')->select();
		if(empty($food_cate->toArray())){
			$this->ajax_return('10190','empty food_cate');
		}
		
		$this->ajax_return('200','success',$food_cate);
	}

	/**
    *	获取食材分类列表
	*/
	public function get_food_list(){
		$category_id = input('post.category_id','','intval');
		if(empty($category_id)){
			$this->ajax_return('10170','empty category_id');
		}
		$foot_article = model('article')->where(['cate_id'=>$category_id,'status'=>1])->select();

		if(empty($foot_article->toArray())){
			$this->ajax_return('10171','empty foot_article');
		}

		$tmp = array();
		foreach($foot_article as $k=>$v){
			$data = array();
			$data['article_id'] = $v['article_id'];
			$data['title'] = $v['title'];
			$data['thumb'] = empty($v['thumb']) ? '' : config('qiniu.buckets')['images']['domain'] . '/uploads/article/'.$v['thumb'];
			$data['url'] = empty($v['uniqid_code']) ? '' : config('qiniu.buckets')['images']['domain'] . '/article/'.$v['uniqid_code'].'.html';
		}
		$tmp[] = $data;
		$this->ajax_return('200','success',$tmp);
	}
     

	/**
    *	改变页面访问量
	*/
	public function change_view_nums(){
		$param = input('post.');
		$this->article_model->where(['uniqid_code'=>$param['uniqid_code']])->setInc('view_nums');
		$view_nums = $this->article_model->where(['uniqid_code'=>$param['uniqid_code']])->field('view_nums')->find();
		$data = ['view_nums'=>$view_nums['view_nums']];
		echo json_encode($data);
	}

	/**
    *	获取产品列表
	*/
	public function goods_list(){
		$page = input('param.page',1,'intval');
		//产品分类
		$goods_cate = model('GoodsClass')->cate_list();
		if(empty($goods_cate)){
			$this->ajax_return('11270','empty catory data');
		}
		//产品列表
        $goods_data =  model('GoodsCommon')->get_goods_list($page);
		if(empty($goods_data)){
            $this->ajax_return('11270','empty data');
        }
		$goods_list = array();
		$goods_list['category'] = $goods_cate;
		$goods_list['goods_list'] = $goods_data;
		$this->ajax_return('200','success',$goods_list);
	}

	/**
    *	获取产品详情
	*/
	/*public function goods_detail(){
		$goods_commonid = input('post.goods_commonid',0,'intval');
		if(empty($goods_commonid)){
			$this->ajax_return('11290','empty data');
		}	

		$goods_info = db('goods_common')->where(['goods_commonid'=>$goods_commonid])->order(['goods_addtime'=>'desc'])->find();
		if(empty($goods_info)){
			$this->ajax_return('11290','empty data');
		}

		$goods_data = array();
		$goods_service = explode(',',$goods_info['goods_server']);
		
		$service_info = ['全国包邮闪电发货','专业营养师在线指导','云端数据库监控','1比1积分升级赠送','3比1积分赠送'];
		$tmp = array();
		$tmp['goods_commonid'] = $goods_info['goods_commonid'];
		$tmp['goods_name'] = $goods_info['goods_name'];
		$tmp['goods_version'] = $goods_info['goods_version'];
		$tmp['goods_description'] = $goods_info['goods_description'];
		
		foreach($goods_service as $ser_key=>$ser_val){
			$tmp['goods_service'][] = $service_info[$ser_val];
		}

		$pattern = '/<\s*img\s+[^>]*?src\s*=\s*(\'|\")(.*?)\\1[^>]*?\/?\s*>/i';
		if(preg_match_all($pattern,$goods_info['goods_body'],$match)){
			foreach ($match[2] as $key => $value) {
				$tmp['goods_body'][] =  config('qiniu.buckets')['images']['domain'] . $value;
			}
		}

		$goods_image = model('GoodsImages')->where(['goods_commonid'=>$goods_commonid,'is_default'=>1])->value('goods_image');
		$tmp['goods_image'] = !empty($goods_image) ? config('qiniu.buckets')['images']['domain'] . '/uploads/product/'.$goods_image : '';
		$tmp['gc_id'] = $goods_info['gc_id'];
		$tmp['goods_price'] = $goods_info['goods_price'];
		$tmp['goods_mprice'] = $goods_info['market_price'];
		$goods_spec = json_decode($goods_info['goods_spec'],true);
		$tmp['goods_spec'] = $goods_spec;
		$goods_data = $tmp;

		$this->ajax_return('200','success',$goods_data);
	}*/

}
