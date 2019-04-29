<?php
namespace app\common\model;
use think\exception\DbException;
use think\Model;

class GoodsCommon extends Model
{
	const GOODS_PUT_ON_SALE=1;//上架
	const GOODS_PUT_OFF_SALE=0;//下架
    protected static function init(){
        self::beforeWrite(function ($user) {
            cache('product', NULL);
        });

        self::beforeUpdate(function ($user) {
            cache('product', NULL);
        });

        self::afterDelete(function ($user) {
            cache('product', NULL);
        });

        //保持缓存
        $result = cache('product');
        if (empty($result)) {
            $result = model('GoodsCommon')->order(array("list_order" => "ASC"))->field('goods_name,goods_commonid')->select()->toArray();
            cache('product',$result);
        }
    }

    /**
     * 获取下单商品返还的积分与经验值以及返还上级用户的积分值
     * @param $goods_commonid
     * @return mixed
     */
    public function goodscommon_get_points_and_experience($goods_commonid){
        $gc_info = model('GoodsCommon')->where(['goods_commonid'=>$goods_commonid])
            ->field('goods_present,goods_experience,goods_parent_points,goods_name')->find();
        return $gc_info;
    }
    /**
     * 数据处理
     */
    public function _initCommonGoodsByParam($param,$type = 'add'){
        $goods_data = [];
        if(empty($param['gc_id'])){
            return [['error' => '商品分类不能为空'],[]];
        }
        $goods_name = trim($param['goods_name']);
        if(empty($goods_name)){
            return [['error' => '商品名称不能为空'],[]];
        }

        $goods_price = trim($param['goods_price']);
        $market_price = trim($param['market_price']);
        $cost_price = trim($param['cost_price']);

        if(ceil($market_price) > 0 && !empty($market_price)){
            if($market_price < $goods_price){
                return [['error' => '市场价格必须大于商品价格'],[]];
            }
        }
        //成本价<商品价格<市场价格
        if(empty($cost_price) || ceil($cost_price) == 0){
            return [['error' => '请填写成本价'],[]];
        }
        if(ceil($cost_price) > 0 && !empty($cost_price)){
            if($goods_price < $cost_price){
                return [['error' => '成本价格必须小于商品价格'],[]];
            }
        }
        $goods_present = trim($param['goods_present']);
        $goods_experience = trim($param['goods_experience']);
        $goods_parent_points = trim($param['goods_parent_points']);
        $goods_storage = intval($param['goods_storage']);
        $goods_salenum = intval($param['goods_salenum']);

        if(!isset($param['goods_server'])){
            return [['error' => '请选择商品服务'],[]];
        }
        $goods_server = $param['goods_server'];
        if(count($goods_server) > 4){
            return [['error' => '服务最多选四个'],[]];
        }
        $server = "";
        foreach($goods_server as $k=>$v){
            $server .= $v . ",";
        }
        $server = substr($server,0,-1);
        $goods_data['goods_server'] = $server;

        //商品简介
        $goods_description = strip_tags($param['goods_description']);
        if(empty($goods_description)){
            return [['error' => '商品简介不能为空'],[]];
        }
        //商品规格
        $spec_data = array();
        if(isset($param['goods_spec']) && !empty($param['goods_spec'])){
            foreach($param['goods_spec'] as $spec_key=>$spec_val){
                if(!empty($spec_val)){
                    $spec_data[$spec_key]['title'] = $spec_val;
                }
            }
            foreach($param['goods_desc'] as $desc_key=>$desc_val){
                if(!empty($desc_val)){
                    $spec_data[$desc_key]['content'] = $desc_val;
                }
            }
        }
        list($spec_name,$spec_val) = $this->createSpec($param);
        if(isset($spec_name['error']) && $spec_name['error']){
            return [['error' => '请填写默认规格'],[]];
        }
        $goods_data['spec_name'] = json_encode($spec_name);
        $goods_data['spec_value'] = json_encode($spec_val);
        $goods_data['goods_description'] = $goods_description;
        $goods_data['gc_id'] = intval($param['gc_id']);
        $goods_data['goods_name'] = $goods_name;
        $goods_data['goods_price'] = $goods_price;
        $goods_data['market_price'] = $market_price;
        $goods_data['cost_price'] = $cost_price;
        $goods_data['goods_experience'] = $goods_experience;
        $goods_data['goods_parent_points'] = $goods_parent_points;
        $goods_data['goods_present'] = $goods_present;
        $goods_data['goods_salenum'] = $goods_salenum;
        if($type == 'add'){
            $goods_data['goods_addtime'] = time();
        }
        $goods_data['goods_storage'] = $goods_storage;
        $goods_data['goods_image'] = !empty($param['thumb']) ? $param['thumb'] : '';
        $goods_data['goods_spec'] = !empty($spec_data) ? json_encode($spec_data) : '';
        $goods_data['uniqid_code'] = uniqid();
        $goods_data['module_id'] = $param['module_id'];
        $goods_data['is_scan_code'] = $param['is_scan_code'];

        $tmp = [];
        if(!empty($param['content'])){
            $goods_data['goods_body'] = $param['content'];
            $pattern = '/<\s*img\s+[^>]*?src\s*=\s*(\'|\")(.*?)\\1[^>]*?\/?\s*>/i';
            if(preg_match_all($pattern,$goods_data['goods_body'],$match)){
                foreach ($match[2] as $key => $value) {
                    $tmp[] = $value;
                }
            }
        }

        return [$goods_data,$tmp];
    }
    /**
     * 生成商品公共表规格名规格值
     */
    public function createSpec($param){
        if(!isset($param['specValue']) ||  !isset($param['specName'])){
            return [array(),array()];
        }
        $spec_name = $spec_val =[];
        foreach ($param['specValue'] as $key=>$v){//格式为 default|颜色|黑色|4 规格类型|规格名|规格值|color_id
            $specVal=explode('|',$v);
            $spec_val[$specVal[0]][] = $specVal[2].'|'.$specVal[3];
            $specName = explode('_',$key);
            if(!in_array($specName[0],$spec_name)){
                $spec_name[] = $specName[0];
            }
        }
        return array($spec_name,$spec_val);
    }

    /**
     * 获取某个商品的规格属性
     * @param $goods_commonid int 产品id
     * @param $spec_values string goods_common表spec_value值
     * @param $spec_name string goods_common表spec_name值
     * @param $attr_only_on_sale boolean true:只获取有效的属性
     * @return array
     */
    public function get_goods_attr($goods_commonid,$spec_values,$spec_name,$attr_only_on_sale=true){
        $spec_names = !empty($spec_name) ? json_decode($spec_name,true) : [];
        $spec_value = json_decode($spec_values,true);
        $values = [];
        if(!empty($spec_value)){
            foreach($spec_value as $key=>$value){
                $tmps = [];
                foreach($value as $v){
                    $tmp = explode('|',$v);
                    $tmps[] = $tmp[0];
                }
                $values[] = $tmps;
            }
        }
        $tmp_data = [];
        foreach($values as $key=>$val){
            $tmp_data[] = array_reverse($val);
        }
        $good_common = $this->field('spec_value')->getByGoodsCommonid($goods_commonid);
        if(!empty($spec_names) && !empty($tmp_data)){
            $attr = model('GoodsAttribute')
                ->where(['goods_commonid'=>$goods_commonid])
                ->field('goods_id,goods_price,goods_image,goods_commonid,spec_name,spec_value,goods_storage,goods_name')
                ->select()->toArray();
            foreach($attr as $k=>$v){
                //过滤无效的属性
                if($attr_only_on_sale && model('GoodsAttribute')->checkAttribution($v['spec_value'], $good_common['spec_value'])===false){
                   unset($attr[$k]);
                   continue;
                }
                $spec_value = json_decode($attr[$k]['spec_value'],true);
                $val = [];
                if(!empty($spec_value)){
                    foreach($spec_value as $spec_val){
                        $val[] = $spec_val;
                    }
                }
                $attr[$k]['spec_value'] = !empty($val) ? $val : [];
                $attr[$k]['compare_value'] = !empty($spec_value) ?implode('|',$spec_value) : '';
                if(!empty($attr[$k]['goods_image'])){
                    $attr[$k]['goods_image'] = config('qiniu.buckets')['images']['domain']
                        . '/uploads/product/' . $attr[$k]['goods_image'];
                }
            }
        }
        $attr = isset($attr) && !empty($attr) ? array_values($attr) : [];
        return [$attr,$tmp_data,$spec_names];
    }

    /**
     * 商品属性
     * @param $goods_commonid
     * @param bool $attr_only_on_sale
     * @return array|bool
     */
    public function get_good_attributions($goods_commonid,$attr_only_on_sale=true){
        $goods_common = $this->field('goods_commonid,spec_name,spec_value')->getByGoodsCommonid($goods_commonid);
        if(empty($goods_common)){
            return false;
        }
        $spec_name = !empty($goods_common['spec_name']) ? json_decode($goods_common['spec_name'],true) : [];
        $spec_values = json_decode($goods_common['spec_value'],true);
        if(!empty($spec_values)){
            $goods_attributions = model('GoodsAttribute')
                ->where(['goods_commonid'=>$goods_commonid])
                ->field('goods_id,goods_price,goods_image,goods_commonid,spec_name,spec_value,goods_storage,goods_name')
                ->select()->toArray();
            if(empty($goods_attributions)){
                return false;
            }
            foreach($goods_attributions as $key=>$attr){
                //过滤无效的属性
                if($attr_only_on_sale && model('GoodsAttribute')->checkAttribution($attr['spec_value'], $goods_common['spec_value'])===false){
                    unset($goods_attributions[$key]);
                    continue;
                }
                $spec_value = array_values(json_decode($attr['spec_value'],true));
                $goods_attributions[$key]['spec_value'] = !empty($spec_value) ? $spec_value : [];
                $goods_attributions[$key]['spec_name'] = !empty($attr['spec_name']) && $attr['spec_name'] !== '[]' ? explode('|',$attr['spec_name']) : [];
                $goods_attributions[$key]['compare_value'] = !empty($spec_value) ?implode('|',$spec_value) : '';
                $image = $attr['goods_image'];
                $goods_attributions[$key]['goods_image'] = !empty($image) ? config('qiniu.buckets')['images']['domain'] . '/uploads/product/'.str_replace(strrchr($image,"."),"",$image).'_140x140.'.substr(strrchr($image, '.'), 1) : '';
            }
            $goods_attributions = array_values($goods_attributions);
        }else{
            $goods_attributions = [];
        }
        $spec_value = array_values($spec_values);
        if(!empty($spec_value)){
            foreach($spec_value as &$value){
                foreach($value as &$val){
                    $arr=explode('|',$val);
                    $val=reset($arr);
                }
                unset($val);
                $value = array_reverse($value);
            }
            unset($value);
        }
        return [$goods_attributions,$spec_value,$spec_name];
    }
    /**
     * 获取商品属性参数 库存 图片
     * @param $goods_commonid
     * @param int $goods_id
     * @param bool $goods_only_on_sale true:只考虑上架商品
     * @param bool $attr_only_on_sale true:只考虑现有规格
     * @return array|bool
     */
    public function get_good_param($goods_commonid,$goods_id = 0,$goods_only_on_sale = true,$attr_only_on_sale = true){
        $good_common = $this->field('spec_name,spec_value,goods_storage,status,goods_name')
            ->getByGoodsCommonid($goods_commonid);
        if(is_null($good_common)){
            return false;
        }
        if($goods_only_on_sale && $good_common->status == GoodsCommon::GOODS_PUT_OFF_SALE){
            return false;
        }
        $goods_image = model('goodsImages')->where(['goods_commonid'=>$goods_commonid])->field('goods_image')->find();
        if(!empty($goods_image)){
            $image = $goods_image['goods_image'];
        }else{
            $image = $good_common['goods_image'];
        }
        $spec_name = '';
        $spec_value = '';
        $storage = $good_common['goods_storage'];
        if($goods_id > 0){
            $good_attribution = model('GoodsAttribute')
                ->field('goods_storage,spec_name,spec_value,goods_image')
                ->getByGoodsId($goods_id);
            if(is_null($good_attribution)){
                return false;
            }
            if($attr_only_on_sale && model('GoodsAttribute')->checkAttribution($good_attribution['spec_value'], $good_common['spec_value']) === false){
                return false;
            }
            if(!empty($good_attribution['goods_image'])){
                $image = $good_attribution['goods_image'];
            }
            $spec_name = $good_attribution['spec_name'];
            $spec_value = $good_attribution['spec_value'];
            $storage = $good_attribution['goods_storage'];
        }
        $spec_value = json_decode($spec_value,true);
        $data = [];
        $value = [];
        if(!empty($spec_value)){
            foreach($spec_value as $v){
                $value[] = $v;
            }
            $data['spec_value'] = count($value) > 0 ? $value : [];
        }else{
            $data['spec_value'] = [];
        }
        $data['goods_image'] = !empty($image) ? config('qiniu.buckets')['images']['domain'] . '/uploads/product/'.str_replace(strrchr($image,"."),"",$image).'_140x140.'.substr(strrchr($image, '.'), 1) : '';
        $data['spec_name'] = !empty($spec_name) && $spec_name !== '[]' ? explode('|',$spec_name) : [];
        $data['goods_storage'] = $storage;
        $data['goods_name'] = $good_common['goods_name'];
        return $data;
    }

    /**
     * 产品列表
     * @param int $page
     * @param bool $goods_only_on_sale true:只考虑上架商品
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function get_goods_list($page=0,$goods_only_on_sale=false){
        try{
            $query = $this->field('goods_name,goods_commonid,goods_price,market_price as goods_mprice,is_scan_code,goods_version,gc_id,goods_storage')
                ->order('goods_addtime DESC');
            $goods_only_on_sale && $query->where(['status'=>GoodsCommon::GOODS_PUT_ON_SALE]);
            $page && $query->page($page)->limit(10);
            $goods_commons = $query->select();
        }catch(DbException $e){
            return false;
        }
        if(empty($goods_commons)){
            return false;
        }
        foreach($goods_commons as &$good){
            $image = model('GoodsImages')->where(['goods_commonid'=>$good['goods_commonid'],'is_default'=>1])->value('goods_image');
            $good['goods_image'] = !empty($image) ? config('qiniu.buckets')['images']['domain'] . '/uploads/product/'. $image : '';
        }
        unset($goods);
        return $goods_commons;
    }
    /**
     * 商品属性 商品编辑初始化
     * @param $goods_commonid
     * @return array|bool
     */
    public function parse_good_attrs($goods_commonid){
        $goods_common = $this->field('goods_commonid,spec_name,spec_value')->getByGoodsCommonid($goods_commonid);
        if(empty($goods_common)){
            return false;
        }
        $spec_names = !empty($goods_common['spec_name']) ? json_decode($goods_common['spec_name'],true) : [];
        $spec_values = json_decode($goods_common['spec_value'],true);
        if(!empty($spec_values)){
            foreach($spec_values as $key=>$value){
                foreach($value as $kk=>$val){
                    $spec_values[$key][$kk]=explode('|',$val);
                }
            }
            $goods_attributions = model('GoodsAttribute')
                ->where(['goods_commonid'=>$goods_commonid])
                ->field('spec_name,spec_value')
                ->select()->toArray();
            if(empty($goods_attributions)){
                return false;
            }
            $data = [];
            foreach($goods_attributions as $key=>$attr){
                $spec_value = array_values(json_decode($attr['spec_value'],true));
                foreach($spec_values as $m=>$values){
                    foreach($values as $value){
                        foreach($spec_value as $kk=>$val){
                           if(in_array($val,$value)){
                               $data[$kk][] = $m;
                               $data[$kk][] = $spec_names[$kk];
                               $data[$kk] = array_merge($data[$kk],$value);
                           }
                        }
                    }
                }
            }
            return $data;
        }
    }
}