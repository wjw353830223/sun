<?php
namespace app\common\model;

use think\Model;

class Goods extends Model
{
	// 关闭自动时间格式化
    protected $createTime = false;
    protected $goods_log_model,$goods_common_model,$goods_attribute_model;
    protected function initialize(){
        parent::initialize();
        $this->goods_log_model = model('GoodsLog');
        $this->goods_common_model = model('GoodsCommon');
        $this->goods_attribute_model = model('GoodsAttribute');
    }

    /**
     * 商品扫码入库
     * @param $member_id 入库用户id
     * @param $good_sn
     * @param $goods_commonid
     * @param int $goods_attr_id
     * @return bool
     * @throws \think\exception\PDOException
     */
    public function inbound_by_scan($member_id,$good_sn,$goods_commonid,$goods_attr_id=0){
        $this->startTrans();
        $this->goods_log_model->startTrans();
        $this->goods_common_model->startTrans();
        if($goods_attr_id > 0){
            $this->goods_attribute_model->startTrans();
        }
        $seller_id = model('StoreSeller')->where(['member_id'=>$member_id])->value('seller_id');
        if(is_null($seller_id)){
            return false;
        }
        $goods_data = [
            'goods_commonid' => $goods_commonid,
            'goods_attr_id' => $goods_attr_id,
            'goods_sn' => $good_sn,
            'store_id' => 1,
            'goods_time'=>time(),
            'seller_id'=>$seller_id,
            'upload_num'=> 1
        ];
        $result =  $this->insertGetId($goods_data);
        if($result === false){
            return false;
        }
        //增加商品数量
        $result = $this->goods_common_model->where(['goods_commonid'=>$goods_commonid])->setInc('goods_storage');
        if($result === false){
            $this->rollback();
            return false;
        }
        if($goods_attr_id > 0){
            $result = $this->goods_attribute_model->where(['goods_commonid'=>$goods_commonid,'goods_id'=>$goods_attr_id])->setInc('goods_storage');
            if($result === false){
                $this->rollback();
                $this->goods_common_model->rollback();
                return false;
            }
        }
        $member_name = model('member')->where(['member_id'=>$member_id])->value('member_name');
        $goods_log = [
            'goods_id' => $goods_commonid,
            'log_msg' => '库管员'.$member_name.'于'.date('Y-m-d H:i',time()).'扫码入库机器人 '.$good_sn,
            'log_time' => time(),
            'log_role' => '库管员',
            'log_user'=>$member_name,
            'log_goodsstate' => 1
        ];
        $result = $this->goods_log_model->save($goods_log);
        if($result === false){
            $this->rollback();
            $this->goods_common_model->rollback();
            if($goods_attr_id > 0){
                $this->goods_attribute_model->rollback();
            }
            return false;
        }
        $this->commit();
        $this->goods_common_model->commit();
        $this->goods_log_model->commit();
        if($goods_attr_id > 0){
            $this->goods_attribute_model->commit();
        }
        return true;
    }

    /**
     * 商品入库 不扫码直接加库存
     * @param $member_id
     * @param $good_num
     * @param $goods_commonid
     * @param int $goods_attr_id
     * @return bool
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function inbound_unscan($member_id,$good_num,$goods_commonid,$goods_attr_id=0){
        $this->startTrans();
        $this->goods_log_model->startTrans();
        $this->goods_common_model->startTrans();
        if($goods_attr_id > 0){
            $this->goods_attribute_model->startTrans();
        }
        $seller_id = model('StoreSeller')->where(['member_id'=>$member_id])->value('seller_id');
        if(is_null($seller_id)){
            return false;
        }
        $goods_data = [
            'goods_commonid' => $goods_commonid,
            'goods_attr_id' => $goods_attr_id,
            'store_id' => 1,
            'goods_time'=>time(),
            'seller_id'=>$seller_id,
            'upload_num'=> $good_num
        ];
        $good_info = $this->where(['goods_commonid'=>$goods_commonid,'seller_id'=>$seller_id,'goods_attr_id'=>$goods_attr_id])->find();
        if(is_null($good_info)){
            $result =  $this->insertGetId($goods_data);
        }else{
            $result = $this->where(['goods_commonid'=>$goods_commonid,'seller_id'=>$seller_id,'goods_attr_id'=>$goods_attr_id])
                ->setInc('upload_num',$good_num);
        }
        if($result === false){
            return false;
        }
        //增加商品数量
        $result = $this->goods_common_model->where(['goods_commonid'=>$goods_commonid])->setInc('goods_storage',$good_num);
        if($result === false){
            $this->rollback();
            return false;
        }
        if($goods_attr_id > 0){
            $result = $this->goods_attribute_model->where(['goods_commonid'=>$goods_commonid,'goods_id'=>$goods_attr_id])
                ->setInc('goods_storage',$good_num);
            if($result === false){
                $this->rollback();
                $this->goods_common_model->rollback();
                return false;
            }
        }
        $member_name = model('member')->where(['member_id'=>$member_id])->value('member_name');
        $goods_log = [
            'goods_id' => $goods_commonid,
            'log_msg' => '库管员'.$member_name.'于'.date('Y-m-d H:i',time()).'添加衍生品 '.$good_num,
            'log_time' => time(),
            'log_role' => '库管员',
            'log_user'=>$member_name,
            'log_goodsstate' => 1
        ];
        $result = $this->goods_log_model->save($goods_log);
        if($result === false){
            $this->rollback();
            $this->goods_common_model->rollback();
            if($goods_attr_id > 0){
                $this->goods_attribute_model->rollback();
            }
            return false;
        }
        $this->commit();
        $this->goods_common_model->commit();
        $this->goods_log_model->commit();
        if($goods_attr_id > 0){
            $this->goods_attribute_model->commit();
        }
        return true;
    }

    /**
     * 已入库的商品
     * @param $seller_id
     * @param $begin_time
     * @param $end_time
     * @param int $page
     * @return array|bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function inbounded_goods($seller_id,$begin_time=0,$end_time=0,$page = 0){
        $query = $this->where(['seller_id'=>$seller_id])
            ->field('goods_commonid,goods_sn,goods_state,store_id,goods_time,seller_id,upload_num,goods_attr_id');
        if($begin_time){
            $query->whereTime('goods_time','>=',$begin_time);
        }
        if($end_time){
            $query->whereTime('goods_time','<=',$end_time);
        }
        $query->order('goods_time','DESC');
        $page && $query->page($page)->limit(20);
        $goods = $query->select()->toArray();
        if(is_null($goods) || empty($goods)){
            return false;
        }
        foreach($goods as &$good){
            $compony = model('Store')->where(['store_id'=>$good['store_id']])->value('store_name');
            $good['company'] = !empty($compony)?$compony:'';
            $good_param = $this->goods_common_model->get_good_param($good['goods_commonid'],$good['goods_attr_id'],false,false);
            if($good_param === false){
                $goods_mame = model('GoodsCommon')->where(['goods_commonid'=>$good['goods_commonid']])->value('goods_name');
                if(!is_null($goods_mame)){
                    $good['spec_name'] = [];
                    $good['spec_value'] = [];
                    $good['goods_storage'] = 0;
                    $good['goods_name'] = $goods_mame;
                }
                continue;
            }
            $good = array_merge($good,$good_param);
        }
        unset($good);
        return $goods;
    }
}
