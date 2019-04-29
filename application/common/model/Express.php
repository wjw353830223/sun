<?php
namespace app\common\model;
use think\Model;
class Express extends Model
{
	protected static function init(){
        self::beforeWrite(function ($user) {
            cache('express', NULL);
        });
        self::afterDelete(function ($user) {
            cache('express', NULL);
        });

        //保持缓存
        $express = cache("express");
        if (empty($express)) {
            $express = model('express')->order(array("express_order" => "ASC"))->select()->toArray();
            cache('express',$express);
        }

    }

    /**
    * 获取快递信息
    * @param $express_id 快递ID
    * @param $field 需要字段，默认为全部
    * @return array 快递信息
    */
    public function get_express($express_id = 0){
        $express = cache("express");
    	if ($express_id < 1) {
    		return $express;
    	}
    	$data = array();
    	
    	foreach ($express as $key => $value) {
    		if ($value['express_id'] == $express_id) {
    			$data = $value;
    			break;
    		}
    	}
    	return $data;
    }
}