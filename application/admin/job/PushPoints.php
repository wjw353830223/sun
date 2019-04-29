<?php
namespace app\admin\job;
/**
 * 推送积分变动到合作伙伴积分系统
 * 启动命令： php think queue:listen --queue points --delay 0 --tries 3 -v
 * Created by JiweiWang.
 * User: Administrator
 * Date: 2018/4/27
 * Time: 14:28
 */

use Lib\Http;
use think\queue\Job;
use think\Request;
class PushPoints{

    const DELAY_TIME_FIRST = 300;//第1次轮训推送失败 5分钟后再次尝试
    const DELAY_TIME_SECOND = 7200;//第2次轮训推送失败 2小时后再次尝试
    public function fire(Job $job, $data)
    {
        $request = Request::instance([]);
        $request->module("common");
        $model = model('JobFail');
        $pre_data = $data = json_decode($data,true);
        $parterner_id = array_shift($data);
        $attempts = $job->attempts();
        if($attempts==1){
            $result = $this->push_points($parterner_id,$data,true);
        }else{
            $result = $this->push_points($parterner_id,$data);
        }
        if(is_array($result)){
            $res = json_decode($result['content'],true);
            if ($res['code']=='200') {
                $member_id = array_shift($data);
                $points = (int)$data['score'];
                if($data['type']=='0'){
                    model('Member')->where(['member_id'=>$member_id])->setInc('parterner_reserved_points',$points);
                }
                if($data['type']=='1'){
                    model('Member')->where(['member_id'=>$member_id])->setDec('parterner_reserved_points',$points);
                }
                $job->delete();
                return;
            }
        }
        //第3次轮训推送失败，删除任务 并记录失败原因
        if($attempts > 2){
            $model->add_record($pre_data ,json_encode($result));
            $job->delete();
            return;
        }
        if($attempts > 1){
            $job->release(self::DELAY_TIME_SECOND);
            return;
        }
        if($attempts == 1){
            $job->release(self::DELAY_TIME_FIRST);
            return;
        }
    }

    /**
     * 该方法用于接收任务执行失败的通知，你可以发送邮件给相应的负责人员
     * @param $jobData
     */
    //public function failed($jobData){
        //send_mail_to_somebody();
        //$request = Request::instance([]);
        //$request->module("common");
        //$model = model('JobFail');
        //$model->add_record($data,json_encode($result));
    //}

    /**
     * @param $parterner_id
     * @param $pre_data
     * @param bool $parterner_reserved_points_change 是否改变$parterner_reserved_points字段的值
     * @return array|bool|string
     */
    private function push_points($parterner_id,$pre_data,$parterner_reserved_points_change=false)
    {
        $request = Request::instance([]);
        $request->module("common");
        $parterner = model('ThirdAuthToken')->field('parterner_appid,parterner_public_key,points_push_url')->getById($parterner_id);
        $parterner_appid = $parterner['parterner_appid'];
        $parterner_public_key = $parterner['parterner_public_key'];
        $points_push_url = $parterner['points_push_url'];
        if(!$points_push_url){
            return false;
        }
        $member_id = array_shift($pre_data);
        //修正接口时间戳参数
        $pre_data['timestamp'] = time();
        $data = array(
            'appid' => $parterner_appid,
            'data' => $pre_data
        );
        $data = json_encode($data);
        $pu_key = openssl_pkey_get_public($parterner_public_key);//这个函数可用来判断公钥是否是可用的
        openssl_public_encrypt($data,$encrypted,$pu_key);//公钥加密
        $encrypted = base64_encode($encrypted);

        if($parterner_reserved_points_change){
            try{
                if($pre_data['type']=='0'){
                    $res = model('Member')->where(['member_id'=>$member_id])->setDec('parterner_reserved_points',(int)$pre_data['score']);
                }
                if($pre_data['type']=='1'){
                    $res = model('Member')->where(['member_id'=>$member_id])->setInc('parterner_reserved_points',(int)$pre_data['score']);
                }
                if($res===false){
                    return false;
                }
            }catch (\Exception $e){
                return false;
            }
        }
        $result = Http::ihttp_post($points_push_url,["data"=>$encrypted]);
        return $result;
    }
}
