<?php
namespace app\api\controller;
class Store extends Apibase
{	
	protected $store_model,$gc_model,$advert_model,$ap_model,$mq_model;
	private $cate_list = [];
	protected function _initialize(){
    	parent::_initialize();
    	$this->advert_model = model('Advert');
        $this->ap_model = model('AdvertPosition');
        $this->gc_model = model('GoodsCommon');
        $this->mq_model = model('MemberQrcode');
    }
   
	/**
     *	获取商城首页信息
	 */
	public function get_info(){
        //会员token
        $param = input('param.');
        if(isset($param['token']) && !empty(trim($param['token']))){
            $token_info = model('MemberToken')->with('member')->where(['token' => trim($param['token'])])->find();
            $member_id = $token_info['member']['member_id'];
        }
        //获取会员邀请码
        $invite_code = '';
        if(isset($member_id)){
            $invite_code = model('MemberQrcode')->where(['member_id'=>$member_id])->value('invite_code');
        }
        $invite_code = !empty($invite_code) ? '?code='.$invite_code : '?code=';
        //广告轮播图
        $advert_info = $this->advert_model
            ->where(['position_id'=>3])
            ->order('slide_sort asc')
            ->select();
        $advert_data = [];
        if(empty($advert_info->toArray())){
            $def_img = $this->ap_model->where(['position_id'=>3,'is_use'=>1])->value('default_content');
            $advert_data[0]['title'] = '';
            $advert_data[0]['adv_pic'] = !empty($def_img) ? config('qiniu.buckets')['images']['domain'] . '/uploads/advert/'.$def_img : '';
            $advert_data[0]['adv_pic_url'] = '';
        }
        $now_time = time();
        foreach($advert_info as $ad_key => $ad_val){
            $tmp = [];
            if(!empty($ad_val['end_time'])){
                if($now_time >= $ad_val['start_time'] && $now_time <= $ad_val['end_time']){
                    $tmp['title'] = $ad_val['title'];
                    $tmp['type'] = $ad_val['type'];
                    $tmp['adv_pic'] = !empty($ad_val['content']['adv_pic'])
                        ? config('qiniu.buckets')['images']['domain'] .'/uploads/advert/'.$ad_val['content']['adv_pic'] : '';
                    if($ad_val['type'] == 'product'){
                        if(!empty($ad_val['content']['goods_commonid'])){
                            $tmp['goods_commonid'] = $ad_val['content']['goods_commonid'];
                            $tmp['goods_storage'] = model('GoodsCommon')
                                ->where(['goods_commonid'=>$ad_val['content']['goods_commonid']])
                                ->value('goods_storage');
                        }else{
                            $tmp['goods_commonid'] = '';
                            $tmp['goods_storage'] = 0;
                        }
                    }elseif($ad_val['type'] == 'url'){
                        $tmp['adv_pic_url'] = $ad_val['content']['adv_pic_url'];
                        $tmp['adv_share_url'] = !empty($ad_val['content']['adv_pic_url'])
                            ? $ad_val['content']['adv_share_url'].$invite_code : '';
                    }
                    $advert_data[] = $tmp;
                }
            }else{
                if($now_time >= $ad_val['start_time']){
                    $tmp['title'] = $ad_val['title'];
                    $tmp['type'] = $ad_val['type'];
                    $tmp['adv_pic'] = !empty($ad_val['content']['adv_pic'])
                        ? config('qiniu.buckets')['images']['domain'] .'/uploads/advert/'.$ad_val['content']['adv_pic'] : '';
                    if($ad_val['type'] == 'product'){
                        if(!empty($ad_val['content']['goods_commonid'])){
                            $tmp['goods_commonid'] = $ad_val['content']['goods_commonid'];
                            $tmp['goods_storage'] = model('GoodsCommon')
                                ->where(['goods_commonid'=>$ad_val['content']['goods_commonid']])
                                ->value('goods_storage');
                        }else{
                            $tmp['goods_commonid'] = '';
                            $tmp['goods_storage'] = 0;
                        }
                    }elseif($ad_val['type'] == 'url'){
                        $tmp['adv_pic_url'] = $ad_val['content']['adv_pic_url'];
                        $tmp['adv_share_url'] = !empty($ad_val['content']['adv_pic_url'])
                            ? $ad_val['content']['adv_share_url'].$invite_code : '';
                    }
                    $advert_data[] = $tmp;
                }
            }
        }
        //商城分区
        $module = model('GoodsModule')
            ->where(['module_state'=>1])
            ->field('module_id,module_name,module_desc,module_photo')
            ->select()->toArray();
        $domain = config('qiniu.buckets')['images']['domain'];
        foreach($module as $k=>$v){
            $module[$k]['module_photo'] = !empty($module[$k]['module_photo'])
                ? $domain . '/uploads/advert/' .$module[$k]['module_photo'] :'';
        }
        //新品首发
        $new_goods = model('GoodsCommon')
            ->where(['status'=>1,'is_new'=>1])
            ->field('goods_commonid,goods_name,goods_price,goods_image,goods_storage')
            ->order(['goods_addtime'=>'desc'])->limit(3)->select();
        foreach($new_goods as $k=>$v){
            $new_goods[$k]['goods_image'] = !empty($new_goods[$k]['goods_image'])
                ? $domain . '/uploads/product/'.$new_goods[$k]['goods_image'] : '';
        }
        //推荐商品
        $page = input('post.page',1,'intval');
        $page_num = 10;
        $count = model('GoodsCommon')->where(['status'=>1])->count();
        $page_count = ceil($count/$page_num);
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;
        }else{
            $limit = 0;
        }
        $recommend = model('GoodsCommon')
            ->where(['status'=>1])
            ->field('goods_commonid,goods_name,goods_storage,goods_present,goods_experience,goods_price,goods_image')
            ->order(['list_order'=>'asc','goods_commonid'=>'desc'])
            ->limit($limit,$page_num)->select();
        foreach($recommend as $k=>$v){
            $recommend[$k]['goods_image'] = !empty($recommend[$k]['goods_image'])
                ? $domain . '/uploads/product/'.$recommend[$k]['goods_image'] : '';
        }
        //专题
        $special = model('Special')
            ->where(['special_state'=>2])
            ->field('special_id,special_title,special_desc,special_field,special_link_url,special_image')
            ->order('special_modify_time desc')->select();
        foreach($special as $k=>$v){
            $special[$k]['special_image'] = !empty($special[$k]['special_image'])
                ? $domain . '/uploads/advert/' .$special[$k]['special_image'] :'';
            $special[$k]['special_link_url'] = !empty($special[$k]['special_link_url']) ?
                $special[$k]['special_link_url'].$invite_code.'&time='.time() : '';
        }
        $data = [
            'advert_info'  => $advert_data,
            'module_info'  => $module,
            'new_product'  => $new_goods,
            'special_info' => $special,
            'recommend'    => $recommend,
            'page_count'   => $page_count
        ];
        $this->ajax_return('200','success',$data);
    }
    public function get_infos(){
        $this->get_info();
    }

	/**
     *	获取产品详情
	 */
	public function goods_detail(){
		$goods_commonid = input('post.goods_commonid',0,'intval');
		if(empty($goods_commonid)){
			$this->ajax_return('11640','empty goods_commonid');
		}

		//会员token
		$token = input('post.token','','trim');
		if(!empty($token)){
			$token_info = model('MemberToken')->with('member')->where(['token' => $token])->find();
			$member_id = $token_info['member']['member_id'];
		}
		$goods_info = $this->gc_model->where(['goods_commonid'=>$goods_commonid,'status'=>1])->order(['goods_addtime'=>'desc'])->find();
		if(empty($goods_info)){
			$this->ajax_return('11641','empty goods_info');
		}

		$goods_data = array();
		$goods_service = explode(',',$goods_info['goods_server']);

		$service_info = ['全国包邮闪电发货','专业营养师在线指导','云端数据库监控','1比1积分升级赠送','3比1积分赠送'];

		$goods_data['goods_commonid'] = $goods_info['goods_commonid'];
		$goods_data['goods_name'] = $goods_info['goods_name'];
		$goods_data['goods_version'] = $goods_info['goods_version'];
		$goods_data['goods_description'] = $goods_info['goods_description'];
		$goods_data['goods_storage'] = $goods_info['goods_storage'];
		$goods_data['goods_salenum'] = (string)$goods_info['goods_salenum'];

		foreach($goods_service as $ser_key=>$ser_val){
			$goods_data['goods_service'][] = !empty($service_info[$ser_val]) ? $service_info[$ser_val] : '';
		}

		$pattern = '/<\s*img\s+[^>]*?src\s*=\s*(\'|\")(.*?)\\1[^>]*?\/?\s*>/i';
		if(preg_match_all($pattern,$goods_info['goods_body'],$match)){
			foreach ($match[2] as $key => $value) {
                $goods_data['goods_body'][] = config('qiniu.buckets')['images']['domain'] .$value;
            }
		}

		//获取产品图片
		$image = model('GoodsImages')->where(['goods_commonid'=>$goods_info['goods_commonid']])->select()->toArray();
		if(empty($image)){
			$goods_data['goods_image'] = [];
		}else{
			foreach ($image as $key => $value) {
				$goods_data['goods_image'][] = config('qiniu.buckets')['images']['domain'] . '/uploads/product/' . $value['goods_image'];
			}
		}

		$goods_data['gc_id'] = $goods_info['gc_id'];

		//获取会员邀请码
        $invite_code = '';
		if(isset($member_id)){
			$invite_code = $this->mq_model->where(['member_id'=>$member_id])->value('invite_code');
		}

		$invite_code = !empty($invite_code) ? '?code='.$invite_code : '?code=';

		$goods_data['goods_price'] = $goods_info['goods_price'];
		$goods_data['goods_mprice'] = $goods_info['market_price'];
		$goods_data['goods_spec'] = json_decode($goods_info['goods_spec'],true) ? json_decode($goods_info['goods_spec'],true) : [];
		$goods_data['goods_present'] = $goods_info['goods_present'];
		$goods_data['goods_experience'] = $goods_info['goods_experience'];
        $goods_data['quiet_url'] = !empty($goods_info['uniqid_code']) ? $this->base_url . '/product/'.$goods_info['uniqid_code'].'.html'.$invite_code : '';//config('qiniu.buckets')['images']['domain']
        list($attr,$tmp_data,$spec_name) = model('GoodsCommon')
            ->get_good_attributions($goods_commonid);
        $goods_data['spec_value'] = $tmp_data;
        $goods_data['spec_name'] = $spec_name;
        $goods_data['attr'] = $attr;
		$this->ajax_return('200','success',$goods_data);
	}
    public function goods_details(){
	    $this->goods_detail();
    }

	/**
     * 搜索页面获取标签
	 */
	public function get_product(){
		//保持缓存
        $result = $this->gc_model
            ->where(['status'=>1])->order(['goods_salenum'=>'desc'])
            ->field('goods_name,goods_commonid')
            ->limit(6)
            ->select()->toArray();
        $this->ajax_return('200','success',$result);
	}

	/**
     *	搜索产品
	 */
	public function search_product(){
	    //保存搜索关键词到历史数据表
        $pro_name = strip_tags(input('post.pro_name','','ltrim'));
        $pro_name = rtrim($pro_name);
        if(!empty($pro_name)){
            $pro_name = preg_replace('/\s{2,}/',' ',$pro_name);
            $preg = preg_match('/\s+/',$pro_name);
            if($preg > 0){
                $pro_name = explode(' ',$pro_name);
            }
        }
		if(empty($pro_name) || count($pro_name) == 0){
            $this->ajax_return('11650','empty pro_name');
        }
        $where = [];
        if(is_array($pro_name)){
            $where['status'] = 1;
            $where['goods_name'] = [['like','%'.$pro_name[0].'%']];
            $j = 1;
            for($i=1;$i<count($pro_name);$i++){
                $where['goods_name'][$i] = ['like','%'.$pro_name[$i].'%'];
                $j++;
            }
            $where['goods_name'][$j] = 'or';
        }else{
            $where = ['goods_name'=>['like','%'.$pro_name.'%'],'status'=>1];
        }

		$goods_info = model('GoodsCommon')->where($where)
            ->field('goods_name,goods_price,market_price goods_mprice,goods_description,goods_commonid,goods_storage')
            ->select()->toArray();
        if(empty($goods_info)){
			$this->ajax_return('11651','empty goods_info');
		}

		$goods_images = array();
        foreach($goods_info as $info_key => $info_val){
            $goods_images[] = $info_val['goods_commonid'];
        }
		$image_array = implode(',', $goods_images);

		$goods_image = model('GoodsImages')->where(['goods_commonid'=>['in',$image_array],'is_default'=>1])->select()->toArray();

		foreach($goods_info as $info_key => $info_val){
			foreach($goods_image as $img_key => $img_val){
				if($info_val['goods_commonid'] == $img_val['goods_commonid']){
                    $goods_info[$info_key]['goods_image'] = !empty($img_val['goods_image']) ? config('qiniu.buckets')['images']['domain'] .'/uploads/product/'.str_replace(strrchr($img_val['goods_image'],"."),"",$img_val['goods_image']).'_140x140.'.substr(strrchr($img_val['goods_image'], '.'), 1) : '';
                }
			}
		}

		$this->ajax_return('200','success',$goods_info);
	}

    /**
     * 产品分类
     */
	public function cate_list(){
        $where = [];
        //获取全部分区
	    $module = model('GoodsModule')
            ->where(['module_state'=>1])
            ->field('module_id,module_name')
            ->select()->toArray();
	    $all_module = [
	        'module_id' => 0,
            'module_name' => '全部分区'
        ];
	    array_unshift($module,$all_module);
        $module_id = input('post.module_id',0,'intval');
        if(!empty($module_id)){
            $where['module_id'] = $module_id;
        }
        $gc_id = input('post.gc_id','','trim');
        //判断分类是否是在分区下
        $res = [];
        if(!empty($module_id)){
            $cate_ids = model('GoodsModule')->where(['module_state'=>1,'module_id'=>$module_id])->value('cate_ids');
            $ids = explode(',',$cate_ids);
            foreach($ids as $v){
                $res[] = $v;
            }
            if(!empty($gc_id)){
                if(!in_array($gc_id,$res)){
                    $this->ajax_return('11940','invalid gc_id or module_id');
                }
            }
        }
        //获取分类
        $gc_where = ['gc_state'=>1,'parent_id'=>0];
        if(!empty($module_id) && count($res) > 0){
            $gc_where['gc_id'] = ['in',$res];
        }
        $goods_class = model('GoodsClass')
            ->where($gc_where)
            ->field('gc_id,gc_name')
            ->select()->toArray();
        $class = [
            'gc_id' => 0,
            'gc_name' => '全部'
        ];
        array_unshift($goods_class,$class);
        $list = [];
        if(empty($module_id) && empty($gc_id)){
            $list = $this->get_cate_list($gc_id);
        }elseif(empty($module_id) && !empty($gc_id)){
            $list = $this->get_cate_list($gc_id);
            $list[] = $gc_id;
        }elseif(!empty($module_id) && empty($gc_id)){
            foreach($res as $v){
                $list = array_merge($list,$this->get_cate_list($v));
            }
            $list = array_merge($list,$res);
        }else{
            $list[] = $gc_id;
        }
        $where['gc_id'] = ['in',$list];
        $where['status'] = 1;
        $page = input('post.page',1,'intval');
        $page_num = 10;
        $count = model('GoodsCommon')->where($where)->count();
        $page_count = ceil($count/$page_num);
        if ($page > 1) {
            $limit = ($page - 1) * $page_num;
        }else{
            $limit = 0;
        }
        $goods = model('GoodsCommon')
            ->where($where)
            ->field('goods_commonid,goods_name,goods_image,goods_price,goods_present,goods_experience,goods_salenum,goods_storage')
            ->order(['goods_salenum'=>'desc','goods_commonid'=>'desc'])
            ->limit($limit,$page_num)
            ->select()->toArray();
        if(!empty($goods)){
            foreach ($goods as $k=>$v){
                $goods[$k]['goods_image'] = !empty($goods[$k]['goods_image'])
                    ? config('qiniu.buckets')['images']['domain'] . '/uploads/product/'.$goods[$k]['goods_image'] : '';
            }
        }
	    $data = [
            'goods_class'  => $goods_class,
            'goods_module' => $module,
            'goods'        => $goods,
            'page_count'   => $page_count
        ];
        $this->ajax_return('200','success',$data);
    }

    /**
     * 获取当前分类下的所有子分类
     */
    private function get_cate_list($gc_id){
        $list = model('GoodsClass')->where(['parent_id'=>$gc_id])->select();
        foreach($list as $v){
            $this->cate_list[] = $v['gc_id'];
            $this->get_cate_list($v['gc_id']);
        }
        return $this->cate_list;
    }
}
