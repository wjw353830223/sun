<?php
/*
 * 第三方检测机构
 */
namespace app\open\controller;

use Qiniu\Auth;
use think\Cache;

class ThirdReport extends ThirdAuth {

	protected $third_report_model;
    public function _initialize() {
        parent::_initialize();
        $this->third_report_model = model('ThirdReport');
	}
    public function get_member_report(){
        $access_key = input('param.access_key','','trim');
        if (empty($access_key)) {
            $this->ajax_return('11700', 'invalid access_key');
        }
        $report = $this->third_report_model->get_report($access_key);
        if(empty($report['file_path'])){
            $this->ajax_return('11381', 'empty data');
        }
        $download_url = $this->get_url($report['file_path']);
        $str_contents = file_get_contents($download_url);
        $result = json_decode($str_contents,true);
        $this->ajax_return('200','success',['result' => $result]);
    }
    /**
     * 获取七牛云私密文件访问token
     * @param $file_name 文件名称
     * @return string 下载文件地址
     */
    private function get_url($file_name = ''){
        $access_key = config('qiniu.access_key');
        $secret_key = config('qiniu.secret_key');
        $auth = new Auth($access_key, $secret_key);
        $url = config('qiniu.buckets')['report']['domain'] . '/' . $file_name;
        return $auth->privateDownloadUrl($url);
    }
}