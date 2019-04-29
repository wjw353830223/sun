<?php
/**
 * 检测报告上传
 */
namespace app\open\controller;
use think\Controller;
use think\Cache;
use Lib\Qrcode;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
class Report extends Controller {

	//签名公钥
	protected $accessKey;
	//签名私钥
	protected $secretKey;
	protected $third_report_model;
	public function _initialize() {
		$this->accessKey = 'kVNnKJYWgyeIQz9u4u_QqpghJwPW1G681R655HYL';
        $this->secretKey = '8kQOa-wEYLl8Q7sb066qFgxNKcOW4GiZoB45T-pN';
        $this->third_report_model = model('ThirdReport');
	}

	public function index(){
		$uuid = random_string(12);
		$cache_name = 'rp_'.$uuid;
		Cache::set($cache_name, '',7200);
		$data = [
			'code' => 200,
			'QRlogin_uuid' => $uuid
		];
		ajax_return($data);
	}

	public function login(){
		$uuid = input('param.uuid','','trim');
		$cache_name = 'rp_'.$uuid;
		$cache_info = Cache::get($cache_name);
		if ($cache_info === false) {
			$data = [
				'code' => 400
			];
			ajax_return($data);
		}
        if(strpos($cache_info, 'third:') === 0){
            $cache_info = substr($cache_info,6);
        }
		if ($cache_info > 0) {
			$data = [
				'code' => 200
			];
			ajax_return($data);
		}else{
			$data = [
				'code' => 408
			];
			ajax_return($data);
		}
	}

	/**
	* 获取二维码
	*/
	public function qrcode(){
		$uuid = input('param.uuid','','trim');
		$cache_name = 'rp_'.$uuid;
		$cache_info = Cache::get($cache_name);
		if ($cache_info === false) {
			$data = [
				'code' => 400
			];
			ajax_return($data);
		}
		$qr = new Qrcode();
		$qr->content = host_url().'/api/qrlogin/?uuid='.$uuid;
        return $qr->build();
	}
	/**
	* 获取健康数据
	*/
	public function health(){
		$uuid = input('param.uuid','','trim');
		$cache_name = 'rp_'.$uuid;
		$cache_info = Cache::get($cache_name);
		if ($cache_info === false) {
			$data = [
				'code' => 400
			];
			ajax_return($data);
		}

		if (empty($cache_info)) {
			$data = [
				'code' => 408
			];
			ajax_return($data);
		}
		$member = model('member')->where('member_id',$cache_info)->find();
		if (is_null($member)) {
		    //第三方检测机构用户
		    if(strpos($cache_info, 'third:') === 0 ){
                $third_member_health_id = Cache::get( 'third_member_health_id_'. substr($cache_info,6) . '_' .$uuid);
                $third_member_health = model('ThirdMemberHealth')->where(['id'=>$third_member_health_id])->find();
                if(!empty($third_member_health)){
                    $data = [
                        'member_height'=>$third_member_health['member_height'],
                        'member_weight'=>$third_member_health['member_weight'],
                        'member_age'=>$third_member_health['member_age'],
                        'member_sex'=>$third_member_health['member_sex'],
                        'member_name'=>$third_member_health['member_name']
                    ];
                    ajax_return($data);
                }
            }
		    $data = [
				'code' => 400
			];
			ajax_return($data);
		}
		$member_name = $member->member_name;
		$member_info = model('member_info')->where('member_id',$cache_info)->field('member_height,member_weight,member_age,member_sex')->find()->toArray();
		$member_info['member_name'] = $member_name;
		ajax_return($member_info);
	}
	/**
	* 上传检测报告
	*/
	public function upload(){
		$uuid = input('param.uuid','','trim');
		$cache_name = 'rp_'.$uuid;
		$cache_info = Cache::get($cache_name);
		if ($cache_info === false) {
			$data = [
				'code' => 400
			];
			ajax_return($data);
		}

		if (empty($cache_info)) {
			$data = [
				'code' => 408
			];
			ajax_return($data);
		}

		$file = request()->file('file');
		if (empty($file)) {
			$data = [
				'code' => 409
			];
			ajax_return($data);
		}
        $filePath = $file->getRealPath();
        if(!$this->is_json(file_get_contents($filePath))){
            $data = [
                'code' => 409
            ];
            ajax_return($data);
        }
        $ext = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);  //后缀
        $key = uniqid() . "." . $ext; //文件重命名
        $res = $this->upload_to_qiniu($filePath, $key);
        if (!$res) {
            $data = [
				'code' => 409
			];
			ajax_return($data);
        }else{
            //第三方检测机构上传记录
            if(strpos($cache_info,'third:') === 0){
                $auth_token_id = substr($cache_info,6);
                $upload_ip = get_client_ip(0,true);
                $cache_name = 'third_member_health_id_'. substr($cache_info,6) . '_' .$uuid;
                $member_health_id = Cache::get($cache_name);
                if(!$this->third_report_model->save_report($auth_token_id, $key, $upload_ip, $member_health_id, time())){
                    $data = [
                        'code' => 409
                    ];
                    ajax_return($data);
                }
                $data = [
                    'code' => 200
                ];
                ajax_return($data);
            }
        	//用户上传记录
        	$report_data = [
        		'member_id' => $cache_info,
        		'file_path' => $key,
        		'upload_ip' => get_client_ip(0,true),
        		'create_time' => time()
        	];

        	$save_result = model('MemberReport')->save($report_data);
        	if ($save_result === false) {
        		$data = [
					'code' => 409
				];
				ajax_return($data);
        	}
            $message = [
                'from_member_id' => 0,
                'to_member_id'   => $cache_info,
                'message_title'  => '',
                'message_time'   => time(),
                'message_state'  => 0,
                'message_type'   => 9,
                'is_more'        => 0,
                'is_push'        => 2,
                'message_body'   => '您收到一条新的健康报告，快来查看吧',
                'push_detail'    => '您收到一条新的健康报告，快来查看吧',
                'push_title'     => ''
            ];
            model('Message')->save($message);
            $data = [
				'code' => 200
			];
			ajax_return($data);
        }
	}
	/**
	* 七牛云文件上传函数
	* @param 
	*/
	private function upload_to_qiniu($file_path, $file_name) {
        $auth = new Auth($this->accessKey, $this->secretKey);
        //空间名称
        $bucket = config('qiniu.buckets')['report']['name'];
        $token = $auth->uploadToken($bucket);
        $uploadMgr = new UploadManager();
        // 调用 UploadManager 的 putFile 方法进行文件的上传。
        list($ret, $err) = $uploadMgr->putFile($token, $file_name, $file_path);
        if ($err !== null) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 判断是否是json数据
     * @param $string
     * @return bool
     */
    function is_json($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}