<?php
namespace app\admin\controller;
class Notice extends Adminbase
{

    protected $message_model;

    public function _initialize()
    {
        parent::_initialize();
        $this->message_model = model("message");
    }

    //发送通知
    public function index()
    {
        //会员列表点击通知发送通知
        $member_id = input('get.id', 0, 'intval');
        $member_mobile = '';
        if ($member_id > 0) {
            $member_info = model('Member')->get($member_id)->toArray();
            $member_mobile = $member_info['member_mobile'];
        }
        $this->assign('member_mobile', $member_mobile);
        return $this->fetch();
    }

    //发送通知提交
    public function send_post()
    {
        $insert_arr = [];
        $message_type = input('post.message_type', 1, 'intval');
        if ($message_type == 1) {
            $content = input('post.content', '', 'trim');//信息内容
            if (empty($content)) {
                $this->error('未填写发送内容');
            }
            $insert_arr['message_body'] = $content;
            $insert_arr['message_type'] = 1;
            $insert_arr['is_push'] = 0;
        } else {
            $message_title = input('post.message_title', '', 'trim');
            if (empty($message_title)) {
                $this->error('活动标题不能为空');
            }
            $active_url = input('post.active_url','','trim');
            if (empty($active_url)) {
                $this->error('活动链接不能为空');
            }
            $detail = input('post.detail','','trim');
            if (empty($detail)) {
                $this->error('活动简介不能为空');
            }
            $insert_arr['push_detail'] = $active_url;
            $insert_arr['message_title'] = $message_title;
            $insert_arr['push_title'] = $message_title;
            $insert_arr['message_body'] = $detail;
            $insert_arr['message_type'] = 0;
            $insert_arr['is_push'] = 2;
        }

        $send_type = input('post.send_type', 0, 'intval');
        $mobile_list = input('post.mobile_list', '', 'trim');
        if (empty($send_type)) {
            $this->error('发送类型填写错误');
        }
        //整理发送列表
        $memberid_list = [];
        //指定会员
        if ($send_type == 1) {
            $model_member = Model('member');
            $tmp = explode("\n", $mobile_list);
            if (!empty($tmp)) {
                foreach ($tmp as $k => $v) {
                    $tmp[$k] = trim($v);
                }
                $tmp = implode(',', $tmp);
                //查询会员列表
                $memberid_list = $model_member->where('member_mobile', 'in', $tmp)->column('member_id');
            }
            unset($tmp);
        }
        if (empty($memberid_list) && $send_type != 2) {
            $this->error('会员手机号填写错误');
        }

        //添加短消息
        $model_message = Model('message');
        $insert_arr['from_member_id'] = 0;
        if ($send_type == 2) {
            $insert_arr['to_member_id'] = 'all';
        } else {
            $insert_arr['to_member_id'] = implode(',', $memberid_list);
        }
        $insert_arr['is_more'] = 1;
        $insert_arr['message_time'] = time();
        $model_message->save($insert_arr);
        $this->success('发送成功', url('notice/index'));
    }
}
