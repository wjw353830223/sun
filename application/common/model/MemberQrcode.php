<?php
namespace app\common\model;

use think\Model;
use Lib\Qrcode;
class MemberQrcode extends Model
{
	// 关闭自动时间格式化
    protected $createTime = false;

    /**
    *	获取/生成二维码
    *   @is_new  是否存在 0.否
	*/
    public function create_qrcode($member_data,$is_new,$qrcode=NULL){
    	if(!empty($qrcode)){
    		@unlink('qrcode/'.$qrcode);
    	}  

    	$invite_code = isset($member_data['invite_code']) && !empty($member_data['invite_code']) ? $member_data['invite_code'] : random_string(8);
        $qrcode = new Qrcode();
        $download_url = "http://static.healthywo.com?code=";
        $qrcode->content = $download_url.$invite_code;
        $qrcode->logo_file = false;
        $qrcode->is_save = true;
        $qrcode_image = $qrcode->build();

        $info_data = [
        	'qrcode' => $qrcode_image,
            'invite_code' => $invite_code,
        	'add_time' => time()
        ];
        
        if($is_new > 0){
            $info_data['member_mobile'] = $member_data['member_mobile'];
            $info_data['member_id'] = $member_data['member_id'];
            $result = $this->save($info_data);
        }else{
            $result = $this->save($info_data,['member_id'=>$member_data['member_id']]);
        }
        
        if($result === false){
        	return false;
        }
        return $qrcode_image;
	}
}