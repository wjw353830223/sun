<?php
namespace app\api\controller;
use Lib\Sms;
use think\Cache;
use Lib\Http;
class Help extends Apibase
{
	protected function _initialize(){
    	parent::_initialize();
    	
    	$this->guestbook_model = model("guestbook");
    }
   

	/**
    *	反馈信息
	*/
	public function feedback(){
		$param = input("param.");
		$back_type = ['0','1','2','3'];
		if(!in_array($param['back_type'],$back_type)){
			$this->ajax_return('10070', 'invalid back_type');
		}

		$content = strip_tags($param['content']);
		if(empty($content) || words_length($content) > 100){
			$this->ajax_return('10071', 'invalid content');
		}

		$contact = trim($param['contact']);
		if(strlen($contact) > 0 && strlen($contact) <11 && !preg_match('/^[1-9][0-9]{4,9}$/',$contact)) {
			$this->ajax_return('10072', 'invalid contact');
		}
		if(strlen($contact) > 10 && !is_mobile($contact)){
			$this->ajax_return('10072', 'invalid contact');
		}

		$data = [
			'member_id'   => $this->member_info['member_id'],
			'back_type'       => intval($param['back_type']),
			'content' 	  => $content,
			'contact'     => $contact,
			'createtime' => time()
		];
		$feedback_data = model("guestbook")->save($data);
		if(!$feedback_data){
			$this->ajax_return('10073', 'failed to save data');
		}
	
		$this->ajax_return('200', 'success', '');
	}

}
