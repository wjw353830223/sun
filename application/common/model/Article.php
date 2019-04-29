<?php
namespace app\common\model;

use think\Model;

class Article extends Model
{
	// 关闭自动时间格式化
    protected $createTime = false;
	public function info(){
        return $this->hasOne('ArticleData','article_id','article_id');
    }
}