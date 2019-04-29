<?php

namespace app\common\model;

use think\exception\PDOException;
use think\Model;

class ShopCar extends Model
{
    protected $autoWriteTimestamp = false;
    // 定义时间戳字段名
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $goods_common_model,$goods_attribute_model,$goods_images_model;
    protected function initialize(){
        parent::initialize();
        $this->goods_common_model = model('GoodsCommon');
        $this->goods_attribute_model = model('GoodsAttribute');
        $this->goods_images_model = model('GoodsImages');
    }

    /**
     * 购物车初始化
     * @param $buyer_id
     * @param array $car_goods
     * @return array|bool|false|\PDOStatement|string|\think\Collection
     */
    public function car_products_init($buyer_id, $car_goods=[],$page = 0){
        if(!empty($car_goods) && is_array($car_goods)){
            foreach($car_goods as &$good){
                $good['good_nums'] = isset($good['good_nums'])?$good['good_nums']:1;
                $good['good_attr_id'] = isset($good['good_attr_id'])?$good['good_attr_id']:0;
                $good['is_check'] = isset($good['is_check'])?$good['is_check']:0;
                $good['created_at'] = isset($good['created_at'])?$good['created_at']:time();
                $good['updated_at'] = isset($good['updated_at'])?$good['updated_at']:time();
                $car_good = $this->where(['buyer_id'=>$buyer_id,'good_common_id'=>$good['good_common_id'], 'good_attr_id'=>$good['good_attr_id']])->find();
                if($car_good){
                    if(!$this->edit_product($car_good->id, $buyer_id, $good['good_nums'], $good['good_attr_id'], $good['is_check'])){
                        return false;
                    }
                }else{
                    if($this->add_product($buyer_id, $good['good_common_id'], $good['good_nums'], $good['good_attr_id'], $good['is_check'], $good['created_at'], $good['updated_at']) === false){
                        return false;
                    }
                }
            }
            unset($good);
        }
        try{
            $query = $this->where(['buyer_id'=>$buyer_id])
                ->field('id as car_id,good_common_id,good_name,good_price,good_nums,good_attr_id,is_check')
                ->order(['updated_at'=>'desc']);
            $page && $query->page($page)->limit(10);
            $car_goods = $query->select()->toArray();
            if(!empty($car_goods)){
                foreach($car_goods as $key=>$car_good){
                    if(($good_param = $this->goods_common_model->get_good_param($car_good['good_common_id'],$car_good['good_attr_id'])) === false){
                        unset($car_goods[$key]);
                        continue;
                    }
                    $good_common = $this->goods_common_model
                        ->field('goods_present,goods_experience,status')
                        ->getByGoodsCommonid($car_good['good_common_id']);
                    $car_goods[$key]['goods_present'] = $good_common['goods_present'];
                    $car_goods[$key]['goods_experience'] = $good_common['goods_experience'];
                    $car_goods[$key] = array_merge($car_goods[$key],$good_param);
                    $car_goods[$key]['good_price'] /= 1000;
                }
                $car_goods = array_values($car_goods);
            }
        } catch(PDOException $e){
            return false;
        }
        return $car_goods;
    }

    /**
     * 添加商品到购物车
     * @param $buyer_id
     * @param $good_common_id
     * @param int $good_nums
     * @param int $good_attr_id
     * @param int $is_check
     * @param null $created_at
     * @param null $updated_at
     * @return $this|bool
     */
    public function add_product($buyer_id, $good_common_id, $good_nums=1, $good_attr_id=0, $is_check = 0, $created_at=null, $updated_at = null){
        if(empty($buyer_id)){
            return false;
        }
        //库存和价格修正
        if(!empty($good_attr_id)){
            $good = $this->goods_attribute_model->where(['goods_commonid'=>$good_common_id,'goods_id'=>$good_attr_id])->find();
        }else{
            $good = $this->goods_common_model->where(['goods_commonid'=>$good_common_id])->find();
        }
        if(is_null($good)){
            return false;
        }
        $allow_good_nums = $good_nums > $good['goods_storage'] ? $good['goods_storage'] : $good_nums;
        $created_at = !empty($created_at)?$created_at:time();
        $updated_at = !empty($updated_at)?$updated_at:time();
        $data = [
            'buyer_id' => $buyer_id,
            'good_name' => $good['goods_name'],
            'good_price' => $good['goods_price'] * 1000,
            'good_nums' => $allow_good_nums,
            'good_common_id' => $good_common_id,
            'good_attr_id' => $good_attr_id,
            'is_check'=>$is_check,
            'created_at' => $created_at,
            'updated_at' => $updated_at
        ];
        $car_good = $this->where(['buyer_id'=>$buyer_id,'good_common_id'=>$good_common_id,'good_attr_id'=>$good_attr_id])->find();
        if($car_good){
            $data['good_nums'] = ['exp','good_nums+'.$allow_good_nums];
            //多次调用可修改数量和选中状态
            return $car_good->where(['buyer_id'=>$buyer_id,'good_common_id'=>$good_common_id,'good_attr_id'=>$good_attr_id])->update($data);
        }
        return $this->create($data);
    }

    /**
     * 修改购物车商品参数
     * @param $car_id
     * @param $buyer_id
     * @param int $good_nums
     * @param int $good_attr_id
     * @param int $is_check
     * @param bool $good_nums_increasing true:数量增量修改
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit_product($car_id, $buyer_id, $good_nums=1, $good_attr_id=0, $is_check = 0, $good_nums_increasing = false){
        if(empty($buyer_id)){
            return false;
        }
        $car_good = $this->getById($car_id);
        if(empty($car_good)){
            return false;
        }
        //库存和价格修正
        if(!empty($car_good['good_attr_id'])){
            $good = $this->goods_attribute_model->where(['goods_commonid'=>$car_good['good_common_id'],'goods_id'=>$good_attr_id])->find();
        }else{
            $good = $this->goods_common_model->where(['goods_commonid'=>$car_good['good_common_id']])->find();
        }
        $allow_good_nums = $good_nums > $good['goods_storage'] ? $good['goods_storage'] : $good_nums;
        if(is_null($good)){
            return false;
        }
        $updated_at = time();
        $data = [
            'buyer_id' => $buyer_id,
            'good_name' => $good['goods_name'],
            'good_price' => $good['goods_price'] * 1000,
            'good_common_id' => $car_good['good_common_id'],
            'good_attr_id' => $good_attr_id,
            'is_check'=>$is_check,
            'updated_at' => $updated_at
        ];
        if($good_nums_increasing){
            $data['good_nums'] = ['exp','good_nums+'.$allow_good_nums];
        }else{
            $data['good_nums'] = $allow_good_nums;
        }
        $car_good_old = $this->where(['buyer_id'=>$buyer_id,'good_common_id'=>$car_good['good_common_id'],'good_attr_id'=>$good_attr_id])->find();
        if($car_good_old && $car_good_old['id'] != $car_id){
            //之前购物车中有相同规格的商品 则删除之前的 保留现在的
            $res = $this->where(['id'=>$car_good_old['id']])->delete();
            if(is_null($res)){
                return false;
            }
        }
        $res = $this->where(['id'=>$car_id])->update($data);
        if(is_null($res)){
            return false;
        }
        return true;
    }

    /**
     * 删除购物车商品
     * @param $buyer_id
     * @param array $car_ids
     * @return bool
     */
    public function delete_products($buyer_id, $car_ids = []){
        if(empty($buyer_id)){
            return false;
        }
        if(empty($car_ids) || !is_array($car_ids)){
            return false;
        }
        $rows = $this->destroy($car_ids);
        return $rows>0?true:false;
    }

    /**
     * 清空购物车
     * @param $buyer_id
     * @return bool|int
     */
    public function clear_products($buyer_id){
        if(empty($buyer_id)){
            return false;
        }
        $rows = $this->destroy(['buyer_id'=>$buyer_id]);
        return $rows>0?true:false;
    }

    /**
     * 购物车商品总金额
     * @param $car_ids
     * @return bool|float|int
     */
    public function car_products_amount($car_ids){
        if(empty($car_ids) || !is_array($car_ids)){
            return false;
        }
        $car_goods = $this->where('id','in', $car_ids)->select();
        if(empty($car_goods)){
            return false;
        }
        $amount = 0;
        foreach($car_goods as $good){
            $amount += $good['good_price'] * $good['good_nums'];
        }
        $amount = round($amount / 1000,2);
        return $amount;
    }

    /**
     * 获取购物车商品总的赠送消费积分 赠送经验值  赠送上级积分
     * @param $car_ids
     * @return array|bool
     * @throws \think\exception\DbException
     */
    public function car_products_points_and_experiences($car_ids){
        if(empty($car_ids) || !is_array($car_ids)){
            return false;
        }
        $car_goods = $this->where('id','in', $car_ids)->select();
        if(empty($car_goods)){
            return false;
        }
        $points = 0;
        $experiences = 0;
        $parent_points = 0;
        $goods = [];
        foreach($car_goods as $good){
            $good_common = $this->goods_common_model->getByGoodsCommonid($good['good_common_id']);
            $goods[$good['id']]['goods_present'] = $good_common['goods_present'] * $good['good_nums'];
            $goods[$good['id']]['goods_experience'] = $good_common['goods_experience'] * $good['good_nums'];
            $goods[$good['id']]['goods_parent_points'] = $good_common['goods_parent_points'] * $good['good_nums'];
            $points += $goods[$good['id']]['goods_present'];
            $experiences += $goods[$good['id']]['goods_experience'];
            $parent_points += $goods[$good['id']]['goods_parent_points'];
        }
        return [
            'goods_present' => $points,
            'goods_experience' => $experiences,
            'goods_parent_points' => $parent_points,
            'goods' => $goods
        ];
    }

    /**
     * 获取购物车总商品数量
     * @param $buyer_id
     * @return float|int
     */
    public function car_pruducts_nums($buyer_id){
        $car_goods = $this->where(['buyer_id'=>$buyer_id])->select();
        foreach($car_goods as $key=> $good){
            $good_param = model('GoodsCommon')->get_good_param($good['good_common_id'],$good['good_attr_id']);
            if($good_param === false){
                unset($car_goods[$key]);
            }
        }
        $total_nums = count($car_goods);
        return $total_nums;
    }
    /**
     *购物车商品推荐
     * 推荐规则：
     * 根据用户购物车中的商品，推荐同分区同分类的商品；
     * 如无同分区同分类的其他商品，则优先推荐同分类其他区的商品；
     * 如无同分类的其他商品，则将总商品销量由高至低推荐；
     * 不推荐已经在购物车中的商品；
     * 共推荐 12 件商品，每次加载 4 件
     * @param array $car_good_common_ids
     * @param int $page
     * @return array
     */
    private function _commend_products($car_good_common_ids=[],$page=1){
        if($page>=4){
            return [];
        }
        //同分区同分类的只取一个 排除重复
        $recommend_ids = [];
        if(!empty($car_good_common_ids)){
            foreach($car_good_common_ids as $good_common_id){
                $common_good = $this->goods_common_model->field('gc_id,module_id')->getByGoodsCommonid($good_common_id);
                if(!empty($recommend_ids)){
                    foreach($recommend_ids as $val){
                        if($common_good['module_id'] == $val['module_id'] && $common_good['gc_id'] == $val['gc_id']){
                            continue 2;
                        }
                    }
                }
                $recommend_ids[] = [
                    'module_id'=>$common_good['module_id'],
                    'gc_id'=>$common_good['gc_id']
                ];
            }
        }
        //根据规则得到商品id数组
        $car_recommend_products_ids = [];
        if(!empty($recommend_ids)){
            foreach($recommend_ids as $recommend_id){
                //同分区 同分类的商品
                $recommend_products_ids = $this->goods_common_model
                    ->where(['module_id'=>$recommend_id['module_id'], 'gc_id'=>$recommend_id['gc_id']])
                    ->where('goods_commonid','not in',$car_good_common_ids)
                    ->where(['status'=>GoodsCommon::GOODS_PUT_ON_SALE])
                    ->order('goods_salenum desc,goods_commonid')
                    ->column('goods_commonid');
                if(empty($recommend_products_ids)){
                    //同分类其它分区的商品
                    $recommend_products_ids = $this->goods_common_model
                        ->where(['gc_id'=>$recommend_id['gc_id']])
                        ->where('module_id','neq',$recommend_id['module_id'])
                        ->where('goods_commonid','not in',$car_good_common_ids)
                        ->where(['status'=>GoodsCommon::GOODS_PUT_ON_SALE])
                        ->order('goods_salenum desc,goods_commonid')
                        ->column('goods_commonid');
                }
                $car_recommend_products_ids = array_flip(array_flip(array_merge($car_recommend_products_ids,$recommend_products_ids)));
            }
        }
        if(empty($car_recommend_products_ids)){
            //将总商品销量由高至低推荐
            $car_recommend_products_ids = $this->goods_common_model
                ->where('goods_commonid','not in',$car_good_common_ids)
                ->where(['status'=>GoodsCommon::GOODS_PUT_ON_SALE])
                ->order('goods_salenum desc,goods_commonid')
                ->column('goods_commonid');
        }
        $car_recommend_products_ids_nums = 12 - count($car_recommend_products_ids);
        if($car_recommend_products_ids_nums > 0){
            //将总商品销量由高至低推荐
            $car_recommend_products_ids_new = $this->goods_common_model
                ->where('goods_commonid','not in',$car_good_common_ids)
                ->where('goods_commonid','not in',$car_recommend_products_ids)
                ->where(['status'=>GoodsCommon::GOODS_PUT_ON_SALE])
                ->order('goods_salenum desc,goods_commonid')
                ->limit($car_recommend_products_ids_nums)
                ->column('goods_commonid');
            $car_recommend_products_ids = array_merge($car_recommend_products_ids,$car_recommend_products_ids_new);
        }
        $car_recommend_products = [];
        $domain = config('qiniu.buckets')['images']['domain'];
        if(!empty($car_recommend_products_ids)){
            $query = $this->goods_common_model
                ->field('goods_commonid,goods_name,goods_storage,goods_present,goods_experience,goods_price,goods_image')
                ->where('goods_commonid','in',$car_recommend_products_ids)
                ->order('goods_salenum desc,goods_commonid');
            $page && $query->page($page)->limit(4);
            !$page && $query->limit(12);
            $car_recommend_products = $query->select();
            foreach($car_recommend_products as &$product){
                $product['goods_image'] = !empty($product['goods_image'])
                    ? $domain . '/uploads/product/'.$product['goods_image'] : '';
            }
            unset($product);
        }
        return $car_recommend_products;
    }
    /**
     * 在线购物车商品推荐
     * @param $buyer_id
     * @param $page
     * @param $buyer_id
     * @param $page
     * @return array
     */
    public function car_commend_products($buyer_id,$page){
        $car_good_common_ids = $this->distinct(true)->field('good_common_id')->where(['buyer_id'=>$buyer_id])->column('good_common_id');
        $car_recommend_products = $this->_commend_products($car_good_common_ids,$page);
        return $car_recommend_products;
    }
    /**
     * 离线购物车商品推荐
     * @param $page
     * @param $car_good_common_ids
     * @return array
     */
    public function car_off_line_commend_products($page,$car_good_common_ids){
        $car_good_common_ids = array_unique($car_good_common_ids);
        $car_recommend_products = $this->_commend_products($car_good_common_ids,$page);
        return $car_recommend_products;
    }

    /**
     * 购物车商品全选/反选
     * @param $buyer_id
     * @param $car_ids
     * @param int $is_check
     * @return bool
     */
    public function products_check_all($buyer_id, $car_ids, $is_check = 1){
        if(empty($buyer_id)){
            return false;
        }
        if(empty($car_ids) || !is_array($car_ids)){
            return false;
        }
        $rows = $this->where('id', 'in',$car_ids)->update(['is_check'=>$is_check]);
        if(is_null($rows)){
           return false;
        }
        return true;
    }
}
