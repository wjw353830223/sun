<?php
namespace app\common\model;

use think\Model;

class Page extends Model
{
	protected static function init(){
        self::beforeUpdate(function ($page) {
        	if(is_array($page['page_id'])){
        		$page_id = explode(',', $page['page_id'][1]);
        		foreach ($page_id as $key => $value) {
        			cache('page_'.$value, NULL);
        		}
        		return true;
        	}
            cache('page_'.$page['page_id'], NULL);
        });

    }

}