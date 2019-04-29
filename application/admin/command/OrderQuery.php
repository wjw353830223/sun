<?php
namespace app\admin\command;

use app\common\model\OrderPay;
use think\console\Command;
use think\console\Input;  
use think\console\Output;
use think\Request;
use think\Log;
class OrderQuery extends Command
{
    protected $limit = 100;//每次循环限制100条 防止内存溢出
	protected function configure(){  
		$this->setName('order:query')->setDescription('Process undo order pay');
	}  
  
	protected function execute(Input $input, Output $output)
    {
        $request = Request::instance([]);
        $request->module("common");//绑定当前模块为common
        $this->_process_undo_pay([$this,'wechat_internal_query'],OrderPay::PAY_TYPE_WECHAT);
        $this->_process_undo_pay([$this,'unipay_internal_query'],OrderPay::PAY_TYPE_UNIONPAY);
        $this->_process_undo_pay([$this,'alipay_internal_query'],OrderPay::PAY_TYPE_ALIPAY);
    }

    /**
     *要调用查询接口的情况
     *当商户后台、网络、服务器等出现异常，商户系统最终未接收到支付通知；
     *调用支付接口后，返回系统错误或未知交易状态情况；
     *调用刷卡支付API，返回USERPAYING的状态；
     *调用关单或撤销接口API之前，需确认支付状态；
     * @param $callback 订单查询函数
     * @param int $pay_type 支付方式
     */
	private function _process_undo_pay($callback,$pay_type = OrderPay::PAY_TYPE_UNIONPAY){
       while(true){
            $order_pay_model = model('OrderPay');
            $records = $order_pay_model->where(['pay_type'=>$pay_type,'api_pay_state'=>OrderPay::API_PAY_STATE_DEFAULT])
                ->order(['pay_id'=>'DESC'])
                ->limit($this->limit)
                ->select()
                ->toArray();
           if(empty($records)){
                break;
           }
           foreach($records as $val){
               if(!$order_pay_model->order_query_process($val['pay_sn'],$val['pay_time'],$val['buyer_id'],$pay_type,$callback)){
                   continue;
               }
           }
        }
    }
    /**
     * 查询交易，可查询N次（不超过6次），每次时间间隔2的N次秒发起,即间隔1、2、4、8、16、32秒查询 查询算法待定
     * @param $out_trade_no  商户订单号
     * @return mixed
     */
    public function wechat_internal_query($out_trade_no){
        $resp_data = model('OrderPay')->wechat_pay_query($out_trade_no);
        sleep(1);
        for($i=0;$i<6;$i++){
            if($resp_data === false || !isset($resp_data['return_code'])){
                return false;
            }
            if($resp_data['return_code']==='SUCCESS' && $resp_data['trade_state'] === 'SUCCESS'){
                return $resp_data;
            }
            sleep(pow(2,$i));
            $resp_data = model('OrderPay')->wechat_pay_query($out_trade_no);
        }
        return $resp_data;
    }
    /**
     * 查询交易，可查询N次（不超过6次），每次时间间隔2的N次秒发起,即间隔1、2、4、8、16、32秒查询
     * @param $txn_time 格式：201804270930
     * @param $pdc_sn
     * @return mixed
     */
    public function unipay_internal_query($pdc_sn,$txn_time){
        $resp_data = model('OrderPay')->pay_query($pdc_sn, $txn_time);
        sleep(1);
        for($i=0;$i<6;$i++){
            if($resp_data === false || !isset($resp_data['respCode'])){
                return false;
            }
            if($resp_data['respCode']==='00' && $resp_data['origRespCode'] === '00'){
                return $resp_data;
            }
            sleep(pow(2,$i));
            $resp_data = model('OrderPay')->pay_query($pdc_sn, $txn_time);
        }
        return $resp_data;
    }
    /**
     * 查询交易，可查询N次（不超过6次），每次时间间隔2的N次秒发起,即间隔1、2、4、8、16、32秒查询 查询算法待定
     * @param $out_trade_no
     * @param $trade_no
     * @return mixed
     */
    public function alipay_internal_query($out_trade_no,$trade_no){
        $resp_data = model('OrderPay')->ali_pay_query($out_trade_no,$trade_no);
        sleep(1);
        $resp_data = json_decode(json_encode($resp_data),true);
        if(!isset($resp_data['alipay_trade_query_response']) || empty($resp_data['alipay_trade_query_response'])){
            return false;
        }
        $resp_data = $resp_data['alipay_trade_query_response'];
        for($i=0;$i<6;$i++){
            if($resp_data === false || !isset($resp_data['code'])){
                return false;
            }
            if($resp_data['code'] =='10000' && $resp_data['trade_status'] == 'TRADE_SUCCESS'){
                return $resp_data;
            }
            sleep(pow(2,$i));
            $resp_data = model('OrderPay')->ali_pay_query($out_trade_no,$trade_no);
            $resp_data = json_decode(json_encode($resp_data),true);
            if(!isset($resp_data['alipay_trade_query_response']) || empty($resp_data['alipay_trade_query_response'])){
                return false;
            }
            $resp_data = $resp_data['alipay_trade_query_response'];
        }
        return $resp_data;
    }
}  



