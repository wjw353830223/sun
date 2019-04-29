<?php
namespace app\robot\controller;
use think\Controller;
use Lib\Watch;
use think\db;
class Family extends Robotbase
{
    protected $family_model,$warns_model,$family_member_model;
    protected function _initialize(){
        parent::_initialize();
        $this->family_model = model('family');
        $this->warns_model = model('warns');
        $this->family_member_model = model('family_member');
    }

    /**
    * 家庭详情
    */
    public function family_info(){
        $robot_sn = $this->token_info['robot_sn'];
        $family_id = $this->family_model->where(['robot_imei'=>$robot_sn])->value('family_id');
       
        if (empty($family_id)) {
            $this->ajax_return('100070','invalid family_id');
        }

        $family_info = $this->family_model->where(array('family_id' => $family_id,'state' => 1))->find();

        if (empty($family_info)) {
            $this->ajax_return('100071','family state wrong');
        }

        $member_model = model('member');
        $has_count = $this->family_member_model->where(['family_id' => $family_id])->count();
        if ($has_count < 1) {
            $this->ajax_return('100072','family does not exist');
        }

        $member_list = $this->family_member_model->where(array('family_id' => $family_id))->order('id asc')->select();
        if (empty($member_list)) {
            $this->ajax_return('100073','empty data');
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
        $true_field_list = model('member')->where(array('member_id' => array('in',$true_ids_str)))->field('member_id,member_mobile,member_name,member_avatar')->select()->toArray();
        if (!empty($member_info['member_avatar'])) {
            $member_info['member_avatar'] = strexists($member_info['member_avatar'],'http') ? $member_info['member_avatar'] : $this->base_url . '/uploads/avatar/' . $member_info['member_avatar'];
        }else{
            $member_info['member_avatar'] = '';
        }
        foreach ($true_list as $key => $value) {
            foreach ($true_field_list as $field_key => $field_val) {
                if ($value['member_id'] == $field_val['member_id']) {
                    $true_list[$key]['member_name'] = $field_val['member_name'];
                    $true_list[$key]['member_mobile'] = $field_val['member_mobile'];
                    if(empty($field_val['member_avatar'])){
                        $true_list[$key]['member_info']['member_avatar'] = '';
                    }else{
                        $true_list[$key]['member_avatar'] = strexists($field_val['member_avatar'],'http') ? $field_val['member_avatar'] : $this->base_url . '/uploads/avatar/' . $field_val['member_avatar'];
                    }
                    
                    break;
                }
            }
        }

        $virtual_list = model('family_member_info')->where(array('info_id' => array('in',$virtual_ids_str)))->field('info_id,mobile,nickname,avatar,member_sex,member_age,member_height,member_weight')->select();
        $return_data = array();
        //家庭详细
        $family_field['family_id'] = $family_id;
        $family_field['family_name'] = $family_info['family_name'];
        $family_field['family_number'] = str_pad($family_id,6,"0",STR_PAD_LEFT);
        $family_field['member_count'] = $family_info['member_count'];
        $family_field['robot_imei'] = $family_info['robot_imei'];
       
        $affix = model('family_affix')->where(array('family_id' => $family_id,'is_use' => 1))->field('file_path,is_image')->find();
        if(empty($affix['file_path'])){
            $affix['file_path'] = '';
        }else{
            $affix['file_path'] = !in_array($affix['file_path'],['1','2','3']) ? $this->base_url.'/uploads/family/'.$affix['file_path'] : '';
        }
        
        $family_field['affix'] = $affix;
        $return_data['family_info'] = $family_field;

        //组合会员数据
        $member_info = array();
        foreach ($member_list as $key => $value) {
            $tmp = array();
            if ($value['is_member'] == 1) {

                foreach ($true_list as $true_key => $true_val) {
                    if ($true_val['member_id'] == $value['member_id']) {
                        $tmp['member_info']['member_id'] = $value['member_id'];
                        $tmp['member_info']['family_member_id'] = $value['id'];
                        $tmp['member_info']['is_member'] = $value['is_member'];
                        $tmp['member_info']['member_name'] = $true_val['member_name'];
                        $tmp['member_info']['member_mobile'] = $true_val['member_mobile'];
                        if(empty($true_val['member_avatar'])){
                            $tmp['member_info']['member_avatar'] = '';
                        }else{
                            $tmp['member_info']['member_avatar'] = strexists($true_val['member_avatar'],'http') ? $true_val['member_avatar'] : $this->base_url . '/uploads/avatar/' . $true_val['member_avatar'];
                        }
                       
                        $tmp['health_info']['member_sex'] = $true_val['member_sex'];
                        $tmp['health_info']['member_age'] = $true_val['member_age'];
                        $tmp['health_info']['member_height'] = $true_val['member_height'];
                        $tmp['health_info']['member_weight'] = $true_val['member_weight'];
                        
                        $member_info[] = $tmp;
                        break;
                    }
                }
            }else{

                foreach ($virtual_list as $virtual_key => $virtual_value) {
                    if ($virtual_value['info_id'] == $value['info_id']) {
                        $tmp['member_info']['member_id'] = 0;
                        $tmp['member_info']['family_member_id'] = $value['id'];
                        $tmp['member_info']['is_member'] = $value['is_member'];
                        $tmp['member_info']['member_name'] = $virtual_value['nickname'];
                        $tmp['member_info']['member_mobile'] = $virtual_value['mobile'];
                        if(empty($virtual_val['member_avatar'])){
                            $tmp['member_info']['member_avatar'] = '';
                        }else{
                            $tmp['member_info']['member_avatar'] = strexists($virtual_val['member_avatar'],'http') ? $virtual_val['member_avatar'] : $this->base_url . '/uploads/avatar/' . $virtual_val['member_avatar'];
                        }
                       
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
    * 获取健康数据
    */
    public function get_monitor(){
        $family_member_id = input('post.family_member_id',0,'intval');

        if ($family_member_id > 0) {
            $member_row = model('family_member')->where(array('id' => $family_member_id))->find();
            if (empty($member_row)) {
                //会员已退出家庭或不存在
                $this->ajax_return('100080','member is out of family');
            }
            if ($member_row['is_member']) {
                $watch_imei = model('member_info')->where(array('member_id' => $member_row['member_id']))->value('watch_imei');
            }else{
                $watch_imei = model('family_member_info')->where(array('info_id' => $member_row['info_id']))->value('watch_imei');
            }

        }

        if (empty($watch_imei)) {
            //未绑定手表
            $this->ajax_return('100081','dose not bind watch');
        }

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
            $this->ajax_return('100082','start_time nlt end_time');
        }
        $result = $watch->send_request($param,'10127');

        $sleep_data = $result['sleepList'];

        if(!$result){
            $this->ajax_return('100083',$watch->get_error());
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
            $this->ajax_return('100083',$watch->get_error());
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
            $this->ajax_return('100083',$watch->get_error());
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
        $result = $watch->send_request($param,'10120');
        if(!$result){
            $this->ajax_return('100083',$watch->get_error());
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

        $return_data = array();

        //增加接口默认数据，避免无数据产生时的错误
        $sleep_db_data = $sleep_model->where(array('watch_imei' => $watch_imei))->order('start_time desc')->field('sleep_time,sleep_grade')->find();
        $sleep_data = array('sleep_time' => '0','sleep_grade' => '5');
        if (!empty($sleep_db_data)) {
            $sleep_data = array('sleep_time' => $sleep_db_data['sleep_time'],'sleep_grade' => $sleep_db_data['sleep_grade']);
        }
        $return_data['sleep_data'] = $sleep_data;

        $avg_heart = $heart_model->where(array('watch_imei' => $watch_imei,'create_time' => array(array('EGT',$min_time),array('LT',$max_time))))->order('create_time desc')->column('avg_heart');
        $heart_data = array('avg_heart' => '0','min_heart' => '0','max_heart' => '0');
        if (!empty($avg_heart)) {
            $heart_data = array('avg_heart' => (string)round(array_sum($avg_heart) / count($avg_heart)),'min_heart' => min($avg_heart),'max_heart' => max($avg_heart));
        }else{
            $heart_db_data = $heart_model->where(array('watch_imei' => $watch_imei))->field('avg_heart,min_heart,max_heart')->find();
            if (!empty($heart_db_data)) {
                $heart_data = array('avg_heart' => $heart_db_data['avg_heart'],'min_heart' => $heart_db_data['min_heart'],'max_heart' => $heart_db_data['max_heart']);
            }
        }
        $return_data['heart_data'] = $heart_data;

        $blood_db_data = $blood_model->where(array('watch_imei' => $watch_imei))->order('create_time desc')->field('systolic,diastolic,create_time')->find();
        $blood_data = ['systolic' => '0','diastolic' => '0','create_time' => '0 :00'];
        if (!empty($blood_db_data)) {
            $blood_data = array('systolic' => $blood_db_data['systolic'],'diastolic' => $blood_db_data['diastolic'],'create_time' => date('h :ia',$blood_db_data['create_time']));
        }
        $return_data['blood_data'] = $blood_data;

        $sport_db_data = $sport_model->where(array('watch_imei' => $watch_imei))->order('create_time desc')->field('step_num,calory')->find();
        $sport_data = array('step_num' => '0','calory' => '0');
        if (!empty($sport_db_data)) {
            $sport_data = array('step_num' => $sport_db_data['step_num'],'calory' => $sport_db_data['calory']);
        }
        $return_data['sport_data'] = $sport_data;
        $this->ajax_return('200','success',$return_data);
    }

    /**
    * 获取位置
    */
    public function get_location(){
        $family_member_id = input('post.family_member_id',0,'intval');

        if ($family_member_id > 0) {
            $member_row = model('family_member')->where(array('id' => $family_member_id))->find();
            if (empty($member_row)) {
                $this->ajax_return('100090','family_member dose not exsit');
            }
            if ($member_row['is_member']) {
                $watch_imei = model('member_info')->where(array('member_id' => $member_row['member_id']))->value('watch_imei');
            }else{
                $watch_imei = model('family_member_info')->where(array('info_id' => $member_row['info_id']))->value('watch_imei');
            }

        }

        if (empty($watch_imei)) {
            $this->ajax_return('100091','dose not bind watch');
        }

        $watch = new Watch();
        $param = [
            'device_imei' => $watch_imei
        ];
        //upload_location
        $result = $watch->send_request($param,'10119');
        if(!$result){
            $this->ajax_return('100093',$watch->get_error());
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
            $this->ajax_return('100092','start_time nlt end_time');
        }
        $get_location = $watch->send_request($param,'10123');
        if (!$get_location) {
            $this->ajax_return('100093',$watch->get_error());
        }

        $location_data = $get_location;
        $result = [
            'lng' => !empty($location_data['locationList']) ? $location_data['locationList'][0]['lng'] : '',
            'lat' => !empty($location_data['locationList']) ? $location_data['locationList'][0]['lat'] : ''
        ];
       $this->ajax_return('200','success',$result);
    }

    /**
    * 获取历史健康数据
    */
    public function get_monitor_history(){
        $family_member_id = input('post.family_member_id',0,'intval');

        // 获取watch_imei
        if ($family_member_id > 0) {

            $member_row = model('family_member')->where('id',$family_member_id)->find();

            if (empty($member_row)) {
                $this->ajax_return('100100','family_member dose not exsit');
            }

            if ($member_row['is_member']) {
                $watch_imei = model('member_info')->where('member_id',$member_row['member_id'])->value('watch_imei');
            } else {
                $watch_imei = model('family_member_info')->where('info_id',$member_row['info_id'])->value('watch_imei');
            }

        } 

        if (empty($watch_imei)) {
            $this->ajax_return('100101','dose not bind watch');
        }

        // 获取时间
        // $date = (int)input('post.data_time');
        // if (!is_int($date) || empty($date)) {
        //     $this->ajax_return('100102','invalid data_time');
        // }

        // $date = strtotime(date('Y-m-d',$date));
        // $min_time = $date;
        // $max_time = $date + 86400;


        $sleep_model = db('health_sleep');
        $heart_model = db('health_heart');
        $blood_model = db('health_blood');
        $sport_model = db('health_sport');
        $watch = new Watch();

        // //更新睡眠数据
        // $param = [
        //     'device_imei' => $watch_imei,
        //     'startTime' => date('Y-m-d H:i:s',$min_time),
        //     'endTime' => date('Y-m-d H:i:s',$max_time),
        //     'pageIndex' => 1
        // ];

        // if ((!empty($param['startTime']) && !empty($param['endTime'])) && $param['startTime'] > $param['endTime'] ) {
        //     $this->ajax_return('100103','start_time nlt end_time');
        // }

        // $result = $watch->send_request($param,'10127');
        // $sleep_data = $result['sleepList'];

        // if(!$result){
        //     $this->ajax_return('100104',$watch->get_error());
        // }

        // if ($result && !empty($sleep_data)) {
        //     //获取历史数据中时间最大的一个
        //     $start_time = $sleep_model->where(['watch_imei' => $watch_imei])->order('start_time desc')->value('start_time');
        //     if (empty($start_time)){
        //        $start_time = 0;
        //     }

        //     //写入比历史数据时间大的最近数据
        //     $insert_arr = array();
        //     foreach ($sleep_data as $key => $value) {

        //         if (strtotime($value['startTime']) > $start_time) {
        //             $tmp = array();
        //             $tmp['row_id'] = $value['id'];
        //             $tmp['watch_imei'] = $watch_imei;
        //             $tmp['start_time'] = strtotime($value['startTime']);
        //             $tmp['sleep_time'] = $value['sleepTime'];
        //             $tmp['deep_time'] = $value['deepTime'];
        //             $tmp['unsleep_time'] = $value['noSleepTime'];
        //             $tmp['sleep_grade'] = sleep_grade($value['sleepTime'],$value['deepTime']);
        //             $insert_arr[] = $tmp;
        //         }
        //    }
        //    $sleep_model->insertALL($insert_arr);
        // }

        // //更新心率数据
        // $param = [
        //     'device_imei' => $watch_imei,
        //     'startTime' => date('Y-m-d H:i:s',$min_time),
        //     'endTime' => date('Y-m-d H:i:s',$max_time),
        //     'pageIndex' => 1
        // ];
        // $result = $watch->send_request($param,'10117');
        // if(!$result){
        //     $this->ajax_return('100104',$watch->get_error());
        // }
        // $heart_data = $result['heartList'];

        // if ($result && !empty($heart_data)) {
        //     //获取历史数据中时间最大的一个
        //     $create_time = $heart_model->where(['watch_imei' => $watch_imei])->order('create_time desc')->value('create_time');
        //     if (empty($create_time)) {
        //        $create_time = 0;
        //     }

        //     //写入比历史数据时间大的最近数据
        //     $insert_arr = array();
        //     foreach ($heart_data as $key => $value) {
        //         if (strtotime($value['date']) > $create_time) {
        //             $tmp = array();
        //             $tmp['watch_imei'] = $watch_imei;
        //             $tmp['create_time'] = strtotime($value['date']);
        //             $tmp['avg_heart'] = $value['avgHeart'];
        //             $tmp['min_heart'] = $value['minHeart'];
        //             $tmp['max_heart'] = $value['maxHeart'];
        //             $insert_arr[] = $tmp;
        //         }
        //    }
        //    $heart_model->insertALL($insert_arr);
        // }

        // //更新血压数据
        // $param = [
        //     'device_imei' => $watch_imei,
        //     'startTime' => date('Y-m-d H:i:s',$min_time),
        //     'endTime' => date('Y-m-d H:i:s',$max_time),
        //     'pageIndex' => 1
        // ];
        // $result = $watch->send_request($param,'10115');
        // if(!$result){
        //     $this->ajax_return('100104',$watch->get_error());
        // }
        // $heart_data = $result['bloPrsInfoList'];

        // if ($result && !empty($blood_data)) {
        //     //获取历史数据中时间最大的一个
        //     $create_time = $blood_model->where(['watch_imei' => $watch_imei])->order('create_time desc')->value('create_time');
        //     if (empty($create_time)) {
        //        $create_time = 0;
        //     }
        //     //写入比历史数据时间大的最近数据
        //     $insert_arr = array();
        //     foreach ($blood_data as $key => $value) {
        //         if (strtotime($value['time']) > $create_time) {
        //             $tmp = array();
        //             $tmp['watch_imei'] = $watch_imei;
        //             $tmp['create_time'] = strtotime($value['time']);
        //             $tmp['systolic'] = $value['systolicVal'];
        //             $tmp['diastolic'] = $value['diastolicVal'];
        //             $tmp['report_desc'] = $value['aysRptResume'];
        //             $tmp['report_url'] = $value['aysRptUrl'];
        //             $insert_arr[] = $tmp;
        //         }
        //    }
        //    $blood_model->insertALL($insert_arr);
        // }

        // //更新运动数据
        // $result = $watch->send_request($param,'10120');
        // if(!$result){
        //     $this->ajax_return('100104',$watch->get_error());
        // }

        // $sport_data = $result['sportInfoList'];

        // if ($result && !empty($sport_data)) {
        //     //获取历史数据中时间最大的一个
        //     $sport_info = $sport_model->where(['watch_imei' => $watch_imei])->order('create_time desc')->field('sport_id,create_time,step_num')->find();
        //     if (empty($sport_info)) {
        //        $create_time = 0;
        //     }
        //     //运动数据每天只返回1条
        //     $sport_data_info = $sport_data[0];

        //     $insert_arr = array();
        //     if (strtotime($sport_data_info['date']) > $sport_info['create_time']) {
        //         $insert_arr['watch_imei'] = $watch_imei;
        //         $insert_arr['create_time'] = strtotime($sport_data_info['date']);
        //         $insert_arr['step_num'] = $sport_data_info['qty'];
        //         $insert_arr['calory'] = $sport_data_info['calory'];
        //         $sport_model->insert($insert_arr);
        //     }

        //     if (strtotime($sport_data_info['date']) == $sport_info['create_time'] && $sport_data_info['qty'] > $sport_info['step_num']) {
        //         $insert_arr['step_num'] = $sport_data_info['qty'];
        //         $insert_arr['calory'] = $sport_data_info['calory'];
        //         $sport_model->where(['watch_imei' => $watch_imei,'sport_id' => $sport_info['sport_id']])->update($insert_arr);
        //     }
        // }

        $return_data = array();

        //增加接口默认数据，避免无数据产生时的错误
        $sleep_db_data = $sleep_model->where(array('watch_imei' => $watch_imei))->field('sleep_time,sleep_grade')->select();
       
        if (!empty($sleep_db_data)) {
            $sleep_data = array();
            foreach ($sleep_db_data as $key => $value) {
                $tmp = array();
                $tmp['sleep_time'] = $value['sleep_time'];
                $tmp['sleep_grade'] = $value['sleep_grade'];
                $tmp['start_time'] = $value['start_time'];
                $sleep_data[] = $tmp; 
            }
        }else{
            $sleep_data[] = array('sleep_time' => '0','sleep_grade' => '5','start_time' => '0 :00');
        }
        $return_data['sleep_data'] = $sleep_data;

        $heart_db_data = $heart_model->where(array('watch_imei' => $watch_imei))->field('avg_heart,min_heart,max_heart,create_time')->select();

        if (!empty($heart_db_data)) {
            $heart_data = array();
            foreach ($heart_db_data as $key => $value) {
                $tmp = array();
                $tmp['avg_heart'] = $value['avg_heart'];
                $tmp['min_heart'] = $value['min_heart'];
                $tmp['max_heart'] = $value['max_heart'];
                $tmp['create_time'] = $value['create_time'];
                // $heart_data[] = array('avg_heart' => $value['avg_heart'],'min_heart' => $value['min_heart'],'max_heart' => $value['max_heart'],'create_time'=>$value['create_time']);
                $heart_data[] = $tmp; 
            }
        } else {
            $heart_data[] = array('avg_heart' => '0','min_heart' => '0','max_heart' => '0','create_time' => '0 :00');
        }

        $return_data['heart_data'] = $heart_data;


        $blood_db_data = $blood_model->where(array('watch_imei' => $watch_imei))->field('systolic,diastolic,create_time')->select();

        if (!empty($blood_db_data)) {
            $blood_data = array();
            foreach ($blood_db_data as $key => $value) {
                $tmp = array();
                $tmp['systolic'] = $value['systolic'];
                $tmp['diastolic'] = $value['diastolic'];
                $tmp['create_time'] = $value['create_time'];
                $blood_data[] = $tmp;
            }
            
        } else {
            $blood_data[] = array('systolic' => '0','diastolic' => '0','create_time' => '0 :00');
        }

        $return_data['blood_data'] = $blood_data;

        $sport_db_data = $sport_model->where(array('watch_imei' => $watch_imei))->field('step_num,calory,create_time')->select();
        if (!empty($sport_db_data)) {
            $sport_data = array();
            foreach ($sport_db_data as $key => $value) {
                $tmp = array();
                $tmp['step_num'] = $value['step_num'];
                $tmp['calory'] = $value['calory'];
                $tmp['create_time'] = $value['create_time'];
                $sport_data[] = $tmp;
            }
        }else{
             $sport_data[] = array('step_num' => '0','calory' => '0');
        }
        $return_data['sport_data'] = $sport_data;

        $this->ajax_return('200','success',$return_data);
    }

}