<?php

namespace app\common\model;
use think\Model;

class GoodsAttribute extends Model
{
    /**
     * 修改商品返回商品ID(SKU数组)
     */
    public function _editGoods($data, $commonid)
    {
        //查询goods表
        $Goods = $this->where('goods_commonid', $commonid)->select();
        // 商品规格
        if (@isset($data['specPrice']) && !@empty($data['specPrice'])) {
            $goodss = [];
            $i = 0;
            foreach ($data['specPrice'] as $key => $value) {
                $spec = explode('_', substr($key, 0, strlen($key) - 1));
                list($color, $specArray, $spec_name) = $this->createSpecArray($spec);
                if($value['cost_price'] > $value['price']){
                    return ['error' => '成本价必须低于商品价'];
                }
                if(ceil($value['market_price']) > 0 && !empty($value['market_price'])){
                    if($value['market_price'] < $value['price']){
                        return ['error' => '市场价格必须大于商品价格'];
                    }
                }
                $goods['goods_commonid']    = $commonid;
                $goods['goods_name'] = $data['goods_name'];// . ' ' . implode(' ', array_values($specArray))
                $goods['goods_price'] = $value['price'];
                $goods['goods_storage'] = $value['goods_storage'];
                $goods['market_price'] = $value['market_price'];
                $goods['cost_price'] = $value['cost_price'];
                $goods['goods_image'] = $value['goods_image'];
                $goods['spec_value'] = json_encode($specArray);
                $goods['spec_name'] = $spec_name;
                $goods['color_id'] = $color;
                $goodss[$i] = $goods;
                $i++;
            }
            //历史原因： count($Goods)>=count(goodss) $goodss[$goodkey] 会报错
            /*foreach ($Goods as $goodkey => $goodvalue) {
                $goods_id = $this->update_attr($goodvalue->goods_id,$goodss[$goodkey]);
                $goodsid_array[] = $goods_id;
            }*/
            $new_attrs_length = count($goodss);
            foreach ($Goods as $goodkey => $goodvalue) {
                if($goodkey >= $new_attrs_length){
                    $this->where(['goods_id'=>$goodvalue->goods_id])->delete();
                }else{
                    if(($res = $this->update_attr($goodvalue->goods_id,$goodss[$goodkey]))===false){
                        return false;
                    }
                }
            }
        } else {
            $goods['goods_commonid']    = $commonid;
            $goods['goods_name'] = $data['goods_name'];
            $goods['goods_price'] = $data['goods_price'];
            $goods['goods_storage'] = $data['goods_storage'];
            //历史原因： 属性表中可能没有商品属性记录 需要补充
            if($Goods->isEmpty()){
                $goods_attr = $goods;
                $goods_attr['color_id']          = 0;
                if(($res = $this->insertGetId($goods_attr))==false){
                    return false;
                }
            }else{
                $Goods = $Goods->toArray();
                $good_prev = array_shift($Goods);
                if(($res = $this->update_attr($good_prev['goods_id'],$goods))===false){
                    return false;
                }
                //删除多余的属性记录
                if(!empty($Goods)){
                    foreach ($Goods as $goodkey => $goodvalue) {
                        $this->where(['goods_id'=>$goodvalue['goods_id']])->delete();
                    }
                }
            }
        }
        return true;
    }

    private function update_attr($goods_id,$data){
        $res = $this->where(['goods_id'=>$goods_id])->update($data);
        if($res === false){
            return false;
        }
        return true;
    }

    /**
     * 生成商品返回商品ID(SKU)数组
     */
    public function _addGoods($data, $common_id){
        // 商品规格
        if (@isset($data['specPrice']) && !@empty($data['specPrice'])) {
            $product = [];
            foreach ($data['specPrice'] as $key=>$value) {
                $spec = explode('_',substr($key,0,strlen($key)-1));
                list($color,$specArray,$spec_name)=$this->createSpecArray($spec);
                if($value['cost_price'] > $value['price']){
                    return ['error' => '成本价必须低于商品价'];
                }
                if($value['market_price'] < $value['price']){
                    return ['error' => '市场价必须高于商品价'];
                }
                $goods['goods_commonid']    = $common_id;
                $goods['goods_name']        = $data['goods_name'];
                $goods['goods_price']       = $value['price'];
                $goods['market_price']      = $value['market_price'];
                $goods['cost_price']        = $value['cost_price'];
                $goods['goods_image']       = $value['goods_image'];
                $goods['goods_storage']     = $value['goods_storage'];
                $goods['spec_value']        = json_encode($specArray);
                $goods['spec_name']         = $spec_name;
                $goods['color_id']          = $color;
                $product[] = $goods;
            }
            $goods_id = model('GoodsAttribute')->insertAll($product);
            if(!$goods_id){
                return false;
            }
        } else {
            $goods['goods_commonid']    = $common_id;
            $goods['goods_name']        = $data['goods_name'];
            $goods['goods_price']       = $data['goods_price'];
            $goods['goods_storage']     = $data['goods_storage'];
            $goods['color_id']          = 0;
            $goods_id = model('GoodsAttribute')->insertGetId($goods);
            if(!$goods_id){
                return false;
            }
        }
        return true;
    }

    /**
     * 根据表单提交商品规格价格生成规格值
     */
    public function createSpecArray($data){
        if(!$data){
            return [0,array(),''];
        }
        $result=array();
        $color = 0;
        $spec_name='';
        for ($i=0;$i<count($data);$i++){
            $array=explode('|',$data[$i]);
            if(in_array('default1',$array)){
                $color=$array[3];
                $spec_name.=$array[1];
            }else{
                $spec_name.='|'.$array[1];
            }
            $result[$array[0]]=$array[2];
        }
        return [$color,$result,$spec_name];
    }

    /**
     * 检查商品属性是否有效
     * @param $attribution_spec_value
     * @param $goods_common_spec_value
     * @return bool
     */
    public function checkAttribution($attribution_spec_value, $goods_common_spec_value){
        $goods_common_spec_value = json_decode($goods_common_spec_value,true);
        $attribution_spec_value = json_decode($attribution_spec_value,true);
        foreach($goods_common_spec_value as $key=>$value){
            foreach($value as $kk=>$val){
                $arr = explode('|',$val);
                $goods_common_spec_value[$key][$kk] = $arr[0];
            }
        }
        foreach($goods_common_spec_value as $key=>$value){
            foreach($attribution_spec_value as $kk=>$val){
                if(!isset($goods_common_spec_value[$kk])){
                    return false;
                }
                if(!in_array($val,$goods_common_spec_value[$kk])){
                    return false;
                }
            }
        }
        return true;
    }
}