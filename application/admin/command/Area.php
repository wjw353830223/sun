<?php
namespace app\admin\command;

use think\console\Command;
use think\console\Input;  
use think\console\Output;
use think\Request;

class Area extends Command
{
    protected $limit = 100;//每次循环限制100条 防止内存溢出
	protected function configure(){  
		$this->setName('area')->setDescription('Generate area file');
	}  
  
	protected function execute(Input $input, Output $output){
        $request = Request::instance([]);
        $request->module("common");
		$this->_get_all_area();
	}

    /**
     * 获取所有的地区
     * 手动构造json 不能通过json_encode($arr)生成
     * 防止$arr太大 数组溢出
     */
	private function _get_all_area(){
	    $model= model('Area');
        $offset = 0;
        $save_path = ROOT_PATH . 'public' . DS . 'json' . DS;
        $data = '[';
        while(true) {
            $areas = $model->order('area_id','ASC')->limit($offset,$this->limit)->select();
            $offset += $this->limit;
            if($areas->isEmpty()){
                strpos($data,',') > 1 && $data = substr($data,0,-1);
                $data .= ']';
                cache('all_areas',$data);
                file_put_contents($save_path . 'area.json',$data,FILE_APPEND);
                break;
            }
            foreach($areas as $area){
                $data .='{"name":"'.$area['area_name'].'","value":"'.$area['area_id'].'","parent":"'.$area['area_parent_id'].'"},';
            }
        }
    }

}  



