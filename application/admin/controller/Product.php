<?php
namespace app\admin\controller;
use Rbac\Tree;
use think\Image;

class Product extends Adminbase
{
    protected $gc_model,$gi_model,$gm_model;
    public function _initialize() {
        parent::_initialize();
        $this->gc_model  = model('GoodsCommon');
        $this->gi_model  = model('GoodsImages');
        $this->gm_model = model('GoodsModule');
    }

    //产品列表
    public function index(){
        $where = $query = [];
        if(request()->isGet()){
            $module_id = input('get.module_id',0,'intval');
            if(!empty($module_id)){
                $where['module_id'] = $module_id;
                $query['module_id'] = $module_id;
            }
            $gc_id = input('get.gc_id',0,'intval');
            if(!empty($gc_id)){
                $where['gc_id'] = $gc_id;
                $query['gc_id'] = $gc_id;
            }
            $goods_name = input('get.goods_name','','trim');
            if(!empty($goods_name)){
                $where['goods_name'] = ['like','%'.$goods_name.'%'];
                $query['goods_name'] = $goods_name;
            }
        }
        $count = $this->gc_model->where($where)->count();
        $lists = $this->gc_model->where($where)
            ->order(['goods_commonid'=>'DESC'])
            ->paginate(20,$count,['query'=>$query]);
        $module = $this->gm_model->select();
        $modules = [];
        foreach($module as $k=>$v){
            $modules[$v['module_id']] = $v['module_name'];
        }
        $class = model('GoodsClass')->select();
        $cate = [];
        foreach($class as $key=>$val){
            $cate[$val['gc_id']] = $val['gc_name'];
        }
        foreach($lists as $k=>$v){
            $lists[$k]['module_name'] = $modules[$v['module_id']];
            $lists[$k]['gc_name'] = $cate[$v['gc_id']];
        }
        $page = $lists->render();
        $module = model('GoodsModule')->where(['module_state'=>1])->select();
        $goods_class = model('GoodsClass')->where(['parent_id'=>0,'gc_state'=>1])->select();
        $this->assign('page',$page);
        $this->assign('lists',$lists);
        $this->assign('module',$module);
        $this->assign('goods_class',$goods_class);
        return $this->fetch();
    }

    /**
     * 产品编辑
     */
    public function edit(){
        $goods_commonid = input('gc_id',0,'intval');
        $goods_common_info = $this->gc_model->where(['goods_commonid'=>$goods_commonid])->find();
        $goods_common_info['goods_server'] = strlen($goods_common_info['goods_server']) > 0 ?
            explode(",",$goods_common_info['goods_server']) : [] ;
        $common_info = json_decode($goods_common_info['goods_spec'],true);
        $cate_ids = model('GoodsModule')->where(['module_id'=>$goods_common_info['module_id']])->value('cate_ids');
        $result = model('GoodsClass')
            ->where(['gc_state'=>1,'gc_id'=>['in',$cate_ids]])
            ->select()->toArray();
        $categorys = '';
        if(!empty($result)){
            $tree = new Tree();
            foreach($result as $r) {
                $r['id'] = $r['gc_id'];
                $r['cname'] = $r['gc_name'];
                $r['selected'] = $r['gc_id'] == $goods_common_info['gc_id'] ? 'selected' : '';
                $count = db('goods_class')->where(['parent_id'=>$r['gc_id']])->count();
                $r['disable'] = ($count > 0) ? 'disabled' : '';
                $array[] = $r;
            }
            $str  = "<option value='\$id' \$selected \$disable>\$spacer \$cname</option>";
            $tree->init($array);
            $categorys = $tree->get_tree(0, $str);
        }

        $goods_image = $this->gi_model
            ->where(['goods_commonid' => $goods_commonid])
            ->field('goods_image,img_id')
            ->select()->toArray();
        //商品服务
        $service = ['全国包邮闪电发货','专业营养师在线指导','云端数据库监控','1比1积分升级赠送','3比1积分赠送'];
        $module = $this->gm_model->select();
        $goods_list = model('GoodsAttribute')->where('goods_commonid',$goods_commonid)->select();
        $specs = json_decode($goods_common_info['spec_value']);
        $spec_names = json_decode($goods_common_info['spec_name']);
        $spec_array = [];
        $goods_price_array = [];
        $i = $j = 0;
        $arr = [];
        if(!empty($specs)){
            foreach ($specs as $spec_name=>$spec){
                foreach ($spec as $k=>$v){
                    $spac_value = strstr($v,'|',true);
                    $color = substr($v,strpos($v,'|')+1);
                    $key = $i == '0' ? 'default1' : 'extend'.$i;
                    $spec_array[$key][$color] = array($key,$spec_names[$i],$spac_value,$color);
                    $arr[$spec_name][$j]['color'] = $color;
                    $arr[$spec_name][$j]['spec_value'] = $spac_value;
                    $arr[$spec_name][$j]['spec_name'] = $spec_names[$i];
                    $j++;
                }
                $i++;
            }
            foreach ($goods_list as $v){
                $spec = json_decode($v['spec_value'],true);
                if(!is_array($spec) && empty($spec)){
                    continue;
                }
                $str = '';
                foreach ($spec as $v1){
                    $str .= trim($v1).'|';
                }
                $goods_price_array[$str]['goods_storage'] = $v['goods_storage'];
                $goods_price_array[$str]['goods_price'] = $v['goods_price'];
                $goods_price_array[$str]['cost_price'] = $v['cost_price'];
                $goods_price_array[$str]['market_price'] = $v['market_price'];
                $goods_price_array[$str]['goods_image'] = $v['goods_image'];
            }
        }
        $this->assign('module',$module);
        $this->assign('server',$service);
        $this->assign('spec_names',$spec_names);
        $this->assign("categorys",$categorys);
        $this->assign("common_info",$common_info);
        $this->assign('affix_data',$goods_image);
        $this->assign('goods_common_info',$goods_common_info);
        $this->assign('goods_price_array',$goods_price_array);
        $this->assign('specs',$arr);
        $this->assign('spec_array',$spec_array);
        return $this->fetch();
    }

    /**
     * 产品编辑提交
     */
    public function edit_post(){
        if (request()->isPost()) {
            $param = input('param.');
            list($goods_data,$tmp) = $this->gc_model->_initCommonGoodsByParam($param);
            if(isset($goods_data['error'])){
                $this->error($goods_data['error']);
            }
            $this->gc_model->startTrans();
            $this->gi_model->startTrans();
            model('GoodsAttribute')->startTrans();
            if(empty($param['photos_alt'])){
                $this->error('相册图集不能为空');
            }
            $dele_result = $this->gi_model->where(['goods_commonid'=>$param['goods_commonid']])->delete();
            if($dele_result === false){
                $this->error('修改失败');
            }
            $image_data = $this->gi_model->add_image($param['photos_alt'],$param['img_id'],$param['goods_commonid']);
            $images_result = $this->gi_model->insertAll($image_data);
            if($images_result === false){
                foreach($image_data as $v){
                    @unlink('uploads/product/'.$v['goods_image']);
                }
                $this->gc_model->rollback();
                $this->error('添加失败');
            }
            $body = [];
            if(isset($tmp)){
                foreach($tmp as $k=>$v){
                    $body[] = $this->base_url.$tmp[$k];
                }
            }

            //生成静态页
            $this->assign([
                'goods_name'  => $goods_data['goods_name'],
                'goods_price' => $goods_data['goods_price'],
                'goods_description' => $goods_data['goods_description'],
                'goods_image' => $this->base_url.'/uploads/product/'.$param['photos_alt'][0],
                'spec_data' => json_decode($goods_data['goods_spec'],true),
                'goods_body' => !empty($body) ? $body : '',
                'goods_service' => $param['goods_server']
            ]);
            $file = $this->fetch("detail_app");
            $uniqid_code = $this->gc_model->where(['goods_commonid'=>$param['goods_commonid']])->value('uniqid_code');
            $goods_data['uniqid_code'] = empty($uniqid_code) ? uniqid() : $uniqid_code;
            $url = './product/'.$goods_data['uniqid_code'].'.html';
            $result = create_page($file,$url);
            if($result === false){
                $this->error('生成静态页失败');
            }
            //更新七牛镜像空间中相关的文件内容
            $key = 'product/'.$goods_data['uniqid_code'].'.html';
            $bucket = config('qiniu.buckets')['images']['name'];
            update_qiniu_file($key, $bucket);

            $result = $this->gc_model->update($goods_data,['goods_commonid'=>$param['goods_commonid']]);
            if(!$result){
                $this->error('修改失败');
            }
            if ($param['changevalue'] == 1) {
                $res = model('GoodsAttribute')->_editGoods($param, $param['goods_commonid']);
                if(isset($res['error'])){
                    $this->error($res['error']);
                }
                if($res === false){
                    $this->gc_model->rollback();
                    $this->gi_model->rollback();
                    $this->error('修改失败');
                }
            } else {
                $delete = model('GoodsAttribute')->where('goods_commonid', $param['goods_commonid'])->delete();
                if ($delete === false) {
                    $this->gc_model->rollback();
                    $this->gi_model->rollback();
                    $this->error('修改失败');
                }
                // 生成商品返回商品ID(SKU)数组
                $res = model('GoodsAttribute')->_addGoods($param, $param['goods_commonid']);
                if(isset($res['error'])){
                    $this->error($res['error']);
                }
                if($res === false){
                    $this->gc_model->rollback();
                    $this->gi_model->rollback();
                    $this->error('修改失败');
                }
            }
            $this->gc_model->commit();
            $this->gi_model->commit();
            model('GoodsAttribute')->commit();
            $this->success('修改成功',url('Product/index'));
        }
    }

    /**
     * 产品添加
     */
    public function add(){
        //商品服务
        $service = ['全国包邮闪电发货','专业营养师在线指导','云端数据库监控','1比1积分升级赠送','3比1积分赠送'];
        $module = $this->gm_model->select();
        $this->assign('module',$module);
        $this->assign('server',$service);
        return $this->fetch();
    }

    /**
     * 产品添加提交
     */
    public function add_post(){
        if(request()->isPost()){
            $param = input('param.');
            list($goods_data,$tmp) = $this->gc_model->_initCommonGoodsByParam($param);
            if(isset($goods_data['error'])){
                $this->error($goods_data['error']);
            }
            $this->gc_model->startTrans();
            $this->gi_model->startTrans();
            model('GoodsAttribute')->startTrans();

            $result = $this->gc_model->save($goods_data);
            $goods_id = $this->gc_model->goods_commonid;
            if($result === false){
                $this->error('添加失败');
            }

            if(empty($param['photos_alt'])){
                $this->gc_model->rollback();
                $this->error('相册图集不能为空');
            }
            $image_data = $this->gi_model->add_image($param['photos_alt'],$param['img_id'],$goods_id);
            $images_result = $this->gi_model->insertAll($image_data);
            if($images_result === false){
                foreach($image_data as $v){
                    @unlink('uploads/product/'.$v['goods_image']);
                }
                $this->gc_model->rollback();
                $this->error('添加失败');
            }
            $body = [];
            if(isset($tmp)){
                foreach($tmp as $k=>$v){
                    $body[] = $this->base_url.$tmp[$k];
                }
            }
            $this->assign([
                'goods_name'  => $goods_data['goods_name'],
                'goods_price' => $goods_data['goods_price'],
                'goods_description' => $goods_data['goods_description'],
                'goods_image' => $this->base_url.'/uploads/product/'.$param['photos_alt'][0],
                'spec_data' => json_decode($goods_data['goods_spec'],true),
                'goods_body' => !empty($body) ? $body : '',
                'goods_service' => $param['goods_server']
            ]);
            $file = $this->fetch("detail_app");
            $url = './product/'.$goods_data['uniqid_code'].'.html';
            $result = create_page($file,$url);
            if($result === false){
                $this->error('生成静态页失败');
            }
            //将静态页直传到七牛
            $key = 'product/'.$goods_data['uniqid_code'].'.html';
            $bucket = config('qiniu.buckets')['images']['name'];
            add_file_to_qiniu($key,$url,$bucket);
            // 生成商品返回商品ID(SKU)数组
            $res = model('GoodsAttribute')->_addGoods($param, $goods_id);
            if(isset($res['error'])){
                $this->error($res['error']);
            }
            if($res === false){
                $this->gc_model->rollback();
                $this->gi_model->rollback();
                $this->error('商品添加失败');
            }
            $this->gc_model->commit();
            $this->gi_model->commit();
            model('GoodsAttribute')->commit();
            $this->success('添加成功',url('Product/index'));
        }
    }

    public function new_state(){
        $is_new = input('param.state',0,'intval');
        $goods_commonid = input('param.id',0,'intval');
        if(!$goods_commonid){
            $this->error('商品id有误');
        }
        $res = model('GoodsCommon')->where(['goods_commonid'=>$goods_commonid])->update(['is_new'=>$is_new]);
        if($res === false){
            $this->error('操作失败');
        }
        $this->success('操作成功');
    }


    public function cate_class(){
        $module_id = input('post.module_id',0,'intval');
        $cate_ids = model('GoodsModule')->where(['module_id'=>$module_id])->value('cate_ids');
        $cate_list = model('GoodsClass')
            ->where(['gc_state'=>1,'parent_id'=>0,'gc_id'=>['in',$cate_ids]])
            ->select()->toArray();
        $categorys = '';
        if(!empty($cate_list)){
            $tree = new Tree();
            foreach($cate_list as $r) {
                $r['id'] = $r['gc_id'];
                $r['cname'] = $r['gc_name'];
                $r['selected'] = '';
                $count = model('GoodsClass')->where(['parent_id'=>$r['gc_id']])->count();
                $r['disable'] = ($count > 0) ? 'disabled' : '';
                $array[] = $r;
            }
            $str  = "<option value='\$id' \$selected \$disable>\$spacer \$cname</option>";
            $tree->init($array);
            $categorys = $tree->get_tree(0, $str);
        }
        return $categorys;
    }

    public function uploads(){
        $file = request()->file('avatar');
        $image = Image::open($file);
        $type = $image->type();
        $save_path = 'public/uploads/product';
        $save_name = uniqid() . '.' . $type;
        $image->save(ROOT_PATH . $save_path . '/' . $save_name);

        $image->thumb(140, 140)->save('uploads/product/'.str_replace(strrchr($save_name,"."),"",$save_name).'_140x140'.'.'.substr(strrchr($save_name, '.'), 1));

        if (is_file(ROOT_PATH . $save_path . '/' . $save_name)) {
            $data = [
                'file' => $save_name,
                'msg'  => '上传成功',
                'code' => 1
            ];
            $bucket = config('qiniu.buckets')['images']['name'];
            $key = 'uploads/product/' . $save_name;
            $path = './' . $key;
            add_file_to_qiniu($key,$path,$bucket);
            echo json_encode($data);
        } else {
            $data = [
                'data' => '',
                'msg'  => $file->getError(),
                'code' => 0
            ];
            echo json_encode($data);
        }
    }
    //产品删除
    public function delete(){
        $goods_commonid = input('get.goods_commonid',0,'intval');
        $this->gc_model->startTrans();
        $this->gi_model->startTrans();
        model('GoodsAttribute')->startTrans();
        $goods_info = $this->gc_model->where(['goods_commonid'=>$goods_commonid])->field('uniqid_code,goods_image')->find();
        if (empty($goods_commonid)) {
            $this->error('数据传入失败！');
        }
        $result = $this->gc_model->destroy($goods_commonid);
        if ($result === false) {
            $this->error('产品删除失败！');
        }
        @unlink('product/'.$goods_info['uniqid_code'].'.html');
        @unlink('uploads/product/'.$goods_info['goods_image']);
        $res = $this->gi_model->where(['goods_commonid'=>$goods_commonid])->delete();
        if($res === false){
            $this->gc_model->rollback();
            $this->error('产品图片失败！');
        }
        $imgs = $this->gi_model->where(['goods_commonid'=>$goods_commonid])->field('goods_image')->select();
        foreach($imgs as $k=>$v){
            @unlink('uploads/product/'.$v['goods_image']);
        }
        $ga_res = model('GoodsAttribute')->where(['goods_commonid'=>$goods_commonid])->delete();
        if($ga_res === false){
            $this->gc_model->rollback();
            $this->gi_model->rollback();
            $this->error('产品属性删除失败');
        }
        $this->gc_model->commit();
        $this->gi_model->commit();
        model('GoodsAttribute')->commit();
        $this->success("产品删除成功！");
    }

    /**
     * @return mixed
     * 产品上下架
     */
    public function update_state(){
        $id = input('param.id','','intval');
        if(empty($id)){
            $this->error('参数传入错误');
        }
        $state = input('param.state',0,'intval');

        $result = $this->gc_model->where(['goods_commonid'=>$id])->update(['status'=>$state]);
        if($result === false){
            $this->error('修改失败');
        }
        $this->success('修改成功');
    } 

    //分类列表
    public function cate_list(){
        $result = model('GoodsClass')->order(array("gc_sort"=>"asc"))->select()->toArray();
        $categorys = '';
        if (!empty($result)) {
            $tree = new Tree();
            $tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
            $tree->nbsp = '&nbsp;&nbsp;&nbsp;';

            foreach ($result as $r) {
                $r['gc_manage'] = '<a href="' . url("product/cate_add", array("gc_parent_id" => $r['gc_id'])) . '">添加子类</a> | <a href="' . url("product/cate_edit", array("gc_id" => $r['gc_id'])) . '">编辑</a> | <a class="js-ajax-delete" href="' . url("product/cate_delete", array("gc_id" => $r['gc_id'])) . '">删除</a>';
                $r['id']=$r['gc_id'];
                $array[] = $r;
            }

            $tree->init($array);
            $str = "<tr>
						<td><input name='list_orders[\$id]' type='text' size='3' value='\$gc_sort' class='input input-order'></td>
						<td>\$id</td>
						<td>\$spacer\$gc_name</td>
						<td>\$gc_manage</td>
					</tr>";
            $categorys = $tree->get_tree(0, $str);
        }
        $this->assign('categorys',$categorys);
        return $this->fetch();
    }

    //添加分类
    public function cate_add(){
        $param = input('param.');
        $categorys ='';
        if(isset($param['gc_parent_id']) && !empty($param['gc_parent_id'])){
            $parent_id = $param['gc_parent_id'];
        }
        $parent_id = isset($parent_id) ? $parent_id : 0;
        $class = model('GoodsClass')->where(['gc_id'=>$parent_id])->find();
        $result = model('GoodsClass')->select()->toArray();
        if(!empty($result)){
            $tree = new Tree();
            $tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
            $tree->nbsp = '&nbsp;&nbsp;&nbsp;';
            foreach ($result as $r) {
                $r['id']=$r['gc_id'];
                $r['selected'] = $r['gc_id'] == $parent_id ? 'selected' : '';
                $array[] = $r;
            }
            $tree->init($array);
            $str = "
					<option value=\$id \$selected>\$spacer\$gc_name</option>
					";
            $categorys = $tree->get_tree(0, $str);
        }
        $this->assign('class',$class);
        $this->assign('categorys',$categorys);
        return $this->fetch();
    }

    //添加分类提交
    public function cate_add_post(){
        if(request()->isPost()){
            $param = input("post.");
            if(empty(trim($param['gc_name']))){
                $this->error('分类名称不能为空');
            }
            $class_data = array();
            $class_data['parent_id'] = $param['gc_parent_id'];
            $class_data['gc_name'] = $param['gc_name'];
            $result = model('GoodsClass')->insert($class_data);
            if ($result !== false) {
                $this->success("添加分类成功",url("product/cate_list"));
            }else{
                $this->error("添加失败！");
            }
        }
    }

    //编辑分类
    public function cate_edit(){
        $gc_id = input('get.gc_id','','intval');
        if(empty($gc_id)){
            $this->error('分类id传入错误');
        }
        $cate_data = model('GoodsClass')
            ->where(["gc_id"=>$gc_id])
            ->field("gc_id,parent_id,gc_name,gc_sort,gc_state")->find();
        if(empty($cate_data)){
            $this->error('分类id传入错误');
        }
        $result = model('GoodsClass')->select()->toArray();
        $categorys = '';
        if(!empty($result)){
            $tree = new Tree();
            $tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
            $tree->nbsp = '&nbsp;&nbsp;&nbsp;';
            foreach ($result as $r) {
                $r['id']=$r['gc_id'];
                $r['selected'] = $r['gc_id'] == $cate_data['parent_id'] ? 'selected' : '';
                $array[] = $r;
            }
            $tree->init($array);
            $str = "
					<option value=\$id \$selected>\$spacer\$gc_name</option>
					";
            $categorys = $tree->get_tree(0, $str);
        }
        $module = model('GoodsModule')->select()->toArray();
        $this->assign('module',$module);
        $this->assign('categorys',$categorys);
        $this->assign("cate_data",$cate_data);
        return $this->fetch();
    }

    //编辑分类提交
    public function cate_edit_post(){
        if(request()->isPost()){
            $param = input("post.");
            if (empty($param['gc_name'])) {
                $this->error("请填写分类名称");
            }
            if($param['parent_id'] == $param['gc_id']){
                $this->error('请重新选择上级菜单');
            }
            $cate_result = model('GoodsClass')->where(["gc_id"=>$param['gc_id']])->update($param);
            if ($cate_result !== false) {
                $this->success("修改分类成功",url("product/cate_list"));
            }else{
                $this->error("修改失败！");
            }
        }
    }


    //产品分类下架
    public function lock(){
        $gc_id = input('get.gc_id',0,'intval');
        if (!empty($gc_id)) {
            $result = db('goods_class')->where(['gc_id'=>$gc_id])->setField('gc_state','0');
            if ($result!==false) {
                $this->success("产品下架成功！");
            } else {
                $this->error('产品下架失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }

    //产品分类上架
    public function unlock(){
        $gc_id = input('get.gc_id',0,'intval');
        if (!empty($gc_id)) {
            $result = db('goods_class')->where(['gc_id'=>$gc_id])->setField('gc_state','1');
            if ($result!==false) {
                $this->success("产品上架成功！");
            } else {
                $this->error('产品上架失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }

    //分类排序
    public function list_orders(){
        $ids = $_POST['list_orders'];
        if (!is_array($ids)) {
            $this->error("参数错误！");
        }
        foreach ($ids as $key => $r) {
            if ($r > 0) {
                db('goods_class')->where(["gc_id"=>$key])->update(['gc_sort'=>$r]);
            }
        }
        $this->success("排序更新成功！");
    }

    //分类删除
    public function cate_delete(){
        $gc_id = input("get.gc_id",0,"intval");
        //查询是否有子分类
        $has_class = model('GoodsClass')->where(["parent_id"=>$gc_id])->count();
        if($has_class > 0){
            $this->error("当前分类下有子分类,不能删除！",url('product/cate_list'));
        }

        $goods_nums = model('GoodsCommon')->where(["gc_id"=>$gc_id])->count();

        if($goods_nums > 0){
            $this->error("当前分类下有产品,不能删除！",url('product/cate_list'));
        }

        $cate_delete = model('GoodsClass')->where(["gc_id"=>$gc_id])->delete();
        if ($cate_delete !== false) {
            $this->success("删除分类成功",url("product/cate_list"));
        }else{
            $this->error("删除失败！");
        }
    }

    /*
     * 排序
    */
    public function listorders(){
        $ids = $_POST['listorders'];
        if (!is_array($ids)) {
            $this->error("参数错误！");
        }
        foreach ($ids as $key => $r) {
            if ($r > 0){
                $this->gc_model->save(['list_order' => $r],['goods_commonid' => $key]);
            }
        }
        $this->success("排序更新成功！");
    }

    // 文件上传
    public function plupload(){
        $app = input('get.app');
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
                $save_path = 'public/uploads/product';
                $type = $image->type();
                $save_name = uniqid() . '.' . $type;
                $info = $file->rule('uniqid')->validate(['size'=>$upload_max_filesize,'ext'=>$all_allowed_exts])->move(ROOT_PATH.$save_path.'/',true,false);
                if($info){
                    $result = $image->thumb(140, 140)->save('uploads/product/'.str_replace(strrchr($info->getFilename(),"."),"",$info->getFilename()).'_140x140'.'.'.substr(strrchr($info->getFilename(), '.'), 1));

                    $url=config('configs.url').$info->getFilename();
                    $filepath = $savepath.$info->getSaveName();

                    $bucket = config('qiniu.buckets')['images']['name'];
                    $key = 'uploads/product/'.$info->getSaveName();
                    $path = './' . $key;
                    add_file_to_qiniu($key, $path, $bucket);
                    $url=config('qiniu.buckets')['images']['domain'].'/'.$key;
                    $thumb_key = 'uploads/product/'.str_replace(strrchr($info->getFilename(),"."),"",$info->getFilename()).'_140x140'.'.'.substr(strrchr($info->getFilename(), '.'), 1);
                    $thumb_path = './' . $thumb_key;
                    add_file_to_qiniu($thumb_key, $thumb_path, $bucket);
                    ajax_return(array('preview_url'=>$url,'filepath'=>$filepath,'url'=>$url,'name'=>$info->getSaveName(),'status'=>1,'message'=>'success'));
                }else{
                    ajax_return(array('name'=>'','status'=>0,'message'=>$info->getError()));
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
            return $this->fetch("plupload");
        }
    }

    // 上传限制设置界面
    public function upload(){
        $upload_setting=sp_get_upload_setting();
        $this->assign($upload_setting);
        return $this->fetch('upload');
    }

    /**
     * 产品分区列表
     */
    public function module_list(){
        $module = $this->gm_model->order('add_time asc')->select()->toArray();
        $domain = config('qiniu.buckets')['images']['domain'];

        foreach ($module as $key => $value) {
            if (!empty($value['module_photo'])) {
                $module[$key]['module_photo'] = $domain . '/uploads/advert/' .$value['module_photo'];
            }
        }

        $this->assign('module',$module);
        return $this->fetch();
    }

    /**
     * 添加产品分区
     */
    public function add_module(){
        if(request()->isGet()){
            return $this->fetch();
        }
        if(request()->isPost()){
            $name = input('param.module_name');
            if(empty($name)){
                $this->error('分区名称不能为空');
            }
            $module_photo = input('param.module_photo','','trim');
            $cate_ids = input('param.cate_ids','','trim');
            $state = input('param.module_state',0,'intval');
            $module_desc = input('param.module_desc','','trim');

            if(!empty($_FILES) && $_FILES['photo']['size'] > 0){
                $file = request()->file('photo');
                $image = \think\Image::open($file);
                $save_path = 'public/uploads/advert';
                $type = $image->type();
                $save_name = uniqid() . '.' . $type;
                $info = $file->rule('uniqid')->validate(['size'=>1048576,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.$save_path.'/',true,false);
                $file_name = $info->getFilename();
                if(!$info){
                    $this->error($file->getError());
                }
                $bucket = config('qiniu.buckets')['images']['name'];
                $key = 'uploads/advert/'. $info->getSaveName();
                $path = './uploads/advert/' . $info->getSaveName();
                add_file_to_qiniu($key,$path,$bucket);
            }else{
                $this->error('分区图不能为空');
            }

            $data = [
                'module_desc'  => $module_desc,
                'module_photo' => $file_name,
                'module_state' => $state,
                'module_name'  => $name,
                'add_time'     => time(),
                'cate_ids'     => $cate_ids
            ];
            $result = $this->gm_model->insert($data);
            if($result === false){
                $this->error('添加失败');
            }
            $this->success('添加完成',url('product/module_list'));
        }
    }

    /**
     * 编辑修改产品分区
     */
    public function edit_module(){
        if(request()->isGet()){
            $id = input('get.id');
            $module = $this->gm_model->where(['module_id'=>$id])->order('add_time desc')->find();
            $this->assign('module',$module);
            return $this->fetch();
        }
        if(request()->isPost()){
            $id = input('param.module_id');
            $name = input('param.module_name');
            if(empty($name)){
                $this->error('分区名称不能为空');
            }

            $module_photo = input('param.module_photo','','trim');
            $state = input('param.module_state',0,'intval');
            $cate_ids = input('param.cate_ids','','trim');
            $module_desc = input('param.module_desc','','trim');

            if(!empty($_FILES) && $_FILES['photo']['size'] > 0){
                $file = request()->file('photo');
                $image = \think\Image::open($file);
                $save_path = 'public/uploads/advert';
                $type = $image->type();
                $save_name = uniqid() . '.' . $type;
                $info = $file->rule('uniqid')->validate(['size'=>1048576,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.$save_path.'/',true,false);
                $file_name = $info->getFilename();
                if(!$info){
                    $this->error($file->getError());
                }
                $bucket = config('qiniu.buckets')['images']['name'];
                $key = 'uploads/advert/'. $info->getSaveName();
                $path = './uploads/advert/' . $info->getSaveName();
                add_file_to_qiniu($key,$path,$bucket);
            }else{
                $file_name = $module_photo;
            }

            $data = [
                'module_desc'  => $module_desc,
                'module_photo' => $file_name,
                'module_state' => $state,
                'module_name'  => $name,
                'cate_ids'     => $cate_ids
            ];

            $result = $this->gm_model->where(['module_id'=>$id])->update($data);
            if($result === false){
                $this->error('修改失败');
            }
            $this->success('修改完成',url('product/module_list'));
        }
    }

    /**
     * 删除产品分区
     */
    public function del_module(){
        $id = input('param.id',0,'intval');
        if(!$id){
            $this->error('参数传入错误');
        }
        $count = model('GoodsCommon')->where(['module_id'=>$id])->count();
        if($count > 0){
            $this->error('该分区下还有商品未处理，暂不能删除');
        }
        $result = model('GoodsModule')->where(['module_id'=>$id])->delete();
        if($result === false){
            $this->error('删除失败');
        }else{
            $this->success('删除成功',url('product/module_list'));
        }
    }

    /**
     * 分区选择分类
     */
    public function get_cate(){
        $result = [];
        $type = input('post.type','','trim');
        if($type == 'add'){
            $result = model('GoodsClass')->where(['gc_state'=>1,'parent_id'=>0])->select()->toArray();
        }elseif($type == 'edit'){
            $module_id = input('post.module_id',0,'intval');
            $result['cate'] = model('GoodsClass')->where(['gc_state'=>1,'parent_id'=>0])->select()->toArray();
            $cate_ids = model('GoodsModule')->where(['module_id'=>$module_id])->value('cate_ids');
            $ids = explode(',',$cate_ids);
            foreach($ids as $v){
                $result['id'][] = $v;
            }
        }
        return $result;
    }
}