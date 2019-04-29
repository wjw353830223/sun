<?php
namespace app\common\model;

use think\Model;

class JobFail extends Model
{
    const Task_TYPE_POINTS = 1;
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected function initialize(){
        parent::initialize();
    }

    /**
     * @param $data
     * @param $error
     * @return $this
     */
    public function add_record($data,$error){
        $record = [
            'type'=>self::Task_TYPE_POINTS,
            'task_data'=>json_encode($data),
            'task_error'=>$error,
        ];
        return $this->create($record);
    }
}