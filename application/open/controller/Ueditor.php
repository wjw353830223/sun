<?php
namespace app\open\controller;
use think\Controller;

class Ueditor extends Controller {
	
	private $stateMap = array( //上传状态映射表，国际化用户需考虑此处数据的国际化
        "SUCCESS", //上传成功标记，在UEditor中内不可改变，否则flash判断会出错
        "文件大小超出 upload_max_filesize 限制",
        "文件大小超出 MAX_FILE_SIZE 限制",
        "文件未被完整上传",
        "没有文件被上传",
        "上传文件为空",
        "ERROR_TMP_FILE" => "临时文件错误",
        "ERROR_TMP_FILE_NOT_FOUND" => "找不到临时文件",
        "ERROR_SIZE_EXCEED" => "文件大小超出网站限制",
        "ERROR_TYPE_NOT_ALLOWED" => "文件类型不允许",
        "ERROR_CREATE_DIR" => "目录创建失败",
        "ERROR_DIR_NOT_WRITEABLE" => "目录没有写权限",
        "ERROR_FILE_MOVE" => "文件保存时出错",
        "ERROR_FILE_NOT_FOUND" => "找不到上传文件",
        "ERROR_WRITE_CONTENT" => "写入文件内容错误",
        "ERROR_UNKNOWN" => "未知错误",
        "ERROR_DEAD_LINK" => "链接不可用",
        "ERROR_HTTP_LINK" => "链接不是http链接",
        "ERROR_HTTP_CONTENTTYPE" => "链接contentType不正确"
    );
	
	public function _initialize() {
		$adminid=sp_get_current_admin_id();
		if(empty($adminid)){
			exit("非法上传！");
		}
	}
	
	public function imageManager(){
		error_reporting(E_ERROR|E_WARNING);
		$path = 'upload'; //最好使用缩略图地址，否则当网速慢时可能会造成严重的延时
		$action = htmlspecialchars($_POST["action"]);
		if($action=="get"){
			$files = $this->getfiles($path);
			if(!$files)return;
			$str = "";
			foreach ($files as $file) {
				$str .= $file."ue_separate_ue";
			}
			echo $str;
		}
	}
	
	// 百度编辑器文件上传
	public function upload(){
		error_reporting(E_ERROR);
		header("Content-Type: application/json; charset=utf-8");
		
		$action = $_GET['action'];
		
		switch ($action) {
			case 'config':
				$result = $this->_ueditor_config();
				break;
				/* 上传图片 */
			case 'uploadimage':
				/* 上传涂鸦 */
			case 'uploadscrawl':
				$result = $this->_ueditor_upload('image');
				break;
				/* 上传视频 */
			case 'uploadvideo':
				$result = $this->_ueditor_upload('video');
				break;
				/* 上传文件 */
			case 'uploadfile':
				$result = $this->_ueditor_upload('file');
				break;
		
				/* 列出图片 */
			case 'listimage':
				$result = "";
				break;
				/* 列出文件 */
			case 'listfile':
				$result = "";
				break;
		
				/* 抓取远程文件 */
			case 'catchimage':
				$result=$this->_get_remote_image();
				break;
		
			default:
				$result = json_encode(array('state'=> '请求地址出错'));
				break;
		}
		
		/* 输出结果 */
		if (isset($_GET["callback"]) && false ) {//TODO 跨域上传
			if (preg_match("/^[\w_]+$/", $_GET["callback"])) {
				echo htmlspecialchars($_GET["callback"]) . '(' . $result . ')';
			} else {
				echo json_encode(array(
						'state'=> 'callback参数不合法'
				));
			}
		} else {
			exit($result) ;
		}
	}
	
	/**
	 * 获取远程图片
	 */
	private function _get_remote_image(){
		
		return json_encode(array(
				'state'=> 'SUCCESS',
				'list'=> $list
		));
	}
	
	/**
	 * 获取百度编辑器配置
	 */
	private function _ueditor_config(){
	    $config_text=preg_replace("/\/\*[\s\S]+?\*\//", "", file_get_contents("./static/js/ueditor/config.json"));
	    $config = json_decode($config_text, true);
	    $config['imageMaxSize'] = 10240*1024;
	    $config['imageAllowFiles'] = array('.jpg','.jpeg','.png','.gif','.bmp4');
	    $config['scrawlMaxSize'] = 10240*1024;
	    
	    $config['catcherMaxSize'] = 10240*1024;
	    $config['catcherAllowFiles'] = array('.jpg','.jpeg','.png','.gif','.bmp4');
	    
	    $config['videoMaxSize'] = 10240*1024;
	    $config['videoAllowFiles'] = array('.mp4','.avi','.wmv','.rm','.rmvb','.mkv');
	    
	    $config['fileMaxSize'] = 10240*1024;
	    $config['fileAllowFiles'] = array('.txt','.pdf','.doc','.docx','.xls','.xlsx','.ppt','.pptx','.zip','.rar');

        $config['imageUrlPrefix'] = config('qiniu.buckets')['images']['domain'];
	    return json_encode($config);
	}
	
	/**
	 * 获取文件后缀名
	 * @param string $str
	 */
	public function _ueditor_extension($str){
	    
	   return ".".trim($str,'.');
	}
	
	/**
	 * 处理文件上传
	 * @param string $filetype 文件类型,如image,vedio,audio,file
	 */
	private function _ueditor_upload($filetype='image'){
	    $file = request()->file('upfile');
		$info = $file->validate(['size'=>10240*1024,'ext'=>'jpg,jpeg,png,gif,bmp4,mp4,avi,wmv,rm,rmvb,mkv,mp3,wma,wav,txt,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar'])
		->move(ROOT_PATH . 'public' . DS . 'uploads/content');

		//开始上传
		if ($info) {
			//上传成功
			$title = $oriName = $info->getFilename();
			$state = 'SUCCESS';
			$save_name = str_replace('\\','/',$info->getSaveName());
			$url = '/uploads/content/'.$save_name;

            $bucket = config('qiniu.buckets')['images']['name'];
            $key = 'uploads/content/'.$save_name;
            $path = './' . $key;
            add_file_to_qiniu($key,$path,$bucket);
		} else {
			$state = $file->getError();
		}
		
		$response=array(
				"state" => $state,
				"url" => $url,
				"title" => $title,
				"original" =>$oriName,
		);
		
		return json_encode($response);
	}
}