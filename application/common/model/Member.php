<?php
namespace app\common\model;
use think\Model;
class Member extends Model
{
	const AUTH_SUCCESS = 1;//实名认证通过
    const AUTH_DEFAULT = 0;//未实名认证
    /**
	* 会员详细关联
	*/
    public function info(){
        return $this->hasOne('MemberInfo','member_id','member_id');
    }

    public function has_info($mobile){
    	$member_id = $this->where(['member_mobile' => $mobile])->value('member_id');
    	$result = model('Member')->where(['member_id' => $member_id])->value('member_name');

    	if (!empty($result)) {
    		return true;
    	}else{
    		return false;
    	}
    }

    public function has_infos($mobile){
        $member_id = $this->where(['member_mobile' => $mobile])->value('member_id');
        $result = model('MemberInfo')->where(['member_id' => $member_id])->value('member_height');
        if (!empty($result)) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 更新member表中多个数据
     * @param $member_id
     * @param $points 下单所用的积分
     * @param $experience 获得的经验
     * @param $freeze_points 获得的冻结积分
     * @param int $predeposit 下单所用的余额
     * @return bool
     */
    public function save_all($member_id,$points,$experience,$freeze_points,$predeposit=0){
        $data = [];
        if(!empty($points)){
            $data['points'] = ['exp','points-'.$points];
        }
        if(!empty($experience)){
            $data['experience'] = ['exp','experience+'.$experience];
        }
        if(!empty($freeze_points)){
            $data['freeze_points'] = ['exp','freeze_points+'.$freeze_points];
        }
        if(!empty($predeposit)){
            $data['av_predeposit'] = ['exp','av_predeposit-'.$predeposit];
        }
        if(!empty($data)){
            $result = $this->where(['member_id'=>$member_id])->update($data);
            if($result === false){
                return false;
            }
        }
        return true;
    }

    /**
    *  消费商基本信息
    */
    public function basic_info($member_id){
        $member_info = $this->field('member_name,member_grade,member_avatar')->where(['member_id'=>$member_id])->find();

        $data = ['member_name'=>'','member_grade'=>'','grade_name' => '','member_avatar' => ''];
        if(!empty($member_info) && $member_info['member_grade'] > 6){
            $grade_array = ['7'=>'F','8'=>'E','9'=>'D','10'=>'C','11'=>'B','12'=>'A'];
            $grade_name = $grade_array[$member_info['member_grade']].'级消费商';
           
            $data = [
                'member_name'=>$member_info['member_name'],
                'member_grade'=>$member_info['member_grade'],
                'grade_name' => $grade_name,
                'member_avatar' => $member_info['member_avatar']
            ];
        }

        return $data;
    }

    /**
     * 增加（或者减少）用户账户余额
     * @param $pdc_member_id
     * @param $amount
     * @param bool $inc
     * @return int|true
     */
    public function member_av_predeposit_update($pdc_member_id,$amount,$inc = true){
       if($inc){
           return $this->where(['member_id'=>$pdc_member_id])->setInc('av_predeposit',$amount);
       }
       return $this->where(['member_id'=>$pdc_member_id])->setDec('av_predeposit',$amount);
    }
    public function member_get_info_by_id($member_id){
        $member_info = $this->where(['member_id'=>$member_id])->find();
        return $member_info;
    }

    /**
     * 会员资料接收数据处理
     * @param $param
     * @param $member_info
     * @return array $host_data
     */
    public function _initMemberInfo($param,$member_info){
        $member_name = trim($param['member_name']);
        if (empty($member_name)) {
            return ['code'=>'10040','msg'=>'invalid member_name'];
        }
        if (empty($param['mobile']) || !is_mobile($param['mobile'])) {
            return ['code'=>'10041','msg'=>'invalid mobile'];
        }
        if (isset($param['invite_code']) && !empty(trim($param['invite_code']))) {
            $invite_code = trim($param['invite_code']);
            //会员上级ID
            $member_id = model('MemberQrcode')
                ->where('invite_code|member_mobile','eq',$invite_code)
                ->value('member_id');
            if(empty($member_id)){
                $member_id = $this->where(['member_mobile'=>$invite_code])->value('member_id');
            }
            //邀请码为当前会员
            if($member_info['member_id'] == $member_id){
                return ['code'=>'10047','msg'=>'invalid invite_code'];
            }
            if(empty($member_id)){
                return ['code'=>'10048','msg'=>'the member_id is empty'];
            }
            $parent_id = $member_id;
        }
        if (empty($param['member_height']) && intval($param['member_height']) > 250) {
            return ['code'=>'10045','msg'=>'invalid member_height'];
        }
        if (empty($param['member_weight']) && intval($param['member_weight']) > 250) {
            return ['code'=>'10046','msg'=>'invalid member_weight'];
        }
        if (empty($param['birthday'])) {
            return ['code'=>'10049','msg'=>'invalid birthday'];
        }
        $host_data = [
            'member_name' => $member_name,
            'parent_id'=> isset($parent_id) && !empty($parent_id) ? $parent_id : ''
        ];
        return $host_data;
    }

    /**
     * 会员资料修改
     * @param $host_data
     * @param $info_data
     * @param $member_id
     * @return bool|true
     */
    public function update_member_info($host_data,$info_data,$member_id){
        $this->startTrans();
        model('MemberInfo')->startTrans();
        model('MemberQrcode')->startTrans();

        $has_info = $this->where(['member_id'=>$member_id])->count();
        if($has_info > 0){
            $member_result = $this->save($host_data, ['member_id' => $member_id]);
            if ($member_result === false) {
                return false;
            }
        }

        $info_result = model('MemberInfo')->where(['member_id'=>$member_id])->find();
        if(empty($info_result)){
            $info_data['member_id'] = $member_id;
            $info_result = model('MemberInfo')->save($info_data);
        }else{
            $info_result = model('MemberInfo')->save($info_data,['member_id'=>$member_id]);
        }

        if ($info_result === false) {
            $this->rollback();
            return false;
        }

        $mq_count = model('MemberQrcode')->where(['member_id'=>$member_id])->count();
        if($mq_count < 1){
            //生成二维码相关信息
            $invite_code = random_string(8);
            $member_mobile = $this->where(['member_id'=>$member_id])->value('member_mobile');
            $mq_data = [
                'member_id' => $member_id,
                'invite_code' => $invite_code,
                'member_mobile' => $member_mobile
            ];

            $result = model('MemberQrcode')->save($mq_data);
            if($result === false){
                $this->rollback();
                model('MemberInfo')->rollback();
                return false;
            }
        }

        $this->commit();
        model('MemberInfo')->commit();
        model('MemberQrcode')->commit();
        return true;
    }

}