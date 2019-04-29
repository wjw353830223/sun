<?php
/**
 * 行为类：统一合作伙伴和常仁用户的积分
 * 在用户下单前获取用户总积分值时触发
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/25
 * Time: 11:44
 */
namespace app\common\behavior;
use think\Request;
class MergeParternerUserPoints
{
    /**
     * 获取用户信息时统一积分
     * @param $member_id
     * @return bool]
     */
    public function getUserInfoBefore(&$member_id){
        return $this->merge_parterner_user_points($member_id);
    }
    /**
     * 返回false则当前标签位的后续行为将不会执行，但应用将继续运行
     * @param $member_id
     * @return bool
     */
    public function merge_parterner_user_points($member_id){
        if(empty($member_id)){
            return false;
        }
        $request = Request::instance([]);
        $request->module("common");
        $model = model('PointsLog');
        $res = $model->merge_parterner_user_points($member_id);
        return $res;
    }
}