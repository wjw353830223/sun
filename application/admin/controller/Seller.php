<?php
namespace app\admin\controller;
use Rbac\Tree;
use think\db;
class Seller extends Adminbase
{
	public function _initialize() {
		parent::_initialize();
	}

	//销售员列表
	public function index(){
		$seller_company_model = db('seller_company');
		$where['is_own_seller'] = 0;
		$lists = $seller_company_model->where($where)->paginate(20,$count);
		$page = $lists->render();
		$this->assign('page',$page);
		$this->assign('lists',$lists);
		return $this->fetch();
	}

	//编辑销售员
	public function edit_seller(){
		if(request()->isGet()){
		    $seller_id = input('get.seller_id');
		    $seller = model('store_seller')->where(['seller_id'=>$seller_id])->find();
            //公司分类
            $result = model('Store')->order(["create_time"=>"asc"])
                ->field('store_id,store_parentid as parent_id,store_name,store_state,store_sales,create_time,store_cityid,store_provinceid,store_areaid,store_address')
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
                    $child['selected'] = $r['store_id'] == $seller['store_id'] ? "selected" : "";
                    $child = array_merge($child,$result[$k]);
                    $array[] = $child;
                }
                $tree->init($array);
                $str = "
					<option value=\$id \$selected>\$spacer\$catname</option>
					";
                $categorys = $tree->get_tree(0, $str);
            }
            $this->assign('categorys',$categorys);
		    $this->assign("seller",$seller);
            return $this->fetch();
        }
        if(request()->isPost()){
		    $seller_id = input('post.seller_id');
            $store_id = input('post.store_id');
            $seller_role = input('post.seller_role');
            $seller_name = input('post.seller_name');
            if(empty($seller_name)){
                $this->error("销售员姓名不能为空");
            }
            //判断该会员是否满足升级成消费商的条件
            $member_id = model('StoreSeller')->where(['seller_id'=>$seller_id])->value('member_id');
            $member_grade = model('Member')->where(['member_id'=>$member_id])->value('member_grade');
            if($seller_role == 1 || $seller_role == 3){
                if($member_grade !== 6){
                    $this->error('该会员等级不够');
                }
                $count = model("member")->where(['parent_id'=>$member_id,'member_grade'=>6])->count();
                if($count < 3){
                    $this->error('该会员的下级用户等级为6级的人数不足三人');
                }
            }
            $data = [
                'seller_role' => $seller_role,
                'store_id' => $store_id,
                'seller_name' => $seller_name,
            ];
            $result = model('StoreSeller')->save($data,['seller_id'=>$seller_id]);
            if($result === false){
                $this->error('编辑失败');
            }else{
                $this->success('编辑成功',url('company/seller_list'));
            }
        }
	}

	//公司编辑
	public function edit(){
		return $this->fetch();
	}

	//公司编辑提交
	public function edit_post(){
		
	}

	//公司添加
	public function add(){
		return $this->fetch();
	}

	//公司添加提交
	public function add_post(){
		
	}
}
