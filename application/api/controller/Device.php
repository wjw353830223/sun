<?php
namespace app\api\controller;
use Lib\Sms;
use Lib\wechatAppPay;
use think\Cache;
use Lib\Http;
use Lib\Watch;
use think\db;
class Device extends Apibase
{  
    //机器人服务器地址
    public $code_url = 'http://robot.healthywo.cn';
    //SN码获取
    public $get_code = '/api/robot/get_code';
    protected $family_model;
	protected function _initialize(){
    	parent::_initialize();
    	$this->family_model = model('family');
    }

	/**
    * 机器人绑定
    */
    public function robot_bind(){
        $family_id = intval(input('post.family_id',0,'trim'));
        if (empty($family_id)) {
            $this->ajax_return('11160','invalid family_id');
        }

        $member_id = $this->member_info['member_id'];
        $family_info = $this->family_model->where(array('member_id' => $member_id,'family_id' => $family_id))->find();

        if (empty($family_info)) {
            $this->ajax_return('11161','dose not own family');
        }

        //已绑定机器人编码需传入old_robot_imei进行换绑
        if (!empty($family_info['robot_imei'])) {

            $old_robot_imei = input('post.old_robot_imei','','trim');
            if ($old_robot_imei != $family_info['robot_imei']) {
                $this->ajax_return('10162','robot_imei is wrong');
            }
        }

        $robot_imei = input('post.robot_imei','','trim');
        if (empty($robot_imei)) {
            $this->ajax_return('11163','invalid robot_imei');
        }

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

        $has_bind = $this->family_model->where(array('robot_imei' => $robot_imei))->count();
        if (!empty($has_bind)) {
            $this->ajax_return('11164','robot_imei has been used');
        }

        $result = $this->family_model->save(['robot_imei' => $robot_imei],['family_id' => $family_id]);

        if ($result === false) {
            //绑定失败
            $this->ajax_return('11165','the behavior is wrong');
        }else{
            $this->ajax_return('200','success','');
        }
    }

    /**
    * 机器人解绑
    */
    public function robot_unbind(){
        $robot_imei = input('post.robot_imei','','trim');

        if (empty($robot_imei)) {
            $this->ajax_return('11170','invalid family_id');
        }

        $family_id = intval(input('post.family_id',0,'trim'));
        if (empty($family_id)) {
            $this->ajax_return('11171','unknow handle');
        }

        $member_id = $this->member_info['member_id'];
        $family_info = $this->family_model->where(array('member_id' => $member_id,'family_id' => $family_id))->find();

        if (empty($family_info)) {
            $this->ajax_return('11172','dose not own family');
        }

        if ($family_info['robot_imei'] != $robot_imei) {
            $this->ajax_return('11173','robot_imei can not unbind');
        }

        $result = $this->family_model->save(['robot_imei' => ''],['family_id' => $family_id]);

        if (empty($result)) {
            $this->ajax_return('11174','robot_imei unbind wrong');
        }else{
            $this->ajax_return('200','success');
        }
    }

    /**
    * 可操作机器人列表
    */
    public function robot_list(){
    	$member_id = $this->member_info['member_id'];
        $family_ids = model('family_member')->where(array('member_id' => $member_id))->order('create_time desc')->column('family_id');
        if (empty($family_ids)) {
            $this->ajax_return('11180','empty data');
        }

        $family_ids_str = implode(',',$family_ids);
        $condition = array('robot_imei'=>array('neq',''),'state' => 1,'family_id' => array('IN',$family_ids_str));
        $family_list = $this->family_model->where($condition)->field('family_id,family_name,robot_imei')->select();

        if (empty($family_list->toArray())) {
            $this->ajax_return('11180','empty data');
        }else{
            $this->ajax_return('200','success',$family_list);
        }

    }

    /**
    * 可管理机器人列表
    */
    public function mange_list(){
    	$member_id = $this->member_info['member_id'];
        $family_list = $this->family_model->where(array('robot_imei' => array('neq',''),'state' => 1,'member_id' => $member_id))->field('family_id,family_name,robot_imei')->select();
        if (empty($family_list->toArray())) {
            $this->ajax_return('11190','dose not have record');
        }

        $this->ajax_return('200','success',$family_list);
    }

     /**
    * 手表列表
    */
    public function watch_list(){
        $member_id = $this->member_info['member_id'];
        $watch_imei = model('member_info')->where(['member_id'=>$member_id])->value('watch_imei');

        if (empty($watch_imei)) {
            $this->ajax_return('11210','invalid watch_imei');
        }

        $return = array();

        $return[]['watch_imei'] = array('watch_imei' => $watch_imei);

        $this->ajax_return('200','success',$return);
    }

    /**
    * 获取位置
    */
    public function get_location(){
    	$member_id = $this->member_info['member_id'];
    	$member_info = model('member_info')->where(array('member_id'=>$member_id))->find();
    	if(empty($member_info)){
    		$this->ajax_return('11240','empty data');
    	}
    	$member_mobile = model('member')->where(array('member_id'=>$member_id))->value('member_mobile');
    	if(empty($member_mobile)){
    		$this->ajax_return('11241','invalid member_mobile');
    	}
        $family_member_id = input('post.family_member_id',0,'intval');

        if ($family_member_id > 0) {
            $member_row = model('family_member')->where(array('id' => $family_member_id))->find();
            if (empty($member_row)) {
                $this->ajax_return('11242','family_member dose not exsit');
            }
            if ($member_row['is_member']) {
                $watch_imei = model('member_info')->where(array('member_id' => $member_row['member_id']))->value('watch_imei');
            }else{
                $watch_imei = model('family_member_info')->where(array('info_id' => $member_row['info_id']))->value('watch_imei');
            }

        }else{
            $watch_imei = $member_info['watch_imei'];
        }

        if (empty($watch_imei)) {
            $this->ajax_return('11243','dose not bind watch');
        }

        $watch = new Watch();
        $param = [
        	'device_imei' => $watch_imei
        ];
        //upload_location
		$result = $watch->send_request($param,'10119');
		if(!$result){
			$this->ajax_return('11244',$watch->get_error());
		}

		//get_location
		$start_time = '0000';
		$end_time = '0000';
		$param = array(
			'device_imei' => $watch_imei,
			'start_time' => $start_time,
			'end_time' => $end_time
		);

		if ((!empty($start_time) && !empty($end_time)) && $start_time < $end_time ) {
			$this->ajax_return('11245','start_time nlt end_time');
		}
        $get_location = $watch->send_request($param,'10123');
        if (!$get_location) {
            $this->ajax_return('11244',$watch->get_error());
        }

        $location_data = $get_location;
        $result = [
            'lng' => !empty($location_data['locationList']) ? $location_data['locationList'][0]['lng'] : '',
            'lat' => !empty($location_data['locationList']) ? $location_data['locationList'][0]['lat'] : ''
        ];
        $this->ajax_return('200','success',$result);
    }

    /**
     *  手表绑定
     */
    public function watch_bind(){
        $member_id = $this->member_info['member_id'];

        //手表编码
        $watch_imei = input('post.watch_imei','','trim');
        if(empty($watch_imei) || !preg_match("/^(?!([A-F]+|\d+)$)[A-F\d]{14}$/",$watch_imei)) {
            $this->ajax_return('11210','invalid watch_imei');
        }

        $member_info_model = model('MemberInfo');
        $member_info = $member_info_model->where(['member_id'=>$member_id])->find();
        if(empty($member_info)){
            $param = [
                'member_id' => $member_id,
                'watch_imei' => $watch_imei
            ];
            $result = $member_info_model->save($param);

            if($result === false){
                $this->ajax_return('11211','the behavior is wrong');
            }
            $this->ajax_return('200','success');
        }

        $has_info = $member_info_model->where(['watch_imei' => $watch_imei])->count();
        if ($has_info > 0) {
            $this->ajax_return('11212','watch_imei has been binded');
        }

        $result = $member_info_model->save(['watch_imei'=>$watch_imei],['member_id'=>$member_id]);

        if($result === false){
            $this->ajax_return('11211','the behavior is wrong');
        }

        $this->ajax_return('200','success');
    }

    /**
    * 手表解绑
    */
    public function watch_unbind(){
        $member_id = $this->member_info['member_id'];
        $watch_imei = input('post.watch_imei','','trim');
        if(empty($watch_imei) || !preg_match("/^(?!([A-F]+|\d+)$)[A-F\d]{14}$/",$watch_imei)){
            $this->ajax_return('11220','invalid watch_imei');
        }

        $member_info_model = model('MemberInfo');
        $member_info = $member_info_model->where(['member_id'=>$member_id])->find();

        if(empty($member_info['watch_imei'])){
            $this->ajax_return('11221','watch has been unbinded');
        }
        
        if ($watch_imei != $member_info['watch_imei']) {
            $this->ajax_return('11220','invalid watch_imei');
        }
        
        $result = $member_info_model ->where(['member_id'=>$member_id])->setField('watch_imei','');
        if ($result ===  false) {
            $this->ajax_return('11222','the behavior is wrong');
        }

        $this->ajax_return('200','success');
    }


    /**
    * 获取历史健康数据
    */
    public function get_monitor_history(){
        $family_member_id = input('post.family_member_id',0,'intval');
        if ($family_member_id > 0) {
            $member_row = model('FamilyMember')->where(['id' => $family_member_id])->find();
            if (empty($member_row)) {
                //会员已退出家庭或不存在
                $this->ajax_return('11251','member is out of family');
            }
            $family_id = model('FamilyMember')
                ->where(['member_id'=>$this->member_info['member_id']])
                ->field('family_id')->select();
            $ids = [];
            foreach($family_id as $v){
                $ids[] = $v['family_id'];
            }
            if(!in_array($member_row['family_id'],$ids)){
                $this->ajax_return('11922','invalid family_member_id');
            }
            if ($member_row['is_member']) {
                //真实会员
                $member_id = $member_row['member_id'];
                $watch_imei = model('member_info')->where(['member_id' => $member_row['member_id']])->value('watch_imei');
                $member_mobile = model('Member')->where(['member_id'=>$member_id])->value('member_mobile');
                $member_info = model('member_info')->where(['member_id'=>$member_id])->find();
            }else{
                //虚拟会员
                $watch_imei = model('family_member_info')
                    ->where(['info_id' => $member_row['info_id']])
                    ->value('watch_imei');
                $member_mobile = model('family_member_info')
                    ->where(['info_id' => $member_row['info_id']])
                    ->value('mobile');
                $member_id = 0;
            }
        }else{
            //当前登录会员
            $member_id = $this->member_info['member_id'];
            $member_mobile = model('Member')->where(['member_id'=>$member_id])->value('member_mobile');
            $member_info = model('member_info')->where(['member_id'=>$member_id])->find();
            $watch_imei = $member_info['watch_imei'];
        }
        if (isset($member_info) && empty($member_info)){
            $this->ajax_return('11250','empty data');
        }
        $date = (int)input('post.data_time','','trim');
        if (!is_int($date) || empty($date)) {
            $this->ajax_return('11262','invalid data_time');
        }
        $type = input('post.type','','trim');
        switch ($type){
            case 'day':
                $first = strtotime(date('Y-m-d',$date));
                $last = $first + 86400;
                break;
            case 'month':
                $first = strtotime(date('Y-m',$date));
                $year = date('Y',$first);
                $month = date('m',$first);
                $day = date('t',$first);
                $last = mktime(23,59,59,$month,$day,$year);
                break;
            default:
                $first = $last = 0;
                $this->ajax_return('11920','invalid type');
                break;
        }
        $analyse_type = input('post.analyse_type','','trim');
        $data= [];
        switch ($analyse_type){
            case 'uric':
                $data['uric_data'] = $this->robot_data($member_id,$member_mobile,$first,$last,'uric',$type);
                break;
            case 'sugar':
                $data['sugar_data'] = $this->robot_data($member_id,$member_mobile,$first,$last,'sugar',$type);
                break;
            case 'chole':
                $data['chole_data'] = $this->robot_data($member_id,$member_mobile,$first,$last,'chole',$type);
                break;
            case 'exergen':
                $data['exergen_data'] = $this->robot_data($member_id,$member_mobile,$first,$last,'exergen',$type);
                break;
            case 'blood':
                $data['blood_data'] = $this->blood_data($member_id,$member_mobile,$first,$last,$watch_imei,$type);
                break;
            case 'sport':
                $data['sport_data'] = $this->sport_data($first,$last,$watch_imei);
                break;
            case 'heart':
                $data['heart_data'] = $this->heart_data($first,$last,$watch_imei,$type);
                break;
            case 'sleep':
                $data['sleep_data'] = $this->sleep_data($first,$last,$watch_imei);
                break;
            default:
                $this->ajax_return('11921','invalid analyse_type');
                break;
        }
        $this->ajax_return('200','success',$data);
    }

    private function robot_data($member_id,$member_mobile,$first,$last,$type,$date_type){
        $where = [];
        if(!empty($member_id)){
            $ids = [];
            $family_id = model('FamilyMember')->where(['member_id'=>$member_id])->field('id')->select();
            foreach($family_id as $v){
                $ids[] = $v['id'];
                $where['family_member_id'] = ['in',$ids];
            }
        }

        if(!empty($member_mobile)){
            $customer_id = db('RobotCustomer')->where(['customer_mobile'=>$member_mobile])->value('customer_id');
            $customer_id = $customer_id ? $customer_id : '';
        }else{
            $customer_id = 0;
        }
        $analyse = [];
        if(!empty($where)){
            $where['analyse_type'] = $type;
            $where['create_time'] = ['egt',$first];
            $analyse = db('RobotAnalyse')->where($where)
                ->where(['create_time'=>['lt',$last]])
                ->order('create_time desc')->field('relation_id,create_time')
                ->select();
        }
        $analyse_customer = [];
        if(!empty($customer_id)) {
            $orwhere = ['customer_id' => $customer_id, 'analyse_type' => $type];
            $orwhere['create_time'] = ['egt', $first];
            $analyse_customer = db('RobotAnalyse')->where($orwhere)
                ->where(['create_time' => ['lt', $last]])
                ->order('create_time desc')
                ->field('relation_id,create_time')
                ->select();
        }
        $data_result = array_merge($analyse,$analyse_customer);
        if(!empty($data_result)){
            $ids = [];
            foreach($data_result as $v){
                $ids[] = $v['relation_id'];
            }
        }
        $data = [];
        if(!empty($ids)) {
            switch ($type) {
                case 'sugar':
                    $data = db('RobotSugar')
                        ->where(['sugar_id' => ['in', $ids]])
                        ->order('create_time desc')
                        ->field('sugar_blood,create_time')->select();
                    break;
                case 'uric':
                    $data = db('RobotUric')
                        ->where(['uric_id' => ['in', $ids]])
                        ->order('create_time desc')
                        ->field('uric_acid,create_time')->select();
                    break;
                case 'chole':
                    $data = db('RobotChole')
                        ->where(['cholesterin_id' => ['in', $ids]])
                        ->order('create_time desc')
                        ->field('cholesterin,create_time')->select();
                    break;
                case 'exergen':
                    $data = db('RobotExergen')
                        ->where(['exergen_id' => ['in', $ids],'temperature_type'=>1])
                        ->order('create_time desc')
                        ->field('temperature,create_time')->select();
                    break;
                default:
                    break;
            }
        }
        $return_data = $this->get_new_data($date_type,$data,$first);
        return $return_data;
    }
    private function blood_data($member_id,$member_mobile,$first,$last,$watch_imei,$date_type){
        if(!empty($watch_imei)){
            $watch = new Watch();
            $param = [
                'device_imei'   => $watch_imei,
                'startTime'     => date('Y-m-d',$first),
                'endTime'       => date('Y-m-d',$last)
            ];
            $result = $watch->send_request($param,'10115');
            if(!$result){
                $this->ajax_return('11254',$watch->get_error());
            }
            $blood_list = $result['bloPrsInfoList'];
            if(!empty($blood_list)){
                $blood = db('HealthBlood')->where(['watch_imei'=>$watch_imei,'create_time'=>['egt',$first]])
                    ->where(['create_time'=>['lt',$last]])->field('create_time')->select();
                $times = [];
                foreach ($blood as $v){
                    $times[] = $v['create_time'];
                }
                $data = [];
                foreach ($blood_list as $v){
                    if(!in_array(strtotime($v['time']),$times)){
                        $tmp = [];
                        $tmp['watch_imei'] = $watch_imei;
                        $tmp['create_time'] = strtotime($v['time']);
                        $tmp['systolic'] = $v['systolicVal'];
                        $tmp['diastolic'] = $v['diastolicVal'];
                        $tmp['report_desc'] = $v['aysRptResume'];
                        $tmp['report_url'] = $v['aysRptUrl'];
                        $data[] = $tmp;
                    }
                }
                db('HealthBlood')->insertAll($data);
            }
        }
        $where = [];
        $ids = [];
        if(!empty($member_id)){
            $family_id = model('FamilyMember')->where(['member_id'=>$member_id])->field('id')->select();
            foreach($family_id as $v){
                $ids[] = $v['id'];
                $where['family_member_id'] = ['in',$ids];
            }
        }
        if(!empty($member_mobile)){
            $customer_id = db('RobotCustomer')->where(['customer_mobile'=>$member_mobile])->value('customer_id');
            $customer_id = $customer_id ? $customer_id : '';
        }else{
            $customer_id = '';
        }
        $analyse = [];
        if(!empty($where)){
            $where['analyse_type'] = 'blood';
            $where['create_time'] = ['egt',$first];
            $analyse = db('RobotAnalyse')->where($where)->where(['create_time'=>['lt',$last]])
                ->order('create_time desc')->field('relation_id,create_time')
                ->select();
        }
        $analyse_customer = [];
        if(!empty($customer_id)) {
            $orwhere = ['customer_id' => $customer_id, 'analyse_type' => 'blood'];
            $orwhere['create_time'] = ['egt', $first];
            $analyse_customer = db('RobotAnalyse')->where($orwhere)
                ->where(['create_time' => ['lt', $last]])
                ->order('create_time desc')
                ->field('relation_id,create_time')
                ->select();
        }
        $data_result = array_merge($analyse,$analyse_customer);
        $blood = [];
        if(!empty($data_result)){
            $ids = [];
            foreach($data_result as $v){
                $ids[] = $v['relation_id'];
            }
            $blood = db('RobotBlood')
                ->where(['blood_id'=>['in',$ids],'create_time'=>['egt',$first]])
                ->where(['create_time'=>['lt',$last]])
                ->order('create_time desc')
                ->field('systolic,diastolic,create_time')->select();
        }
        $blood_data = db('HealthBlood')
            ->where(['watch_imei'=>$watch_imei,'create_time'=>['egt',$first]])
            ->where(['create_time'=>['lt',$last]])
            ->field('create_time,systolic,diastolic')
            ->order('create_time desc')
            ->select();
        if(!$blood_data){
            $blood_data = [];
        }
        $data = array_merge($blood,$blood_data);
        if(!$data){
            $time = [];
            foreach($data as $v){
                $time = $v['create_time'];
            }
            array_multisort($time, SORT_DESC, $data);
        }
        $return_data = $this->get_new_data($date_type,$data,$first);
        return $return_data;
    }
    private function sport_data($first,$last,$watch_imei){
        if(empty($watch_imei)){
            $this->ajax_return('11252','dose not bind watch');
        }
        $watch = new Watch();
        $param = [
            'device_imei'   => $watch_imei,
            'startTime'     => date('Y-m-d',$first),
            'endTime'       => date('Y-m-d',$last)
        ];
        $result = $watch->send_request($param,'10120');
        if(!$result){
            $this->ajax_return('11254',$watch->get_error());
        }
        $sport_data = $result['sportInfoList'];
        if(!empty($sport_data)){
            $sport = db('HealthSport')->where(['watch_imei'=>$watch_imei,'create_time'=>['egt',$first]])
                ->where(['create_time'=>['lt',$last]])->field('create_time')->select();
            $times = [];
            foreach ($sport as $v){
                $times[] = $v['create_time'];
            }
            $data = [];
            foreach($sport_data as $v){
                if(!in_array(strtotime($v['date']),$times)){
                    $tmp = [];
                    $tmp['create_time'] = strtotime($v['date']);
                    $tmp['watch_imei'] = $watch_imei;
                    $tmp['step_num'] = $v['qty'];
                    $tmp['calory'] = $v['calory'];
                    $data[] = $tmp;
                }
            }
            db('HealthSport')->insertAll($data);
        }

        $sport_data = db('HealthSport')->where(['watch_imei'=>$watch_imei,'create_time'=>['egt',$first]])
            ->where(['create_time'=>['lt',$last]])
            ->field('sum(step_num) step_num,sum(calory) calory,create_time')
            ->order('create_time desc')
            ->group('create_time')
            ->select();
        return $sport_data;
    }
    private function heart_data($first,$last,$watch_imei,$date_type){
        if(empty($watch_imei)){
            $this->ajax_return('11252','dose not bind watch');
        }
        $watch = new Watch();
        $param = [
            'device_imei'   => $watch_imei,
            'startTime'     => date('Y-m-d',$first),
            'endTime'       => date('Y-m-d',$last)
        ];
        $result = $watch->send_request($param,'10116');
        if(!$result){
            $this->ajax_return('11254',$watch->get_error());
        }
        $heart_list = $result['heartList'];
        if(!empty($heart_list)){
            $heart = db('HealthHeart')->where(['watch_imei'=>$watch_imei,'create_time'=>['egt',$first]])
                ->where(['create_time'=>['lt',$last]])->field('create_time')->select();
            $times = [];
            foreach ($heart as $v){
                $times[] = $v['create_time'];
            }
            $data = [];
            foreach ($heart_list as $v){
                if(!in_array(strtotime($v['date']),$times)){
                    $tmp = [];
                    $tmp['watch_imei'] = $watch_imei;
                    $tmp['create_time'] = strtotime($v['date']);
                    $tmp['avg_heart'] = $v['heart'];
//                    $tmp['min_heart'] = $v['minHeart'];
//                    $tmp['max_heart'] = $v['maxHeart'];
                    $data[] = $tmp;
                }
            }
            db('HealthHeart')->insertAll($data);
        }
        $heart_data = db('HealthHeart')
            ->where(['watch_imei'=>$watch_imei,'create_time'=>['egt',$first]])
            ->where(['create_time'=>['lt',$last]])
            ->field('create_time,avg_heart,min_heart,max_heart')
            ->order('create_time desc')
            ->select();
        $return_data = $this->get_new_data($date_type,$heart_data,$first);
        return $return_data;
    }
    private function sleep_data($first,$last,$watch_imei){
        if(empty($watch_imei)){
            $this->ajax_return('11252','dose not bind watch');
        }
        $watch = new Watch();
        $param = [
            'device_imei'   => $watch_imei,
            'startTime'     => date('Y-m-d',$first),
            'endTime'       => date('Y-m-d',$last)
        ];
        $result = $watch->send_request($param,'10127');
        if(!$result){
            $this->ajax_return('11254',$watch->get_error());
        }
        $sleep_list = $result['sleepList'];
        if(!empty($sleep_list)){
            $sleep = db('HealthSleep')->where(['watch_imei'=>$watch_imei,'start_time'=>['egt',$first]])
                ->where(['start_time'=>['lt',$last]])->field('start_time')->select();
            $times = [];
            foreach ($sleep as $v){
                $times[] = $v['start_time'];
            }
            $data = [];
            //写入数据库中没有保存的数据
            foreach ($sleep_list as $v){
                if(!in_array(strtotime($v['startTime']),$times)){
                    $tmp = [];
                    $tmp['row_id'] = $v['id'];
                    $tmp['watch_imei'] = $watch_imei;
                    $tmp['start_time'] = strtotime($v['startTime']);
                    $tmp['sleep_time'] = $v['sleepTime'];
                    $tmp['deep_time'] = $v['deepTime'];
                    $tmp['unsleep_time'] = $v['noSleepTime'];
                    $tmp['sleep_grade'] = sleep_grade($v['sleepTime'],$v['deepTime']);
                    $data[] = $tmp;
                }
            }
            db('HealthSleep')->insertAll($data);
        }
        $sleep_data = db('HealthSleep')
            ->where(['watch_imei'=>$watch_imei,'start_time'=>['egt',$first]])
            ->where(['start_time'=>['lt',$last]])
            ->field('sleep_time,sleep_grade,start_time')
            ->order('start_time desc')
            ->select();
        return $sleep_data;
    }
    private function get_new_data($date_type,$data,$first){
        switch ($date_type) {
            case 'month':
                $return_data = [];
                $days = date('t',$first);
                $time = $first;
                for($i = 1;$i<=$days;$i++){
                    $tmp = [];
                    $create_time = [];
                    foreach($data as $k=>$v){
                        if($v['create_time'] >= $time && $v['create_time'] < $time + 86400){
                            $tmp[] = $v;
                            $create_time[] = $v['create_time'];
                        }
                    }
                    $time += 86400;
                    if(!empty($tmp)){
                        array_multisort($create_time, SORT_DESC, $tmp);
                        $return_data[] = $tmp[0];
                    }
                }

                if(!empty($return_data)){
                    $return_time = [];
                    foreach($return_data as $v){
                        $return_time[] = $v['create_time'];
                    }
                    array_multisort($return_time, SORT_DESC, $return_data);
                }
                break;
            default:
                $return_data = $data;
                break;
        }
        return $return_data;
    }

    /**
    * 获取健康数据
    */
    public function get_monitor(){
        $family_member_id = input('post.family_member_id',0,'intval');
        if ($family_member_id > 0) {
            $member_row = model('family_member')->where(['id' => $family_member_id])->find();
            if (empty($member_row)) {
                //会员已退出家庭或不存在
                $this->ajax_return('11251','member is out of family');
            }

            if ($member_row['is_member']) {
                $member_id = $member_row['member_id'];
                $watch_imei = model('member_info')->where(['member_id' => $member_row['member_id']])->value('watch_imei');
                $member_mobile = model('Member')->where(['member_id'=>$member_id])->value('member_mobile');
                $member_info = model('member_info')->where(['member_id'=>$member_id])->find();
            }else{
                $watch_imei = model('family_member_info')
                    ->where(['info_id' => $member_row['info_id']])
                    ->value('watch_imei');
                $member_mobile = model('family_member_info')
                    ->where(['info_id' => $member_row['info_id']])
                    ->value('mobile');
            }
        }else{
            $member_id = $this->member_info['member_id'];
            $member_mobile = model('Member')->where(['member_id'=>$member_id])->value('member_mobile');
            $member_info = model('member_info')->where(['member_id'=>$member_id])->find();
            $watch_imei = $member_info['watch_imei'];
        }
        if (isset($member_info) && empty($member_info)){
            $this->ajax_return('11250','empty data');
        }
        $return_data = [];
        if (!empty($watch_imei)) {
            $watch = new Watch();
            $str_time = strtotime(date('Y-m-d'));
            $min_time = $str_time;
            $max_time = $str_time + 86400;
            $sleep_model = db('health_sleep');
            $heart_model = db('health_heart');
            $blood_model = db('health_blood');
            $sport_model = db('health_sport');

            //更新睡眠数据
            $param = [
                'device_imei' => $watch_imei,
                'startTime' => date('Y-m-d H:i:s',$min_time),
                'endTime' => date('Y-m-d H:i:s',$max_time),
                'pageIndex' => 1
            ];
            if ((!empty($param['startTime']) && !empty($param['endTime'])) && $param['startTime'] > $param['endTime'] ) {
                $this->ajax_return('11253','start_time nlt end_time');
            }
            $result = $watch->send_request($param,'10127');

            $sleep_data = $result['sleepList'];

            if(!$result){
                $this->ajax_return('11254',$watch->get_error());
            }
            if ($result && !empty($sleep_data)) {
                //获取历史数据中时间最大的一个
                $start_time = $sleep_model->where(['watch_imei' => $watch_imei])->order('start_time desc')->value('start_time');
                if (empty($start_time)){
                   $start_time = 0;
                }

                //写入比历史数据时间大的最近数据
                $insert_arr = array();
                foreach ($sleep_data as $key => $value) {
                    if (strtotime($value['startTime']) > $start_time) {
                        $tmp = array();
                        $tmp['row_id'] = $value['id'];
                        $tmp['watch_imei'] = $watch_imei;
                        $tmp['start_time'] = strtotime($value['startTime']);
                        $tmp['sleep_time'] = $value['sleepTime'];
                        $tmp['deep_time'] = $value['deepTime'];
                        $tmp['unsleep_time'] = $value['noSleepTime'];
                        $tmp['sleep_grade'] = sleep_grade($value['sleepTime'],$value['deepTime']);
                        $insert_arr[] = $tmp;
                    }
               }
               $sleep_model->insertAll($insert_arr);
            }

            //更新心率数据
            $param = [
                'device_imei' => $watch_imei,
                'startTime' => date('Y-m-d H:i:s',$min_time),
                'endTime' => date('Y-m-d H:i:s',$max_time),
                'pageIndex' => 1
            ];
            $result = $watch->send_request($param,'10116');
            if(!$result){
                $this->ajax_return('11254',$watch->get_error());
            }
            $heart_data = $result['heartList'];

            if ($result && !empty($heart_data)) {
                //获取历史数据中时间最大的一个
                $create_time = $heart_model->where(['watch_imei' => $watch_imei])->order('create_time desc')->value('create_time');
                if (empty($create_time)) {
                   $create_time = 0;
                }

                //写入比历史数据时间大的最近数据
                $insert_arr = array();
                foreach ($heart_data as $key => $value) {
                    if (strtotime($value['date']) > $create_time) {
                        $tmp = array();
                        $tmp['watch_imei'] = $watch_imei;
                        $tmp['create_time'] = strtotime($value['date']);
                        $tmp['avg_heart'] = $value['heart'];
                        $insert_arr[] = $tmp;
                    }
               }
               $heart_model->insertAll($insert_arr);
            }

            //更新血压数据
            $param = [
                'device_imei' => $watch_imei,
                'startTime' => date('Y-m-d H:i:s',$min_time),
                'endTime' => date('Y-m-d H:i:s',$max_time),
                'pageIndex' => 1
            ];
            $result = $watch->send_request($param,'10115');

            if(!$result){
                $this->ajax_return('11254',$watch->get_error());
            }
            $blood_data = $result['bloPrsInfoList'];

            if ($result && !empty($blood_data)) {
                //获取历史数据中时间最大的一个
                $create_time = $blood_model->where(['watch_imei' => $watch_imei])->order('create_time desc')->value('create_time');
                if (empty($create_time)) {
                   $create_time = 0;
                }
                //写入比历史数据时间大的最近数据
                $insert_arr = array();
                foreach ($blood_data as $key => $value) {
                    if (strtotime($value['time']) > $create_time) {
                        $tmp = array();
                        $tmp['watch_imei'] = $watch_imei;
                        $tmp['create_time'] = strtotime($value['time']);
                        $tmp['systolic'] = $value['systolicVal'];
                        $tmp['diastolic'] = $value['diastolicVal'];
                        $tmp['report_desc'] = $value['aysRptResume'];
                        $tmp['report_url'] = $value['aysRptUrl'];
                        $insert_arr[] = $tmp;
                    }
                }

                $blood_model->insertAll($insert_arr);
            }

            //更新运动数据
            $param = [
                'device_imei'   => $watch_imei,
                'startTime'     => date('Y-m-d H:i:s',$min_time),
                'endTime'       => date('Y-m-d H:i:s',$max_time),
                'sportType'     => '1',
            ];
            $result = $watch->send_request($param,'10120');
            if(!$result){
                $this->ajax_return('11254',$watch->get_error());
            }

            $sport_data = $result['sportInfoList'];

            if ($result && !empty($sport_data)) {
                //获取历史数据中时间最大的一个
                $sport_info = $sport_model->where(['watch_imei' => $watch_imei])->order('create_time desc')->field('sport_id,create_time,step_num')->find();
                if (empty($sport_info)) {
                   $create_time = 0;
                }
                //运动数据每天只返回1条
                $sport_data_info = $sport_data[0];

                $insert_arr = array();
                if (strtotime($sport_data_info['date']) > $sport_info['create_time']) {
                    $insert_arr['watch_imei'] = $watch_imei;
                    $insert_arr['create_time'] = strtotime($sport_data_info['date']);
                    $insert_arr['step_num'] = $sport_data_info['qty'];
                    $insert_arr['calory'] = $sport_data_info['calory'];
                    $sport_model->insert($insert_arr);
                }

                if (strtotime($sport_data_info['date']) == $sport_info['create_time'] && $sport_data_info['qty'] > $sport_info['step_num']) {
                    $insert_arr['step_num'] = $sport_data_info['qty'];
                    $insert_arr['calory'] = $sport_data_info['calory'];
                    $sport_model->where(['watch_imei' => $watch_imei,'sport_id' => $sport_info['sport_id']])->update($insert_arr);
                }
            }

            //增加接口默认数据，避免无数据产生时的错误
            $sleep_db_data = $sleep_model->where(['watch_imei' => $watch_imei])
                ->order('start_time desc')->field('sleep_time,sleep_grade,start_time')->find();
            $sleep_data = ['sleep_time' => '0','sleep_grade' => '5','start_time'=>'0'];
            if (!empty($sleep_db_data)) {
                $sleep_data = [
                    'sleep_time' => $sleep_db_data['sleep_time'],
                    'sleep_grade' => $sleep_db_data['sleep_grade'],
                    'start_time'=>$sleep_db_data['start_time']
                ];
            }
            $return_data['sleep_data'] = $sleep_data;

            $avg_heart = $heart_model
                ->where(['watch_imei' => $watch_imei,'create_time' => [['EGT',$min_time],['LT',$max_time]]])
                ->order('create_time desc')->column('avg_heart');
            $heart_last_time = $heart_model->where(['watch_imei' => $watch_imei])
                ->order('create_time desc')->value('create_time');
            $heart_data = ['avg_heart' => '0','min_heart' => '0','max_heart' => '0','create_time'=>'0'];
            if (!empty($avg_heart)) {
                $heart_data = [
                    'avg_heart' => (string)round(array_sum($avg_heart) / count($avg_heart)),
                    'min_heart' => min($avg_heart),
                    'max_heart' => max($avg_heart),'create_time'=>$heart_last_time
                ];
            }else{
                $heart_db_data = $heart_model->where(['watch_imei' => $watch_imei])
                    ->field('avg_heart,min_heart,max_heart,create_time')->find();
                if (!empty($heart_db_data)) {
                    $heart_data = [
                        'avg_heart' => $heart_db_data['avg_heart'],
                        'min_heart' => $heart_db_data['min_heart'],
                        'max_heart' => $heart_db_data['max_heart'],
                        'create_time'=>$heart_db_data['create_time']
                    ];
                }
            }
            $return_data['heart_data'] = $heart_data;

            $blood_db_data = $blood_model->where(['watch_imei' => $watch_imei])
                ->order('create_time desc')
                ->field('systolic,diastolic,create_time')->find();
            $blood_data = ['systolic' => '0','diastolic' => '0','create_time' => '0'];
            if (!empty($blood_db_data)) {
                $blood_data = [
                    'systolic' => $blood_db_data['systolic'],
                    'diastolic' => $blood_db_data['diastolic'],
                    'create_time' => $blood_db_data['create_time']
                ];
            }
            $return_data['blood_data'] = $blood_data;

            $sport_db_data = $sport_model->where(['watch_imei' => $watch_imei])
                ->order('create_time desc')
                ->field('step_num,calory,create_time')->find();

            $sport_data = ['step_num' => '0','calory' => '0','create_time'=>'0'];
            if (!empty($sport_db_data)) {
                $sport_data = [
                    'step_num' => $sport_db_data['step_num'],
                    'calory' => $sport_db_data['calory'],
                    'create_time'=>$sport_db_data['create_time']
                ];
            }
            $return_data['sport_data'] = $sport_data;
        }
        $where = [];
        if(isset($member_id)){
            $ids = [];
            $family = model('FamilyMember')->where(['member_id'=>$member_id])->field('id')->select();
            foreach($family as $v){
                $ids[] = $v['id'];
                $where['family_member_id'] = ['in',$ids];
            }
        }

        $customer_id = db('RobotCustomer')
            ->where(['customer_mobile'=>$member_mobile])
            ->value('customer_id');
        $customer_id = $customer_id ? $customer_id : '';
        //血糖数据
        if(!empty($where)){
            $where['analyse_type'] = 'sugar';
            $sugar_analyse = db('RobotAnalyse')->where($where)->order('create_time desc')->find();
        }
        if(!empty($customer_id)){
            $orwhere = ['customer_id' => $customer_id, 'analyse_type' => 'sugar'];
            $sugar_analyse_customer = db('RobotAnalyse')->where($orwhere)->order('create_time desc')->find();
        }

        if(isset($sugar_analyse_customer) && !empty($sugar_analyse_customer) &&
            isset($sugar_analyse) && !empty($sugar_analyse)){
            if($sugar_analyse_customer['create_time'] > $sugar_analyse['create_time']){
                $sugar_id = $sugar_analyse_customer['relation_id'];
            }else{
                $sugar_id = $sugar_analyse['relation_id'];
            }
        }elseif(isset($sugar_analyse_customer) && !empty($sugar_analyse_customer) && !isset($sugar_analyse)){
            $sugar_id = $sugar_analyse_customer['relation_id'];
        }elseif(isset($sugar_analyse) && !empty($sugar_analyse) && !isset($sugar_analyse_customer)){
            $sugar_id = $sugar_analyse['relation_id'];
        }else{
            $sugar_id = 0;
        }
        if(!empty($sugar_id)){
            $sugar = db('RobotSugar')
                ->where(['sugar_id'=>$sugar_id])
                ->order('create_time desc')->field('sugar_blood,create_time')->find();
            if($sugar){
                $return_data['sugar_data'] = $sugar;
            }else{
                $return_data['sugar_data'] = ['sugar_blood'=>0,'create_time'=>0];
            }
        }else{
            $return_data['sugar_data'] = ['sugar_blood'=>0,'create_time'=>0];
        }
        //血脂数据
        if(!empty($where)) {
            $where['analyse_type'] = 'chole';
            $chole_analyse = db('RobotAnalyse')->where($where)->order('create_time desc')->find();
        }
        if(!empty($customer_id)) {
            $orwhere = ['customer_id' => $customer_id, 'analyse_type' => 'chole'];
            $chole_analyse_customer = db('RobotAnalyse')->where($orwhere)->order('create_time desc')->find();
        }
        if(isset($chole_analyse_customer) && !empty($chole_analyse_customer) &&
            isset($chole_analyse) && !empty($chole_analyse)){
            if($chole_analyse_customer['create_time'] > $chole_analyse['create_time']){
                $cholesterin_id = $chole_analyse_customer['relation_id'];
            }else{
                $cholesterin_id = $chole_analyse['relation_id'];
            }
        }elseif(isset($chole_analyse_customer) && !empty($chole_analyse_customer) && !isset($chole_analyse)){
            $cholesterin_id = $chole_analyse_customer['relation_id'];
        }elseif(isset($chole_analyse) && !empty($chole_analyse) && empty($chole_analyse_customer)){
            $cholesterin_id = $chole_analyse['relation_id'];
        }else{
            $cholesterin_id = 0;
        }
        if(!empty($cholesterin_id)){
            $chole = db('RobotChole')
                ->where(['cholesterin_id'=>$cholesterin_id])
                ->order('create_time desc')->field('cholesterin,create_time')->find();
            if($chole){
                $return_data['chole_data'] = $chole;
            }else{
                $return_data['chole_data'] = ['cholesterin'=>0,'create_time'=>0];
            }
        }else{
            $return_data['chole_data'] = ['cholesterin'=>0,'create_time'=>0];
        }
        //尿酸数据
        if(!empty($where)){
            $where['analyse_type'] = 'uric';
            $uric_analyse = db('RobotAnalyse')->where($where)->order('create_time desc')->find();
        }
        if(!empty($customer_id)) {
            $orwhere = ['customer_id' => $customer_id, 'analyse_type' => 'uric'];
            $uric_analyse_customer = db('RobotAnalyse')->where($orwhere)->order('create_time desc')->find();
        }
        if(isset($uric_analyse_customer) && !empty($uric_analyse_customer) &&
            isset($uric_analyse) && !empty($uric_analyse)){
            if($uric_analyse_customer['create_time'] > $uric_analyse['create_time']){
                $uric_id = $uric_analyse_customer['relation_id'];
            }else{
                $uric_id = $uric_analyse['relation_id'];
            }
        }elseif(isset($uric_analyse_customer) && !empty($uric_analyse_customer) && !isset($uric_analyse)){
            $uric_id = $uric_analyse_customer['relation_id'];
        }elseif(isset($uric_analyse) && !empty($uric_analyse) && empty($uric_analyse_customer)){
            $uric_id = $uric_analyse['relation_id'];
        }else{
            $uric_id = 0;
        }
        if(!empty($uric_id)){
            $uric = db('RobotUric')
                ->where(['uric_id'=>$uric_id])
                ->order('create_time desc')->field('uric_acid,create_time')->find();
            if($uric){
                $return_data['uric_data'] = $uric;
            }else{
                $return_data['uric_data'] = ['uric_acid'=>0,'create_time'=>0];
            }
        }else{
            $return_data['uric_data'] = ['uric_acid'=>0,'create_time'=>0];
        }
        //血压数据
        if(!empty($where)){
            $where['analyse_type'] = 'blood';
            $blood_analyse = db('RobotAnalyse')->where($where)->order('create_time desc')->find();
        }
        if(!empty($customer_id)) {
            $orwhere = ['customer_id' => $customer_id, 'analyse_type' => 'blood'];
            $blood_analyse_customer = db('RobotAnalyse')->where($orwhere)->order('create_time desc')->find();
        }
        if(isset($blood_analyse_customer) && !empty($blood_analyse_customer) &&
            isset($blood_analyse) && !empty($blood_analyse)){
            if($blood_analyse_customer['create_time'] > $blood_analyse['create_time']){
                $blood_id = $blood_analyse_customer['relation_id'];
            }else{
                $blood_id = $blood_analyse['relation_id'];
            }
        }elseif(isset($blood_analyse_customer) &&!empty($blood_analyse_customer) && !isset($blood_analyse)){
            $blood_id = $blood_analyse_customer['relation_id'];
        }elseif(isset($blood_analyse) && !empty($blood_analyse) && !isset($blood_analyse_customer)){
            $blood_id = $blood_analyse['relation_id'];
        }else{
            $blood_id = 0;
        }
        if(!empty($blood_id)){
            $blood = db('RobotBlood')
                ->where(['blood_id'=>$blood_id])
                ->order('create_time desc')->field('systolic,diastolic,create_time')->find();
            if($blood){
                if(isset($return_data['blood_data'])){
                    if($blood['create_time'] > $return_data['blood_data']['create_time']){
                        $return_data['blood_data'] = $blood;
                    }
                }else{
                    $return_data['blood_data'] = $blood;
                }
            }
        }
        if(!isset($return_data['blood_data'])){
            $return_data['blood_data'] = ['systolic' => 0,'diastolic' => 0,'create_time' => 0];
        }
        //体温数据
        if(!empty($where)){
            $where['analyse_type'] = 'exergen';
            $exer_analyse = db('RobotAnalyse')
                ->where($where)
                ->field('relation_id')
                ->order('create_time desc')->select();
        }
        if(!empty($customer_id)){
            $orwhere = ['customer_id' => $customer_id, 'analyse_type' => 'exergen'];
            $exer_analyse_customer = db('RobotAnalyse')
                ->where($orwhere)->field('relation_id')
                ->order('create_time desc')->select();
        }
        $ids = [];
        if(isset($exer_analyse_customer) && !empty($exer_analyse_customer) &&
            isset($exer_analyse) && !empty($exer_analyse)){
            foreach($exer_analyse_customer as $key=>$val){
                $ids[] = $val['relation_id'];
            }
            foreach($exer_analyse as $key=>$value){
                $ids[] = $value['relation_id'];
            }
        }elseif(isset($exer_analyse_customer) && !empty($exer_analyse_customer)
            && (!isset($exer_analyse) || empty($exer_analyse))){
            foreach($exer_analyse_customer as $key=>$val){
                $ids[] = $val['relation_id'];
            }
        }elseif(isset($exer_analyse) && !empty($exer_analyse)
            && (!isset($exer_analyse_customer) || empty($exer_analyse_customer))){
            foreach($exer_analyse as $key=>$value){
                $ids[] = $value['relation_id'];
            }
        }

        if(!empty($ids)){
            $exer = db('RobotExergen')
                ->where(['exergen_id'=>['in',$ids],'temperature_type'=>1])//体温
                ->order('create_time desc')->field('temperature,create_time')->find();
            if($exer){
                $return_data['exer_data'] = $exer;
            }else{
                $return_data['exer_data'] = ['temperature'=>0,'create_time'=>0];
            }
        }else{
            $return_data['exer_data'] = ['temperature'=>0,'create_time'=>0];
        }
        if(empty($watch_imei)){
            $this->ajax_return('11252','dose not bind watch',$return_data);
        }
        $this->ajax_return('200','success',$return_data);
    }

     /**
    * 我的设备
    */
    public function device_list(){
        $member_id = $this->member_info['member_id'];
        //获取手表编码
        $watch_robot_data = array();
        $watch_imei = model('member_info')->where(['member_id'=>$member_id])
            ->field('watch_imei,watch_name')->find();
        //获取机器人编码
        $family_ids = model('family_member')->where(array('member_id' => $member_id))
            ->order('create_time desc')->column('family_id');
        
        $family_ids_str = implode(',',$family_ids);
        $condition = array('robot_imei'=>array('neq',''),'state' => 1,'family_id' => array('IN',$family_ids_str));
        $family_list = $this->family_model->where($condition)->field('family_id,family_name,robot_imei')->select()->toArray();
        
        $watch_robot_data['watch_imei'] = !empty($watch_imei['watch_imei']) ? $watch_imei['watch_imei'] : "";
        $watch_robot_data['watch_name'] = !empty($watch_imei['watch_name']) ? $watch_imei['watch_name'] : "";
        $watch_robot_data['robot_imei'] = $family_list;

        $this->ajax_return('200','success',$watch_robot_data);
    }

    /**
     * 获取手表的联系人列表
     */
    public function member_list(){
        //获取当前用户绑定的手表编码
        $watch_imei = model('MemberInfo')
            ->where(['member_id'=>$this->member_info['member_id']])
            ->value('watch_imei');
        if(empty($watch_imei)){
            $this->ajax_return('11664','please bind watch');
        }
        $member = model('WatchMember')->where(['watch_imei' => $watch_imei])->select();
        if(!$member){
            $this->ajax_return('11690','no contact');
        }
        $member = $member->toArray();
        foreach($member as $k=>$v){
            if (!empty($member[$k]['member_avatar'])) {
                $member[$k]['member_avatar'] = strexists($member[$k]['member_avatar'],'http')
                    ? $member[$k]['member_avatar']
                    : $this->base_url . '/uploads/avatar/' . $member[$k]['member_avatar'];
            }else{
                $member[$k]['member_avatar'] = '';
            }
        }
        $this->ajax_return('200','success',$member);
    }

    /**
     * 添加手表联系人
     */
    public function add_watch_member(){
        //获取当前用户绑定的手表编码
        $watch_imei = model('MemberInfo')
            ->where(['member_id'=>$this->member_info['member_id']])
            ->value('watch_imei');
        if(empty($watch_imei)){
            $this->ajax_return('11663','please bind watch');
        }
        $count = model('WatchMember')->where(['watch_imei' => $watch_imei])->count();
        if($count == 10){
            $this->ajax_return('11666','max count 10');
        }
        $data = [];
        $param = input('param.');
        if(empty(trim($param['member_name']))){
            $this->ajax_return('11660','empty watch_member_name');
        }
        $length = words_length(trim($param['member_name']));
        if($length < 2 || $length > 8){
            $this->ajax_return('11664','invalid watch_member_name length');
        }
        $data['member_name'] = trim($param['member_name']);
        if(empty(trim($param['member_mobile'])) || !is_mobile(trim($param['member_mobile']))){
            $this->ajax_return('11661','invalid watch_member_mobile');
        }
        $m_count = model('WatchMember')
            ->where(['member_mobile' => trim($param['member_mobile']),'watch_imei'=> $watch_imei])
            ->count();
        if($m_count > 0){
            $this->ajax_return('11665','the member is save');
        }
        $data['member_mobile'] = trim($param['member_mobile']);
        $file = request()->file('member_avatar');
        if (!is_null($file)) {
            $info = $file->rule('uniqid')->validate(['size' => 156780, 'ext' => 'jpg,png,gif,jpeg'])
                ->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'avatar');
            if ($info) {
                $data['member_avatar'] = $info->getFilename();
            } else {
                $this->ajax_return('10043',$file->getError());
            }
        }
        $data['watch_imei'] = $watch_imei;
        $avatar_img = "123456789.png";
        $avatar = !empty($data['member_avatar']) ? base64_encode($data['member_avatar']) : base64_encode($avatar_img);
        $member = [
            'device_imei' => $watch_imei,
            'device_family_number' => $param['member_mobile'],
            'device_family_name' => $param['member_name'],
            'head' => $avatar
        ];
        //添加手表联系人同步
        $watch = new Watch();
        $result = $watch->send_request($member,'10110');
        if(isset($result['result']) && $result['result'] !== 1 || $result < 1){
            $this->ajax_return('11099',$result);
        }
        $data['relative_id'] = !empty($result['relative_id']) ? $result['relative_id'] : '';
        $watch_member = model('WatchMember')->insertGetId($data);
        if(!$watch_member){
            $del_res = $watch->send_request($member,'10111');
            if(!$del_res){
                $this->ajax_return('11099',$del_res);
            }
            $this->ajax_return('11662','failed to save data');
        }
        $this->ajax_return('200','success');
    }

    /**
     * 获取要修改的联系人信息
     */
    public function get_member_info(){
        //获取当前用户绑定的手表编码
        $watch_imei = model('MemberInfo')
            ->where(['member_id'=>$this->member_info['member_id']])
            ->value('watch_imei');
        if(!$watch_imei){
            $this->ajax_return('11663','please bind watch');
        }
        $wm_id = input('param.wm_id');
        if(empty(trim($wm_id))){
            $this->ajax_return('11683','empty wm_id');
        }
        $member_info = model('WatchMember')->where(['wm_id'=>trim($wm_id)])->find()->toArray();
        if (!empty($member_info['member_avatar'])) {
            $member_info['member_avatar'] = strexists($member_info['member_avatar'],'http')
                ? $member_info['member_avatar'] :
                $this->base_url . '/uploads/avatar/' . $member_info['member_avatar'];
        }else{
            $member_info['member_avatar'] = '';
        }
        $this->ajax_return('200','success',$member_info);
    }

    /**
     * 修改手表联系人信息
     */
    public function edit_watch_member(){
        //获取当前用户绑定的手表编码
        $watch_imei = model('MemberInfo')
            ->where(['member_id'=>$this->member_info['member_id']])
            ->value('watch_imei');
        if(!$watch_imei){
            $this->ajax_return('11663','please bind watch');
        }
        $where = $member = $data = [];
        $data['device_imei'] = $watch_imei;
        $param = input('param.');
        if(empty(trim($param['wm_id']))){
            $this->ajax_return('11683','empty wm_id');
        }
        $where['wm_id'] = trim($param['wm_id']);
        if(empty(trim($param['relative_id']))){
            $this->ajax_return('11670','empty relative_id');
        }
        $where['relative_id'] = trim($param['relative_id']);
        $data['relative_id'] = trim($param['relative_id']);

        $member_info = model('WatchMember')->where($where)->find();
        if(!$member_info){
            $this->ajax_return('11680','the member is not found');
        }

        $m_count = model('WatchMember')
            ->where(['member_mobile' => trim($param['member_mobile']),'watch_imei'=> $watch_imei,'wm_id' =>['neq',trim($param['wm_id'])] ])
            ->count();
        if($m_count > 0){
            $this->ajax_return('11685','the member has existed');
        }

        if(empty(trim($param['member_name']))){
            $this->ajax_return('11681','empty member_name');
        }
        $length = words_length(trim($param['member_name']));
        if($length < 2 || $length > 8){
            $this->ajax_return('11664','invalid watch_member_name length');
        }
        $data['device_family_name'] = trim($param['member_name']);
        $member['member_name'] = trim($param['member_name']);
        if(empty(trim($param['member_mobile'])) || !is_mobile(trim($param['member_mobile']))){
            $this->ajax_return('11682','invalid member_mobile');
        }
        $data['device_family_number'] = trim($param['member_mobile']);
        $member['member_mobile'] = trim($param['member_mobile']);
        //头像
        $file = request()->file('member_avatar');
        if (!is_null($file)) {
            $info = $file->rule('uniqid')->validate(['size' => 156780, 'ext' => 'jpg,png,gif,jpeg'])
                ->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'avatar');
            if ($info) {
                $member['member_avatar'] = $info->getFilename();
                $data['head'] = base64_encode($info->getFilename());
            } else {
                $this->ajax_return('10043',$file->getError());
            }
        }
        //同步手表联系人
        $watch = new Watch();
        $result = $watch->send_request($data,'10112');
        if(!$result){
            $this->ajax_return('11099',$watch->get_error());
        }
        $watch_member = model('WatchMember')->where($where)->update($member);
        if($watch_member === false){
            $del_res = $watch->send_request($data,'10111');
            if(!$del_res){
                $this->ajax_return('11099',$watch->get_error());
            }
            $this->ajax_return('11684','update data fail');
        }
        $this->ajax_return('200','success');
    }

    /**
     * 删除手表联系人
     */
    public function del_watch_member(){
        //获取当前用户绑定的手表编码
        $watch_imei = model('MemberInfo')
            ->where(['member_id'=>$this->member_info['member_id']])
            ->value('watch_imei');
        if(!$watch_imei){
            $this->ajax_return('11663','please bind watch');
        }
        $data = [];
        $relative_id = input('param.relative_id');
        if(empty(trim($relative_id))){
            $this->ajax_return('11670','empty relative_id');
        }
        $data['relative_id'] = $relative_id;
        $member_id = input('param.wm_id');
        if(empty($member_id)){
            $this->ajax_return('11671','empty wm_id');
        }
        $member_mobile = input('param.member_mobile');
        if(empty(trim($member_mobile)) || !is_mobile(trim($member_mobile))){
            $this->ajax_return('11674','invalid member_mobile');
        }
        $where = [
            'relative_id' => trim($relative_id),
            'wm_id' => $member_id,
            'member_mobile' => trim($member_mobile)
        ];
        $member_mobile = model('WatchMember')
            ->where($where)
            ->value('member_mobile');
        if(!$member_mobile){
            $this->ajax_return('11672','the member is not found');
        }
        $data['device_family_number'] = $member_mobile;
        $data['device_imei'] = $watch_imei;
        $watch = new Watch();
        $result = $watch->send_request($data,'10111');
        if(!$result){
            $this->ajax_return('11099',$result);
        }
        $del = model('WatchMember')
            ->where(['relative_id' => $relative_id,'wm_id' => $member_id])
            ->delete();
        if($del === false){
            $member_name = model('WatchMember')
                ->where(['relative_id' => $relative_id,'wm_id' => $member_id])
                ->value('member_name');
            $data['member_name'] = $member_name;
            $add_res = $watch->send_request($data,'10110');
            if(!$add_res){
                $this->ajax_return('11099',$watch->get_error());
            }
            $this->ajax_return('11673','del member false');
        }
        $this->ajax_return('200','success');
    }

    /**
     * 获取当前手表设备信息
     */
    public function get_watch_info(){
        //获取当前用户绑定的手表编码
        $watch_imei = model('MemberInfo')
            ->where(['member_id'=>$this->member_info['member_id']])
            ->value('watch_imei');
        if(!$watch_imei){
            $this->ajax_return('11673','please bind watch');
        }
        $where = ['member_id' => $this->member_info['member_id'],'watch_imei' => $watch_imei];
        $watch_info = model('MemberInfo')->where($where)
            ->field('member_id,member_sex,birthday,member_weight,member_height,watch_name,watch_imei')
            ->find()->toArray();
        $watch_info['birthday'] = date('Y-m-d',$watch_info['birthday']);
        $this->ajax_return('200','success',$watch_info);
    }

    /**
     * 修改设备信息
     */
    public function edit_device_info(){
        $watch_imei = model('MemberInfo')
            ->where(['member_id'=>$this->member_info['member_id']])
            ->value('watch_imei');
        if(!$watch_imei){
            $this->ajax_return('11663','please bind watch');
        }
        $data = [];
        $watch_info = [];
        $param = input('param.');
        if(empty(trim($param['watch_name']))){
            $this->ajax_return('11700','empty watch_name');
        }
        $data['watch_name'] = trim($param['watch_name']);
        $watch_info['device_name'] = trim($param['watch_name']);

        if(empty(trim($param['member_sex']))){
            $this->ajax_return('11701','empty member_sex');
        }
        $data['member_sex'] = trim($param['member_sex']);
        $watch_info['device_sex'] = trim($param['member_sex']);

        if(empty(trim($param['birthday']))){
            $this->ajax_return('11702','empty birthday');
        }
        $data['birthday'] = strtotime(trim($param['birthday']));
        $watch_info['device_birthday'] = strtotime(trim($param['birthday']));

        if(empty(trim($param['member_height'])) || intval($param['member_height']) > 280 || intval($param['member_height']) < 100){
            $this->ajax_return('11703','invalid member_height');
        }
        $data['member_height'] = trim($param['member_height']);
        $watch_info['device_height'] = trim($param['member_height']);

        if(empty(trim($param['member_weight'])) || intval($param['member_weight']) > 260 || intval($param['member_weight']) < 20){
            $this->ajax_return('11704','invalid member_weight');
        }
        $data['member_weight'] = trim($param['member_weight']);
        $watch_info['device_weight'] = trim($param['member_weight']);

        $where = ['member_id' => $this->member_info['member_id'],'watch_imei' => $watch_imei];
        $watch_infos = model('MemberInfo')->where($where)
            ->field('member_sex,birthday,member_weight,member_height,watch_name')
            ->find()->toArray();

        $watch = new Watch();
        $result = $watch->send_request($watch_info,"10121");
        if(!$result){
            $this->ajax_return('11099',$result);
        }
        $update_info = model('MemberInfo')->where($where)->update($data);
        if($update_info === false){
            $old_data = [];
            $old_data['device_name'] = $watch_infos['watch_name'];
            $old_data['device_height'] = $watch_infos['member_height'];
            $old_data['device_weight'] = $watch_infos['member_weight'];
            $old_data['device_sex'] = $watch_infos['member_sex'];
            $old_data['device_birthday'] = $watch_infos['birthday'];
            $result = $watch->send_request($old_data,"10121");
            if(!$result){
                $this->ajax_return('11099',$result);
            }
            $this->ajax_return('11705','update data fail');
        }
        $this->ajax_return('200','success');
    }

    /**
     * 查询手表编码是否存在
     */
    public function watch_search()
    {
        $watch_imei = input("post.watch_imei");
        if(empty($watch_imei)){
            $this->ajax_return("11570","empty watch_imei");
        }

        if(!preg_match("/\d{15}/",$watch_imei)){
            $this->ajax_return("11571","watch_imei false");
        }

        $watch_info = db("watch_imei")->where(['watch_imei'=>$watch_imei])->find();
        if(!$watch_info){
            $this->ajax_return("11572","invalid watch_imei");
        }else{
            $this->ajax_return("200","success");
        }
    }


}
