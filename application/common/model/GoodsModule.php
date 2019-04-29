<?php

namespace app\common\model;

use think\Model;

class GoodsModule extends Model
{
    protected $createTime = false;
    protected $pk = 'module_id';
    protected $autoWriteTimestamp = 'datetime';
}