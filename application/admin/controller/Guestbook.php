<?php
namespace app\admin\controller;
use think\Db;

class Guestbook extends Adminbase
{
	protected $guestbook_model; 
	public function _initialize() {
		parent::_initialize();
		$this->guestbook_model = model('guestbook');
	}

	//留言管理首页
	public function index(){
		$count = $this->guestbook_model->where(['status' => 1])->count();
        $guest_lists = $this->guestbook_model->with('member')->where(['status' => 1])->order('id desc')->paginate(20,$count);
        $page = $guest_lists->render();
        $this->assign('page',$page);
        $guest_array = ['0'=>'APP使用','1'=>'账号','2'=>'智能硬件','3'=>'营养师'];
        foreach($guest_lists as $k => $v){
            foreach($guest_array as $kk => $vv){
                if($v['back_type'] == $kk){
                    $guest_lists[$k]['back_type'] = $vv;
                }
            }
        }
    	$this->assign('guest_lists', $guest_lists);
        return $this->fetch();
	}

	public function delete(){
		$id = input("get.id",0,'intval');
		$result = $this->guestbook_model->where(["id"=>$id])->update(['status' => 0]);
		if($result !== false){
			$this->success("删除成功！");
		}else{
			$this->error('删除失败！');
		}
	}

	public function leave_message(){
        $count = DB::name('LeaveMessage')->count();
	    $message = DB::name('LeaveMessage')->order('message_time desc')->paginate(20,$count);
	    $page = $message->render();
	    $this->assign('message',$message);
	    $this->assign('page',$page);
	    return $this->fetch();
    }

    public function del_message(){
        $message_id = input('get.id',0,'intval');
        if(empty($message_id)){
            $this->error('id传入有误');
        }
        $res = DB::name('LeaveMessage')->where(['message_id'=>$message_id])->delete();
        if($res === false){
            $this->error('删除失败');
        }
        $this->success('删除成功',url('guestbook/leave_message'));
    }

}