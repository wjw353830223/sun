<?php
namespace app\admin\command;

use think\console\Command;
use think\console\Input;  
use think\console\Output;
use think\console\input\Argument;
class Parterner extends Command
{
	protected function configure(){  
		$this->setName('parterner:add_base')->setDescription('add a third parterner');
        $this->addArgument('name', Argument::REQUIRED, "The name of the merchant");
        $this->addArgument('phone', null, "The phone of the merchant");
        $this->addArgument('address', null, "The address of the mechant");
	}  
  
	protected function execute(Input $input, Output $output){
        $third_name = $input->getArgument('name');
        $third_phone = $input->getArgument('phone');
        $third_address = $input->getArgument('address');
		$this->_add_third_merchant($third_name,$third_phone,$third_address);
	}
    private function _add_third_merchant($name,$phone,$address=''){
        $third_auth_token_model = model('ThirdAuthToken','common\model');
        $appid=$third_auth_token_model->generate_app_id();
        $secret=$third_auth_token_model->generate_secret();
        $data = [
            'third_name'=>$name,
            'third_phone'=>$phone,
            'third_address'=>$address,
            'app_id'=>$appid,
            'app_secret'=>$secret,
        ];
        $parterner_id = $third_auth_token_model->insertGetId($data);
        if(!empty($parterner_id)){
            echo 'parterner_id:'.$parterner_id.PHP_EOL.'app_id:' . $appid . PHP_EOL . 'app_secret:' . $secret . PHP_EOL;
        }else{
            echo 'fail';
        }
    }

}  



