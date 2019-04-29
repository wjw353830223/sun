<?php
namespace app\admin\controller;
require __DIR__ . '/../../../extend/qiniu-sdk-7.2.1/autoload.php';
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
class Ota extends Adminbase
{
	public function _initialize() {
		parent::_initialize();
		$this->menu_model = model("ota");
	}

	//ota列表
	public function index(){
		$otas = $this->menu_model->select();
		$this->assign('otas', $otas);
		return $this->fetch();
	}

	//固件发布
    public function publish() {
        if(request()->method() == 'POST') {
        	$para = input('post.');
        	$record = array();
        	$record['url'] = '';
        	if (strlen($para['name']) == 0) {
        		$this->error('名称不为空');
        	}
        	$record['name'] = $para['name'];
        	if (strlen($para['version']) == 0) {
        		$this->error('版本号不能为空');
        	}
        	$record['version'] = $para['version'];

        	$find_res = $this->menu_model->where(['name'=>$record['name'], 'version'=>$record['version']])->select()->toArray();
        	if (!empty($find_res)) {
        		$this->error('同样的固件已经存在');
        	}

        	if (strlen($para['proId']) == 0) {
        		$this->error('产品类型不能为空');
        	}
        	$record['proId'] = $para['proId'];
        	if (strlen($para['appType']) == 0) {
        		$this->error('应用类型需要选择');
        	}
        	if ($para['appType'] == '2') {
        		if (strlen($para['appId']) == 0) {
        			$this->error('其它应用时需要填写应用id');
        		}
        		$record['appId'] = $para['appId'];
        	} else {
        		$record['appId'] = $para['appType'];
        	}
        	if (array_key_exists('allows', $para)) {
        		$record['allows'] = $para['allows'];
        	}
        	if(!empty($_FILES) && $_FILES['file']['size'] > 0) {
        		$file = request()->file('file');
	        	$save_path = 'public/uploads/ota/';
	        	$info = $file->rule('uniqid')->move(ROOT_PATH.$save_path,true,false);
	        	if(!$info){
			        $this->error($file->getError());
			    } else {
			    	$record['url'] = $info->getFileName();
			    	// $file_path = ROOT_PATH.$save_path.$info->getFileName();
			    	// $res = $this->upload_to_qiniu($file_path, $info->getFileName());
			    	// if (!res) {
			    	// 	$this->error('上传到七牛服务器失败');
			    	// }
			    	$id = db('ota')->insertGetId($record);
		        	if (!$id) {
		        		$this->error('添加到数据库失败');
		        	} 
		        	$this->success('添加成功');
			    }
        	} else {
        		$this->error('请添加固件');
        	}
        } else {
        	return $this->fetch();
        }
    }


    public function update() {
    	 if (request()->isPost()) {
            $ota_id = input('post.id', 0, 'intval');
            $status = input('post.status', 0, 'intval');
            $result = $this->menu_model->where('id',$ota_id)->setField('status', $status);
            $this->success('修改成功');
        }
    }

    private function upload_to_qiniu($file_path, $file_name) {
    	$accessKey = 'kVNnKJYWgyeIQz9u4u_QqpghJwPW1G681R655HYL';
		$secretKey = '8kQOa-wEYLl8Q7sb066qFgxNKcOW4GiZoB45T-pN';
		$auth = new Auth($accessKey, $secretKey);
		$bucket = 'otafirmware';
		$token = $auth->uploadToken($bucket);
		$uploadMgr = new UploadManager();
		// 调用 UploadManager 的 putFile 方法进行文件的上传。
		list($ret, $err) = $uploadMgr->putFile($token, $file_name, $file_path);
		if ($err !== null) {
		    var_dump($err);
		    return false;
		} else {
		    var_dump($ret);
		    return true;
		}
    }

    private function is_empty($val) {
    	$new_val = trim($val);
    	return empty($new_val) && $new_val != '0';
    }

}