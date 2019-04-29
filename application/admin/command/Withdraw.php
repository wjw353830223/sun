<?php
namespace app\admin\command;

use app\common\model\PdCash;
use think\console\Command;
use think\console\Input;  
use think\console\Output;
use Unipay\LogUtil;

class Withdraw extends Command
{
    protected $limit = 100;//每次循环限制100条 防止内存溢出
	protected function configure(){  
		$this->setName('withdraw')->setDescription('Process undo withdraw');
	}  
  
	protected function execute(Input $input, Output $output){
		$this->_process_undo_withdraw();
	}

    /**
     * 银联提现防止遗漏处理中的提现
     * 处理因为非通信类问题没有及时收到应答（比如应用切换等情况），
     * 或者收到应答时处理失败，
     * 商户应该对没有及时收到应答的交易通过查询接口来查询该交易的状态
     */
	private function _process_undo_withdraw(){
       while(true){
            $cash_model = model('PdCash','common\model');
            $records = $cash_model->where(['pdc_payment_state'=>PdCash::PDC_PAYMENT_STATE_PROCESSING])
                ->order(['pdc_add_time'=>'DESC'])
                ->limit($this->limit)
                ->select()
                ->toArray();
            if(empty($records)){
                break;
            }
            $member_model = model('Member', 'common\model');
            $pdlog_model = model('PdLog','common\model');
            $message_model = model('Message','common\model');
            foreach($records as $val){
                $pd_cash_info = $cash_model->pdcash_get_by_pdcsn($val['pdc_sn']);
                $member_mobile = $member_model->where(['member_id'=>$pd_cash_info['pdc_member_id']])->value('member_mobile');
                $resp_data = $this->internal_query($val['pdc_sn'],date('YmdHis', $val['pdc_payment_time']));
                $pdc_payment_admin = isset($resp_data['reqReserved']) ? intval($resp_data['reqReserved']) : 0;
                if($resp_data === false){
                    continue;
                }
                if($resp_data['respCode']!=='00' || (isset($resp_data['origRespCode']) && $resp_data['origRespCode'] !== '00')){
                    //查询为失败的处理
                    $data = [
                        'pdc_payment_state'  => PdCash::PDC_PAYMENT_STATE_FAIL,
                        'pdc_payment_admin' => $pdc_payment_admin
                    ];
                    $cash_model->startTrans();
                    $member_model->startTrans();
                    $pdlog_model->startTrans();
                    $message_model->startTrans();
                    if(!$cash_model->pdcash_update($pd_cash_info['pdc_id'],$data)){
                        continue;
                    }
                    $amount = json_decode($pd_cash_info['pdc_data'],true)['amount'];
                    if(!$member_model->member_av_predeposit_update($pd_cash_info['pdc_member_id'],$amount)){
                        $cash_model->rollback();
                        continue;
                    }
                    $msg = isset($resp_data['origRespMsg'])?$resp_data['origRespMsg']:(isset($resp_data['respMsg'])?$resp_data['respMsg']:'');
                    $pd_log = [
                        'member_id'     => $pd_cash_info['pdc_member_id'],
                        'member_mobile' => $member_mobile,
                        'av_amount'     => $amount,
                        'type'          => 'cash_fail',
                        'add_time'      => time(),
                        'log_desc'      => '余额转出失败，失败原因：'. $msg,
                    ];
                    if(!$pdlog_model->pdlog_add($pd_log)){
                        $member_model->rollback();
                        $cash_model->rollback();
                        continue;
                    }
                    //发送消息
                    $push_detail = '您的余额转出失败，请重新申请操作';
                    $title = '提现通知';
                    $message = [
                        'from_member_id' => 0,
                        'to_member_id'   => $pd_cash_info['pdc_member_id'],
                        'message_title'  => $title,
                        'message_body'   => json_encode(['title'=> '提现失败','points'=>'+'.$amount]),
                        'message_time'   => time(),
                        'message_state'  => 0,
                        'message_type'   => 4,
                        'is_more'        => 0,
                        'is_push'        => 2,
                        'push_title'     => '',
                        'push_detail'    => $push_detail,
                        'push_member_id' => $pd_cash_info['pdc_member_id']
                    ];
                    if(!$message_model->send_message($member_mobile,$push_detail,$title,$message)){
                        $message_model->rollback();
                        $pdlog_model->rollback();
                        $member_model->rollback();
                        $cash_model->rollback();
                        continue;
                    }
                    $cash_model->commit();
                    $member_model->commit();
                    $pdlog_model->commit();
                    $message_model->commit();
                    continue;
                }
                //查询为成功的处理
                $data = [
                    'pdc_payment_state'  => PdCash::PDC_PAYMENT_STATE_SUCCESS,
                    'pdc_payment_admin' => $pdc_payment_admin
                ];
                $amount = json_decode($pd_cash_info['pdc_data'],'true')['amount'];
                $cash_model->startTrans();
                $pdlog_model->startTrans();
                $message_model->startTrans();
                if(!$cash_model->pdcash_update($pd_cash_info['pdc_id'],$data)){
                    continue;
                }
                //提现记录
                $pd_log = [
                    'member_id'     => $pd_cash_info['pdc_member_id'],
                    'member_mobile' => $member_mobile,
                    'av_amount'     => $amount,
                    'type'          => 'cash_withdrawn',
                    'add_time'      => time(),
                    'log_desc'      => '余额转出',
                    'pd_data'       => $pd_cash_info['pdc_data']
                ];
                if(!$pdlog_model->pdlog_add($pd_log)){
                    $cash_model->rollback();
                    continue;
                }
                //发送消息
                $push_detail = '您账户的'.$amount.'元已提现成功';
                $title = '提现通知';
                $message = [
                    'from_member_id' => 0,
                    'to_member_id'   => $pd_cash_info['pdc_member_id'],
                    'message_title'  => $title,
                    'message_body'   => json_encode(['title'=> '提现成功','points'=>'-'.$amount]),
                    'message_time'   => time(),
                    'message_state'  => 0,
                    'message_type'   => 4,
                    'is_more'        => 0,
                    'is_push'        => 2,
                    'push_title'     => '',
                    'push_detail'    => $push_detail,
                    'push_member_id' => $pd_cash_info['pdc_member_id']
                ];
                if(!$message_model->send_message($member_mobile,$push_detail,$title,$message)){
                    $cash_model->rollback();
                    $pdlog_model->rollback();
                    $message_model->rollback();
                    continue;
                }
                $cash_model->commit();
                $pdlog_model->commit();
                $message_model->commit();
            }
        }
    }

    /**
     * 查询交易，可查询N次（不超过6次），每次时间间隔2的N次秒发起,即间隔1、2、4、8、16、32秒查询
     * @param $txn_time 格式：201804270930
     * @param $pdc_sn
     * @return mixed
     */
    private function internal_query($pdc_sn,$txn_time){
        $resp_data = model('PdCash','common\model')->withdraw_query($pdc_sn, $txn_time);
        sleep(1);
        for($i=0;$i<6;$i++){
            if($resp_data === false || !isset($resp_data['respCode'])){
                return false;
            }
            if($resp_data['respCode']==='00' && $resp_data['origRespCode'] === '00'){
                return $resp_data;
            }
            sleep(pow(2,$i));
            $resp_data = model('PdCash','common\model')->withdraw_query($pdc_sn, $txn_time);
        }
        return $resp_data;
    }
}  



