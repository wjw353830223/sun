<?php
namespace app\api\controller;
class Address extends Apibase
{
	protected $addr_model;
	protected function _initialize(){
    	parent::_initialize();
    	$this->addr_model = model('Address');
    }

    /**
    * 获取收获地址列表
    *
    */
    public function get_list(){
    	$member_id = $this->member_info['member_id'];
    	$addr_list = $this->addr_model->where('member_id',$member_id)->select()->toArray();
    	if (empty($addr_list)) {
    		$this->ajax_return('10330', 'empty data');
    	}

    	$this->ajax_return('200','success',$addr_list);
    }

    /**
    * 添加收货地址
    */
   	public function add_address(){
   		$true_name = input('post.true_name','','trim');
   		if (empty($true_name) || words_length($true_name) < 2) {
    		$this->ajax_return('10340','invalid true_name');
    	}

   		$mobile = input('post.mobile','','trim');
    	if (empty($mobile) || !is_mobile($mobile)) {
    		$this->ajax_return('10341','invalid mobile');
    	}

   		$area_id = input('post.area_id',0,'intval');
   		if (empty($area_id)) {
   			$this->ajax_return('10342','invalid area data');
   		}

   		$is_default = input('post.is_default',0,'intval');

   		$area_info = model('Area')->get_info($area_id);

 
   		$area_deep = count($area_info);
   		if ($area_deep < 3) {
   			$this->ajax_return('10342','invalid area data');
   		}

   		$address = input('post.address','','trim');
   		if (empty($address)) {
   			$this->ajax_return('10343','invalid address');
   		}

   		$this->addr_model->startTrans();
   		//无地址数据时，新添加地址强制为默认
   		$has_address = $this->addr_model->where('member_id',$this->member_info['member_id'])->count();
   		if ($has_address > 0) {
   			if ($is_default > 0) {
   				$this->addr_model->where('member_id',$this->member_info['member_id'])->update(['is_default' => '0']);
   				$default = '1';
   			}else{
   				$default = '0';
   			}
   		}else{
   			$default = '1';
   		}

   		$data = array();

   		//组合地址数据
   		foreach ($area_info as $key => $value) {
   			if ($value['area_deep'] == 1) {
   				$data['province_id'] = $value['area_id'];
   			}elseif ($value['area_deep'] == 2) {
   				$data['city_id'] = $value['area_id'];
   			}elseif ($value['area_deep'] == 3) {
   				$data['area_id'] = $value['area_id'];
   			}
   		}

   		$data['true_name'] = $true_name;
   		$data['mob_phone'] = $mobile;
   		$data['area_info'] = model('Area')->get_name($area_id);
   		$data['address'] = $address;
   		$data['member_id'] = $this->member_info['member_id'];
   		$data['is_default'] = $default;
   		$result = $this->addr_model->insertGetId($data);
        $address_info = array();
        $address_info['address_id'] = $result;
   		if ($result) {
   			$this->addr_model->commit();
   			$this->ajax_return('200','success',$address_info);
   		}else{
   			$this->ajax_return('10344','failed to add data');
   		}
   	}

    /**
    * 修改收货地址
    */
    public function edit_address(){
      //地址id
      $address_id = input('post.address_id','0','intval');
      if(empty($address_id)){
        $this->ajax_return('10355','invalid address_id');
      }

      $true_name = input('post.true_name','','trim');
      if (empty($true_name) || words_length($true_name) < 2) {
        $this->ajax_return('10350','invalid true_name');
      }

      $mobile = input('post.mobile','','trim');
      if (empty($mobile) || !is_mobile($mobile)) {
        $this->ajax_return('10351','invalid mobile');
      }

      $area_id = input('post.area_id',0,'intval');
      if (empty($area_id)) {
        $this->ajax_return('10352','invalid area data');
      }

      $area_info = model('Area')->get_info($area_id);
 
      $area_deep = count($area_info);
      if ($area_deep < 3) {
        $this->ajax_return('10352','invalid area data');
      }

      $address = input('post.address','','trim');
      if (empty($address)) {
        $this->ajax_return('10353','invalid address');
      }

      $this->addr_model->startTrans();

      $data = array();

      //组合地址数据
      foreach ($area_info as $key => $value) {
        if ($value['area_deep'] == 1) {
          $data['province_id'] = $value['area_id'];
        }elseif ($value['area_deep'] == 2) {
          $data['city_id'] = $value['area_id'];
        }elseif ($value['area_deep'] == 3) {
          $data['area_id'] = $value['area_id'];
        }
      }

      $data['true_name'] = $true_name;
      $data['mob_phone'] = $mobile;
      $data['area_info'] = model('Area')->get_name($area_id);
      $data['address'] = $address;
      $data['member_id'] = $this->member_info['member_id'];
      $result = $this->addr_model->save($data,['address_id'=>$address_id]);
      if ($result) {
        $this->addr_model->commit();
        $this->ajax_return('200','success');
      }else{
        $this->ajax_return('10354','failed to edit data');
      }
    }

    //删除收货地址
    public function delete_address(){
      $address_id = input('post.address_id','0','intval');
      if(empty($address_id)){
        $this->ajax_return('10360','invalid address_id');
      }

      $member_id = $this->member_info['member_id'];
      $has_info = db('address')->where(['address_id'=>$address_id,'member_id'=>$member_id])->find();
      if(empty($has_info)){
        $this->ajax_return('10361','the address dose not exsit');
      }
      
      $is_default = $has_info['is_default'];
     
      $result = db('address')->where(['address_id'=>$address_id])->delete();
      if($result === false){
          $this->ajax_return('10362','failed to delete data');
      }

      if($is_default == 1){
        $last_id = db('address')->where(['member_id'=>$this->member_info['member_id']])->order(['address_id'=>'desc'])->value('address_id');
        if(!empty($last_id)){
          db('address')->where(['address_id'=>$last_id])->setField('is_default','1');
        }
      }

      $this->ajax_return('200','success');
    }

     /**
    * 修改默认收货地址
    */
    public function set_default(){
        $address_id = input('post.address_id','','intval');
        if(empty($address_id)){
          $this->ajax_return('100460','invalid address_id');
        }

        $has_info = db('address')->where(['address_id'=>$address_id])->value('is_default');
        if($has_info == 1){
          $this->ajax_return('100461','address is default');
        }

        $member_id = $this->member_info['member_id'];
        $default = db('address')->where(['member_id'=>$member_id])->setField('is_default','0');
        if($default === false){
          $this->ajax_return('100462','handle is wrong');
        }

        $result = db('address')->where(['address_id'=>$address_id])->update(['is_default'=>1]);
        if($result === false){
          $this->ajax_return('100463','failed to edit data');
        }

        $this->ajax_return('200','success');
    }
}