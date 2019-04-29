<?php
namespace app\robot\controller;
use Think\Db;
class Customer extends Robotbase
{
    protected function _initialize()
    {
        parent::_initialize();
    }

    /**
    * 添加客户信息
    */
    public function create_customer(){
        $robot_sn = $this->token_info['robot_sn'];

        //客户姓名
        $name = input('post.name','','trim');
        $str_len = mb_strlen($name,'UTF-8');

        if (is_badword($name)) {
            $this->ajax_return('100130','name has badword');
        }

        if ($str_len < 2 || $str_len > 10) {
            $this->ajax_return('100131','Invalid name');
        }
        
        //客户性别
        $sex = input('post.sex',1,'intval');

        if ($sex < 1 || $sex > 2) {
            $this->ajax_return('100132','Invalid sex');
        }

        //客户生日
        $birthday = input('post.birthday','','trim');

        if (!is_birthday($birthday)) {
            $this->ajax_return('100133','Invalid birthday');
        }

        //客户手机号
        $mobile = input('post.mobile','','trim');

        if (!empty($mobile) && !is_mobile($mobile)) {
            $this->ajax_return('100134','Invalid mobile');
        }

        //客户身高
        $height = input('post.height',0,'intval');
        if (empty($height) || $height < 100|| $height > 280) {
            $this->ajax_return('100135','Invalid height');
        }

        //客户体重
        $weight = input('post.weight',0,'intval');
        if (empty($weight) || $weight < 20 || $weight > 260) {
            $this->ajax_return('100136','Invalid weight');
        }

        $data = [
            'customer_mobile' => $mobile,
            'customer_name' => $name,
            'robot_sn' => $robot_sn,
            'customer_sex' => $sex,
            'customer_birthday' => $birthday,
            'customer_height' => $height,
            'customer_weigh' => $weight,
            'create_time' => time()
        ];

        $customer_id = Db::name('robot_customer')->insertGetId($data);
        if ($customer_id < 1) {
            $this->ajax_return('100137','Insert data falied');
        }

        unset($data['robot_sn']);

        $data['customer_id'] = $customer_id;

        $this->ajax_return('200','success',$data);
    }

    /**
    * 修改客户信息
    */
    public function edit_customer(){
        $customer_id = input('post.customer_id',0,'intval');
        if (empty($customer_id)) {
            $this->ajax_return('100140','Invalid customer_id');
        }

        $robot_sn = $this->token_info['robot_sn'];

        $customer = Db::name('robot_customer')->where('customer_id',$customer_id)->find();

        if ($customer['robot_sn'] != $robot_sn) {
            $this->ajax_return('100140','Invalid customer_id');
        }

        
        //客户姓名
        $name = input('post.name','','trim');
        $str_len = mb_strlen($name,'UTF-8');

        if (!empty($name)) {
            if (is_badword($name)) {
                $this->ajax_return('100130','name has badword');
            }

            if ($str_len < 2 || $str_len > 10) {
                $this->ajax_return('100131','Invalid name');
            }
        }
        
        //客户性别
        $sex = input('post.sex',1,'intval');

        if ($sex < 1 || $sex > 2) {
            $this->ajax_return('100132','Invalid sex');
        }

        //客户生日
        $birthday = input('post.birthday','','trim');

        if (!empty($birthday) && !is_birthday($birthday)) {
            $this->ajax_return('100133','Invalid birthday');
        }

        //客户手机号
        $mobile = input('post.mobile','','trim');

        if (!empty($mobile) && !is_mobile($mobile)) {
            $this->ajax_return('100134','Invalid mobile');
        }

        //客户身高
        $height = input('post.height',0,'intval');
        if (!empty($height) && ($height < 100|| $height > 280)) {
            $this->ajax_return('100135','Invalid height');
        }

        //客户体重
        $weight = input('post.weight',0,'intval');
        if (!empty($weight) && ($weight < 20 || $weight > 260)) {
            $this->ajax_return('100136','Invalid weight');
        }

        $data = [
            'customer_mobile' => $mobile,
            'customer_name' => $name,
            'customer_sex' => $sex,
            'customer_birthday' => $birthday,
            'customer_height' => $height,
            'customer_weigh' => $weight
        ];

        $data = array_filter($data);

        if (empty($data)) {
            $this->ajax_return('100141','No data update');
        }

        $result = Db::name('robot_customer')->where('customer_id',$customer_id)->data($data)->update();
        if ($result === false) {
            $this->ajax_return('100142','Update data falied');
        }

        $this->ajax_return('200','success',$data);
    }

    /**
    * 修改客户信息
    */
    public function delete_customer(){
        $customer_id = input('post.customer_id',0,'intval');
        if (empty($customer_id)) {
            $this->ajax_return('100140','Invalid customer_id');
        }

        $robot_sn = $this->token_info['robot_sn'];

        $customer = Db::name('robot_customer')->where('customer_id',$customer_id)->find();

        if ($customer['robot_sn'] != $robot_sn) {
            $this->ajax_return('100140','Invalid customer_id');
        }

        
        $result = Db::name('robot_customer')->where('customer_id',$customer_id)->delete();
        if ($result === false) {
            $this->ajax_return('100150','Delete data falied');
        }

        $this->ajax_return('200','success');
    }

    /**
    * 修改客户信息
    */
    public function customer_list(){

        $robot_sn = $this->token_info['robot_sn'];

        $page = input('post.page',1,'intval');

        $list = Db::name('robot_customer')
                ->where('robot_sn',$robot_sn)
                ->field('customer_id,customer_name,customer_mobile,customer_sex')
                ->limit(10)
                ->page($page)
                ->order('create_time DESC')
                ->select();

        if (empty($list)) {
            $this->ajax_return('100160','Empty list');
        }

        $this->ajax_return('200','success',$list);
    }

    /**
    * 获取客户信息
    */
    public function customer_info(){
        $customer_id = input('post.customer_id',0,'intval');
        if (empty($customer_id)) {
            $this->ajax_return('100140','Invalid customer_id');
        }

        $robot_sn = $this->token_info['robot_sn'];

        $customer = Db::name('robot_customer')
                    ->where('customer_id',$customer_id)
                    ->field('customer_id,customer_name,robot_sn,customer_mobile,customer_sex,customer_birthday,customer_height,customer_weigh')
                    ->find();

        if ($customer['robot_sn'] != $robot_sn) {
            $this->ajax_return('100140','Invalid customer_id');
        }

        unset($customer['robot_sn']);

        $this->ajax_return('200','success',$customer);
    }
}