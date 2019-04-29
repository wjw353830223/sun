<?php
namespace app\admin\controller;
use Rbac\Tree;
use Lib\Qrcode;
class Company extends Adminbase
{
	protected $seller_model;
    public function _initialize() {
        parent::_initialize();
        $this->seller_model = model('StoreSeller');
    }

    /**
     * @return mixed
     * 区域三级联动
     */
    public function area()
    {
        $area_parent_id = input('post.area_parent_id');
        $area = model('Area')->where(['area_parent_id'=>$area_parent_id])->select();
        ajax_return($area);
    }
	//自营公司列表
	public function own_company(){
		if(request()->isGet()){
			$where = array();
			$company_name = input('get.company_name','','trim');
			if(!empty($company_name)){
				$where['company_name'] = ['like','%'.$company_name.'%'];
			}
		}

		$result = db('store')->order(["create_time"=>"asc"])
            ->field('store_id,store_parentid as parent_id,store_name,store_state,store_sales,create_time,store_cityid,store_provinceid,store_areaid,store_address')
            ->select();
		$store_admin = db('store_admin')->field('stadmin_name,store_id')->select();
		$store_data = array();
		foreach($result as $k=>$v){
			foreach($store_admin as $admin_key=>$admin_val){
				$tmp = array();
				if($v['store_id'] == $admin_val['store_id']){
					$result[$k]['stadmin_name'] = $admin_val['stadmin_name'];
					break;
				}
			}
		}
		$categorys = '';
		if (!empty($result)) {
			$tree = new Tree();
			$tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
			$tree->nbsp = '&nbsp;&nbsp;&nbsp;';

			foreach ($result as $r) {
				$r['str_manage'] = '<a href="' . url("company/add", ["store_id" => $r['store_id']]) . '">添加子公司</a> | <a href="' . url("company/add_seller", array("store_id" => $r['store_id'])) . '">添加销售员</a> | <a href="' . url("company/edit", array("store_id" => $r['store_id'])) . '">编辑</a>  | <a class="js-ajax-delete" href="' . url("company/delete", array("id" => $r['store_id'])) . '">删除</a> ';
				$r['id']=$r['store_id'];
				switch ($r['store_state']) {
					case '0':
						$r['store_state'] = '关闭';
						break;
					case '1':
						$r['store_state'] = '开启';
						break;
					case '2':
						$r['store_state'] = '审核中';
						break;
				}
				$r['create_time'] = date('Y-m-d H:i',$r['create_time']);
				$array[] = $r;
			}

			$tree->init($array);
			$str = "<tr>
						<td>\$spacer\$store_name</td>
						<td>\$stadmin_name</td>
						<td>\$store_state</td>
						<td>\$store_sales</td>
						<td>\$store_address</td>
						<td>\$create_time</td>
						<td>\$str_manage</td>
					</tr>";
			$categorys = $tree->get_tree(0, $str);
		}
		$this->assign('categorys',$categorys);
		return $this->fetch();
	}

	//自营公司编辑
	public function edit(){
		$store_id = input('get.store_id',0,'intval');
		//当前编辑公司的信息
        $store_info = db('store')->where(['store_id'=>$store_id])
            ->field('store_id,store_parentid,store_state,store_name,store_address,store_cityid,store_provinceid,store_areaid')
            ->find();
		//公司分类
		$result = model('Store')->order(["create_time"=>"asc"])
            ->field('store_id,store_parentid as parent_id,store_name,store_state,store_sales,create_time,store_cityid,store_provinceid,store_areaid,store_address')
            ->where(['store_id'=>['neq',$store_info['store_id']]])
            ->select()->toArray();
        $categorys ='';
        if(!empty($result)){
			$tree = new Tree();
            $tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
            $tree->nbsp = '&nbsp;&nbsp;&nbsp;';
            $array = [];
            foreach ($result as $k=>$r) {
                $child = [];
                $child['id'] = $r['store_id'];
                $child['catname'] = $r['store_name'];
                $child['selected'] = $r['store_id'] == $store_info['store_parentid'] ? "selected" : "";
                $child = array_merge($child,$result[$k]);
                $array[] = $child;
            }
			$tree->init($array);
			$str = "<option value=\$id \$selected>\$spacer\$catname</option>";
			$categorys = $tree->get_tree(0, $str);
        }
        //区域
        $province = db('area')->where(['area_parent_id'=>0])->select();
        $this->assign('province',$province);
        $city = db('area')->where(['area_parent_id'=>$store_info['store_provinceid']])->select();
        $this->assign('city',$city);
        $area = db('area')->where(['area_parent_id'=>$store_info['store_cityid']])->select();
        $this->assign('area',$area);
        //公司详情
        $store_admin_info = db('store_admin')
            ->where(['store_id'=>$store_id])
            ->field('stadmin_name,stadmin_password')
            ->find();
        $store_info = array_merge($store_info,$store_admin_info);
        $this->assign('store_info',$store_info);
        $this->assign('categorys',$categorys);
		return $this->fetch();
	}

	//自营公司编辑提交
	public function edit_post(){
		$param = input('post.');
		$store_id = $param['store_id'];
        $store_info = model('Store')->where(['store_id'=>$store_id])->find();
		if(empty($store_info)){
			$this->error('帐户不存在');
		}
        $has_info = model('StoreAdmin')->where(['store_id'=>$store_id])->find();

		if($param['parent_id'] == $param['store_id']){
            $this->error('该公司的上级公司不能为本身');
        }

        $data = [
            'store_parentid'   => $param['parent_id'],
            'store_state'      => $param['store_state'],
            'store_name'       => $param['company_name'],
            'store_provinceid' => $param['province_id'],
            'store_cityid'     => $param['city_id'],
            'store_areaid'     => $param['area_id'],
            'store_address'    => $param['company_address']
        ];
        model('Store')->startTrans();
        model('StoreAdmin')->startTrans();
        $result = model('Store')->save($data,['store_id'=>$store_id]);
		if($result === false){
            $this->error('公司信息修改失败');
		}
        $info = [];
        if(!empty($param['stadmin_password'])){
            if($param['stadmin_password'] !== $param['re_passwd']){
                $this->error('两次密码输入不一致');
            }
            if(sp_compare_password($param['stadmin_password'],$has_info['stadmin_password'],$has_info['passwd_encrypt'])){
                $this->error('新密码与原密码相同');
            }
            $info['stadmin_password'] = sp_password($param['stadmin_password'],$has_info['passwd_encrypt']);
        }
        $info['stadmin_name'] = $param['seller_name'];

		$storeAdmin = model('StoreAdmin')->save($info,['store_id'=>$store_id]);
		if($storeAdmin === false){
            model('Store')->rollback();
            $this->error('公司密码修改失败');
        }
        model('Store')->commit();
        model('StoreAdmin')->commit();
        $this->success('修改成功',url('company/own_company'));
	}

	//自营公司添加
	public function add(){
		//公司分类
		$parent_id = input('get.ordre_id');
		$result = db('store')->order(["create_time"=>"asc"])->field('store_id,store_parentid as parent_id,store_name,store_state,store_sales,create_time,store_cityid,store_provinceid,store_areaid,store_address')->select();
        $categorys ='';
        if(!empty($result)){
			$tree = new Tree();
			$tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
			$tree->nbsp = '&nbsp;&nbsp;&nbsp;';
			foreach ($result as $r) {
				$r['id'] = $r['store_id'];
				$r['catname'] = $r['store_name'];
				if(!empty($parent_id)){
					$r['selected'] = $r['order_id'] == $parent_id ? 'selected' : '';
				}

				$array[] = $r;
			}
			$tree->init($array);
			$str = "
					<option value=\$id \$selected>\$spacer\$catname</option>
					";
			$categorys = $tree->get_tree(0, $str);
        }
        //区域
        $province = db('area')->where(['area_parent_id'=>0])->select();
        $this->assign('province',$province);
        $city = db('area')->where(['area_parent_id'=>1])->select();
        $this->assign('city',$city);
        $area = db('area')->where(['area_parent_id'=>36])->select();
        $this->assign('area',$area);
        $this->assign('categorys',$categorys);
		return $this->fetch();
	}

	//自营公司添加提交
	public function add_post(){
		$param = input('post.');

		if(empty($param['company_name'])){
			$this->error('公司名称不能为空');
		}
		$company_data = array();
		$company_name = strip_tags($param['company_name']);

		//地址
		if(empty($param['province_id']) || empty($param['city_id']) || empty($param['area_id'])){
			$this->error('公司地址不完整');
		}
		//详细地址
		if(empty($param['company_address'])){
			$this->error('公司详细地址不能为空');
		}

		if(empty($param['admin_name'])){
			$this->error('公司账号不能为空');
		}

		$store_data = array();
		$store_data['stadmin_name'] = $param['admin_name'];

		if(empty($param['seller_passwd']) || empty($param['re_passwd'])){
			$this->error('密码不能为空');
		}

		if(empty($param['seller_passwd']) !== empty($param['re_passwd'])){
			$this->error('两次密码输入不一致');
		}
		$salt = random_string(6,5);
        $store_data['passwd_encrypt'] = $salt;
		$store_data['stadmin_password'] = sp_password($param['seller_passwd'],$salt);

		$param = [
            'store_name'=>$company_name,
            'store_parentid'=>$param['parent_id'],
            'create_time'=>time(),
            'store_provinceid'=>$param['province_id'],
            'store_cityid'=>$param['city_id'],
            'store_areaid'=>$param['area_id'],
            'store_address'=>$param['company_address'],
            'store_state'=>$param['store_state']
        ];
		$store_result = db('store')->insertGetId($param);
		if($store_result){
			$store_data['store_id'] = $store_result;
			$company_result = db('store_admin')->insert($store_data);
			if($company_result){
				$this->success('添加成功',url('company/own_company'));
			}else{
				$this->error('添加失败');
			}
		}else{
			$this->error('添加失败');
		}
	}

	//自营公司关闭
	public function lock(){
		$seller_id = input('get.seller_id',0,'intval');
		if($seller_id){
			$seller_result = db('seller')->where(['seller_id'=>$seller_id])->setField('seller_state','0');
			$company_result = db('seller_company')->where(['seller_id'=>$seller_id])->setField('seller_state','0');
			if($seller_result && $company_result){
				$this->redirect(url('company/own_company'));
			}
			else{
				$this->error('关闭失败');
			}
		}else{
			$this->error('传入参数错误');
		}
	}

	//自营公司开启
	public function unlock(){
		$seller_id = input('get.seller_id',0,'intval');
		if($seller_id){
			$seller_result = db('seller')->where(['seller_id'=>$seller_id])->setField('seller_state','1');
			$company_result = db('seller_company')->where(['seller_id'=>$seller_id])->setField('seller_state','1');
			if($seller_result && $company_result){
				$this->redirect(url('company/own_company'));
			}
			else{
				$this->error('开启失败');
			}
		}else{
			$this->error('传入参数错误');
		}
	}

    /**
     * 自营公司删除
     */
    public function delete(){
        $id = input('get.id');
        $store = model('Store')->where(['store_id'=>$id])->find();
        if(empty($store)){
            $this->error('该公司不存在');
        }
        $seller = model('StoreSeller')->where(['store_id'=>$id])->count();
        if($seller > 0){
            $this->error('该公司旗下还有销售员，不能删除');
        }
        $seller_store = model('Store')->where(['store_parentid'=>$id])->count();
        if($seller_store > 0){
            $this->error('请先删除子公司');
        }
        if(model('Store')->where(['store_id'=>$id])->delete()){
            $this->success('公司删除成功');
        }
    }

	//销售员列表
	public function seller_list(){
		$where = [];
		$query = [];
		if(request()->isGet()){
			$seller_name = input('get.seller_name','','trim');
			if(!empty($seller_name)){
                $query['seller_name'] = $seller_name;
				$where['seller_name'] = ['like','%'.$seller_name.'%'];
			}
			$id_card = input('get.id_card','','trim');
			if(!empty($id_card)){
                if(!validation_filter_id_card($id_card)){
                    $this->error("身份证号格式不正确");
                }
                $query['id_card'] = $id_card;
				$where['id_card'] = ['like','%'.$id_card.'%'];
			}
			$seller_state = input('get.seller_state','','trim');
			if(!empty($seller_state)){
                $query['seller_state'] = $seller_state;
				$where['seller_state'] = $seller_state;
			}
            $seller_mobile = input('get.seller_mobile','','trim');
            if(!empty($seller_mobile)){
                if(!is_mobile($seller_mobile)){
                    $this->error("账号格式不正确");
                }
                $query['seller_mobile'] = $seller_mobile;
                $where['seller_mobile'] = $seller_mobile;
            }
            $seller_role = input('get.seller_role',"");
            if(isset($seller_role) && !empty($seller_role)){
                $query['seller_role'] = $seller_role;
                $where['seller_role'] = $seller_role;
            }
		}
		$count = model('StoreSeller')->where($where)->order("apply_time desc")->count();
		$seller = model('StoreSeller')->where($where)
            ->order("apply_time desc")->paginate(15,$count,['query'=>$query]);
        foreach($seller as $key=>$vo){
    		$store_name = model('store')->where(['store_id'=>$vo['store_id']])->value('store_name');
            $seller[$key]['store_name'] = $store_name;
        }
        $member_id = [];
        foreach($seller as $k=>$v){
            $member_id[] = $v['member_id'];
        }
        $member = model('Member')->where(['member_id'=>['in',$member_id]])->select();
        foreach($seller as $key=>$val){
            foreach($member as $k=>$v){
                if($val['member_id'] == $v['member_id']){
                    $seller[$key]['member_name'] = $member[$k]['member_name'];
                    $seller[$key]['is_auth'] = $member[$k]['is_auth'];
                }
            }
        }
        $auth = [];
        foreach($member as $k=>$v){
            if($v['is_auth'] == 1){
                $auth[] = $v['member_id'];
            }
        }

        $member_auth = model('MemberAuth')->where(['member_id'=>['in',$auth],'auth_state'=>1])->select();
        foreach($seller as $key=>$val){
            foreach($member_auth as $k=>$v){
                if(in_array($seller[$key]['member_id'],$auth)){
                    if($val['member_id'] == $v['member_id']){
                        $seller[$key]['seller_name'] = $member_auth[$k]['auth_name'];
                        $seller[$key]['id_card'] = $member_auth[$k]['id_card'];
                    }
                }else{
                    $seller[$key]['seller_name'] = '';
                    $seller[$key]['id_card'] = '';
                }
            }
        }

        $page = $seller->render();
        $this->assign('page',$page);
        $this->assign("seller",$seller);
        return $this->fetch();
	}

	//销售员详情
	public function seller_detail(){
		$seller_id = input("get.id",'','intval');
        $seller = model('StoreSeller')->where(['seller_id'=>$seller_id])->find();
        if(!$seller){
            $this->error('该销售员不存在');
        }
        $seller_detail = model('StoreSeller')->where(['seller_id'=>$seller_id])->find();
        $member_auth = model('MemberAuth')->where(['member_id'=>$seller_detail['member_id']])->find();
        if($member_auth){
            $seller_detail['id_card'] = $member_auth['id_card'];
            $seller_detail['card_face'] = $member_auth['card_face'];
            $seller_detail['card_back'] = $member_auth['card_back'];
            $seller_detail['card_hand'] = $member_auth['card_hand'];
            $seller_detail['seller_name'] = $member_auth['auth_name'];
        }else{
            $seller_detail['id_card'] = '';
            $seller_detail['card_face'] = '';
            $seller_detail['card_back'] = '';
            $seller_detail['card_hand'] = '';
        }
        $this->assign('seller',$seller_detail);
        return $this->fetch();
	}


	//销售员锁定
	public function seller_lock()
	{
		$seller_id = input("get.id",'','intval');
		$seller = model('StoreSeller')->where(['seller_id'=>$seller_id])->find();
		if(!$seller){
			$this->error('该销售员不存在');
		}
		$result = model('StoreSeller')->save(['seller_state'=>3],['seller_id'=>$seller_id]);
		if($result){
			$this->success('锁定成功');
		}else{
			$this->error('锁定失败');
		}
	}

	//销售员解锁
	public function seller_unlock()
	{
		$seller_id = input("get.id",'','intval');
		$seller = model('StoreSeller')->where(['seller_id'=>$seller_id])->find();
		if(!$seller){
			$this->error('该销售员不存在');
		}
		$result = model('StoreSeller')->save(['seller_state'=>2],['seller_id'=>$seller_id]);
		if($result){
			$this->success('解锁成功');
		}else{
			$this->error('解锁失败');
		}
	}

	//通过审核
	public function examine(){
		$seller_id = input("get.seller_id",0,'intval');
        $data = [
        	'seller_state'  => 2,
        	'seller_role'   => 1,
            'is_alert'      => 1
        ];
        $this->seller_model->startTrans();
//        model('Team')->startTrans();
        model('Member')->startTrans();
        $result = $this->seller_model->save($data,['seller_id'=>$seller_id]);
        if($result === false){
            $this->error('消费商状态修改失败');
		}
        $member_id = $this->seller_model->where(['seller_id'=>$seller_id])->value('member_id');
        $member_grade = model('Member')->where(['member_id'=>$member_id])->update(['member_grade'=>7]);
        if($member_grade === false){
            $this->seller_model->rollback();
            $this->error('消费商审核失败');
        }
        /*//消费商团队信息
        $count = model('Member')->where(['parent_id'=>$member_id])->count();
        $team = [
            'member_id' => $member_id,
            'create_time' => time(),
            'team_number' => $count
        ];
        //成为消费商的同时创建消费团队
        $group = model('Team')->insertGetId($team);
        if($group === false){
            model('Member')->rollback();
            $this->seller_model->rollback();
            $this->error('消费商审核失败');
        }*/
        $this->seller_model->commit();
//        model('Team')->commit();
        model('Member')->commit();
        $this->success('审核成功');
    }

	/**
    * 添加销售员
    */
    public function add_seller(){
    	if(request()->isGet()){
    	    $store_id = input('get.store_id');
    	    $store = model("store")->where(['store_state'=>1])->select();
    	    $this->assign('store_id',$store_id);
    	    $this->assign('store',$store);
   			return $this->fetch();
    	}
    	if(request()->isPost()){
    		$store_id = input('post.store_id');
            $seller_role = input('post.seller_role');
            $seller_name = input('post.seller_name');
    		if(empty($seller_name)){
    			$this->error("销售员姓名不能为空");
    		}
    		$seller_mobile = input('post.seller_mobile');
    		if(empty($seller_mobile)){
    		    $this->error("登录手机号不能为空");
            }
            if(!is_mobile($seller_mobile)){
                $this->error("登录手机号格式不正确");
            }
            $member = model("member")->where(['member_mobile'=>$seller_mobile])->find();
            if(!$member){
            	$this->error("该会员不存在，请重新输入登录手机号");
            }
            $seller = model("StoreSeller")->where(['member_id'=>$member->member_id])->find();
            if($seller){
            	$this->error("该销售员已经存在");
            }
	        $qrcode_data = [
	        	'seller_state'  => 2,
	        	'seller_role'   => $seller_role,
	        	'store_id'      => $store_id,
                'seller_name'   => $seller_name,
                'seller_mobile' => $seller_mobile,
                'apply_time'    => time(),
                'member_id'     => $member->member_id,
                'is_alert'      => 1
	        ];
            $this->seller_model->startTrans();
            model("Member")->startTrans();
	        $result = $this->seller_model->insertGetId($qrcode_data);
	        if(!$result){
                $this->error('消费商添加失败');
            }
            //判断该会员是否满足升级成消费商的条件
            if((int)$seller_role !== 2){
                if($member['member_grade'] !== 6){
                    $this->error('该会员等级不够');
                }
                $count = model("member")->where(['parent_id'=>$member['member_id'],'member_grade'=>6])->count();
                if($count < 3){
                    $this->error('该会员的绑定用户等级为6级的人数不足三人');
                }
                $member_grade = model('Member')->where(['member_id'=>$member->member_id])->update(['member_grade'=>7]);
                if($member_grade === false){
                    $this->seller_model->rollback();
                    $this->error('消费商等级修改失败');
                }
            }

            $this->seller_model->commit();
            model("Member")->commit();
            $this->success('添加成功',url('company/own_company'));
    	}
    }

    /**
     * 删除销售员
     */
    public function delete_seller(){
        $seller_id = input('get.id');
        $seller = model('StoreSeller')->where(['seller_id'=>$seller_id])->find();
        if(!$seller){
            $this->error('参数传入错误');
        }
        if(!model('StoreSeller')->where(['seller_id'=>$seller_id])->delete()){
            $this->error('删除失败');
        }
        $this->success('删除成功');
    }
}