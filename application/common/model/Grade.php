<?php
namespace app\common\model;

use think\Model;

class Grade extends Model
{
	// 关闭自动时间格式化
    protected $createTime = false;
    protected static function init(){
        self::beforeInsert(function ($grade) {
            cache('grade', NULL);
        });

        self::beforeUpdate(function ($grade) {
        	cache('grade', NULL);
        });
        
        self::afterDelete(function ($grade) {
            cache('grade', NULL);
        });

        //保持缓存
        $grade = cache("grade");
        if (empty($grade)) {
            $grade = model('grade')->order(array("grade_id" => "ASC"))->select()->toArray();
            cache('grade',$grade);
        }
    }

}