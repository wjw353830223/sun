<?php
namespace app\admin\controller;
use Rbac\Tree;
class Article extends Adminbase
{
    protected $article_model,$cateid;
	public function _initialize() {
		parent::_initialize();
		$this->article_model = model("article");
		$this->cateid = 3;      //健康资讯id
	}

	//文章管理首页
	public function index(){
	    $query = [];
		if(request()->isGet()){   
			$where = [];
			$category = input('get.category',0,'intval');
			if(!empty($category)){
                $query['cate_id'] = $category;
				$where['cate_id'] = $category;
			}
			
			$start_time = input('get.start_time');
			if(!empty($start_time)){
				$start_time = strtotime($start_time);
                $query['start_time'] = strtotime($start_time);
				$where['create_time'] = ['egt',$start_time];
			}

			$end_time = input('get.end_time');
			if(!empty($end_time)){
				$end_time = strtotime($end_time);
				$query['end_time'] = strtotime($end_time);
				$where['create_time'] = ['lt',$end_time];
			}

	        $end_time = input('get.end_time');
			if(!empty($start_time) && !empty($end_time)){
                $query['start_time'] = strtotime($start_time);
				$start_time = strtotime($start_time);
                $query['end_time'] = strtotime($end_time);
				$over_time = strtotime($end_time);
				$where['create_time'] = [['egt',$start_time],['lt',$over_time]];
			}
			$keywords = trim(input('get.keywords'));
			if(!empty($keywords)){
                $query['keywords'] = $keywords;
				$where['title'] = ['like','%'.$keywords.'%'];
			}
		}

		//分类
		$result = model("category")->field("catname,category_id,parent_id")->select()->toArray();
		$categorys = '';

        if(!empty($result)){
        	$tree = new Tree();
			foreach($result as $r) {
				$r['id'] = $r['category_id'];
                $r['cname'] = $r['catname'];
                $count = model('category')->where(array('parent_id'=>$r['category_id']))->count();
                $r['disable'] = ($count > 0) ? 'disabled' : '';
                $array[] = $r;
			}
			$str  = "<option value='\$id' \$disable >\$spacer \$cname</option>";
			$tree->init($array);
			$categorys = $tree->get_tree(0, $str);
        }

        $count = $this->article_model ->where($where)->count();
		$lists = $this->article_model->where($where)
            ->order(['article_id'=>'DESC'])->paginate(15,$count,['query'=>$query]);

		$admin_name = model('admin')->field('admin_id,admin_name')->select()->toArray();
		foreach ($lists as $key => $value) {
			//添加作者名称
			foreach ($admin_name as $name_key => $name_value) {
				if ($value['anchor_id'] == $name_value['admin_id']) {
					$lists[$key]['admin_name'] = $name_value['admin_name'];
                    break;
				}
			}

			//添加分类
			foreach ($result as $cate_key => $cate_value) {
				if ($value['cate_id'] == $cate_value['category_id']) {
					$lists[$key]['cate_name'] = $cate_value['catname'];
					break;
				}
			}
		}
		$this->assign("category",$categorys);
		$this->assign('lists',$lists);
		return $this->fetch();
	}


	//文章添加
	public function add(){
        $result = model("category")->order(["list_order"=>"DESC"])->select()->toArray();
        $categorys = '';
        if(!empty($result)){
        	$tree = new Tree();
			foreach($result as $r) {
				$r['id'] = $r['category_id'];
				$r['cname'] = $r['catname'];
				$count = model('category')->where(['parent_id'=>$r['category_id']])->count();
				$r['disable'] = ($count > 0) ? 'disabled' : '';
				$array[] = $r;
			}
			$str  = "<option value='\$id'  \$disable>\$spacer \$cname</option>";
			$tree->init($array);
			$categorys = $tree->get_tree(0, $str);
        }
        $this->assign("categorys",$categorys);
		return $this->fetch();
	}

	//文章添加处理
	public function add_post(){
		$terms = $_POST['terms'];
		if(empty($terms)){
			$this->error("文章分类不得为空");
		}
		$title = input("post.title","","trim");
		if(empty($title)){
			$this->error("文章标题不能为空");
		}
		$thumb = input("post.thumb");

		$keywords = input("post.keywords","","trim");
		//摘要
		$excerpt = input("post.excerpt");
	    $content = input("post.content");
	    if(empty($content)){
	    	$this->error("内容不能为空");
	    }

	    $admin_id = sp_get_current_admin_id();
	    foreach($terms as $key=>$val){
	    	$uniqid = uniqid();
	    	$this->assign('title',$title);
	    	$this->assign('content',$content);
	    	$this->assign('time',time());
	    	$this->assign('uniqid_code',$uniqid);
	    	$this->assign('host_url',$this->host_url());
	    	if($val == $this->cateid){
	    		$this->assign('author',session('name'));
	    	}
	    	$info_html = $this->fetch("content");
	    	$url = './article/'.$uniqid.'.html';
	    	$file_result = file_put_contents($url, $info_html);
	    	if(!$file_result){
	    		$this->error("生成静态页失败");
	    	}

	    	$key = 'article/'.$uniqid.'.html';
	    	$path = $url;
	    	$bucket = config('qiniu.buckets')['images']['name'];
            add_file_to_qiniu($key,$path,$bucket);
	    	$data = [
                "thumb"=>$thumb,
                "cate_id"=>$val,
                "title"=>$title,
                "keywords"=>$keywords,
                "description"=>$excerpt,
                "create_time"=>time(),
                "anchor_id"=>$admin_id,
                "uniqid_code"=>$uniqid
            ];
	    	$article_result = $this->article_model->insertGetId($data);
	    	$article_info_result = model("article_data")->insert(["article_id"=>$article_result,"content"=>$content]);
	    	model("category")->where(['category_id'=>$val])->setInc('count');
			//匹配及替换内容中的图片
		    $pattern = '/<\s*img\s+[^>]*?src\s*=\s*(\'|\")(.*?)\\1[^>]*?\/?\s*>/i';
			if(preg_match_all($pattern,$content,$match)){
				foreach ($match[2] as $kk => $vv) {
					$count = preg_match('/^(http|https)/',$vv);
					if(!$count){
						$url = $this->host_url().$vv;
						$content = str_replace($vv,$url,$content);
					}
				}
			}
	    	
	    }
	    if($article_result && $article_info_result){
	    	$this->success("添加内容成功");
	    }else{
	    	$this->error("添加失败");
	    }
	}
	
	//文章编辑
	public function edit(){
		$article_id = input("get.id");
		$article_info = $this->article_model->alias('a')
            ->where(["a.article_id"=>$article_id])
            ->join('sun_article_data data','a.article_id=data.article_id')
            ->field("a.uniqid_code,a.article_id,a.title,a.cate_id,a.thumb,a.keywords,a.description,a.create_time,a.status,data.content")
            ->find()->toArray();
		$result = model("category")->order(array("list_order"=>"DESC"))->select()->toArray();
		$tree = new Tree();
		foreach($result as $r) {
			$r['id'] = $r['category_id'];
			$r['cname'] = $r['catname'];
			$r['selected'] = $r['category_id'] == $article_info['cate_id'] ? 'selected' : '';
			$count = model('category')->where(array('parent_id'=>$r['category_id']))->count();
			$r['disable'] = ($count > 0) ? 'disabled' : '';
			$array[] = $r;
		}
		$str  = "<option value='\$id' \$selected \$disable>\$spacer \$cname</option>";
		$tree->init($array);
		$categorys = $tree->get_tree(0, $str);
		$this->assign("article",$article_info);
		$this->assign("categorys",$categorys);
		return $this->fetch();
	}

	//文章编辑处理
	public function edit_post(){
		$article_id = input("post.article_id");
		$terms = end($_POST['terms']);
		if(empty($terms)){
			$this->error("文章分类不得为空");
		}
		$title = input("post.title","","trim");
		if(empty($title)){
			$this->error("文章标题不能为空");
		}
		$keywords = input("post.keywords","","trim");
		//摘要
		$excerpt = input("post.excerpt");
	    $content = input("post.content");
	    if(empty($content)){
	    	$this->error("内容不能为空");
	    }

	    $article_info = $this->article_model->alias('a')->where(array("a.article_id"=>$article_id))->join('sun_article_data data','a.article_id=data.article_id')->field("a.anchor_id,a.cate_id,a.thumb,a.title,a.keywords,a.description,a.create_time,a.status,data.content")->find()->toArray();
	    $article_array = array();
	    
	    if($article_info['cate_id'] != $terms){
	    	$article_array['cate_id'] = $terms; 
	    }
	    if($article_info['title'] !== $title){
	    	$article_array['title'] = $title; 
	    }
	    if($article_info['keywords'] !== $keywords){
	    	$article_array['keywords'] = $keywords; 
	    }
	    if($article_info['description'] !== $excerpt){
	    	$article_array['description'] = $excerpt; 
	    }
	   	$thumb = input("post.thumb");
	   	if($article_info['thumb'] !== $thumb){
	   		$article_array['thumb'] = $thumb;
	   	}
	    $article_data_array = array();
	    if($article_info['content'] !== $content){
	    	$article_data_array['content'] = $content;
	    }
	    //作者
	    $anchor = model('admin')->where(['admin_id'=>$article_info['anchor_id']])->field('admin_name')->find();
	    //匹配及替换内容中的图片
	    $pattern = '/<\s*img\s+[^>]*?src\s*=\s*(\'|\")(.*?)\\1[^>]*?\/?\s*>/i';
		if(preg_match_all($pattern,$content,$match)){
			foreach ($match[2] as $kk => $vv) {
				$count = preg_match('/^(http|https)/',$vv);
				if(!$count){
					$url = $this->host_url().$vv;
					$content = str_replace($vv,$url,$content);
				}
			}
		}
		if(empty($article_data_array) && empty($article_array)){
			$this->success("修改文章成功!",url("article/index"));
		}
	    if(!empty($article_array) && !empty($article_data_array)){
			$article_result = $this->article_model->save($article_array,array("article_id"=>$article_id));
	    	$article_data_result = model("article_data")->save($article_data_array,array("article_id"=>$article_id));
			if($article_result && $article_data_result){
				$uniqid_code = input("post.uniqid_code");
	    		$url = './article/'.$uniqid_code.'.html';
	    		$this->assign('title',$title);
	    		$this->assign('content',$content);
	    		$this->assign('uniqid_code',$uniqid_code);
	    		$this->assign('host_url',$this->host_url());
	    		if($terms == $this->cateid){
	    			$this->assign('author',$anchor['admin_name']);
	    		}
	    		$this->assign('time',time());
	    		$info_html = $this->fetch("content");
	    		$file_result = file_put_contents($url, $info_html);
	    		if(!$file_result){
		    		$this->error("生成静态页失败");
		    	}
                $bucket = config('qiniu.buckets')['images']['name'];
                $key = 'article/'.$uniqid_code.'.html';
                update_qiniu_file($key, $bucket);
				$this->success("修改文章成功!",url("article/index"));
			}else{
				$this->error("修改失败");
			}
		}
		
		if(!empty($article_array) && empty($article_data_array)){
			$article_result = $this->article_model->save($article_array,array("article_id"=>$article_id));
			if($article_result){
				$uniqid_code = input("post.uniqid_code");
	    		$url = './article/'.$uniqid_code.'.html';
	    		$this->assign('title',$title);
	    		$this->assign('content',$content);
	    		$this->assign('uniqid_code',$uniqid_code);
	    		$this->assign('host_url',$this->host_url());
	    		$this->assign('time',time());
	    		if($terms == $this->cateid){
	    			$this->assign('author',$anchor['admin_name']);
	    		}
	    		$info_html = $this->fetch("content");
	    		$file_result = file_put_contents($url, $info_html);
	    		if(!$file_result){
		    		$this->error("生成静态页失败");
		    	}
                $bucket = config('qiniu.buckets')['images']['name'];
                $key = 'article/'.$uniqid_code.'.html';
                update_qiniu_file($key, $bucket);
				$this->success("修改文章成功!",url("article/index"));
			}else{
				$this->error("修改失败");
			}
		}
		if(!empty($article_data_array) && empty($article_array)){
			$article_data_result = model("article_data")->save($article_data_array,array("article_id"=>$article_id));
			if($article_data_result){
				$uniqid_code = input("post.uniqid_code");
	    		$url = './article/'.$uniqid_code.'.html';
	    		$this->assign('title',$title);
	    		$this->assign('content',$content);
	    		$this->assign('uniqid_code',$uniqid_code);
	    		$this->assign('host_url',$this->host_url());
	    		$this->assign('time',time());
	    		if($terms == $this->cateid){
	    			$this->assign('author',$anchor['admin_name']);
	    		}
	    		$info_html = $this->fetch("content");
	    		$file_result = file_put_contents($url, $info_html);
	    		if(!$file_result){
		    		$this->error("生成静态页失败");
		    	}
                $bucket = config('qiniu.buckets')['images']['name'];
                $key = 'article/'.$uniqid_code.'.html';
                update_qiniu_file($key, $bucket);
				$this->success("修改文章成功!",url("article/index"));
			}else{
				$this->error("修改失败");
			}
		}
	}
    
    //下架文章
	public function undercarriage(){
        $article_id = input("get.id",0,"intval");
        if(!empty($article_id)){
        	$article_result = $this->article_model->save(array("status"=>0),array("article_id"=>$article_id));
	 		if ($article_result !== false) {
	    		$this->success("下架文章成功",url("article/index"));
	    	}else{
	    		$this->error("下架失败！");
	    	}
        }else{
        	$this->error('传入数据有误!');
        }
        
	}
     
    //上架文章
	public function dercarriage(){
        $article_id = input("get.id",0,"intval");
        if(!empty($article_id)){
        	$article_result = $this->article_model->save(array("status"=>1),array("article_id"=>$article_id));
	 		if ($article_result !== false) {
	    		$this->success("上架文章成功",url("article/index"));
	    	}else{
	    		$this->error("上架失败！");
	    	}
        }else{
        	$this->error("传入数据有误!");
        }
	}

	//文章删除
	public function delete(){
		$article_id = input("get.id");
		$category_id = input("get.cate_id","","intval");
		$uniqid_code = $this->article_model->where(['article_id'=>$article_id])->value('uniqid_code');
		$url = './article/'.$uniqid_code.'.html';
		$delete_file = unlink($url);
		if($delete_file){
			$article_result = $this->article_model->where(array("article_id"=>$article_id))->delete();
			$article_data_result = model("article_data")->where(array("article_id"=>$article_id))->delete();
			if ($article_result && $article_data_result) {
				model("category")->where(array("category_id"=>$category_id))->setDec("count");
	    		$this->success("文章删除成功",url("article/index"));
	    	}else{
	    		$this->error("删除失败！");
	    	}
		}else{
			$this->error("删除失败！");
		}
		
		
	}

	//排序
	public function listorders(){
		$ids = $_POST['listorders'];
        if (!is_array($ids)) {
            $this->error("参数错误！");
        }
        foreach ($ids as $key => $r) {
            if ($r > 0) {
                $this->article_model->save(array('list_order' => $r),array('article_id' => $key));
            }
        }
        $this->success("排序更新成功！");
	}

	//批量删除
	public function delete_group(){
		$ids = $_POST['ids'];
		if(!is_array($ids)){
            $this->error("参数错误！");
        }
        $ids = implode(',',$ids);
        $cate_id = $this->article_model->field('cate_id')->select()->toArray();
        $cate_id = implode(',',$cate_id);
        // echo $cate_id;die;
        $this->article_model->where(array('article_id'=>array('in',$ids)))->delete();
        model("article_data")->where(array('article_id'=>array('in',$ids)))->delete();
        model("category")->where(array('category_id'=>array('in',$cate_id)))->setDec('count');
        $this->success("删除成功！");
	}

	//文章批量复制
	public function header(){
		if(request()->isPost()){
			if(isset($_GET['ids']) && isset($_POST['term_id'])){
	            $ids=explode(',', input('get.ids'));
	            $ids=array_map('intval', $ids);
	            $admin_id=sp_get_current_admin_id();
	            $term_id=input('post.term_id',0,'intval');
	            $term_count= model('category')->where(array('category_id'=>$term_id))->count();
	            if($term_count==0){
	                $this->error('分类不存在！');
	            }
	            
	            $data=array();
	            
	            foreach ($ids as $id){
	                $article_info = $this->article_model->alias('a')->where(array("a.article_id"=>$id))
                        ->join('sun_article_data data','a.article_id=data.article_id')
                        ->field("a.title,a.cate_id,a.thumb,a.keywords,a.description,a.status,data.content")
                        ->find()->toArray();
	                if($article_info){
	                	$article = array_pop($article_info);
                        $admin_id=sp_get_current_admin_id();
                        $article_info['anchor_id']=$admin_id;
                        $article_info['create_time']=time();
                        $article_info['anchor_id']=$admin_id;
	                    $article_id = $this->article_model->insertGetId($article_info);
	                    $article_data_result = model("article_data")->insert(["article_id"=>$article_id,"content"=>$article]);
	                }
	            }
	            
	            if ($article_id && $article_data_result) {
	                $this->success("复制成功！");
	            } else {
	                $this->error("复制失败！");
	            }
	        }
		}else{
			$tree = new Tree();
	        $tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
	        $tree->nbsp = '&nbsp;&nbsp;&nbsp;';
	        $terms = model("category")->select()->toArray();
	        $new_terms=array();
	        foreach ($terms as $r) {
	        	$r['name']=$r['catname'];
	            $r['id']=$r['category_id'];
	            $r['parentid']=$r['parent_id'];
	            $new_terms[] = $r;
	        }
	        $tree->init($new_terms);
	        $tree_tpl="<option value='\$id'>\$spacer\$name</option>";
	        $tree=$tree->get_tree(0,$tree_tpl);
	
	        $this->assign("terms_tree",$tree);
	        return $this->fetch("open/copy");
		}
	}

	// 文章批量移动
	public function move(){
		if(request()->isPost()){
			if(isset($_GET['ids']) && isset($_POST['term_id'])){
			    $term_id=input('post.term_id',0,'intval');
		        $ids=explode(',', input('get.ids'));
		        $ids=array_map('intval', $ids);
		         
		        foreach ($ids as $id){
		        	$article_result = $this->article_model->where(array("article_id"=>$id))->update(["cate_id"=>$term_id]);
		        }
			    if($article_result !== false){
                    $this->success("移动成功！");
			    }else{
			    	$this->success("移动失败！");
			    }
			}
		}else{
			$tree = new Tree();
			$tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
			$tree->nbsp = '&nbsp;&nbsp;&nbsp;';
			$terms = model("category")->select()->toArray();
			$new_terms=array();
			foreach ($terms as $r) { 
				$r['name']=$r['catname'];
				$r['id']=$r['category_id'];
				$r['parentid']=$r['parent_id'];
				$new_terms[] = $r;
			}
			$tree->init($new_terms);
			$tree_tpl="<option value='\$id'>\$spacer\$name</option>";
			$tree=$tree->get_tree(0,$tree_tpl);
			 
			$this->assign("terms_tree",$tree);
			return $this->fetch("open/move");
		}
	}

	// 文件上传
    public function plupload(){
        $upload_setting=sp_get_upload_setting();
        $filetypes=array(
            'image'=>array('title'=>'Image files','extensions'=>$upload_setting['image']['extensions']),
            'video'=>array('title'=>'Video files','extensions'=>$upload_setting['video']['extensions']),
            'audio'=>array('title'=>'Audio files','extensions'=>$upload_setting['audio']['extensions']),
            'file'=>array('title'=>'Custom files','extensions'=>$upload_setting['file']['extensions'])
        );
        
        $image_extensions=explode(',', $upload_setting['image']['extensions']);
        
        if (request()->isPost()) {
            $all_allowed_exts=array();
            foreach ($filetypes as $mfiletype){
                array_push($all_allowed_exts, $mfiletype['extensions']);
            }
            $all_allowed_exts=implode(',', $all_allowed_exts);
            $all_allowed_exts=explode(',', $all_allowed_exts);
            $all_allowed_exts=array_unique($all_allowed_exts);
            
            $file_extension=sp_get_file_extension($_FILES['file']['name']);
            $upload_max_filesize=$upload_setting['upload_max_filesize'][$file_extension];
            $upload_max_filesize=empty($upload_max_filesize)?2097152:$upload_max_filesize;//默认2M
            
            $app=input('post.app/s','');
            if(!in_array($app, config('configs.module_allow_list'))){
                $app='default';
            }else{
                $app= strtolower($app);
            }
            
			$savepath=$app.'/'.date('Ymd').'/';
            if(!empty($_FILES) && $_FILES['file']['size'] > 0){
				$file = request()->file('file');
				$image = \think\Image::open($file);
	        	$save_path = 'public/uploads/article';
                $type = $image->type();
	        	$save_name = uniqid() . '.' . $type;
	        	$info = $file->rule('uniqid')->validate(['size'=>$upload_max_filesize,'ext'=>$all_allowed_exts])
                    ->move(ROOT_PATH.$save_path.'/',true,false);
	        	
	        	if($info){
			        $url=config('configs.url').$info->getFilename();
                	$filepath = $savepath.$info->getSaveName();
                	ajax_return(['preview_url'=>$url,'filepath'=>$filepath,'url'=>$url,'name'=>$info->getSaveName(),'status'=>1,'message'=>'success']);
			    }else{
			    	ajax_return(['name'=>'','status'=>0,'message'=>$info->getError()]);
			    }
			}
        } else {
            $filetype = input('get.filetype/s','image');
            $mime_type=array();
            if(array_key_exists($filetype, $filetypes)){
                $mime_type=$filetypes[$filetype];
            }else{
                $this->error('上传文件类型配置错误！');
            }
            $multi=input('get.multi',0,'intval');
            $app=input('get.app/s','');
            $upload_max_filesize=$upload_setting[$filetype]['upload_max_filesize'];
            $this->assign('extensions',$upload_setting[$filetype]['extensions']);
            $this->assign('upload_max_filesize',$upload_max_filesize);
            $this->assign('upload_max_filesize_mb',intval($upload_max_filesize/1024));
            $this->assign('mime_type',json_encode($mime_type));
            $this->assign('multi',$multi);
            $this->assign('app',$app);
            return $this->fetch("open/plupload");
        }
    }

    // 上传限制设置界面
	public function upload(){
	    $upload_setting=sp_get_upload_setting();
	    $this->assign($upload_setting);
	    return $this->fetch('open/upload');
	}

	 /**
    * 获取当前网站域名
    *
    */
    public function host_url(){
        $base_url = 'http';
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {
            $base_url .= "s";
        }
        $base_url .= "://";

        if (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != "80") {
            $base_url .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"];
        }else{
            $base_url .= $_SERVER["SERVER_NAME"];
        }
        return $base_url;
    }
} 

