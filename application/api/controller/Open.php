<?php
namespace app\api\controller;
use Lib\Sms;
use Lib\Watch;
use think\Cache;
use Lib\Http;
class Open extends Apibase
{
	protected $member_model,$token_model,$info_model,$seller_member,$sm_model;
	protected function _initialize(){
    	parent::_initialize();
    	$this->member_model = model('Member');
    	$this->token_model = model('MemberToken');
    	$this->info_model = model('MemberInfo');
    	$this->sm_model = model('SellerMember');
    }
   
	/**
	* 会员快捷登录
	*/ 
	public function login(){
		$mobile = input('post.mobile','','trim');
		$code = input('post.code','','intval');
		$client_type = input('param.client_type');
		if (empty($mobile) || empty($code)) {
			$this->ajax_return('10010','invalid param');
		}

		//严格验证手机号
		if (!is_mobile($mobile)) {
			$this->ajax_return('10011','invalid mobile');
		}

		//验证验证码
		 if($mobile == '13088888888'){
		 	Cache::set('sms'.$mobile,'888888',300);
		 }
		 //测试服库管员
        if($mobile == '18810639952'){
            Cache::set('sms'.$mobile,'888',300);
        }
//		 $cache_code = Cache::pull('sms'.$mobile);
//		 if (is_null($cache_code) || $cache_code != $code) {
//		 	$this->ajax_return('10012','invalid code');
//		 }

		$member_info = $this->member_model->where('member_mobile',$mobile)->find();

		if (!empty($member_info)) {

			//锁定用户无法登录
			$member_info = $member_info->toArray();
			if ($member_info['member_state'] < 1) {
				$this->ajax_return('10014','the user is locked');
			}

			//更新登录信息
			$this->member_model->where(['member_mobile' => $mobile])
                ->update(['login_time' => time(),'login_ip' => get_client_ip(0,true),'login_num' => ['exp','login_num+1']]);
		}else{
			//创建会员
            $salt = random_string(6,5);
            $password = random_string(6,5);
            $pwd = sp_password($password,$salt);
			$data = [
			    'member_mobile'     => $mobile,
                'login_num'         => 1,
                'member_time'       => time(),
                'login_time'        => time(),
                'login_ip'          => get_client_ip(0,true),
                'encrypt'           => $salt,
                'member_password'   => $pwd
            ];
			$result = $this->member_model->save($data);
			if ($result === false) {
				$this->ajax_return('10013','failed to login');
			}

			//获取添加会员ID
			$member_id = $this->member_model->member_id;

			$has_info = $this->sm_model->where(['customer_mobile'=>$mobile])->count();
			if($has_info > 0){
                $customer_data = [
                    'member_id' => $member_id,
                    'is_member' => 1
                ];
            }
		}
        //创建token
        $token = $this->token_model->create_token($mobile,$client_type);
        if (is_null($token)) {
            $this->ajax_return('10013','failed to login');
        }else{
            $has_info = $this->member_model->has_infos($mobile) ? '1' : '0';
            $this->ajax_return('200','success',['token' => $token,'has_info' => $has_info]);
        }
	}

    /**
     * 会员账号密码登录
     */
    public function user_login(){
        $param = input('post.');
        if(!isset($param['member_mobile']) || empty(trim($param['member_mobile']))){
            $this->ajax_return('12030','empty member_mobile');
        }
        $mobile = trim($param['member_mobile']);
        if(!is_mobile($mobile)){
            $this->ajax_return('12031','invalid member_mobile');
        }
        //判断当前登录账号是否存在，不存在则跳转注册页面
        $member_count = $this->member_model->where(['member_mobile'=>$mobile])->count();
        if($member_count == 0){
            $this->ajax_return('12032','the member is not exist');
        }
        //判断密码是否正确
        if(!isset($param['member_password']) || empty(trim($param['member_password']))){
            $this->ajax_return('12033','empty member_password');
        }
        $password = trim($param['member_password']);
        if(strlen($password) < 6 || strlen($password) > 20){
            $this->ajax_return('12034','invalid member_password');
        }
        //判断密码散列
        $salt = $this->member_model->where(['member_mobile'=>$mobile])->value('encrypt');
        if(empty($salt) || strlen($salt) < 6){
            $salt = random_string(6,5);
            $this->member_model->where(['member_mobile'=>$mobile])->update(['encrypt'=>$salt]);
        }

        $pwd = sp_password($password,$salt);
        $count = $this->member_model->where(['member_mobile'=>$mobile,'member_password'=>$pwd])->count();
        if($count == 0){
            $this->ajax_return('12035','false member_password');
        }
        $data = [
            'login_time' => time(),
            'login_ip' => get_client_ip(0,true),
            'login_num' => ['exp','login_num+1']
        ];
        $this->member_model->where(['member_mobile' => $mobile])
            ->update($data);

        $client_type = input('param.client_type');
        //创建token
        $token = $this->token_model->create_token($mobile,$client_type);
        if (is_null($token)) {
            $this->ajax_return('12036','failed to login');
        }else{
            $has_info = $this->member_model->has_infos($mobile) ? '1' : '0';
            $this->ajax_return('200','success',['token' => $token,'has_info' => $has_info]);
        }
    }

    /**
     * 会员注册
     */
    public function register(){
        $param = input('post.');
        if(!isset($param['member_mobile']) || empty(trim($param['member_mobile']))){
            $this->ajax_return('12040','empty member_mobile');
        }
        $mobile = trim($param['member_mobile']);
        if(!is_mobile($mobile)){
            $this->ajax_return('12031','invalid member_mobile');
        }
        //判断当前登录账号是否存在
        $member_count = $this->member_model->where(['member_mobile'=>$mobile])->count();
        if($member_count > 0){
            $this->ajax_return('12041','the member is exist');
        }
        if(!isset($param['code']) || empty(trim($param['code']))){
            $this->ajax_return('12042','empty code');
        }
        $code = trim($param['code']);
        //验证验证码
         $cache_code = Cache::pull('sms'.$mobile);
         if (is_null($cache_code) || $cache_code != $code) {
         	$this->ajax_return('12043','invalid code');
         }
        //验证密码
        if(!isset($param['member_password']) || empty(trim($param['member_password']))){
            $this->ajax_return('12044','empty member_password');
        }
        $password = trim($param['member_password']);
        if(strlen($password) < 6 || strlen($password) > 20){
            $this->ajax_return('12045','invalid member_password');
        }
        $salt = random_string(6,5);
        $pwd = sp_password($password,$salt);
        $data = [
            'member_mobile'     => $mobile,
            'member_password'   => $pwd,
            'encrypt'           => $salt,
            'member_time'       => time(),
            'login_time'        => time(),
            'login_ip'          => get_client_ip(0,true)
        ];
        $res = $this->member_model->insertGetId($data);
        if($res === false){
            $this->ajax_return('12046','failed to register');
        }
        //注册之后直接登录
        $client_type = input('param.client_type');
        //创建token
        $token = $this->token_model->create_token($mobile,$client_type);
        if (is_null($token)) {
            $this->ajax_return('12047','failed to login');
        }else{
            $has_info = $this->member_model->has_infos($mobile) ? '1' : '0';
            $this->ajax_return('200','success',['token' => $token,'has_info' => $has_info]);
        }
    }

    /**
     * 重置密码
     */
    public function reset_password(){
        $mobile = input('post.member_mobile','','trim');
        if(empty($mobile) || !is_mobile($mobile)){
            $this->ajax_return('12060','invalid member_mobile');
        }
        $count = $this->member_model->where(['member_mobile'=>$mobile])->count();
        if($count == 0){
            $this->ajax_return('12061','the member is not exist');
        }
        $code = input('post.code','','trim');
        if(empty($code)){
            $this->ajax_return('12062','empty code');
        }
        //验证验证码
        if($mobile == '13088888888'){
            Cache::set('sms'.$mobile,'888888',300);
        }
        $cache_code = Cache::pull('sms'.$mobile);
        if (is_null($cache_code) || $cache_code != $code) {
            $this->ajax_return('12063','invalid code');
        }
        $password = input('post.member_password','','trim');
        $salt = $this->member_model->where(['member_mobile'=>$mobile])->value('encrypt');
        if(empty($password) || strlen($password) < 6 || strlen($password) > 20){
            $this->ajax_return('12064','invalid member_password');
        }
        if(empty($salt)){
            $salt = random_string(6,5);
        }
        $pwd = sp_password($password,$salt);
        $res = $this->member_model->where(['member_mobile'=>$mobile])->update(['member_password'=>$pwd]);
        if($res === false){
            $this->ajax_return('12065','failed to reset password');
        }
        $this->ajax_return('200','success');
    }

    /**
     * 注册之前判断邀请码是否有效
     */
    public function invite_code_force(){
        $invite_code = input('post.invite_code','','trim');
        $count = model('MemberQrcode')->where(['invite_code'=>$invite_code])->count();
        if($count < 1){
            $this->ajax_return('12020','invalid invite_code');
        }
        $this->ajax_return('200','success');
    }


	/**
	* QQ快捷登录
	*/
	public function login_tencent(){
		$access_token = input('post.access_token','','trim');
        $openid = input('post.openid','','trim');
        $client_type = input('param.client_type');
        $member_password = input('param.member_password','','trim');
        if (empty($access_token) || empty($openid)) {
            $this->ajax_return('10050','invalid param');
        }
		//通过token和openid获取会员信息验证参数正误
		$base_url = 'https://graph.qq.com/user/get_user_info?';
//		$oauth_consumer_key = $client_type == 'ios' ? config('other_login.ios_key') : config('other_login.android_key');
        $oauth_consumer_key = config('other_login.app_key');

		$param = ['access_token' => $access_token,'oauth_consumer_key' => $oauth_consumer_key,'openid' => $openid];
		$query_param = http_build_query($param);
		$result = Http::ihttp_get($base_url.$query_param);
		if (empty($result) || $result['code'] != 200) {
			$this->ajax_return('10051','bad request');
		}
		$content = json_decode($result['content'],true);
		if (isset($content['ret']) && $content['ret'] < 0) {
			$this->ajax_return('10050','invalid param');
		}
		
		//通过openid判定是否有绑定记录
		$member_info = $this->member_model->where(['tencent_sn' => $openid])->find();
		if (!empty($member_info)) {
			//锁定用户无法登录
			$member_info = $member_info->toArray();
			if ($member_info['member_state'] < 1) {
				$this->ajax_return('10052','the user is locked');
			}

			//更新登录信息
			$this->member_model->where(['tencent_sn' => $openid])->update(['login_time' => time(),'login_ip' => get_client_ip(0,true),'login_num' => ['exp','login_num+1']]);

			//创建token
			$token = $this->token_model->create_token($member_info['member_mobile'],$client_type);
			if (is_null($token)) {
				$this->ajax_return('10053','failed to login');
			}else{
				$has_info = $this->member_model->has_infos($member_info['member_mobile']) ? '1' : '0';
                $data = ['token' => $token,'has_info'=>$has_info,'member_mobile'=>$member_info['member_mobile']];
				$this->ajax_return('200','success',$data);
			}
		}

		$mobile = input('post.mobile','','trim');
		$code = input('post.code',0,'intval');
		if (empty($mobile) || empty($code)) {
			$this->ajax_return('10054','invalid param');
		}

		//严格验证手机号
		if (!is_mobile($mobile)) {
			$this->ajax_return('10055','invalid mobile');
		}

		//验证验证码
		$cache_code = Cache::pull('sms'.$mobile);
		if (is_null($cache_code) || $cache_code != $code) {
			$this->ajax_return('10056','invalid code');
		}
        if(empty($member_password) || strlen($member_password) < 6 || strlen($member_password) > 20){
            $this->ajax_return('12070','invalid member_password');
        }
		$member_info = $this->member_model->where('member_mobile',$mobile)->find();
		//无手机号绑定记录则添加会员记录
		if (!empty($member_info)) {
			//锁定用户无法登录
			$member_info = $member_info->toArray();
			if ($member_info['member_state'] < 1) {
				$this->ajax_return('10052','the user is locked');
			}
			//判断手机号是否已绑定QQ账号
            $tencent_sn = $this->member_model->where(['member_mobile'=>$mobile])->value('tencent_sn');
			if(!empty($tencent_sn)){
			    $this->ajax_return('12071','the member is binded QQ');
            }
            $salt = $member_info['encrypt'];
            if(empty($salt)){
                $salt = random_string(6,5);
            }
            $pwd = sp_password($member_password,$salt);
            $data = [
                'tencent_sn'        => $openid,
                'login_time'        => time(),
                'login_ip'          => get_client_ip(0,true),
                'login_num'         => ['exp','login_num+1'],
                'member_password'   => $pwd,
                'encrypt'           => $salt
            ];
			//更新登录信息
			$this->member_model->where(['member_mobile' => $mobile])->update($data);

			//创建token
			$token = $this->token_model->create_token($mobile,$client_type);
			if (is_null($token)) {
				$this->ajax_return('10053','failed to login');
			}else{
				$has_info = $this->member_model->has_infos($mobile) ? '1' : '0';
                $data = ['token' => $token,'has_info'=>$has_info,'member_mobile'=>$mobile];
				$this->ajax_return('200','success',$data);
			}
		}else{
            $salt = random_string(6,5);
            $pwd = sp_password($member_password,$salt);
			//创建会员
			$data = [
			    'member_name'       => $content['nickname'],
                'member_avatar'     => $content['figureurl_qq_2'],
                'tencent_sn'        => $openid,
                'member_mobile'     => $mobile,
                'login_num'         => 1,
                'member_time'       => time(),
			    'login_time'        => time(),
                'login_ip'          => get_client_ip(0,true),
                'encrypt'           => $salt,
                'member_password'   => $pwd
            ];
			$member_result = $this->member_model->save($data);
			if ($member_result === false) {
				$this->ajax_return('10053','failed to login');
			}

			//获取添加会员ID
			$member_id = $this->member_model->member_id;

			$has_info = $this->sm_model->where(['customer_mobile'=>$mobile])->count();
			if($has_info > 0){
				$customer_data = [
					'member_id' => $member_id,
					'is_member' => 1
				];
				$this->sm_model->save($customer_data,['customer_mobile'=>$mobile]);
			}

			//附表数据
			$side_data = array();
			$side_data['member_id'] = $this->member_model->member_id;
			$side_data['member_sex'] = $content['gender'] == '女' ? 2 : 1;

			$this->info_model->save($side_data);
			//创建token
			$token = $this->token_model->create_token($mobile,$client_type);
			if (is_null($token)) {
				$this->ajax_return('10053','failed to login');
			}else{
				$has_info = $this->member_model->has_infos($mobile) ? '1' : '0';
                $data = ['token' => $token,'has_info'=>$has_info,'member_mobile'=>$mobile];
				$this->ajax_return('200','success',$data);
			}
		}
	}

	/**
	* 微信登录
	*/
	public function login_weichat(){
		$access_token = input('post.access_token','','trim');
		$openid = input('post.openid','','trim');
		$client_type = input('param.client_type');
        $member_password = input('param.member_password','','trim');
		if (empty($access_token) || empty($openid)) {
			$this->ajax_return('10050','invalid param');
		}

		//通过Token和Openid获取用户信息
		$base_url = 'https://api.weixin.qq.com/sns/userinfo?';
		$param = ['access_token' => $access_token,'openid' => $openid];
        $query_param = http_build_query($param);
		$result = Http::ihttp_get($base_url.$query_param);
		if (empty($result) || $result['code'] != 200) {
			$this->ajax_return('10051','bad request');
		}

        $content = json_decode($result['content'],true);
        //通过openid判定是否有绑定记录
		$member_info = $this->member_model->where(['weichat_sn' => $openid])->find();

		if (!empty($member_info)) {
			//锁定用户无法登录
			$member_info = $member_info->toArray();
			if ($member_info['member_state'] < 1) {
				$this->ajax_return('10052','the user is locked');
			}

			//更新登录信息
			$this->member_model->where(['weichat_sn' => $openid])->update(['login_time' => time(),'login_ip' => get_client_ip(0,true),'login_num' => ['exp','login_num+1']]);

			//创建token
			$token = $this->token_model->create_token($member_info['member_mobile'],$client_type);
			if (is_null($token)) {
				$this->ajax_return('10053','failed to login');
			}else{
				$has_info = $this->member_model->has_infos($member_info['member_mobile']) ? '1' : '0';
				$data = ['token' => $token,'has_info'=>$has_info,'member_mobile'=>$member_info['member_mobile']];
				$this->ajax_return('200','success',$data);
			}
		}
		$mobile = input('post.mobile','','trim');

		$code = input('post.code',0,'intval');
		if (empty($mobile) || empty($code)) {
			$this->ajax_return('10054','invalid param');
		}

		//严格验证手机号
		if (!is_mobile($mobile)) {
			$this->ajax_return('10055','invalid mobile');
		}

		//验证验证码
		$cache_code = Cache::pull('sms'.$mobile);
		if (is_null($cache_code) || $cache_code != $code) {
			$this->ajax_return('10056','invalid code');
		}
        if(empty($member_password) || strlen($member_password) < 6 || strlen($member_password) > 20){
            $this->ajax_return('12070','invalid member_password');
        }
	    $member_info = $this->member_model->where(['member_mobile'=>$mobile])->find();

	    if(!empty($member_info)){
           $member_info = $member_info->toArray();
            if($member_info['member_state'] < 1){
                $this->ajax_return('10014','the user is locked');
            }
            //判断手机号是否已绑定微信账号
            $weichat_sn = $this->member_model->where(['member_mobile'=>$mobile])->value('weichat_sn');
            if(!empty($weichat_sn)){
                $this->ajax_return('12072','the member is binded wechat');
            }
            //设置密码
            $salt = $member_info['encrypt'];
            if(empty($salt)){
                $salt = random_string(6,5);
            }
            $pwd = sp_password($member_password,$salt);
            $data = [
                'weichat_sn'        => $openid,
                'login_time'        => time(),
                'login_ip'          => get_client_ip(0,true),
                'login_num'         => ['exp','login_num+1'],
                'member_password'   => $pwd,
                'encrypt'           => $salt
            ];
            $this->member_model->where(['member_mobile' => $mobile])->update($data);

            $token = $this->token_model->create_token($mobile,$client_type);
            if(is_null($token)){
            	$this->ajax_return('10053','failed to login');
            }else{
            	$has_info = $this->member_model->has_infos($mobile) ? '1' : '0';
                $data = ['token' => $token,'has_info'=>$has_info,'member_mobile'=>$mobile];
				$this->ajax_return('200','success',$data);
			}
	    }else{
            $salt = random_string(6,5);
            $pwd = sp_password($member_password,$salt);
	    	//创建会员信息
	    	$member_info = array(
                'member_name'       => $content['nickname'],
                'member_mobile'     => $mobile,
                'member_avatar'     => $content['headimgurl'],
                'login_num'         => 1,
                'login_time'        => time(),
                'login_ip'          => get_client_ip(0,true),
                'weichat_sn'        => $openid,
                'member_time'       => time(),
                'encrypt'           => $salt,
                'member_password'   => $pwd
	    	);
	    	$result = $this->member_model->save($member_info);
            if($result === false){
            	$this->ajax_return('10053','failed to login');
            }

            //获取添加会员ID
			$member_id = $this->member_model->member_id;

			$has_info = $this->sm_model->where(['customer_mobile'=>$mobile])->count();
			if($has_info > 0){
				$customer_data = [
					'member_id' => $member_id,
					'is_member' => 1
				];
				$this->sm_model->save($customer_data,['customer_mobile'=>$mobile]);
			}

            $info_array = array();
            $info_array['member_id'] = $this->member_model->member_id;
            switch ($result['gender']) {
            	case 'm':
            		$sex = '男';
            		break;
            	case 'f':
            		$sex = '女';
            		break;
            	case 'n':
            		$sex = '未知';
            		break;
            }
            $info_array['member_sex'] = $sex == '女' ? 2 : 1;
            $this->info_model->save($info_array);
            //创建token
			$token = $this->token_model->create_token($mobile,$client_type);
			if (is_null($token)) {
				$this->ajax_return('10053','failed to login');
			}else{
				$has_info = $this->member_model->has_infos($mobile) ? '1' : '0';
                $data = ['token' => $token,'has_info'=>$has_info,'member_mobile'=>$mobile];
				$this->ajax_return('200','success',$data);
			}
	    }
	}


	/**
	* 微博登录
	*/ 
	public function login_weibo(){
		$access_token = input('post.access_token','','trim');
        $uid = input('post.uid','','trim');
        $client_type = input('param.client_type');
        $member_password = input('param.member_password','','trim');
        if(empty($access_token) || empty($uid)){
            $this->ajax_return('10050','invalid param');
        }

		$param = [
			'access_token' => $access_token,
			'uid' => $uid
		];
		$base_url = "https://api.weibo.com/2/users/show.json?";
		$query_param = http_build_query($param);
		$result = Http::ihttp_get($base_url.$query_param);
		if (empty($result) || $result['code'] != 200) {
			$this->ajax_return('10051','bad request');
		}

		$content = json_decode($result['content'],true);
        //通过openid判定是否有绑定记录
		$member_info = $this->member_model->where(['weibo_sn' => $uid])->find();

		if (!empty($member_info)) {
			//锁定用户无法登录
            $member_info = $member_info->toArray();
            if ($member_info['member_state'] < 1) {
                $this->ajax_return('10052','the user is locked');
            }

			//更新登录信息
			$this->member_model->where(['weibo_sn' => $uid])->update(['login_time' => time(),'login_ip' => get_client_ip(0,true),'login_num' => ['exp','login_num+1']]);

			//创建token
            $token = $this->token_model->create_token($member_info['member_mobile'],$client_type);
            if (is_null($token)) {
                $this->ajax_return('10053','failed to login');
            }else{
                $has_info = $this->member_model->has_infos($member_info['member_mobile']) ? '1' : '0';
                $data = ['token' => $token,'has_info'=>$has_info,'member_mobile'=>$member_info['member_mobile']];
                $this->ajax_return('200','success',$data);
            }
		}
		$mobile = input('post.mobile','','trim');
        $code = input('post.code',0,'intval');
        if (empty($mobile) || empty($code)) {
            $this->ajax_return('10054','invalid param');
        }

		//严格验证手机号
		if (!is_mobile($mobile)) {
			$this->ajax_return('10055','invalid mobile');
		}

		//验证验证码
		$cache_code = Cache::pull('sms'.$mobile);

		if (is_null($cache_code) || $cache_code != $code) {
			$this->ajax_return('10056','invalid code');
		}
        if(empty($member_password) || strlen($member_password) < 6 || strlen($member_password) > 20){
            $this->ajax_return('12070','invalid member_password');
        }
	    $member_info = $this->member_model->where(['member_mobile'=>$mobile])->find();
	    if(!empty($member_info)){
            $member_info = $member_info->toArray();
            if($member_info['member_state'] < 1){
                $this->ajax_return('10052','the user is locked');
            }
            //判断手机号是否已绑定微博账号
            $weibo_sn = $this->member_model->where(['member_mobile'=>$mobile])->value('weibo_sn');
            if(!empty($weibo_sn)){
                $this->ajax_return('12073','the member is binded weibo');
            }
            $salt = $member_info['encrypt'];
            if(empty($salt)){
                $salt = random_string(6,5);
            }
            $pwd = sp_password($member_password,$salt);
            $data = [
                'weibo_sn'          => $uid,
                'login_time'        => time(),
                'login_ip'          => get_client_ip(0,true),
                'login_num'         => ['exp','login_num+1'],
                'member_password'   => $pwd,
                'encrypt'           => $salt
            ];
            $this->member_model->where(['member_mobile' => $mobile])->update($data);

            $token = $this->token_model->create_token($mobile,$client_type);
            if(is_null($token)){
            	$this->ajax_return('10053','failed to login');
            }else{
            	$has_info = $this->member_model->has_infos($mobile) ? '1' : '0';
                $data = ['token' => $token,'has_info'=>$has_info,'member_mobile'=>$mobile];
				$this->ajax_return('200','success',$data);
			}
	    }else{
	        $salt = random_string(6,5);
            $pwd = sp_password($member_password,$salt);
	    	//创建会员信息
	    	$member_info = array(
                'member_name'       => $content['screen_name'],
                'member_mobile'     => $mobile,
                'member_avatar'     => $content['profile_image_url'],
                'login_num'         => 1,
                'login_time'        => time(),
                'login_ip'          => get_client_ip(0,true),
                'weibo_sn'          => $uid,
                'member_time'       => time(),
                'encrypt'           => $salt,
                'member_password'   => $pwd
	    	);
	    	$result = $this->member_model->save($member_info);
            if($result === false){
            	$this->ajax_return('10053','failed to login');
            }

            //获取添加会员ID
			$member_id = $this->member_model->member_id;

			$has_info = $this->sm_model->where(['customer_mobile'=>$mobile])->count();
			if($has_info > 0){
				$customer_data = [
					'member_id' => $member_id,
					'is_member' => 1
				];
				$this->sm_model->save($customer_data,['customer_mobile'=>$mobile]);
			}

            $info_array = array();
            $info_array['member_id'] = $member_id;
            $this->info_model->save($info_array);
            //创建token
			$token = $this->token_model->create_token($mobile,$client_type);
			if (is_null($token)) {
				$this->ajax_return('10053','failed to login');
			}else{
				$has_info = $this->member_model->has_infos($mobile) ? '1' : '0';
                $data = ['token' => $token,'has_info'=>$has_info,'member_mobile'=>$mobile];
				$this->ajax_return('200','success',$data);
			}
	    }
	}

	/**
	* 发送验证码
	*/ 
	public function send_sms(){
		$mobile = input('post.mobile','','trim');
		//严格验证手机号
		if (empty($mobile) || !is_mobile($mobile)) {
			$this->ajax_return('10020','invalid mobile');
		}

		$code = random_string(6,1);
		$sms = new Sms;
		try {
			$sms->send($mobile,$code);
		} catch (Exception $e) {
			$this->ajax_return('10021','bad send request');
		}
		Cache::set('sms'.$mobile,$code,300);
		$this->ajax_return('200','success');
	}
    /**
     * 获取服务器时间
     */
    public function get_timestamp(){
        $this->ajax_return('200','success',['timestamp'=>time()]);
    }
	/**
	* 获取最新版本信息
	*/ 
	public function version_info(){
		$os_type = input('post.os_type','','trim');
		if (!in_array($os_type,array('android','ios'))) {
            $this->ajax_return('10120','error os_type');
        }
        $os_type = $os_type === 'android' ? 1 : 2;
		$version_info = model('Version')->where(['os_type'=>$os_type,'status'=>1])->order(['create_time'=>'DESC'])->find();
		if(empty($version_info)){
			$this->ajax_return('10121','version is empty');
		}

		$this->ajax_return('200','success',$version_info);
	}

    /**
     * 校验第三方的接口访问权限
     */
    public function check_third_access_auth($param){
        //时间戳向后不大于300 向前不大于300
        $now_time = time();
        $timestamp = isset($param['_timestamp']) ? intval($param['_timestamp']) : 0;
        if (!is_int($timestamp) || $now_time - $timestamp > 300 || $timestamp - $now_time > 300) {
            $this->ajax_return('10001','invalid timestamp');
        }
        $signature = isset($param['signature']) ? trim($param['signature']) : '';
        if (empty($signature) || !preg_match("/^(?!([a-f]+|\d+)$)[a-f\d]{40}$/",$signature)) {
            $this->ajax_return('10002','invalid signature');
        }
        if (cache('third_sign_'.$signature)) {
            $this->ajax_return('10003','invalid access');
        }
        $access_token = isset($param['access_token']) ? trim($param['access_token']) : '';
        if (empty($access_token)) {
            $this->ajax_return('10004','invalid access_token');
        }

        ksort($param);
        unset($param['signature']);

        $sort_str = http_build_query($param);
        $oper_sign = sha1($sort_str);

        if ($oper_sign !== $signature) {
            $this->ajax_return('10005','no auth to access');
        }
        cache('third_sign_'.$signature,$now_time,600);
        return true;
    }
    /**
     * 合作伙伴获取常仁用户token
     */
    public function third_member_token(){
        $param = input('param.');
        $this->check_third_access_auth($param);
	    $mobile = $param['mobile'];
        //严格验证手机号
        if (empty($mobile) || !is_mobile($mobile)) {
            $this->ajax_return('10011','invalid mobile');
        }
        $model = model('ThirdMember');
        if(($token = $model->get_token($mobile,$param['access_token']))===false){
            $this->ajax_return('10012','the member is not found');
        }
        $this->ajax_return('200','success',['token'=>$token]);
    }

    /**
     * 合作伙伴推送用户信息到常仁
     */
    public function third_push_member(){
        $param = input('post.','','trim');
        $this->check_third_access_auth($param);
        //严格验证手机号
        if (empty($param['member_mobile']) || !is_mobile($param['member_mobile'])) {
            $this->ajax_return('10011','invalid mobile');
        }
        $model = model('ThirdMember');
        $token = $model->get_token($param['member_mobile'],$param['access_token']);
        if($token){
            $this->ajax_return('200','success',['token'=>$token]);
        }
        if(($token = $model->add_member($param,$param['access_token']))===false){
            $this->ajax_return('10021','failed to add user');
        }
        $this->ajax_return('200','success',['token'=>$token]);
    }
	/**
    * 下载页面
    */
    public function download_page(){
        return $this->fetch();
    }

    /**
     * 获取手表测试数据
     */
    public function get_watch_data(){
        $watch_imei = input('post.watch_imei','','trim');
        $command = input('post.command','','trim');
        $data = [
            'device_imei'   => $watch_imei
        ];
        $param = input('param.');
        if(isset($param['startTime'])){
            $data['startTime'] = $param['startTime'];
        }
        if(isset($param['endTime'])){
            $data['endTime'] = $param['endTime'];
        }

        $watch = new Watch();
        $result = $watch->send_request($data,$command);

        $this->ajax_return('200','success',$result);
    }
}
