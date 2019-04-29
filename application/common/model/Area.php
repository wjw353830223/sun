<?php
namespace app\common\model;
use think\Model;
class Area extends Model
{
	protected $area_name,$area_info;
	protected static function init(){
        self::beforeWrite(function ($user) {
            cache('area', NULL);
        });
        self::afterDelete(function ($user) {
            cache('area', NULL);
        });

        //保持缓存
        $area = cache("area");
        if (empty($area)) {
           	$area = db('area')->order(array("area_id" => "ASC"))->select();
            cache('area',$area);
        }
    }

    /**
    * 获取完整的地址名称
    */
    public function get_name($area_id = 0){
    	$area = cache("area");
    	if ($area_id < 1) {
            return '未知';
        }

    	foreach ($area as $key => $value) {
    		if ($value['area_id'] == $area_id) {
                $this->area_name[] = $value['area_name'];
                if ($value['area_parent_id'] > 0) {
                    $this->get_name($value['area_parent_id']);
                }
                break;
            }
    	}

    	$area_name = array_reverse($this->area_name);
    	return implode(' ',$area_name);
    }

     /**
    * 获取完整的地址详细
    */
    public function get_info($area_id = 0){
        $area = cache("area");
        if ($area_id < 1) {
            return '未知';
        }

        foreach ($area as $key => $value) {
            if ($value['area_id'] == $area_id) {
                $this->area_info[] = $value;
                if ($value['area_parent_id'] > 0) {
                    $this->get_info($value['area_parent_id']);
                }
                break;
            }
        }

        $area_info = array_reverse($this->area_info);
        return $area_info;
    }

    /**
     * 获取所有的地区
     * @return bool|mixed|string
     */
    public function get_all_area(){
        if(($areas = cache('all_areas')) !== false){
            return $areas;
        }
        $file_path = ROOT_PATH . 'public' . DS . 'json' . DS . 'area.json';
        if(!is_file($file_path)){
            return false;
        }
        if(($areas = file_get_contents($file_path))===false){
            return false;
        }
        cache('all_areas',$areas);
        return $areas;
    }
}