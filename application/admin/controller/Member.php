<?php
namespace app\admin\controller;
require APP_PATH . '../vendor/qiniu/php-sdk/autoload.php';

use app\common\model\MemberAuth;
use JPush\Client;
use JPush\Exceptions\JPushException;
use think\File;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
class Member extends Adminbase
{

	protected $accessKey,$secretKey,$member_model,$member_auth,$appKey,$masterSecret,$message;

	public function _initialize() {
		parent::_initialize();
		$this->member_model = model("member");
		$this->member_auth = model('MemberAuth');
		$this->message = model('Message');
        $this->accessKey = 'kVNnKJYWgyeIQz9u4u_QqpghJwPW1G681R655HYL';
        $this->secretKey = '8kQOa-wEYLl8Q7sb066qFgxNKcOW4GiZoB45T-pN';
        $this->appKey = '25b3a2a3df4d0d26b55ea031';
        $this->masterSecret = '3efc28f3eb342950783754f2';
	}

	//会员列表
	public function index(){
		$where=array();
        $param = input('param.');
        if(isset($param['keyword']) && !empty($param['keyword'])){
            $keyword = trim($param['keyword']);
            $where['member_mobile'] = ['like',"%$keyword%"];
        }
        $count = $this->member_model->where($where)->count();
        $list = $this->member_model->where($where)->order('member_id desc')->paginate(20,$count);

    	$page = $list->render();
        $this->assign('page',$page);
        $this->assign('list',$list);
        return $this->fetch();
	}

	//会员编辑
	public function edit(){
		$profession = ['1'=>'国家公务员','2'=>'专业技术人员','3'=>'职员','4'=>'企业管理人员','5'=>'工人','6'=>'农民','7'=>'学生','8'=>'现役军人','9'=>'自由职业者','10'=>'个体经营者','11'=>'退(离)休人员'];
		$medical_type = ['1'=>'城镇职工医保','2'=>'新农合医保','3'=>'其他'];
		$member_id = input('get.id',0,'intval');
		if (empty($member_id)) {
            $this->error('参数传入错误!');
		}
        $member = $this->member_model->where(['member_id'=>$member_id])->find();
        $member_seller = db('store_seller')->where(array('member_id'=>$member_id))->find();
        $member_info = model('member_info')->where(['member_id'=>$member_id])->find();
        $this->assign('member',$member);
        $this->assign('member_seller',$member_seller);
        $this->assign('member_info',$member_info);
        $this->assign('profession',$profession);
        $this->assign('medical_type',$medical_type);
        return $this->fetch();
	}

	//会员编辑提交
	public function edit_post(){   
		if(request()->isPost()){ 
			$param = input('post.');
            $member_id = $param['id'];
            $member_data = $member_info_data =array();
            $member_info = $this->member_model
                ->where(['member_id'=>$member_id])
                ->field('member_mobile,member_avatar,member_name')
                ->find();
			
			if(!empty($member_info['member_avatar']) && $member_info['member_avatar'] !== $param['member_avatar']){
				$member_data['member_avatar'] = $param['member_avatar'];
			}
			
			if(empty($param['member_mobile']) || !is_mobile($param['member_mobile'])){
	            $this->error("手机号码格式不正确");
			}
			
			if($member_info['member_mobile'] !== $param['member_mobile']){
				$member_data['member_mobile'] = $param['member_mobile'];
			}

			if(empty($param['member_name'])){
	            $this->error("昵称不能为空");
			}

			if($member_info['member_name'] !== $param['member_name']){
				$member_data['member_name'] = $param['member_name'];
			}
			$member_infos = model('member_info')->where(['member_id'=>$member_id])->find();
			

			if(!empty($param['member_age']) && !is_numeric($param['member_age']) || intval($param['member_age'])>200){
				$this->error("年龄格式不正确");
			}

			if(!empty($param['member_height']) && !is_numeric($param['member_height']) || intval($param['member_height'])>250){
				$this->error("身高格式不正确");
			}
			
			if(!empty($param['member_weight']) && !is_numeric($param['member_weight']) || intval($param['member_weight'])>250){
				$this->error("体重格式不正确");
			}
			if($member_infos['true_name'] !== strip_tags($param['true_name'])){
				$member_info_data['true_name'] = strip_tags($param['true_name']);
			}
			if($member_infos['member_age'] !== intval($param['member_age'])){
				$member_info_data['member_age'] = intval($param['member_age']);
			}
			if($member_infos['member_height'] !== intval($param['member_height'])){
				$member_info_data['member_height'] = intval($param['member_height']);
			}
			if($member_infos['member_weight'] !== intval($param['member_weight'])){
				$member_info_data['member_weight'] = intval($param['member_weight']);
			}
			if(isset($param['member_sex'])){
				if($member_infos['member_sex'] !== intval($param['member_sex'])){
                    $member_info_data['member_sex'] = $param['member_sex'];
                }
			}
			
			if($member_infos['profession'] !== intval($param['profession'])){
				$member_info_data['profession'] = $param['profession'];
			}
			if($member_infos['medical_type'] !== intval($param['medical_type'])){
				$member_info_data['medical_type'] = $param['medical_type'];
			}

			if(empty($member_data) && empty($member_info_data)){
				$this->success("修改会员成功!",url("member/index"));
			}
			if(!empty($member_data) && !empty($member_info_data)){
				$member_result = $this->member_model->save($member_data,array("member_id"=>$member_id));
				if(!empty($member_infos)){
					$member_result = model("member_info")->save($member_info_data,["member_id"=>$member_id]);
				}else{
					$member_info_data['member_id'] = $member_id;
					$member_result = model("member_info")->save($member_info_data);
				}
				if($member_result){
					$this->success("修改会员成功!",url("member/index"));
				}else{
					$this->error("修改失败");
				}
			}
			if(!empty($member_data) && empty($member_info_data)){
				$member_result = $this->member_model->save($member_data,["member_id"=>$member_id]);
				if($member_result){
					$this->success("修改会员成功!",url("member/index"));
				}else{
					$this->error("修改失败");
				}
			}
			if(empty($member_data) && !empty($member_info_data)){
				if(!empty($member_infos)){
					$member_result = model("member_info")->save($member_info_data,["member_id"=>$member_id]);
				}else{
					$member_info_data['member_id'] = $member_id;
					$member_result = model("member_info")->save($member_info_data);
				}
				if($member_result){
					$this->success("修改会员成功!",url("member/index"));
				}else{
					$this->error("修改失败");
				}
			}
		}
	}

	//会员添加
	public function add(){
		$profession = ['1'=>'国家公务员','2'=>'专业技术人员','3'=>'职员','4'=>'企业管理人员','5'=>'工人','6'=>'农民','7'=>'学生','8'=>'现役军人','9'=>'自由职业者','10'=>'个体经营者','11'=>'退(离)休人员'];
		$medical_type = ['1'=>'城镇职工医保','2'=>'新农合医保','3'=>'其他'];

		$this->assign('profession',$profession);
		$this->assign('medical_type',$medical_type);
		return $this->fetch();
	}

	//会员添加提交
	public function add_post(){
		if(request()->isPost()){
			$param = input('post.');
			if(empty($param['member_mobile']) || !is_mobile($param['member_mobile'])){
	            $this->error("手机号码无效");
			}
			$member_id = model('Member')->where(['member_mobile' => trim($param['member_mobile'])])->value('member_id');
			if($member_id){
			   $this->error('该手机号已经被注册');
            }
			
			if(empty($param['member_name'])){
				$this->error("昵称不能为空");
			}
			
			$member_info = $data = array();
			$member_info['member_time'] = time();
			$member_info['login_time'] = time();
            $member_info['member_mobile'] = trim($param['member_mobile']);
            $member_info['member_name'] = trim($param['member_name']);
            $member_info['member_avatar'] = $param['member_avatar'];

			if(!empty($param['member_age']) && !is_numeric($param['member_age'])){
				$this->error("年龄格式不正确");
			}
			
			if(!empty($param['member_height']) && !is_numeric($param['member_height'])){
				$this->error("身高格式不正确");
			}
			
			if(!empty($param['member_weight']) && !is_numeric($param['member_weight'])){
				$this->error("体重格式不正确");
			}
			if(!empty($param['member_sex'])){
				$data['member_sex'] = intval($param['member_sex']);
			}
			if(!empty($param['member_height'])){
				$data['member_height'] = intval($param['member_height']);
			}
			if(!empty($param['member_weight'])){
				$data['member_weight'] = intval($param['member_weight']);
			}
			if(!empty($param['profession'])){
				$data['profession'] = intval($param['profession']);
			}
			if(!empty($param['medical_type'])){
				$data['medical_type'] = intval($param['medical_type']);
			}
			if(!empty($param['true_name'])){
				$data['true_name'] = strip_tags($param['true_name']);
			}
			if(!empty($param['member_age'])){
				$data['member_age'] = intval($param['member_age']);
			}
			// if(!empty($param['member_type'])){
			// 	$data = [
			// 		'member_id'=>$member_result,
			// 		'seller_mobile' => $param['member_mobile'],
			// 		'store_id' => 1,
			// 		'seller_role' => $param['member_type'],
			// 		'invite_code' => random_string(8)
			// 	];
			// 	db('store_seller')->insert($data);
			// }

            $this->member_model->startTrans();
            model("MemberInfo")->startTrans();
            model('MemberQrcode')->startTrans();
            $member_result = $this->member_model->insertGetId($member_info);
            if(!$member_result){
                $this->error("添加失败");
            }
            $data['member_id'] = $member_result;
            if($data){
                $member_info_result = model("MemberInfo")->allowField(true)->save($data);
                if(!$member_info_result){
                    $this->member_model->rollback();
                    $this->error("添加失败");
                }
            }
            $member_data = [
                'member_id'     => $member_result,
                'member_avatar' => $param['member_avatar'],
                'member_mobile' => $member_info['member_mobile']
            ];
            $qrcode = model('MemberQrcode')->create_qrcode($member_data,1);
            if($qrcode === false){
                $this->member_model->rollback();
                model("MemberInfo")->rollback();
                $this->error('二维码生成失败');
            }
            $this->member_model->commit();
            model("MemberInfo")->commit();
            model('MemberQrcode')->commit();
            $this->success("添加会员成功!",url('member/index'));
		}
	}

	//会员头像上传
	public function avatar_upload(){
		$file = request()->file('avatar');
        $image = \think\Image::open($file);
        $type = $image->type();
        $save_path = 'public/uploads/avatar';
        $save_name = uniqid() . '.' . $type;

		//图片缩略上传
		$image->thumb(500, 499)->save(ROOT_PATH . $save_path . '/' . $save_name);

    	//开始上传
    	if (is_file(ROOT_PATH . $save_path . '/' . $save_name)) {
    		$data = [
    			'file' => $save_name,
    			'msg'  => '上传成功',
    			'code' => 1
            ];
    		//兼容ajaxfileupload插件的数据输出模式
    		echo json_encode($data);
    	} else {
    		$data = [
    			'data' => '',
    			'msg'  => $file->getError(),
    			'code' => 0
            ];
    		echo json_encode($data);
    	}
	}

	/**
     * 图片裁剪
     */
    public function pic_cut(){
    	if (request()->isPost()) {
    		$param = input('post.');
    		$pathinfo = pathinfo($param['url']);
    		$image = \think\Image::open('.'.$param['url']);
			//将图片裁剪为300x300并保存为crop.png
			$image->crop($param['w'], $param['h'],$param['x1'],$param['y1'])->save('.'.$param['url']);
			if (!empty($param['filename'])) {
				@unlink($param['filename']);
			}
			exit($pathinfo['basename']);
    	}else{
    		$param = input('get.');
	    	$src = $param['src'];
	    	$img_src = ROOT_PATH.'public'.$src;
	    	if (empty($src) || !is_file($img_src)) {
	    		$this->error('参数错误');
	    	}
	    	$size = getimagesize($img_src);
			$param['height'] = $size[1];
			$param['width'] = $size[0];
	    	$this->assign('param',$param);
	        return $this->fetch('open/pic_cut');
    	}
    }


	/**
     * 会员锁定
     */
	public function lock(){
		$member_id = input("get.id",0,"intval");
		if(!empty($member_id)){
			$result = $this->member_model->save(['member_state'=>0],['member_id'=>$member_id]);
	        if ($result !== false) {
	        	model('member_token')->where(['member_id'=>$member_id])->delete();
	    		$this->success("会员锁定成功",url("member/index"));
	    	}else{
	    		$this->error("锁定失败！");
	    	}
		}else{
			$this->error("传入数据有误!");
		}
	}

	/**
     * 会员解锁
     */
	public function unlock(){
		$member_id = input("get.id",0,"intval");
		if(!empty($member_id)){
			$result = $this->member_model->save(array('member_state'=>1),array('member_id'=>$member_id));
	        if ($result !== false) {
	    		$this->success("会员锁定成功",url("member/index"));
	    	}else{
	    		$this->error("锁定失败！");
	    	}
    	}else{
    		$this->error("传入数据有误!");
    	}
	}

	/**
     * 会员详情
     */
	public function member_detail(){
	    $id = input('get.id','','int');
	    if(!$id){
	        $this->error('参数传入错误');
        }
	    $member = model('Member')->where(['member_id'=>$id])->find()->toArray();
	    $member_info = model('MemberInfo')->where(['member_id'=>$id])->find();
	    if($member_info){
            $member_info = $member_info->toArray();
        }
        if(is_array($member) && is_array($member_info)){
            $member = array_merge($member,$member_info);
        }
        $profession = ['1'=>'国家公务员','2'=>'专业技术人员','3'=>'职员','4'=>'企业管理人员','5'=>'工人','6'=>'农民','7'=>'学生','8'=>'现役军人','9'=>'自由职业者','10'=>'个体经营者','11'=>'退(离)休人员'];
        $medical_type = ['1'=>'城镇职工医保','2'=>'新农合医保','3'=>'其他'];
        $grade = model('Grade')->where(['grade_state'=>1])->select();

        foreach($grade as $k=>$value){
            if($member['member_grade'] == $grade[$k]['grade']){
                $member['grade_name'] = $grade[$k]['grade_name'];
            }
        }

        $this->assign('profession',$profession);
        $this->assign('medical_type',$medical_type);
        $this->assign('member',$member);
        return $this->fetch();
    }

    /**
     * 会员上下级关系
     */
    public function member_relation(){
        $id = input("post.id");
        //获取销售员信息
        $self = model('Member')
            ->where(['member_id'=>$id])
            ->field('member_mobile as contact,member_name as name')
            ->find()->toArray();
        $seller_role = model('StoreSeller')->where(['member_id'=>$id])->value('seller_role');
        switch($seller_role){
            case 1: $self['name'] = $self['name'].'(消费商)';break;
            case 2: $self['name'] = $self['name'].'(会员兼库管员)';break;
            case 3: $self['name'] = $self['name'].'(消费商兼库管员)';break;
            default: $self['name'] = $self['name'].'(普通会员)';break;
        }
        //获取上级
        $parent_id = model('Member')->where(['member_id'=>$id])->value('parent_id');
        $parent = model('Member')
            ->where(['member_id'=>$parent_id])
            ->field('member_mobile as contact,member_name as name')
            ->find();
        //获取下级关系
        $lower = $this->get_data($id);
        $ids = [];
        foreach($lower as $k=>$v){
            $ids[] = $lower[$k]['member_id'];
        }
        $store_seller = model('StoreSeller')
            ->where(['member_id'=>['in',$ids]])
            ->field('member_id,seller_role')
            ->select()->toArray();
        $id = [];
        foreach($store_seller as $key=>$val){
            $id[] = $val['member_id'];
        }
        if($store_seller){
            foreach($lower as $k=>$v){
                foreach($store_seller as $key=>$val){
                    if(in_array($v['member_id'],$id)){
                        if($v['member_id'] == $val['member_id']){
                            switch($val['seller_role']){
                                case 1: $lower[$k]['name'] = $lower[$k]['name'].'(消费商)';break;
                                case 2: $lower[$k]['name'] = $lower[$k]['name'].'(会员兼库管员)';break;
                                case 3: $lower[$k]['name'] = $lower[$k]['name'].'(消费商兼库管员)';break;
                            }
                        }
                    }else{
                        $lower[$k]['name'] = $lower[$k]['name'].'(普通会员)';
                    }
                    unset($lower[$k]['member_id']);
                }
            }
        }else{
            foreach($lower as $k=>$v){
                $lower[$k]['name'] = $lower[$k]['name'].'(普通会员)';
                unset($lower[$k]['member_id']);
            }
        }

        $seller = [];
        foreach($lower as $k=>$v){
            $seller[]['text'] = $lower[$k];
        }

        if($parent){
            $parent = $parent->toArray();
            $seller_role = model('StoreSeller')->where(['member_id'=>$parent_id])->value('seller_role');
            switch($seller_role){
                case 1: $parent['name'] = $parent['name'].'(消费商)';break;
                case 2: $parent['name'] = $parent['name'].'(会员兼库管员)';break;
                case 3: $parent['name'] = $parent['name'].'(消费商兼库管员)';break;
                default: $parent['name'] = $parent['name'].'(普通会员)';break;
            }
            $data = [
                'text'     => $parent,
                'children' => [
                    [
                        'text'     => $self,
                        'children' => $seller
                    ]
                ]
            ];
        }else{
            $data = [
                'text'     => $self,
                'children' => $seller
            ];
        }
        ajax_return(json_encode($data));
    }

    public function get_data($myid){
        $member = model('Member')->where(['parent_id'=>$myid])
            ->field('member_id,member_name as name,member_mobile as contact')->select();
        if(!$member){
            $member = [];
        }else{
            $member = $member->toArray();
        }
        return $member;
    }

    /**
     * 实名认证
     */
    public function auth(){
        $where = $query = [];
        $member_mobile = trim(input('param.member_mobile'));
        if(isset($member_mobile) && !empty($member_mobile)){
            if(!is_mobile($member_mobile)){
                $this->error('会员账号不符合要求');
            }
            $member_id = $this->member_model->where(['member_mobile'=>$member_mobile])->value('member_id');
            $where['member_id'] = $member_id;
        }
        $auth_state = input('param.auth_state');
        if(isset($auth_state) && strlen($auth_state) > 0){
            $where['auth_state'] = $auth_state;
            $query['auth_state'] = $auth_state;
        }

        $count = $this->member_auth->where($where)->count();
        $member_auth = $this->member_auth->where($where)->order('ma_id desc')->paginate(15,$count,['query'=>$query]);
        $ids = [];
        foreach($member_auth as $val){
            $ids[] = $val['member_id'];
        }
        $member = $this->member_model->where(['member_id'=>['in',$ids]])->select();
        foreach($member_auth as $k=>$v){
            foreach($member as $key=>$val){
                if($member_auth[$k]['member_id'] == $member[$key]['member_id']){
                    $member_auth[$k]['member_name'] = $member[$key]['member_name'];
                    $member_auth[$k]['member_mobile'] = $member[$key]['member_mobile'];
                    $member_auth[$k]['is_auth'] = $member[$key]['is_auth'];
                }
            }
        }
        $page = $member_auth->render();
        $this->assign('member_auth',$member_auth);
        $this->assign('page',$page);
        return $this->fetch();
    }
    /**
     * 查看详情
     */
    public function auth_detail(){
        $id = input('get.id');
        $member_auth = $this->member_auth->where(['ma_id'=>$id])->find();
        $member = $this->member_model->where(['member_id'=>$member_auth['member_id']])->find();
        $member_auth['member_mobile'] = $member['member_mobile'];
        $member_auth['member_name'] = $member['member_name'];
        $this->assign('member_auth',$member_auth);
        return $this->fetch();
    }

    /**
     * 审核通过实名认证
     */
    public function examine_auth(){
        $ma_id = input('param.ma_id');
        $auth_state = input('param.auth_state');
        $member_id = $this->member_auth->where(['ma_id'=>$ma_id])->value('member_id');
        if(!$member_id){
            $this->error('该会员不存在');
        }
        if($auth_state == 2){
            $reason = input('param.reason','','trim');
            if(!isset($reason) && empty($reason)){
                $this->error('请填写拒绝理由');
            }
        }
        $reason = isset($reason) ? $reason : '';
        $data = [
            'auth_state' =>   $auth_state,
            'reason'     =>   $reason
        ];
        $message = [
            'from_member_id' => 0,
            'to_member_id'   => $member_id,
            'message_title'  => '实名认证通知',
            'message_time'   => time(),
            'message_state'  => 0,
            'message_type'   => 6,
            'is_more'        => 0,
            'is_push'        => 2
        ];
        $this->member_auth->startTrans();
        $this->member_model->startTrans();
        model('Message')->startTrans();
        $result = $this->member_auth->where(['ma_id'=>$ma_id])->update($data);
        if($result === false){
            $this->error('审核失败');
        }
        if($auth_state == 2){
            $message['message_body'] = "您的实名认证申请未通过，请重新认证。\n原因：$reason";
            $message['push_detail'] = "您的实名认证申请未通过，请重新认证。\n原因：$reason";
            $message['push_title']  = '实名认证未通过';
            $message_data = model('Message')->save($message);
            if($message_data === false){
                $this->member_auth->rollback();
                $this->error('审核失败');
            }
        }else{
            $res = $this->member_model->where(['member_id'=>$member_id])->update(['is_auth'=>1]);
            if($res === false){
                $this->member_auth->rollback();
                $this->error('审核失败');
            }
            $message['message_body'] = '您的实名认证申请已经通过。';
            $message['push_detail'] = '您的实名认证申请已经通过。';
            $message['push_title'] = '';
            $message_data = model('Message')->save($message);
            if($message_data === false){
                $this->member_auth->rollback();
                $this->member_model->rollback();
                $this->error('审核失败');
            }
        }
        $message['push_member_id'] = $member_id;
        $member_mobile = model('Member')->where(['member_id'=>$member_id])->value('member_mobile');
        $client = new Client($this->appKey,$this->masterSecret);
        $push = $client->push();
        $cid = $push->getCid();
        $cid = $cid['body']['cidlist'][0];
        try{
            $res = $push->setCid($cid)
                ->options(['apns_production'=>true])
                ->setPlatform(['android','ios'])
                ->addAlias((string)$member_mobile)
                ->iosNotification($message['push_detail'],['title'=>$message['push_title']])
                ->androidNotification($message['push_detail'],['title'=>$message['push_title']])
                ->send();
        }catch (JPushException $e){
            $this->member_auth->commit();
            $this->member_model->commit();
            model('Message')->commit();
            $this->success('审核通过',url('member/auth'));
        }
        if(isset($res) && !empty($res)){
            if($res['http_code'] !== 200){
                $this->error('消息推送失败');
            }
        }
        $this->member_auth->commit();
        $this->member_model->commit();
        model('Message')->commit();
        $this->success('审核通过',url('member/auth'));
    }
    /**
     * debug
     * 查看详情测试（银联代付实名认证用）
     * 记得删除
     */
    public function auth_detail_test(){
        $id = input('get.id');
        $member_auth = $this->member_auth->where(['ma_id'=>$id])->find();
        $member = $this->member_model->where(['member_id'=>$member_auth['member_id']])->find();
        $member_auth['member_mobile'] = $member['member_mobile'];
        $member_auth['member_name'] = $member['member_name'];
        $this->assign('member_auth',$member_auth);
        return $this->fetch();
    }
    /**
     * 实名认证检测（银联代付实名认证）
     * 需要新加字段phone_no，acc_no， card_cvn2， expired
     */
    public function examine_union_auth(){
        $ma_id = input('param.ma_id');
        $member_auth_info = $this->member_auth->where(['ma_id'=>$ma_id])->find();
        if(empty($member_auth_info)){
            $this->error('没有该认证请求记录');
        }
        $member_auth_info['auth_name'] = input('param.auth_name');
        $member_auth_info['id_card'] = input('param.id_card');
        //以下是数据库需要新加的字段（644-649）
        $member_auth_info['phone_no']=input('param.phone_no');
        $member_auth_info['acc_no']=input('param.acc_no');
        $member_auth_info['card_cvn2'] = input('param.card_cvn2');
        $member_auth_info['expired'] = input('param.expired');

        $member_id = $member_auth_info['member_id'];
        if(!$member_id){
            $this->error('该会员不存在');
        }
        $message = [
            'from_member_id' => 0,
            'to_member_id'   => $member_id,
            'message_title'  => '实名认证通知',
            'message_time'   => time(),
            'message_state'  => 0,
            'message_type'   => 6,
            'is_more'        => 0,
            'is_push'        => 2,
            'push_member_id' => $member_id,
        ];
        $union_res = $this->member_auth->unipay_back_auth($member_auth_info['phone_no'], $member_auth_info['id_card'], $member_auth_info['auth_name'], $member_auth_info['acc_no'], $member_auth_info['card_cvn2'], $member_auth_info['expired']);
        $member_mobile = model('Member')->where(['member_id'=>$member_id])->value('member_mobile');
        $this->member_auth->startTrans();
        $this->message->startTrans();
        if($union_res['state'] !== 'success'){
            $data = [
                'auth_state' =>   MemberAuth::AUTH_STATE_FAIL,
                'reason'     =>   $union_res['msg']
            ];
            $result = $this->member_auth->where(['ma_id'=>$ma_id])->update($data);
            if($result === false){
                $this->error('审核状态修改失败');
            }
            $push_detail = "您的实名认证申请未通过，请重新认证。\n原因：".$union_res['msg'];
            $title = '实名认证未通过';
            $message['message_body'] = $push_detail;
            $message['push_detail'] = $push_detail;
            $message['push_title']  = $title;
            if(!$this->message->send_message($member_mobile,$push_detail,$title,$message)){
                $this->member_auth->rollback();
                $this->message->rollback();
                $this->error('消息推送失败');
            }
            $this->member_auth->commit();
            $this->message->commit();
            $this->error('审核不通过，原因：'.$union_res['msg']);
        }
        $this->member_model->startTrans();
        $data = [
            'auth_state' =>   MemberAuth::AUTH_STATE_SUCCESS,
            'reason'     =>   ''
        ];
        $res = $this->member_model->where(['member_id'=>$member_id])->update(['is_auth'=>1]);
        if($res === false){
            $this->error('审核失败');
        }
        $result = $this->member_auth->where(['ma_id'=>$ma_id])->update($data);
        if($result === false){
            $this->member_model->rollback();
            $this->error('审核状态修改失败');
        }
        $push_detail = '您的实名认证申请已经通过。';
        $title = '实名认证通过';
        $message['message_body'] = $push_detail;
        $message['push_detail'] = $title;
        $message['push_title']  = $title;
        if(!$this->message->send_message($member_mobile,$push_detail,$title,$message)){
            $this->member_auth->rollback();
            $this->member_model->rollback();
            $this->message->rollback();
            $this->error('消息推送失败');
        }
        $this->member_auth->commit();
        $this->member_model->commit();
        $this->message->commit();
        $this->success('审核通过');
    }
    /**
     * 会员检测报告
     */
    public function report(){
        $member_id = input('get.id');
        if(empty($member_id)){
            $this->error('参数传入错误');
        }
        $query = ['id'=>$member_id];
        $count = model('MemberReport')->where(['member_id'=>$member_id])->count();
        $report = model('MemberReport')
            ->where(['member_id'=>$member_id])
            ->order('create_time desc')
            ->paginate(15,$count,['query'=>$query]);
        $page = $report->render();
        $this->assign('report',$report);
        $this->assign('page',$page);
        return $this->fetch();
    }


    /**
     * 删除检测报告
     */
    public function del_report(){
        $report_id = input('get.id');
        if(empty($report_id)){
            $this->error('参数传入错误');
        }
        $file_path = model('MemberReport')->where(['report_id'=>$report_id])->value('file_path');
        $auth = new Auth($this->accessKey, $this->secretKey);
        $bucket = 'monitoring-report';
        $delMgr = new BucketManager($auth);
        //删除七牛云服务器的文件
        $res = $delMgr->delete($bucket,$file_path);
        if(!empty($res)){
            $this->error('删除失败');
        }
        //删除数据库中数据
        $result = model('MemberReport')->where(['report_id'=>$report_id])->delete();
        if($result === false){
            $this->error('删除失败');
        }
        $this->success('删除成功');
    }

    /**
     * 会员登录次数统计
     */
    public function login_num(){
        $member = model('Member')->select();
        $login_num = [];
        foreach($member as $k=>$v){
            $res = array_key_exists($v['login_num'], $login_num);
            if(!$res){
                $login_num[$v['login_num']] = 1;
            }else{
                $login_num[$v['login_num']]++;
            }
        }
        ksort($login_num);
        $this->assign('login_num',$login_num);
        return $this->fetch();
    }

}