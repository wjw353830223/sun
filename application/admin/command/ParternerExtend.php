<?php
namespace app\admin\command;

use think\console\Command;
use think\console\Input;  
use think\console\Output;
use think\console\input\Argument;
class ParternerExtend extends Command
{
	protected function configure(){  
		$this->setName('parterner:add_extend_data')->setDescription('add extend information for the third parterner');
        $this->addArgument('parterner_id', Argument::REQUIRED, "The idetification of the third parterner");
        $this->addArgument('field', Argument::REQUIRED, "The name of the add_extend_data");
        $this->addArgument('value', Argument::REQUIRED, "The value of the field");
	}  
  
	protected function execute(Input $input, Output $output){
        $parterner_id = $input->getArgument('parterner_id');
        $field = $input->getArgument('field');
        $value = $input->getArgument('value');
		$this->_update_third_merchant($parterner_id,$field,$value);
	}
    private function _update_third_merchant($parterner_id,$field,$value){
        $third_auth_token_model = model('ThirdAuthToken','common\model')->getById($parterner_id);
        if(!$third_auth_token_model){
            echo 'the parterner_id is not exists';
        }
        $third_auth_token_model[$field] =$value;
        if($field == 'parterner_public_key'){
            $third_auth_token_model[$field] = base64_decode($value);
        }
        if($third_auth_token_model->save()){
            echo 'add success'. PHP_EOL;
        }else{
            echo 'fail';
        }
    }

}  



