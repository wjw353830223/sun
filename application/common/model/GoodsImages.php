<?php
namespace app\common\model;
use think\Model;

class GoodsImages extends Model
{
    protected static function init(){
       
    }

    public function add_image($images,$img_ids,$goods_id){
        $image_data = [];
        foreach($images as $k=>$v){
            $tmp = [];
            $tmp['goods_image'] = $v;
            $tmp['goods_commonid'] = $goods_id;
            $tmp['img_id'] = $img_ids[$k];
            $tmp['is_default'] = $k == 0 ? 1 : 0;
            $image_data[] = $tmp;
        }
        return $image_data;
    }
}