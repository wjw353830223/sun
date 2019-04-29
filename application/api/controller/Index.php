<?php
namespace app\api\controller;

class Index extends Apibase
{
	protected function _initialize(){
    	parent::_initialize();
    }
    
    public function index(){
    	$data = [];

    	//获取启动广告
    	$position_model = model('AdvertPosition');
    	$advert_list = $position_model->get_advert(2);
    	//图片添加URL
    	if (!empty($advert_list['advert'])) {
    		foreach ($advert_list['advert'] as $key => $value) {
    			$advert_list['advert'][$key]['adv_pic'] = config('qiniu.buckets')['images']['domain'] . '/uploads/advert/' . $value['content']['adv_pic'];
    			unset($advert_list['advert'][$key]['content']);
    		}
    	}
    	$data['start_advert'] = $advert_list;

    	//获取首页轮播图
    	$banner_list = $position_model->get_advert(1);
    	//图片添加URL
    	if (!empty($banner_list['advert'])) {
    		foreach ($banner_list['advert'] as $key => $value) {
    			$banner_list['advert'][$key]['adv_pic'] = config('qiniu.buckets')['images']['domain'] . '/uploads/advert/' . $value['content']['adv_pic'];
                $banner_list['advert'][$key]['adv_pic_url'] = !empty($value['content']['adv_pic_url']) ? config('qiniu.buckets')['images']['domain'] . '/uploads/advert/' . $value['content']['adv_pic_url'] : '';
    			unset($banner_list['advert'][$key]['content']);
    		}
    	}
    	$data['banner_advert'] = $banner_list;

    	//获取首页新闻
    	$article_list = array();
    	$article_list = model('article')->where(['cate_id'=>3,'status'=>1])->field('article_id,title,thumb,view_nums,uniqid_code')->order(['create_time'=>'DESC'])->limit(5,0)->select()->toArray();
    	if (!empty($article_list)) {
    		foreach($article_list as $key => $value){
				$article_list[$key]['thumb'] = !empty($value['thumb']) ? config('qiniu.buckets')['images']['domain'] .'/uploads/article/'.$value['thumb'] : '';
				$article_list[$key]['url'] = !empty($value['uniqid_code']) ? config('qiniu.buckets')['images']['domain'] . '/article/'.$value['uniqid_code'].'.html' : '';
				unset($article_list[$key]['uniqid_code']);
			}
    	}
       
        //获取最新产品
        $gc_id = ['1','2'];
        $goods_info = db('goods_common')->where(['gc_id'=>['in',$gc_id]])->field('goods_commonid,goods_name,goods_price,gc_id,goods_image,goods_storage,market_price goods_mprice')->order('list_order asc')->select();
        foreach ($goods_info as $info_key => $info_val) {
            $goods_image = model('goods_images')->where(['goods_commonid'=>$info_val['goods_commonid'],'is_default'=>1])->value('goods_image');
            $goods_info[$info_key]['goods_image'] = !empty($goods_image) ? config('qiniu.buckets')['images']['domain'] . '/uploads/product/'.str_replace(strrchr($goods_image,"."),"",$goods_image).'_140x140.'.substr(strrchr($goods_image, '.'), 1) : '';
        }
		$data['article_list'] = $article_list;
        $data['goods_info'] = $goods_info;
    	$this->ajax_return('200', 'success',$data);
    }
}
