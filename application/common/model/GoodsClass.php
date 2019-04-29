<?php

namespace app\common\model;


use think\exception\DbException;
use think\Model;

class GoodsClass extends Model
{
    const CATE_STATUS_ON_SALE = 1;
    const CATE_STATUS_OFF_SALE = 0;
    /**
     * 产品分类列表
     * @param int $parent_id
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function cate_list($parent_id = 0){
        try{
            $goods_cate = $this->where(['parent_id'=>$parent_id,'gc_state'=>GoodsClass::CATE_STATUS_ON_SALE])
                ->field('gc_id,gc_name')
                ->order(['gc_sort'=>'asc'])
                ->select();
        }catch (DbException $e) {
            return false;
        }
        if(empty($goods_cate)){
            return false;
        }
        return $goods_cate;
    }
}