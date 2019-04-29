<?php
namespace app\api\controller;
use Qiniu\Auth;

class Report extends Apibase
{
	//检测历史表
	protected $report_model;
	//七牛签名公钥
	protected $accessKey;
	//七牛签名私钥
	protected $secretKey;
	//检测报告绑定私密空间域名
	protected $report_url;
	protected function _initialize(){
    	parent::_initialize();
    	$this->accessKey = 'kVNnKJYWgyeIQz9u4u_QqpghJwPW1G681R655HYL';
        $this->secretKey = '8kQOa-wEYLl8Q7sb066qFgxNKcOW4GiZoB45T-pN';
        $this->report_url = 'http://report.healthywo.com/';
    	$this->report_model = model('MemberReport');
    }

    /**
    * 获取检测历史
    */
    public function index(){
    	$page = input('param.page',1,'intval');
    	$member_id = $this->member_info['member_id'];
        //数据分页
        $count = $this->report_model->where(['member_id' => $member_id])->count();
        if ($count < 1) {
            $this->ajax_return('11710','empty data');
        }

        $page_num = 5;
        $page_count = ceil($count / $page_num);
        $limit = 0;
        if ($page > 0) {
            $limit = ($page - 1) * $page_num;
        }else{
            $limit = 0;
        }

        $list = $this->report_model->where(['member_id' => $member_id])
        ->field('report_id,create_time')->order('create_time DESC')
        ->limit($limit,$page_num)->select()->toArray();

        foreach ($list as $key => $value) {
        	$list[$key]['report_name'] = 'MRA系统检测';
        }
         $this->ajax_return('200','success',$list);
    }

    /**
    * 获取检测报告内容
    */
    public function get_contents(){
    	$report_id = input('param.report_id',0,'intval');
    	if (empty($report_id)) {
    		$this->ajax_return('11730','invalid report id');
    	}

    	$member_id = $this->member_info['member_id'];

    	$file_path = $this->report_model->where(['member_id' => $member_id,'report_id' => $report_id])->value('file_path');
    	if (empty($file_path)) {
    		$this->ajax_return('11731','invalid report data');
    	}

    	$download_url = $this->get_url($file_path);

    	$str_contents = file_get_contents($download_url);

    	$arr_contents = array();
    	if (!empty($str_contents)) {
    		$arr_contents = json_decode($str_contents,true);
    	}
    	
    	$this->ajax_return('200','success',$arr_contents);
    }

    /**
    * 获取七牛云私密文件访问token
    * @param $file_name 文件名称
    * @return string 下载文件地址
    */
    private function get_url($file_name = ''){
    	// 构建Auth对象
		$auth = new Auth($this->accessKey, $this->secretKey);

		$base_url = $this->report_url . $file_name;

		return $auth->privateDownloadUrl($base_url);
    }
}