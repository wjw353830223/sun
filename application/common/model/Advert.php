<?php
namespace app\common\model;

use think\Model;

class Advert extends Model
{
	//删除广告后自动减少广告位广告数
	protected static function init(){
        Advert::event('after_delete', function ($advert) {
           model('AdvertPosition')->where(['position_id' => $advert->position_id])->setDec('advert_nums');
           return true;
        });
    }

    public function getContentAttr($value){
        return json_decode($value,true);
    }

	public function position(){
		return $this->hasOne('AdvertPosition','position_id','position_id')->field('position_id,name,type');
	}

}