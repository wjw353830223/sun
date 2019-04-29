<?php
/**
 * 春雨医生异步通知处理
 */
namespace app\open\controller;
use JPush\Client;
use JPush\Exceptions\APIRequestException;
use Lib\Http;
use think\Controller;
class SpringNotify extends Controller {

	//春雨合作方key
	protected $partner_key,$appKey,$masterSecret,$partner;
	protected $msg_model,$member_model;
	public function _initialize() {
		$this->partner_key = 'AHJvdbfPKNDHifb4';
		$this->partner = 'taiyangjiankang';
//		$this->partner_key = 'iEv8qLQ5BN2nCG9y';
		$this->msg_model = model('Message');
        $this->appKey = '25b3a2a3df4d0d26b55ea031';
        $this->masterSecret = '3efc28f3eb342950783754f2';
	}

	/**
	 * 医生回复通知
	 */
	public function doctor_reply(){
		$param = input('post.');
        $atime = $param['atime'];
		$sign = isset($param['sign']) ? trim($param['sign']) : '';
		$get_sign = $this->get_sign($param['problem_id'],$atime);
		if($sign !== $get_sign){
			$this->ajax_return('1','签名不正确');
		}
        $detail_data = [
            'user_id' => $param['user_id'],
            'partner' => $this->partner,
            'problem_id' => $param['problem_id'],
            'sign' => substr(md5($this->partner_key.$atime.$param['user_id']), 8, 16),
            'atime' => $atime
        ];
        $url = 'https://www.chunyuyisheng.com';
        $detail_url = '/cooperation/wap/get_partner_problem_detail/?'.http_build_query($detail_data);
        $detail = Http::ihttp_get($url.$detail_url);
        $problem = json_decode($detail['content'],true);
        $ask = $problem['problem']['ask'];
        $doctor = $problem['doctor']['name'];
        $msg_status = $doctor.'医生已经回复您的"'.$ask.'"问诊信息，快来查看吧!';
		
		$result = $this->msg_storage($param['user_id'],$msg_status);
        if($result === false){
            $this->ajax_return('2','存储消息失败');
        }

		$this->ajax_return('0','问题回复通知成功');
	}

	/**
	 * 问题关闭通知
	 */
	public function problem_close(){
		$param = input('post.');
        $atime = $param['atime'];
        $sign = isset($param['sign']) ? trim($param['sign']) : '';
        $get_sign = $this->get_sign($param['problem_id'],$atime);
        if($sign !== $get_sign){
            $this->ajax_return('1','签名不正确');
        }

		$status = ['close','refund'];
		$msg_status = in_array($param['status'],$status) && $param['status'] == 'close' ? '您提交的问题已回答完成自动关闭' : '您提交的问题已关闭，费用将原路退回到您的账户';
		
		$result = $this->msg_storage($param['user_id'],$msg_status);
		if($result === false){
			$this->ajax_return('2','存储消息失败');
		}

		$this->ajax_return('0','问题关闭通知成功');
	}

	/**
    * 全局中断输出
    * @param $code string 响应码
    * @param $msg string 简要描述
    */
    public function ajax_return($code = 0,$msg = ''){
    	$data = array(
            'error'   => (int)$code,
            'error_msg'   =>  $msg,
        );
        ajax_return($data);
    }

    /**
	* 获取认证码
	*/
	protected function get_sign($problem_id,$time){
		if (empty($problem_id)) {
			return false;
		}

		$sign = substr(md5(trim($this->partner_key).$time.trim($problem_id)), 8, 16);
		
		return $sign;
	}

	/**
	* 存储信息
	*/
	protected function msg_storage($user_id,$msg_status){
        $member_mobile = model('Member')->where(['member_id'=>$user_id])->value('member_mobile');
        $client = new Client($this->appKey,$this->masterSecret);
        $push = $client->push();
        $cid = $push->getCid();
        $cid = $cid['body']['cidlist'][0];
        try{
            $res = $push->setCid($cid)
                ->options(['apns_production'=>true])
                ->setPlatform(['android','ios'])
                ->addAlias((string)$member_mobile)
                ->iosNotification($msg_status,['title'=>'在线问诊'])
                ->androidNotification($msg_status,['title'=>'在线问诊'])
                ->send();
        }catch (APIRequestException $e){
            return false;
        }
        if($res['http_code'] == 200) {
            $msg_data = [
                'to_member_id' => $user_id,
                'message_title' => '在线问诊',
                'message_body' => $msg_status,
                'message_time' => time(),
                'message_type' => 3,
                'is_push' => 2,
                'is_more' => 0,
                'push_detail' => $msg_status,
                'push_title' => '在线问诊',
                'push_member_id' => $user_id
            ];

            $result = $this->msg_model->save($msg_data);
            if ($result === false) {
                return false;
            }
        }else{
            return false;
        }
		return true;
	}
}