<?php
namespace app\api\controller;
use Lib\Sms;
use think\Cache;
use Lib\Http;
use Lib\Watch;
use think\Db;
class Family extends Apibase
{
    //机器人服务器地址
    public $code_url = 'http://robot.healthywo.cn';
    //SN码获取
    public $get_code = '/api/robot/get_code';
    protected $family_model,$token_model,$affix_model,
        $family_member_model,$member_info_model;
	protected function _initialize(){
    	parent::_initialize();
    	$this->family_model = model('Family');
        $this->token_model = model('MemberToken');
        $this->affix_model = model('FamilyAffix');
        $this->family_member_model = model('FamilyMember');
        $this->member_info_model = model('FamilyMemberInfo');
    }

	/**
    * 创建家庭
    */
    public function add_family(){
    	//家庭数据组合
        $family_name = input('post.family_name','','trim');

        //家庭名称字数不超过4个字
		$content_len = strlen($family_name);
		$i = $count = 0;
		while($i < $content_len){
			$chr = ord($family_name[$i]);
			$count++;$i++;
			if($i >= $content_len){
				break;
			}
			if ($chr & 0x80){
				$chr <<= 1;
				while ($chr & 0x80){
					$i++;
               		$chr <<= 1;
				}
			}
		}

		if($count < 2){
			$this->ajax_return('11000','at least two words');
		}
        if (is_badword($family_name) || $count > 10 ) {
            $this->ajax_return('11001','invalid family_name');
        }
        //绑定机器人
        $robot_imei = input('post.robot_imei','','trim');
        if (!empty($robot_imei)) {
            if (is_md5($robot_imei)) {
                $result = Http::ihttp_get($this->code_url . $this->get_code . '?bind_code=' . $robot_imei);
                //网络请求状态
                if (empty($result) || $result['code'] != 200) {
                    $this->ajax_return('11163','invalid robot_imei');
                }

                //程序返回状态
                $content = json_decode($result['content'],true);
                if ($content['code'] != 200) {
                    $this->ajax_return('11163','invalid robot_imei');
                }
                $robot_imei = $content['result']['robot_sn'];
            }

            if (!preg_match("/^[A-Z\d]{18}-[A-Z\d]{4}$/",$robot_imei) && !is_robotsn($robot_imei)) {
                $this->ajax_return('11163','invalid robot_imei');
            }

            $has_robot = $this->family_model->where(['robot_imei' => $robot_imei])->count();
            if ($has_robot > 0) {
                $this->ajax_return('11003','have been binded');
            }
        }

        //封面编号 check_type大于0时忽略上传文件，check_type为空时必须上传图片
        $check_type = input('post.check_type',0,'intval');

        $save_name = array();
        if (empty($check_type)) {
            //文件上传
			$file = request()->file('photo');
        	$save_path = 'public/uploads/family';
        	$info = $file->rule('uniqid')->validate(['size'=>3145728,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.$save_path.'/',true,false);
		    $save_name = ROOT_PATH.$save_path.'/'.$info->getFilename();
        	if(!$info){
        		$this->ajax_return('11004',$file->getError());
		    }
            $bucket = config('qiniu.buckets')['images']['name'];
            $key = 'uploads/family/' . $info->getSaveName();
        	$path = './' . $key;
            add_file_to_qiniu($key,$path,$bucket);
        }

        $member_id = $this->member_info['member_id'];
        $message_model = model('Message');
        $this->family_model->startTrans();
        $this->affix_model->startTrans();
        $this->family_member_model->startTrans();
        $message_model->startTrans();

        $family_data = [
            'member_id' =>$member_id,
            'family_name' => $family_name,
            'member_count' => 1,
            'robot_imei' => $robot_imei,
            'add_time' => time(),
            'has_affix' => 1
        ];
        $result = $this->family_model->insertGetId($family_data);
        if (!$result) {
            $this->ajax_return('11005','failed to save data');
        }
        //家庭成员关系表
        $member_result = $this->family_member_model->save(['member_id' => $member_id,'family_id' => $result,'is_member' => 1,'create_time' => time()]);

        if ($member_result === false) {
            $this->family_model->rollback();
            $this->ajax_return('11005','failed to save data');
        }

        //发送邀请成员的消息
        $member_ids = input('post.member_ids','','trim');
        $member_ids_arr = explode('|',$member_ids);
        $member_ids_str = implode(',',$member_ids_arr);
        $member_list = model('member')->where(array('member_id' => array('in',$member_ids_str)))->select();

        //提取真实ID进行消息发送
        if (!empty($member_list)) {
            $message_data = array();
            $body_json = json_encode(array('member_name' => $this->member_info['member_name'],'member_avatar' => $this->member_info['member_avatar'],'family_name' => $family_name,'family_id' => $result,'is_invite'=>1));
            foreach ($member_list as $key => $value) {

                //过滤邀请本人加入的记录
                if ($value['member_id'] != $member_id) {
                    $tmp = array();
                    $tmp['from_member_id'] = $member_id;
                    $tmp['to_member_id'] = $value['member_id'];
                    $tmp['message_title'] = '会员邀请消息';
                    $tmp['message_time'] = time();
                    $tmp['message_type'] = 2;
                    $tmp['message_body'] = $body_json;
                    $message_data[] = $tmp;
                }
            }

            if (!empty($message_data)) {
                $message_result = $message_model->saveAll($message_data);
                if ($message_result === false) {
                    $this->family_model->rollback();
                    $this->family_member_model->rollback();
                   $this->ajax_return('11005','failed to save data');
                }
            }
        }

        //图片上传数据组合
        $update_affix = array();

        if (!empty($info)) {
            $index = 0;
        	$image = \think\Image::open($save_name);
        	$tmp = array();
            $tmp['file_path'] = basename($save_name);
            $tmp['file_size'] = $image->size()[0];
            $tmp['upload_time'] = time();
            $tmp['upload_ip'] = get_client_ip();
            $tmp['family_id'] = $result;
            $tmp['is_image'] = 1;
            $tmp['is_use'] = $index < 1 ? 1 : 0;
            $update_affix[] = $tmp;
            $index++;
        }else{
         //添加默认图组
            $tmp = array();
            $tmp['file_path'] = $check_type > 0 ? $check_type : 1;
            $tmp['file_size'] = 0;
            $tmp['upload_time'] = time();
            $tmp['upload_ip'] = get_client_ip();
            $tmp['family_id'] = $result;
            $tmp['is_image'] = 0;
            $tmp['is_use'] = $check_type > 0 ? 1 : 0;
            $update_affix[] = $tmp;
        }
        
        $affix_result = $this->affix_model->saveAll($update_affix);
        if ($affix_result === false) {
            $this->family_model->rollback();
            $this->family_member_model->rollback();
            $message_model->rollback();
            //循环删除上传文件
            foreach($update_affix as $key => $val){
                unlink(THINKPHP_ROOT.$val['file_path']);
            }

            $this->ajax_return('11005','failed to save data');
        }

        $this->family_model->commit();
        $this->family_member_model->commit();
        $message_model->commit();
        $this->affix_model->commit();
        $data = array();
        $data = ['family_id' => $result];
        $this->ajax_return('200','success',$data);
    }

    /**
    *  获取家庭列表(已加入家庭列表)
    */
    public function family_list(){
        //获取会员的家庭关系
        $member_id = $this->member_info['member_id'];
        $family_id_arr = $this->family_member_model->where(['member_id' => $member_id])->field('family_id,id')->select();
        if(empty($family_id_arr->toArray())){
        	$this->ajax_return('11011','invalid family_id');
        }

        if (!empty($family_id_arr->toArray())) {
        	$family_id_arr = $family_id_arr->toArray();
        	foreach($family_id_arr as $k=>$v){
        		$family_id_arr[$k] = $v['family_id'];
        	}
            $family_id_str = implode(',',$family_id_arr);
            $family_list = array();
            //获取家庭列表
            $family_list = $this->family_model->where(array('family_id' => array('in',$family_id_str)))->field('family_id,family_name,member_count,robot_imei')->select()->toArray();
            if (empty($family_list)) {
                $this->ajax_return('11010','empty data');
            }

            $affix_list = $this->affix_model->where(array('family_id' => array('in',$family_id_str),'is_use' => 1))->field('family_id,file_path,is_image')->select();

            $family_id_arr = array();
            foreach ($family_list as $k => $v) {
                $family_id_arr[$k] = $v['family_id'];
            }
            $family_member_id = $this->family_member_model->where(['member_id'=>$member_id,'family_id'=>['in',$family_id_arr],'is_member'=>1])->field('family_id,id')->select();

            foreach ($family_list as $key => $value) {
                foreach ($affix_list as $affix_key => $affix_val) {
                    if ($value['family_id'] == $affix_val['family_id']) {
                        $family_list[$key]['thumb'] = !in_array($affix_val['file_path'],['1','2','3']) ?  config('qiniu.buckets')['images']['domain'] . '/uploads/family/'.$affix_val['file_path'] : $affix_val['file_path'];
                        $family_list[$key]['is_image'] = $affix_val['is_image'];
                        $family_list[$key]['robot_imei'] = $value['robot_imei'];
                        break;
                    }
                }

                //返回当前用户所在家庭的成员id
                foreach ($family_member_id as $member_key => $member_val) {
                    if ($value['family_id'] == $member_val['family_id']) {
                        $family_list[$key]['family_member_id'] = $member_val['id'];
                    }
                }
            }
        }

        if(empty($family_list)){
        	 $this->ajax_return('11010','empty data');
        }
        $this->ajax_return('200','success',$family_list);
    }

    /**
    * 邀请会员加入家庭
    */
    public function invite_member(){
        $family_id = input('post.family_id',0,'intval');
        if (empty($family_id)) {
            $this->ajax_return('11020','illegal handle');
        }

        $family_info = $this->family_model->where(['family_id' => $family_id,'state' => 1])->find();
        if (empty($family_info)) {
            $this->ajax_return('11021','dose not exit or other error');
        }

        if ($family_info['member_id'] != $this->member_info['member_id']) {
            $this->ajax_return('11022','is not a creater');
        }

        $invite_id = input('post.invite_id','','trim');
        if (empty($invite_id)) {
            $this->ajax_return('11020','illegal handle');
        }

        if ($family_info['member_id'] == $invite_id) {
            $this->ajax_return('11020','illegal handle');
        }
        //判断会员是否存在
        $has_member = model('member')->where(['member_id' => $invite_id])->count();
        if ($has_member < 1) {
            $this->ajax_return('11023','dose not exit');
        }
        //判断会员是否已加入当前家庭
        $has_row = $this->family_member_model->where(['family_id' => $family_id,'member_id' => $invite_id])->count();
        if ($has_row > 0) {
            $this->ajax_return('11024','have been in family');
        }

        $message_model = model('message');
        $member_id = $this->member_info['member_id'];
        $message_row = $message_model
            ->where(['from_member_id' => $member_id,
                'to_member_id' => $invite_id,
                'message_type' => 2])
            ->select();
        $num = 0;
        foreach ($message_row as $k=>$v){
            if(is_null($v['read_member_id']) and is_null($v['del_member_id'])){
                $num++;
            }
        }
        if ($num > 0) {
            $this->ajax_return('11025','have been send');
        }

        $can_row = $message_model
            ->where(['from_member_id' => $this->member_info['member_id'],
                'to_member_id' => $invite_id,
                'message_type' => 2,
                'read_member_id'=>['eq',$invite_id]
            ])->field('message_id,message_body,read_member_id,del_member_id')->select();
        foreach ($can_row as $key => $val){
            if(!is_null($val['read_member_id']) && is_null($val['del_member_id'])){
                $message_body = json_decode($val['message_body'],true);
                if($message_body['is_join'] == 1){
                    $res = model('Message')
                        ->where(['message_id'=>$val['message_id']])
                        ->update(['del_member_id'=>$invite_id]);
                    if($res === false){
                        $this->ajax_return('10103','failed to delete data');
                    }
                }
            }
        }
        $count = $message_model->where(['from_member_id' => $this->member_info['member_id'],
            'to_member_id' => $invite_id,
            'message_type' => 2,
            'read_member_id'=>['eq',$invite_id]
        ])->field('del_member_id')->select();
        $i = 0;
        foreach ($count as $k=>$v){
            if(is_null($v['del_member_id'])){
                $i++;
            }
        }
        if ($i > 2) {
            $this->ajax_return('11026','times out of limit');
        }
        $body_json = json_encode(array('member_name' => $this->member_info['member_name'],'member_avatar' => $this->member_info['member_avatar'],'messge_type'=>2,
          'family_name' => $family_info['family_name'],'family_id' => $family_id,'is_invite'=>1,'is_join'=>0));
        $data = [
            'from_member_id' => $this->member_info['member_id'],
            'to_member_id' => $invite_id,
            'message_title' => '家庭邀请消息',
            'message_body' => $body_json,
            'message_time' => time(),
            'message_type' => 2,
            'is_push'      => 2,
            'push_detail'  => $this->member_info['member_name'].'邀请您加入'.$family_info['family_name'].'家庭',
            'push_title'   => '家庭邀请'
        ];
        $message_result = $message_model->save($data);

        if ($message_result === false) {
            $this->ajax_return('11027','failed to invite');
        }

        $this->ajax_return('200','success','');
    }


     /**
    * 申请加入家庭
    */
    public function apply_family(){
        $family_id = input('post.family_id',0,'intval');
        if (empty($family_id)) {
            $this->ajax_return('11030','invalid family_id');
        }

        $family_info = $this->family_model->where(array('family_id' => $family_id,'state' => 1))->find();
        if (empty($family_info)) {
            $this->ajax_return('11031','dose not exit or other error');
        }

        $member_id = $this->member_info['member_id'];
        if ($family_info['member_id'] == $member_id) {
            $this->ajax_return('11032','illegal handle');
        }

        $has_row = $this->family_member_model->where(array('family_id' => $family_id,'member_id' => $member_id))->count();
        if ($has_row > 0) {
            $this->ajax_return('11033','have been in family');
        }

        $message_model = model('Message');
        //已发送消息但是未读
        $message_row = model('Message')->where(
            ['from_member_id' => $member_id,
                'to_member_id' => $family_info['member_id'],
                'message_type' => 2
            ])->field('read_member_id,del_member_id')->select();
        $num = 0;
        foreach ($message_row as $k=>$v){
            if(is_null($v['read_member_id']) and is_null($v['del_member_id'])){
                $num++;
            }
        }
        if ($num > 0) {
            $this->ajax_return('11034','have been send');
        }

        $can_row = $message_model->where(
            ['from_member_id' => $member_id,
                'to_member_id' => $family_info['member_id'],
                'message_type' => 2,
                'read_member_id'=>['eq',$family_info['member_id']]
            ])->field('message_id,message_body,read_member_id,del_member_id')->select();
        foreach ($can_row as $key => $val){
            if(!is_null($val['read_member_id']) && is_null($val['del_member_id'])){
                $message_body = json_decode($val['message_body'],true);
                if($message_body['is_join'] == 1){
                    $res = model('Message')
                        ->where(['message_id'=>$val['message_id']])
                        ->update(['del_member_id'=>$family_info['member_id']]);
                    if($res === false){
                        $this->ajax_return('10103','failed to delete data');
                    }
                }
            }
        }
        $count = $message_model->where(
            ['from_member_id' => $member_id,
                'to_member_id' => $family_info['member_id'],
                'message_type' => 2,
                'read_member_id'=>['eq',$family_info['member_id']],
            ])->field('del_member_id')->select();
        $i = 0;
        foreach ($count as $k=>$v){
            if(is_null($v['del_member_id'])){
                $i++;
            }
        }
        if ($i > 2) {
            $this->ajax_return('11035','times out of limit');
        }
        $body_json = json_encode([
            'member_name' => $this->member_info['member_name'],
            'member_avatar' => $this->member_info['member_avatar'],
            'messge_type'=>2,
            'family_name' => $family_info['family_name'],
            'family_id' => $family_id,
            'is_invite'=>2,
            'is_join'=>0
        ]);

        $insert_data = [
            'from_member_id' => $member_id,
            'to_member_id'   => $family_info['member_id'],
            'message_title'  => '申请加入家庭消息',
            'message_body'   => $body_json,
            'message_time'   => time(),
            'message_type'   => 2,
            'is_push'        => 2,
            'push_detail'    => $this->member_info['member_name'].'申请加入'.$family_info['family_name'].'家庭',
            'push_title'     => '家庭邀请'
        ];

        $message_result = $message_model->save($insert_data);
        if ($message_result === false) {
            $this->ajax_return('11036','failed to save data');
        }

        $this->ajax_return('200','success','');
    }


    /**
    * 搜索会员
    */
    public function search_member(){
        $keyword = input('post.keyword','','trim');
        if (empty($keyword)) {
            $this->ajax_return('11040','invalid  keyword');
        }

        if (is_mobile($keyword)) {
            $search_info = model('member')->where(array('member_mobile' => $keyword))->field('member_id,member_avatar,member_name,member_mobile as mobile')->find();
            if (empty($search_info)) {
                $this->ajax_return('11041','empty data');
            }
            $search_info['member_avatar'] = empty($search_info['member_avatar']) ? "" : config('qiniu.buckets')['images']['domain'] . '/uploads/family/'.$search_info['member_avatar'];
            $this->ajax_return('200','success',$search_info);
        }
        $this->ajax_return('11041','empty data');
    }

    /**
    * 搜索家庭
    */
    public function search_family(){
        $keyword = input('post.keyword','','trim');

        if (empty($keyword)) {
            $this->ajax_return('11050','invalid  keyword');
        }

        $search_info = $this->family_model->where("family_name like '%".$keyword."%'")->field('family_id,member_id,family_name')->select();

        if (empty($search_info)) {
            $this->ajax_return('11051','empty data');
        }

        $id_array = array();
        $family_array = array();

        foreach ($search_info as $key => $value) {
            $id_array[] = $value['member_id'];
            $family_array[] = $value['family_id'];
        }

        $id_str = implode(',',$id_array);
        $own_info = model('member')->where(array('member_id' => array('in',$id_str)))->field('member_id,member_name')->select();
        if (empty($own_info)) {
            $this->ajax_return('11051','empty data');
        }

        $family_id_str = implode(',',$family_array);
        $affix_list = $this->affix_model->where(array('family_id' => array('in',$family_id_str),'is_use' => 1))->order('upload_time asc')->field('family_id,file_path,is_image')->select();

        foreach ($search_info as $key => $value) {
            foreach ($own_info as $keys => $val) {
                if ($val['member_id'] == $value['member_id']) {
                    $search_info[$key]['member_name'] = $val['member_name'];
                    break;
                }
            }
            foreach ($affix_list as $affix_key => $affix_val) {
                if ($value['family_id'] == $affix_val['family_id']) {
                    $search_info[$key]['thumb'] = $affix_val['is_image'] > 0 ? config('qiniu.buckets')['images']['domain'] . '/uploads/family/'.$affix_val['file_path'] : $affix_val['file_path'];
                }
            }
        }

        $this->ajax_return('200','success',$search_info);
    }

    /**
    * 家庭管理获取已创建家庭列表
    */
    public function get_own_famliy(){
    	$member_id = $this->member_info['member_id'];
    	$family_list = array();
        $family_list = $this->family_model->where(array('member_id' => $member_id))->field('family_id,family_id,family_name,member_count')->order('add_time asc')->select()->toArray();
        if (empty($family_list)) {
            $this->ajax_return('11060','empty data');
        }

        $family_id_arr = array();
        foreach ($family_list as $key => $value) {
            $family_list[$key]['family_number'] = str_pad($value['family_id'],6,"0",STR_PAD_LEFT);
            $family_id_arr[] = $value['family_id'];
        }

        $family_id_str = implode(',',$family_id_arr);
        $affix_list = $this->affix_model->where(array('family_id' => array('in',$family_id_str),'is_use' => 1))->order('upload_time asc')->field('family_id,file_path,is_image')->select();

        foreach ($family_list as $key => $value) {
            foreach ($affix_list as $affix_key => $affix_val) {
                if ($value['family_id'] == $affix_val['family_id']) {
                    $family_list[$key]['thumb'] = $affix_val['is_image'] > 0 ? config('qiniu.buckets')['images']['domain'] . '/uploads/family/'.$affix_val['file_path'] : $affix_val['file_path'];
                    $family_list[$key]['is_image'] = $affix_val['is_image'];
                    break;
                }
            }
        }

        if(empty($family_list)){
        	$this->ajax_return('11060','empty data');
        }
        $this->ajax_return('200','success',$family_list);
    }


    /**
    * 家庭管理
    */
    public function family_mange(){
        $family_id = input('post.family_id',0,'intval');

        if (empty($family_id)) {
            $this->ajax_return('11070','invalid family_id');
        }

        $family_info = $this->family_model->where(['family_id' => $family_id,'state' => 1])->find();
        if (empty($family_info)) {
            $this->ajax_return('11071','family state wrong');
        }

        $member_id = $this->member_info['member_id'];
        if ($member_id != $family_info['member_id']) {
            $this->ajax_return('11072','is not family owner');
        }

        $member_row = $this->family_member_model->where(['family_id' => $family_id])->field('id,info_id,member_id,family_id,is_member')->order('create_time asc')->select();

        $true_member_ids = $member_ids = array();

        //分拣真实会员和虚拟会员
        foreach ($member_row as $key => $value) {

            if ($value['is_member'] == 1) {

                $true_member_ids[] = $value['member_id'];
            }else{

                $member_ids[] = $value['info_id'];
            }
        }

        $true_member_list = $member_list = array();

        if (!empty($true_member_ids)) {

            $true_member_ids = implode(',',$true_member_ids);
            $true_member_list = model('member')->where(array('member_id' => array('IN',$true_member_ids)))->field('member_id,member_name,member_avatar')->select()->toArray();
        }

        if (!empty($member_ids)) {

            $member_ids = implode(',',$member_ids);
            $member_list = $this->member_info_model->where(array('info_id' => array('in',$member_ids)))->field('info_id,nickname,avatar')->select()->toArray();
        }
        // $data_list = array_merge($true_member_list,$member_list);
        foreach ($member_row as $key => $value) {
            $tmp = array();
            $tmp['family_member_id'] = $value['id'];
            $tmp['is_member'] = $value['is_member'];

            if ($value['is_member'] == 1) {
                foreach ($true_member_list as $data_key => $data_value) {
                    if ($value['member_id'] == $data_value['member_id']) {
                        $tmp['member_name'] = $data_value['member_name'];
                        $tmp['member_avatar'] = !empty($data_value['member_avatar']) ? config('qiniu.buckets')['images']['domain'] . '/uploads/avatar/'.$data_value['member_avatar'] : '';
                        break;
                    }
                }

            }else{
                foreach ($member_list as $data_key => $data_value) {

                    if ($value['info_id'] == $data_value['info_id']) {
                        $tmp['member_name'] = $data_value['nickname'];
                        $tmp['member_avatar'] = empty($data_value['avatar']) ? '' : config('qiniu.buckets')['images']['domain'] . '/uploads/avatar/'.$data_value['avatar'];
                        break;
                    }
                }
            }

            $return_data[] = $tmp;
        }

        // $affix_list = $this->affix_model->where(['family_id' => $family_id])->field('affix_id,file_path,is_image,is_use')->select();

        // foreach ($affix_list as $affix_key => $affix_val) {
        // 	if ($affix_val['is_image'] > 0) {
        // 		$affix_list[$affix_key]['file_path'] = empty($affix_val['file_path']) ? '' : $this->base_url.'/uploads/family/'.$affix_val['file_path'];
        // 	}
        // }
        $affix_list = $this->affix_model->where(['family_id' => $family_id])->field('affix_id,file_path,is_image,is_use')->find();
        $affix_data = array();
        $affix_data['file_path'] = !in_array($affix_list['file_path'],['1','2','3']) ? config('qiniu.buckets')['images']['domain'] . '/uploads/family/'.$affix_list['file_path'] : $affix_list['file_path'];
        $affix_data['is_image'] = $affix_list['is_image'];
        $tmp = array();
        $tmp[] = $affix_data;
        $data = [
        	'affix_list' => $tmp,
        	'family_name' => $family_info['family_name'],
        	'robot_imei' => $family_info['robot_imei'],
        	'member_list' => $return_data
        ];

        $this->ajax_return('200','success',$data);
    }


     /**
    * 家庭详情
    */
    public function family_info(){
        $family_id = input('post.family_id',0,'intval');

        if (empty($family_id)) {
            $this->ajax_return('11080','invalid family_id');
        }

        $family_info = $this->family_model->where(array('family_id' => $family_id,'state' => 1))->find();

        if (empty($family_info)) {
            $this->ajax_return('11081','family state wrong');
        }

        $member_id = $this->member_info['member_id'];
        $member_model = model('member');
        $has_count = $this->family_member_model->where(['member_id' => $member_id,'family_id' => $family_id])->count();
        if ($has_count < 1) {
            $this->ajax_return('11082','does not exist');
        }

        $member_list = $this->family_member_model->where(array('family_id' => $family_id))->order('id asc')->select();
        if (empty($member_list)) {
            $this->ajax_return('11083','empty data');
        }

        //分拣真实会员与虚拟会员
        $true_ids = $virtual_ids = array();
        foreach ($member_list as $key => $value) {

            if ($value['is_member'] == 1) {
                $true_ids[] = $value['member_id'];
            }else{
                $virtual_ids[] = $value['info_id'];
            }
        }
      
        $true_ids_str = implode(',',$true_ids);
        $virtual_ids_str = implode(',',$virtual_ids);

        $true_list = model('member_info')->where(array('member_id' => array('in',$true_ids_str)))->field('member_id,member_sex,member_age,member_height,member_weight')->select()->toArray();
        $true_field_list = model('member')->where(array('member_id' => array('in',$true_ids_str)))->field('member_id,member_name,member_avatar')->select()->toArray();

        foreach ($true_list as $key => $value) {
            foreach ($true_field_list as $field_key => $field_val) {
                if ($value['member_id'] == $field_val['member_id']) {
                    $true_list[$key]['member_name'] = $field_val['member_name'];
                    $true_list[$key]['member_avatar'] = $field_val['member_avatar'];
                    break;
                }
            }
        }

        $virtual_list = model('family_member_info')->where(array('info_id' => array('in',$virtual_ids_str)))->field('info_id,nickname,avatar,member_sex,member_age,member_height,member_weight')->select();
        $return_data = array();
        //家庭详细
        $family_field['family_id'] = $family_id;
        $family_field['family_name'] = $family_info['family_name'];
        $family_field['family_number'] = str_pad($family_id,6,"0",STR_PAD_LEFT);
        $family_field['member_count'] = $family_info['member_count'];
        $family_field['robot_imei'] = $family_info['robot_imei'];
        $family_field['is_mange'] = $family_info['member_id'] == $member_id ? '1' : '0';
        $affix = model('family_affix')->where(array('family_id' => $family_id,'is_use' => 1))->field('file_path,is_image')->find();
        $affix['file_path'] = !in_array($affix['file_path'],['1','2','3']) ?  config('qiniu.buckets')['images']['domain'] . '/uploads/family/'.$affix['file_path'] : $affix['file_path'];
        $family_field['affix'] = $affix;
        $return_data['family_info'] = $family_field;

        //组合会员数据
        $member_info = array();
        foreach ($member_list as $key => $value) {
            $tmp = array();
            if ($value['is_member'] == 1) {

                foreach ($true_list as $true_key => $true_val) {
                    if ($true_val['member_id'] == $value['member_id']) {

                        $tmp['member_info']['family_member_id'] = $value['id'];
                        $tmp['member_info']['is_member'] = $value['is_member'];
                        $tmp['member_info']['member_name'] = $true_val['member_name'];
                        $tmp['member_info']['member_avatar'] = empty($true_val['member_avatar']) ? '' :  config('qiniu.buckets')['images']['domain'] . '/uploads/avatar/'.$true_val['member_avatar'];
                        $tmp['health_info']['member_sex'] = $true_val['member_sex'];
                        $tmp['health_info']['member_age'] = $true_val['member_age'];
                        $tmp['health_info']['member_height'] = $true_val['member_height'];
                        $tmp['health_info']['member_weight'] = $true_val['member_weight'];
                        //提升当前会员资料的优先级
                        if ($true_val['member_id'] == $member_id) {
                            $tmp['member_info']['is_mange'] = 1;
                            array_unshift($member_info,$tmp);
                        }else{
                            $tmp['member_info']['is_mange'] = 0;
                            $member_info[] = $tmp;
                        }
                        break;
                    }
                }
            }else{

                foreach ($virtual_list as $virtual_key => $virtual_value) {
                    if ($virtual_value['info_id'] == $value['info_id']) {
                        $tmp['member_info']['family_member_id'] = $value['id'];
                        $tmp['member_info']['is_member'] = $value['is_member'];
                        $tmp['member_info']['member_name'] = $virtual_value['nickname'];
                        $tmp['member_info']['member_avatar'] = empty($virtual_value['avatar']) ? '' :  config('qiniu.buckets')['images']['domain'] . '/uploads/avatar/'.$virtual_value['avatar'];
                        $tmp['member_info']['is_mange'] = 0;
                        $tmp['health_info']['member_sex'] = $virtual_value['member_sex'];
                        $tmp['health_info']['member_age'] = $virtual_value['member_age'];
                        $tmp['health_info']['member_height'] = $virtual_value['member_height'];
                        $tmp['health_info']['member_weight'] = $virtual_value['member_weight'];
                        $member_info[] = $tmp;
                        break;
                    }
                }
            }
        }

        $return_data['member_list'] = $member_info;
        $this->ajax_return('200','success',$return_data);
    }


    /**
    * 添加家庭成员
    */
    public function add_family_member(){

        $family_id = input('post.family_id',0,'intval');
        if (empty($family_id)) {
            $this->ajax_return('11090','invalid family_id');
        }

        $member_id = $this->member_info['member_id'];
        $family_mange = $this->family_model->where(['family_id' => $family_id])->field('member_id')->find();

        if ($family_mange['member_id'] != $member_id) {
            $this->ajax_return('11091','dose not own family');
        }
        //昵称
        $member_name = input('post.member_name','','trim');
        if (empty($member_name) || is_badword($member_name)) {
            //未填写昵称或含有特殊字符
            $this->ajax_return('11092','invalid member_name');
        }
        //手机号
        $member_mobile = input('post.member_mobile','','trim');
        if (!empty($member_mobile) && !is_mobile($member_mobile)) {
            $this->ajax_return('11093','invalid member_mobile');
        }
        //性别
        $member_sex = input('post.member_sex',1,'intval');
        //身高
        $member_height = input('post.member_height','','intval');
        if ($member_height > 0 && $member_height > 300) {
            $this->ajax_return('11094','invalid member_height');
        }
        //年龄
        $member_age = input('post.member_age',0,'intval');

        if (empty($member_age) || $member_age > 200) {
            $this->ajax_return('11095','invalid member_age');
        }
        //年龄
        $member_weight = input('post.member_weight','','intval');
        if ($member_weight > 0 && $member_weight > 300) {
            $this->ajax_return('11096','invalid member_weight');
        }
        //设备
        $watch_imei = input('post.watch_imei','','trim');
        if (!empty($watch_imei) && strlen($watch_imei) < 4) {
            $this->ajax_return('11097','invalid watch_imei');
        }

        $insert_data = array();
        if (!empty($_FILES['avatar']['name'])) {
            //文件上传
            $file = request()->file('avatar');

            $save_path = 'public/uploads/avatar';

        	$info = $file->rule('uniqid')->validate(['size'=>3145728,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.$save_path.'/',true,false);

		    $save_name= ROOT_PATH.$save_path.'/'.$info->getFilename();
        	if(!$info){
        		$this->ajax_return('11098',$file->getError());
		    }

            $bucket = config('qiniu.buckets')['images']['name'];
            $key = 'uploads/avatar/' . $info->getSaveName();
            $path = './' . $key;
            add_file_to_qiniu($key,$path,$bucket);

        	$insert_data['avatar'] = $info->getFilename();
		    $image = \think\Image::open($save_name);

		    $image->thumb(200, 200)->save(ROOT_PATH.$save_path.'/'.$info->getFilename());
            // $image->thumb(200, 200)->save(THINKPHP_ROOT.$root_path.$info['avatar']['savepath'].basename($info['avatar']['savename'],'.'.$info['avatar']['ext']).'_200x200.jpg');

        }

        $insert_data['mobile'] = $member_mobile;
        $insert_data['nickname'] = $member_name;
        $insert_data['member_sex'] = $member_sex;
        $insert_data['member_age'] = $member_age;
        $insert_data['member_height'] = $member_height;
        $insert_data['member_weight'] = $member_weight;
        $insert_data['watch_imei'] = $watch_imei;

        $insert_data = array_filter($insert_data);
        $info_model = model('family_member_info');
        $info_model->startTrans();
        $this->family_member_model->startTrans();

        $result = $info_model->insertGetId($insert_data);
        if ($result === false) {
            $this->ajax_return('11099','failed to save data');
        }
        $member_result = $this->family_member_model->save(['info_id' => $result,'family_id' => $family_id,'create_time' => time()]);

        if ($member_result === false) {
            $info_model->rollback();
            $this->ajax_return('11099','failed to save data');
        }

        $family_result = $this->family_model->where(['family_id' => $family_id])->setInc('member_count');
        if ($family_result == false) {
            $info_model->rollback();
            $this->family_member_model->rollback();
            $this->ajax_return('11099','failed to save data');
        }
        $this->family_model->commit();
        $info_model->commit();
        $this->family_member_model->commit();
        $data = ['member_name' => $member_name];
        $data['avatar'] = !empty($insert_data['avatar']) ?   config('qiniu.buckets')['images']['domain'] . '/uploads/avatar/'.$insert_data['avatar'] : '';

        $this->ajax_return('200','success',$data);
    }

    /**
    * 修改家庭成员
    */
    public function edit_family_member(){
        $family_member_id = input('post.family_member_id',0,'intval');
        if (empty($family_member_id)) {
            $this->ajax_return('11100','invalid family_member_id');
        }

        $family_member_row = $this->family_member_model->where(['id' => $family_member_id])->find();
        if(empty($family_member_row)) {
            $this->ajax_return('11101','illegal handle');
        }

        if ($family_member_row['is_member'] == 1) {
            $this->ajax_return('11102','invited member dose not been edit');
        }
        $member_id = $this->member_info['member_id'];
        $family_mange = $this->family_model->where(array('family_id' => $family_member_row['family_id']))->field('member_id')->find();

        if ($family_mange['member_id'] != $member_id) {
            $this->ajax_return('11103','dose not own family');
        }

        $family_member_info = model('family_member_info')->where(array('info_id' => $family_member_row['info_id']))->find();
        if (empty($family_member_info)) {
            $this->ajax_return('11101','illegal handle');
        }

        $member_name = input('post.member_name','','trim');
        if (empty($member_name) || is_badword($member_name)) {
            $this->ajax_return('11104','invalid member_name');
        }
        $member_sex = input('post.member_sex',1,'intval');
        $member_age = input('post.member_age',0,'intval');

        if (empty($member_age) || $member_age > 200) {
            $this->ajax_return('11105','invalid member_age');
        }

        $member_mobile = input('post.member_mobile','','trim');
        if (!empty($member_mobile) && !is_mobile($member_mobile)) {
            $this->ajax_return('11106','invalid member_mobile');
        }

        $member_height = input('post.member_height','','intval');
        if ($member_height > 0 && $member_height > 300) {
            $this->ajax_return('11107','invalid member_height');
        }

        $member_weight = input('post.member_weight','','intval');
        if ($member_weight > 0 && $member_weight > 300) {
            $this->ajax_return('11108','invalid member_weight');
        }

        $watch_imei = input('post.watch_imei','','trim');
        if (!empty($watch_imei) && strlen($watch_imei) < 4) {
            $this->ajax_return('11109','invalid watch_imei');
        }

        $insert_data = array();
        if (!empty($_FILES['avatar']['name'])) {
            //文件上传
            $file = request()->file('avatar');
            $save_path = 'public/uploads/avatar';
        	$info = $file->rule('uniqid')->validate(['size'=>3145728,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.$save_path.'/',true,false);

		    $save_name= ROOT_PATH.$save_path.'/'.$info->getFilename();
        	if(!$info){
        		$this->ajax_return('11110',$file->getError());
		    }
            $bucket = config('qiniu.buckets')['images']['name'];
            $key = 'uploads/avatar/' . $info->getSaveName();
            $path = './' . $key;
            add_file_to_qiniu($key,$path,$bucket);
            // unlink(ROOT_PATH.$save_path.'/'.$family_member_info['avatar']);
		    $insert_data['avatar'] = $info->getFilename();
            $image = \think\Image::open($save_name);
			$image->thumb(200, 200)->save(ROOT_PATH.$save_path.'/'.$info->getFilename());
			 // $image->thumb(200, 200)->save(THINKPHP_ROOT.$root_path.$info['avatar']['savepath'].basename($info['avatar']['savename'],'.'.$info['avatar']['ext']).'_200x200.jpg');
        }

        $insert_data['mobile'] = $member_mobile;
        $insert_data['nickname'] = $member_name;
        $insert_data['member_sex'] = $member_sex;
        $insert_data['member_age'] = $member_age;
        $insert_data['member_height'] = $member_height;
        $insert_data['member_weight'] = $member_weight;
        $insert_data['watch_imei'] = $watch_imei;
        $insert_data = array_filter($insert_data);
        $result = model('family_member_info')->save($insert_data,['info_id' => $family_member_row['info_id']]);

        if ($result === false) {
            $this->ajax_return('11111','failed to save data');
        }

        $this->ajax_return('200','success','');
    }

    /**
    * 家庭名称编辑
    */
    public function edit_family_info(){
        $family_name = input('post.family_name');
        //家庭名称字数不超过4个字
		$content_len = strlen($family_name);
		$i = $count = 0;
		while($i < $content_len){
			$chr = ord($family_name[$i]);
			$count++;$i++;
			if($i >= $content_len){
				break;
			}
			if ($chr & 0x80){
				$chr <<= 1;
				while ($chr & 0x80){
					$i++;
               		$chr <<= 1;
				}
			}
		}

		if($count < 2){
			$this->ajax_return('11000','at least two words');
		}

        if (is_badword($family_name) || $count > 10 ) {
            $this->ajax_return('11121','invalid family_name');
        }


        $family_id = intval(input('post.family_id',0,'trim'));
        if (empty($family_id)) {
            $this->ajax_return('11122','invalid family_id');
        }
        $member_id = $this->member_info['member_id'];
        $family_info = $this->family_model->where(array('member_id' => $member_id,'family_id' => $family_id))->find();
        if (empty($family_info)) {
            $this->ajax_return('11123','dose not own family');
        }

        if (!empty($family_name)) {
            $result = $this->family_model->save(['family_name' => $family_name],['family_id' => $family_id]);

            if ($result === false) {
                $this->ajax_return('11124','failed to save data');
            }
        }

        if (!empty($_FILES['photo']['name'])) {

        	$file = request()->file('photo');
        	$save_path = 'public/uploads/family';
        	$info = $file->rule('uniqid')->validate(['size'=>3145728,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.$save_path.'/',true,false);

		    $save_name = ROOT_PATH.$save_path.'/'.$info->getFilename();
        	if(!$info){
        		$this->ajax_return('11125',$file->getError());
		    }
            $bucket = config('qiniu.buckets')['images']['name'];
            $key = 'uploads/family/' . $info->getSaveName();
            $path = './' . $key;
            add_file_to_qiniu($key,$path,$bucket);

            // $affix_file = $this->affix_model->where(['family_id'=>$family_id])->value('file_path');
            // unlink(ROOT_PATH.$save_path.'/'.$affix_file);

            $index = 0;
            //图片上传数据组合
            $update_affix = array();
        	$image = \think\Image::open($save_name);
        	$tmp = array();
            $tmp['file_path'] = basename($save_name);
            $tmp['file_size'] = $image->size()[0];
            $tmp['upload_time'] = time();
            $tmp['upload_ip'] = get_client_ip();
            $tmp['is_use'] = 1;
            $tmp['is_image'] = 1;
            $update_affix[] = $tmp;
            //取消原始图片的使用
            // $affix_update_result = $this->affix_model->save($tmp,['family_id' => $family_id]);

            // if ($affix_update_result === false) {
            //     //循环删除上传文件
            //     foreach($update_affix as $key => $val){
            //         unlink(APP_PATH.'/uploads/avatar/'.$val['file_path']);
            //     }
            //     $this->ajax_return('11124','failed to save data');
            // }
            $affix_result = $this->affix_model->save($tmp,['family_id' => $family_id]);
            if ($affix_result === false) {

                //循环删除上传文件
                foreach($update_affix as $key => $val){
                    unlink(APP_PATH.'/uploads/avatar/'.basename($save_name));
                }

               $this->ajax_return('11124','failed to save data');
            }

        }else{
            $affix_id = input('post.affix_id',0,'intval');
            if (!empty($affix_id)) {

                $affix_info = $this->affix_model->where(array('affix_id' => $affix_id,'family_id' => $family_id))->find();
                $check_type = input('post.check_type',0,'intval');

                if (!$affix_info['is_image'] && empty($check_type)) {
                    $this->ajax_return('11126','empty the cover');

                }

                //取消原始图片的使用
                $affix_update_result = $this->affix_model->save(['is_use' => 0],['family_id' => $family_id]);
                if ($affix_update_result === false) {
                    $this->ajax_return('11124','failed to save data');
                }

                $affix_use_data = array();
                if (!$affix_info['is_image']) {
                   $affix_use_data = array('is_use' => 1,'file_path' => $check_type,'is_image'=>0);
                }else{
                    $affix_use_data = array('is_use' => 1);
                }

                $affix_use_result = $this->affix_model->save($affix_use_data,['affix_id' => $affix_id,'family_id' => $family_id]);
                if ($affix_use_result === false) {
                     $this->ajax_return('11124','failed to save data');
                }
            }
        }

        $this->ajax_return('200','success','');
    }

    /**
    * 获取家庭成员详细
    */
    public function get_member_info(){
        $member_id = $this->member_info['member_id'];

        // 获取用户在家庭表中的ID
        $family_member_id = input('post.family_member_id',0,'intval');

        if (empty($family_member_id)) {
            $this->ajax_return('11130','invalid family_member_id');
        }

        // 获取会员相关的家庭信息
        $family_member_info = $this->family_member_model->where(['id' => $family_member_id])->find();

        // 判断是否为邀请会员
        if (empty($family_member_info) || $family_member_info['is_member']) {
            $this->ajax_return('11131','invite members not to edit');
        }

        // 获取所属家庭的详细信息
        $family_info = $this->family_model->where(['family_id' => $family_member_info['family_id'],'state' => 1])->find();

        // 判断家庭是否启用，若未启用判断用户是否为家庭创建者
        if (empty($family_info) || $family_info['member_id'] != $member_id) {
            $this->ajax_return('11132','family not enable or is not family owner');
        }

        // 获取家庭成员详细
        $info = $this->member_info_model->where(['info_id' => $family_member_info['info_id']])->find();

        if (empty($info)) {
            $this->ajax_return('11133','failed to get data');
        }

        unset($info['info_id']);
        $info['family_member_id'] = $family_member_id;
        $info['avatar'] =  !empty($info['avatar']) ? config('qiniu.buckets')['images']['domain'] . '/uploads/avatar/'.$info['avatar']: '';
        $this->ajax_return('200','success',$info);
    }

    /**
    * 移除家庭成员
    */
    public function delete_family_member(){
        // 获取用户id
        $member_id = $this->member_info['member_id'];

        // 获取用户需要移除的成员的家庭ID
        $family_member_id = input('post.family_member_id',0,'intval');
        if (empty($family_member_id)) {
            $this->ajax_return('11140','invalid family_member_id');
        }
        // 获取需要移除的会员相关的家庭信息
        $family_member_row = $this->family_member_model->where(['id' => $family_member_id])->find();

        if (empty($family_member_row)) {
            $this->ajax_return('11141','family_member_id not found in data');
        }
        // 获取家庭拥有者ID
        $family_mange = $this->family_model->where(['family_id' => $family_member_row['family_id']])->value('member_id');

        // 判断当前用户是否为家庭拥有者
        if ($family_mange != $member_id) {
            $this->ajax_return('11142','is not family owner');
        }

        // 判断移除的用户是否为家庭所有者
        if ($family_mange == $family_member_row['member_id']) {
            $this->ajax_return('11143','can not remove the family owner');
        }
        // 移除用户
        $result = $this->family_member_model->where(['id' => $family_member_id])->delete();

        if ($result === false) {
            $this->ajax_return('11144','failed to delete data');
        }else{
            // 移除与用户相关的信息
            $this->member_info_model->where(['info_id' => $family_member_row['info_id']])->delete();
            $this->family_model->where(['family_id' => $family_member_row['family_id']])->setDec('member_count');
            $this->ajax_return('200','success','');
        }
    }


    /**
    * 退出家庭
    */
    public function quit_family(){
        // 获取用户id
        $member_id = $this->member_info['member_id'];

        // 获取需要退出的家庭ID
        $family_id = input('post.family_id',0,'intval');

        if (empty($family_id)) {
            $this->ajax_return('11150','invalid family_id');
        }

        // 查询将退出的家庭拥有者ID和成员数量
        $family_info = $this->family_model->where(['family_id' => $family_id,'state' => 1])->field('member_id,member_count')->find();

        // 退出家庭不存在或未开放
        if (empty($family_info)) {
            $this->ajax_return('11151','family not exist or state wrong');
        }

        // 判断是否为家庭拥有者退出家庭
        if ($family_info['member_id'] == $member_id) {
            // 判断家庭拥有者所退出家庭是否有其他成员
            if($family_info['member_count'] > 1){
                $this->ajax_return('11152','family owner can not quit');
            }

            // 若家庭拥有者所退出的家庭只有拥有者自己，执行退出操作
            $result = $this->family_model->where(['family_id'=>$family_id,'member_id'=>$member_id])->delete();

            if ($result === false) {
                $this->ajax_return('11153','failed to exit');
            }else{
                $this->affix_model->where(['family_id'=>$family_id])->delete();
                $this->family_member_model->where(['family_id'=>$family_id,'member_id'=>$member_id])->delete();
                $this->ajax_return('200','success','');
            }
        }

        $has_row = $this->family_member_model->where(['member_id' => $member_id,'family_id' => $family_id])->count();

        if (empty($has_row)) {
            $this->ajax_return('11154','family does not exist');
        }

        // $watch_imei = model('member_info')->where(['member_id'=>$family_info['member_id']])->value('watch_imei');

        $result = $this->family_member_model->where(['member_id' => $member_id,'family_id' => $family_id])->delete();

        if ($result === false) {
            $this->ajax_return('11153','failed to exit');
        }else{
            $this->family_model->where(['family_id' => $family_id])->setDec('member_count');
            $this->ajax_return('200','success','');
        }
    }
}
