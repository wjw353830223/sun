<?php
namespace app\robot\controller;
use Think\Db;
class Analyse extends Robotbase
{
    protected function _initialize()
    {
        parent::_initialize();
    }

   	/**
   	* 血压计数据上传
   	*/
    public function set_blood()
    {
    	$family_member_id = input('post.family_member_id',0,'intval');
    	$customer_id = input('post.customer_id',0,'intval');

    	if ($family_member_id < 1 && $customer_id < 1) {
    		$this->ajax_return('100170','invalid param');
    	}

    	//脉搏
    	$pulse = input('post.pulse',0,'intval');

    	//收缩压
    	$systolic = input('post.systolic',0,'intval');

    	//收缩压
    	$diastolic = input('post.diastolic',0,'intval');

    	$robot_sn = $this->token_info['robot_sn'];

    	if ($pulse < 1 || $systolic < 1 || $diastolic < 1) {
    		$this->ajax_return('100171','invalid analyse param');
    	}

    	if ($family_member_id > 0) {
    		
    		$family = Db::name('family_member')
    				  ->alias('a')
    				  ->join('__FAMILY__ b ','b.family_id = a.family_id')
    				  ->where('a.id',$family_member_id)
    				  ->field('b.robot_imei')
    				  ->find();
    		
    		if (empty($family['robot_imei'])) {
    			$this->ajax_return('100172','invalid family_member_id');
    		}

    		if ($family['robot_imei'] != $robot_sn) {
    			$this->ajax_return('100172','invalid family_member_id');
    		}

    	}else{

    		$customer_robot =  Db::name('robot_customer')->where('customer_id',$customer_id)->value('robot_sn');

    		if (empty($customer_robot)) {
    			$this->ajax_return('100174','invalid customer_id');
    		}

    		if ($customer_robot != $robot_sn) {
    			$this->ajax_return('100174','invalid customer_id');
    		}

    	}

    	// 启动事务
		Db::startTrans();
		try{
			$blood_data = [
				'robot_sn' => $robot_sn,
				'pulse' => $pulse,
				'systolic' => $systolic,
				'diastolic' => $diastolic,
				'create_time' => time()
			];

		    $blood_id = Db::name('robot_blood')->insertGetId($blood_data);

		    $analyse_data = [
		    	'robot_sn' => $robot_sn,
		    	'relation_id' => $blood_id,
		    	'analyse_type' => 'blood',
		    	'create_time' => time()
		    ];

		    //优先家庭成员数据
		    if ($family_member_id > 0) {
		    	$analyse_data['family_member_id'] = $family_member_id;
		    }else{
		    	$analyse_data['customer_id'] = $customer_id;
		    }


		    Db::name('robot_analyse')->insert($analyse_data);
		    // 提交事务
		    Db::commit();    
		} catch (\Exception $e) {
		    // 回滚事务
		    Db::rollback();
		    $this->ajax_return('100173','field to save data');
		}

		$this->ajax_return('200','success',$blood_data);
    }

    /**
   	* 胆固醇数据上传
   	*/
    public function set_chole()
    {
    	$family_member_id = input('post.family_member_id',0,'intval');
    	$customer_id = input('post.customer_id',0,'intval');

    	if ($family_member_id < 1 && $customer_id < 1) {
    		$this->ajax_return('100170','invalid param');
    	}

    	$cholesterin = input('post.cholesterin',0,'intval');

    	$robot_sn = $this->token_info['robot_sn'];

    	if ($cholesterin < 1) {
    		$this->ajax_return('100180','invalid cholesterin');
    	}

    	if ($family_member_id > 0) {
    		
    		$family = Db::name('family_member')
    				  ->alias('a')
    				  ->join('__FAMILY__ b ','b.family_id = a.family_id')
    				  ->where('a.id',$family_member_id)
    				  ->field('b.robot_imei')
    				  ->find();
    		
    		if (empty($family['robot_imei'])) {
    			$this->ajax_return('100172','invalid family_member_id');
    		}

    		if ($family['robot_imei'] != $robot_sn) {
    			$this->ajax_return('100172','invalid family_member_id');
    		}

    	}else{

    		$customer_robot =  Db::name('robot_customer')->where('customer_id',$customer_id)->value('robot_sn');

    		if (empty($customer_robot)) {
    			$this->ajax_return('100174','invalid customer_id');
    		}

    		if ($customer_robot != $robot_sn) {
    			$this->ajax_return('100174','invalid customer_id');
    		}
    	}

    	// 启动事务
		Db::startTrans();
		try{
			$chole_data = [
				'robot_sn'    => $robot_sn,
				'cholesterin' => $cholesterin,
				'create_time' => time()
			];

		    $relation_id = Db::name('robot_chole')->insertGetId($chole_data);

		    $analyse_data = [
		    	'robot_sn' => $robot_sn,
		    	'relation_id' => $relation_id,
		    	'analyse_type' => 'chole',
		    	'create_time' => time()
		    ];

		    //优先家庭成员数据
		    if ($family_member_id > 0) {
		    	$analyse_data['family_member_id'] = $family_member_id;
		    }else{
		    	$analyse_data['customer_id'] = $customer_id;
		    }

		    Db::name('robot_analyse')->insert($analyse_data);
		    // 提交事务
		    Db::commit();    
		} catch (\Exception $e) {
		    // 回滚事务
		    Db::rollback();
		    $this->ajax_return('100173','field to save data');
		}

		$this->ajax_return('200','success',$chole_data);
    }

    /**
   	* 血糖数据上传
   	*/
    public function set_sugar()
    {
    	$family_member_id = input('post.family_member_id',0,'intval');
    	$customer_id = input('post.customer_id',0,'intval');

    	if ($family_member_id < 1 && $customer_id < 1) {
    		$this->ajax_return('100170','invalid param');
    	}

    	$sugar_blood = input('post.sugar_blood',0,'intval');

    	$robot_sn = $this->token_info['robot_sn'];

    	if ($sugar_blood < 1) {
    		$this->ajax_return('100190','invalid sugar blood');
    	}

    	if ($family_member_id > 0) {
    		
    		$family = Db::name('family_member')
    				  ->alias('a')
    				  ->join('__FAMILY__ b ','b.family_id = a.family_id')
    				  ->where('a.id',$family_member_id)
    				  ->field('b.robot_imei')
    				  ->find();
    		
    		if (empty($family['robot_imei'])) {
    			$this->ajax_return('100172','invalid family_member_id');
    		}

    		if ($family['robot_imei'] != $robot_sn) {
    			$this->ajax_return('100172','invalid family_member_id');
    		}

    	}else{

    		$customer_robot =  Db::name('robot_customer')->where('customer_id',$customer_id)->value('robot_sn');

    		if (empty($customer_robot)) {
    			$this->ajax_return('100174','invalid customer_id');
    		}

    		if ($customer_robot != $robot_sn) {
    			$this->ajax_return('100174','invalid customer_id');
    		}

    	}

    	// 启动事务
		Db::startTrans();
		try{
			$sugar_data = [
				'robot_sn'    => $robot_sn,
				'sugar_blood' => $sugar_blood,
				'create_time' => time()
			];

		    $relation_id = Db::name('robot_sugar')->insertGetId($sugar_data);

		    $analyse_data = [
		    	'robot_sn' => $robot_sn,
		    	'relation_id' => $relation_id,
		    	'analyse_type' => 'sugar',
		    	'create_time' => time()
		    ];

		    //优先家庭成员数据
		    if ($family_member_id > 0) {
		    	$analyse_data['family_member_id'] = $family_member_id;
		    }else{
		    	$analyse_data['customer_id'] = $customer_id;
		    }

		    Db::name('robot_analyse')->insert($analyse_data);
		    // 提交事务
		    Db::commit();    
		} catch (\Exception $e) {
		    // 回滚事务
		    Db::rollback();
		    $this->ajax_return('100173','field to save data');
		}

		$this->ajax_return('200','success',$sugar_data);
    }

    /**
   	* 尿酸数据上传
   	*/
    public function set_uric()
    {
    	$family_member_id = input('post.family_member_id',0,'intval');
    	$customer_id = input('post.customer_id',0,'intval');

    	if ($family_member_id < 1 && $customer_id < 1) {
    		$this->ajax_return('100170','invalid param');
    	}

    	$uric_acid = input('post.uric_acid',0,'intval');

    	$robot_sn = $this->token_info['robot_sn'];

    	if ($uric_acid < 1) {
    		$this->ajax_return('100200','invalid uric acid');
    	}

    	if ($family_member_id > 0) {
    		
    		$family = Db::name('family_member')
    				  ->alias('a')
    				  ->join('__FAMILY__ b ','b.family_id = a.family_id')
    				  ->where('a.id',$family_member_id)
    				  ->field('b.robot_imei')
    				  ->find();
    		
    		if (empty($family['robot_imei'])) {
    			$this->ajax_return('100172','invalid family_member_id');
    		}

    		if ($family['robot_imei'] != $robot_sn) {
    			$this->ajax_return('100172','invalid family_member_id');
    		}

    	}else{

    		$customer_robot =  Db::name('robot_customer')->where('customer_id',$customer_id)->value('robot_sn');

    		if (empty($customer_robot)) {
    			$this->ajax_return('100174','invalid customer_id');
    		}

    		if ($customer_robot != $robot_sn) {
    			$this->ajax_return('100174','invalid customer_id');
    		}

    	}

    	// 启动事务
		Db::startTrans();
		try{
			$uric_data = [
				'robot_sn'    => $robot_sn,
				'uric_acid' => $uric_acid,
				'create_time' => time()
			];

		    $relation_id = Db::name('robot_uric')->insertGetId($uric_data);

		    $analyse_data = [
		    	'robot_sn' => $robot_sn,
		    	'relation_id' => $relation_id,
		    	'analyse_type' => 'uric',
		    	'create_time' => time()
		    ];

		    //优先家庭成员数据
		    if ($family_member_id > 0) {
		    	$analyse_data['family_member_id'] = $family_member_id;
		    }else{
		    	$analyse_data['customer_id'] = $customer_id;
		    }

		    Db::name('robot_analyse')->insert($analyse_data);
		    // 提交事务
		    Db::commit();    
		} catch (\Exception $e) {
		    // 回滚事务
		    Db::rollback();
		    $this->ajax_return('100173','field to save data');
		}

		$this->ajax_return('200','success',$uric_data);
    }

    /**
   	* 额温计数据上传
   	*/
    public function set_exergen()
    {
    	$family_member_id = input('post.family_member_id',0,'intval');
    	$customer_id = input('post.customer_id',0,'intval');

    	if ($family_member_id < 1 && $customer_id < 1) {
    		$this->ajax_return('100170','invalid param');
    	}

    	$temperature = input('post.temperature',0,'intval');

    	$robot_sn = $this->token_info['robot_sn'];

    	if ($temperature < 1) {
    		$this->ajax_return('100210','invalid temperature');
    	}

    	$temperature_type = input('post.temperature_type',0,'intval');
    	if (empty($temperature_type) || $temperature_type < 1 || $temperature_type > 3) {
    		$this->ajax_return('100211','invalid temperature type');
    	}

    	if ($family_member_id > 0) {
    		
    		$family = Db::name('family_member')
    				  ->alias('a')
    				  ->join('__FAMILY__ b ','b.family_id = a.family_id')
    				  ->where('a.id',$family_member_id)
    				  ->field('b.robot_imei')
    				  ->find();
    		
    		if (empty($family['robot_imei'])) {
    			$this->ajax_return('100172','invalid family_member_id');
    		}

    		if ($family['robot_imei'] != $robot_sn) {
    			$this->ajax_return('100172','invalid family_member_id');
    		}

    	}else{

    		$customer_robot =  Db::name('robot_customer')->where('customer_id',$customer_id)->value('robot_sn');

    		if (empty($customer_robot)) {
    			$this->ajax_return('100174','invalid customer_id');
    		}

    		if ($customer_robot != $robot_sn) {
    			$this->ajax_return('100174','invalid customer_id');
    		}

    	}

    	// 启动事务
		Db::startTrans();
		try{
			$exergen_data = [
				'robot_sn'    => $robot_sn,
				'temperature' => $temperature,
				'temperature_type' => $temperature_type,
				'create_time' => time()
			];

		    $relation_id = Db::name('robot_exergen')->insertGetId($exergen_data);

		    $analyse_data = [
		    	'robot_sn' => $robot_sn,
		    	'relation_id' => $relation_id,
		    	'analyse_type' => 'exergen',
		    	'create_time' => time()
		    ];

		    //优先家庭成员数据
		    if ($family_member_id > 0) {
		    	$analyse_data['family_member_id'] = $family_member_id;
		    }else{
		    	$analyse_data['customer_id'] = $customer_id;
		    }

		    Db::name('robot_analyse')->insert($analyse_data);
		    // 提交事务
		    Db::commit();    
		} catch (\Exception $e) {
		    // 回滚事务
		    Db::rollback();
		    $this->ajax_return('100173','field to save data');
		}

		$this->ajax_return('200','success',$exergen_data);
    }
}