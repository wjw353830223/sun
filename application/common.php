<?php
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Cdn\CdnManager;
use Qiniu\Config;
use Qiniu\Storage\BucketManager;
/**
* 应用公共函数文件
*
*/

/**
 * 全局获取验证码图片
 * 生成的是个HTML的img标签
 * @param string $imgparam <br>
 * 生成图片样式，可以设置<br>
 * length=4&font_size=20&width=238&height=50&use_curve=1&use_noise=1<br>
 * length:字符长度<br>
 * font_size:字体大小<br>
 * width:生成图片宽度<br>
 * heigh:生成图片高度<br>
 * use_curve:是否画混淆曲线  1:画，0:不画<br>
 * use_noise:是否添加杂点 1:添加，0:不添加<br>
 * @param string $imgattrs<br>
 * img标签原生属性，除src,onclick之外都可以设置<br>
 * 默认值：style="cursor: pointer;" title="点击获取"<br>
 * @return string<br>
 * 原生html的img标签<br>
 * 注，此函数仅生成img标签，应该配合在表单加入name=verify的input标签<br>
 * 如：&lt;input type="text" name="verify"/&gt;<br>
 */
function sp_verifycode_img($imgparam='length=4&font_size=20&width=238&height=50&use_curve=1&use_noise=1',$imgattrs='style="cursor: pointer;" title="点击获取"'){
	$src = url('open/checkcode/index',$imgparam);
	$img=<<<hello
<img class="verify_img" src="$src" onclick="this.src='$src&time='+Math.random();" $imgattrs/>
hello;
	return $img;
}

/**
* 统一密码生成
* @param string $password 客户输入密码
* @param string $salt 账户绑定散列码
* @return string 
*/
function sp_password($password,$salt){
	if (empty($salt) || strlen($salt) < 6 ) {
		return false;
	}
	return md5(md5($password).$salt);
}

/**
 * 获取当前登录的管事员id
 * @return int
 */
function sp_get_current_admin_id(){
    return session('ADMIN_ID');
}

/**
 * 随机字符串生成
 * @param int $len 生成的字符串长度
 * @param int $rule 字符串规则 1.纯数字 2.纯小写字母 3.纯大写字母 4.大小写字母混合 5.大小写字母混合加数字(default)
 * @return string
 */
function random_string($len = 6,$rule = 0) {
    $chars = array();

    $lower = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z"
    );

    $capital = array(
            "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z"
    );

    $nums = array("0", "1", "2","3", "4", "5", "6", "7", "8", "9");

    switch ($rule) {
        case '1':
            $chars = $nums;
            break;
        case '2':
            $chars = $lower;
            break;
        case '3':
            $chars = $capital;
            break;
        case '4':
            $chars = array_merge($lower,$capital);
            break;
        case '5':
            $chars = array_merge($lower,$capital,$nums);
            break;
        default:
            $chars = array_merge($lower,$capital,$nums);
            break;
    }

    $charsLen = count($chars) - 1;
    shuffle($chars);    // 将数组打乱
    $output = "";
    for ($i = 0; $i < $len; $i++) {
        $output .= $chars[random_int(0, $charsLen)];
    }
    return $output;
}

/**
 * 验证输入的手机号码
 *
 * @access  public
 * @param   string      $user_mobile      需要验证的手机号码
 *
 * @return bool
 */
function is_mobile($user_mobile){
    $chars = "/^((\(\d{2,3}\))|(\d{3}\-))?1(3|5|7|8|9)\d{9}$/";

    if (preg_match($chars, $user_mobile)){
        return true;
    }else{
        return false;
    }
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装） 
 * @return mixed
 */
function get_client_ip($type = 0,$adv=false) {
    $type       =  $type ? 1 : 0;
    static $ip  =   NULL;
    if ($ip !== NULL) return $ip[$type];
    if($adv){
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos    =   array_search('unknown',$arr);
            if(false !== $pos) unset($arr[$pos]);
            $ip     =   trim($arr[0]);
        }elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip     =   $_SERVER['HTTP_CLIENT_IP'];
        }elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip     =   $_SERVER['REMOTE_ADDR'];
        }
    }elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip     =   $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u",ip2long($ip));
    $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}

/**
* 字符串含有检测
* @param $string string 源字符串
* @param $find string 检测字符串
*/
function strexists($string, $find) {
	return !(strpos($string, $find) === FALSE);
}

/**
 * Ajax方式返回数据到客户端
 * @access protected
 * @param mixed $data 要返回的数据
 * @param String $type AJAX返回数据格式
 * @param int $json_option 传递给json_encode的option参数
 * @return void
 */
function ajax_return($data,$type='',$json_option=0) {
    if(empty($type)) $type  =   'json';
    switch (strtoupper($type)){
        case 'JSON' :
            // 返回JSON数据格式到客户端 包含状态信息
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode($data,$json_option));
        case 'XML'  :
            // 返回xml格式数据
            header('Content-Type:text/xml; charset=utf-8');
            exit(xml_encode($data));
        case 'EVAL' :
            // 返回可执行的js脚本
            header('Content-Type:text/html; charset=utf-8');
            exit($data);            
        default:
    			//no defailt
    		break;

    }
}

/**
 * XML编码
 * @param mixed $data 数据
 * @param string $root 根节点名
 * @param string $item 数字索引的子节点名
 * @param string $attr 根节点属性
 * @param string $id   数字索引子节点key转换的属性名
 * @param string $encoding 数据编码
 * @return string
 */
function xml_encode($data, $root='think', $item='item', $attr='', $id='id', $encoding='utf-8') {
    if(is_array($attr)){
        $_attr = array();
        foreach ($attr as $key => $value) {
            $_attr[] = "{$key}=\"{$value}\"";
        }
        $attr = implode(' ', $_attr);
    }
    $attr   = trim($attr);
    $attr   = empty($attr) ? '' : " {$attr}";
    $xml    = "<?xml version=\"1.0\" encoding=\"{$encoding}\"?>";
    $xml   .= "<{$root}{$attr}>";
    $xml   .= data_to_xml($data, $item, $id);
    $xml   .= "</{$root}>";
    return $xml;
}

/**
 * 数据XML编码
 * @param mixed  $data 数据
 * @param string $item 数字索引时的节点名称
 * @param string $id   数字索引key转换为的属性名
 * @return string
 */
function data_to_xml($data, $item='item', $id='id') {
    $xml = $attr = '';
    foreach ($data as $key => $val) {
        if(is_numeric($key)){
            $id && $attr = " {$id}=\"{$key}\"";
            $key  = $item;
        }
        $xml    .=  "<{$key}{$attr}>";
        $xml    .=  (is_array($val) || is_object($val)) ? data_to_xml($val, $item, $id) : $val;
        $xml    .=  "</{$key}>";
    }
    return $xml;
}

/**
 * 字符截取 支持UTF8
 * @param $string
 * @param $length
 * @param $dot
 */
function str_cut($string, $length, $dot = '...') {
    $strlen = strlen($string);
    if($strlen <= $length) return $string;
    $string = str_replace(array(' ','&nbsp;', '&amp;', '&quot;', '&#039;', '&ldquo;', '&rdquo;', '&mdash;', '&lt;', '&gt;', '&middot;', '&hellip;'), array('∵',' ', '&', '"', "'", '“', '”', '—', '<', '>', '·', '…'), $string);
    $strcut = '';

    $length = intval($length-strlen($dot)-$length/3);
    $n = $tn = $noc = 0;
    while($n < strlen($string)) {
        $t = ord($string[$n]);
        if($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
            $tn = 1; $n++; $noc++;
        } elseif(194 <= $t && $t <= 223) {
            $tn = 2; $n += 2; $noc += 2;
        } elseif(224 <= $t && $t <= 239) {
            $tn = 3; $n += 3; $noc += 2;
        } elseif(240 <= $t && $t <= 247) {
            $tn = 4; $n += 4; $noc += 2;
        } elseif(248 <= $t && $t <= 251) {
            $tn = 5; $n += 5; $noc += 2;
        } elseif($t == 252 || $t == 253) {
            $tn = 6; $n += 6; $noc += 2;
        } else {
            $n++;
        }
        if($noc >= $length) {
            break;
        }
    }
    if($noc > $length) {
        $n -= $tn;
    }
    $strcut = substr($string, 0, $n);
    $strcut = str_replace(array('∵', '&', '"', "'", '“', '”', '—', '<', '>', '·', '…'), array(' ', '&amp;', '&quot;', '&#039;', '&ldquo;', '&rdquo;', '&mdash;', '&lt;', '&gt;', '&middot;', '&hellip;'), $strcut);
    return $strcut.$dot;

}


 /**
 * 检测输入中是否含有错误字符
 *
 * @param char $string 要检查的字符串名称
 * @return TRUE or FALSE
 */
function is_badword($string) {
    $badwords = array("\\",'&',' ',"'",'"','/','*',',','<','>',"\r","\t","\n","#");
    foreach($badwords as $value){
        if(strpos($string, $value) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * 检查用户名是否符合规定
 *
 * @param STRING $username 要检查的用户名
 * @return  TRUE or FALSE
 */
function is_username($username) {
    $strlen = strlen($username);
    if(is_badword($username)){
        return false;
    } elseif ( 20 < $strlen || $strlen < 2 ) {
        return false;
    }
    return true;

}

/**
 * 获取CMF上传配置
 */
function sp_get_upload_setting(){
    // $upload_setting=sp_get_option('upload_setting');
    if(empty($upload_setting)){
        $upload_setting = array(
            'image' => array(
                'upload_max_filesize' => '10240',//单位KB
                'extensions' => 'jpg,jpeg,png,gif,bmp4'
            ),
            'video' => array(
                'upload_max_filesize' => '10240',
                'extensions' => 'mp4,avi,wmv,rm,rmvb,mkv'
            ),
            'audio' => array(
                'upload_max_filesize' => '10240',
                'extensions' => 'mp3,wma,wav'
            ),
            'file' => array(
                'upload_max_filesize' => '10240',
                'extensions' => 'txt,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar'
            )
        );
    }
    
    if(empty($upload_setting['upload_max_filesize'])){
        $upload_max_filesize_setting=array();
        foreach ($upload_setting as $setting){
            $extensions=explode(',', trim($setting['extensions']));
            if(!empty($extensions)){
                $upload_max_filesize=intval($setting['upload_max_filesize'])*1024;//转化成KB
                foreach ($extensions as $ext){
                    if(!isset($upload_max_filesize_setting[$ext]) || $upload_max_filesize>$upload_max_filesize_setting[$ext]*1024){
                        $upload_max_filesize_setting[$ext]=$upload_max_filesize;
                    }
                }
            }
        }
        
        $upload_setting['upload_max_filesize']=$upload_max_filesize_setting;
        cache("cmf_system_options_upload_setting",$upload_setting);
    }else{
        $upload_setting=cache("cmf_system_options_upload_setting");
    }
    
    return $upload_setting;
}

/**
 * 获取文件扩展名
 * @param string $filename
 */
function sp_get_file_extension($filename){
    $pathinfo=pathinfo($filename);
    return strtolower($pathinfo['extension']);
}

/**
 * 判断字符长度
 * @param string $filename
 */
function words_length($str){
    $content_len = strlen($str);
    $i = $count = 0;
    while($i < $content_len){
        $chr = ord($str[$i]);
        $count++;$i++;
        if($i >= $content_len){
            break;
        }
        if ($chr & 0x80){
            $chr <<= 1;
            while ($chr & 0x80){
                $i++;
                $chr <<= 1;
            }
        }
    }
        
    return $count;
}

/**
 * 密码比较方法,所有涉及密码比较的地方都用这个方法
 * @param string $password 要比较的密码
 * @param string $password_in_db 数据库保存的已经加密过的密码
 * @return boolean 密码相同，返回true
 */
function sp_compare_password($password,$password_in_db,$encrypt){
    if(strpos($password_in_db, "###")===0){
        return sp_password($password,$encrypt)==$password_in_db;
    }else{
        return sp_password($password,$encrypt)==$password_in_db;
    }
}


/**
 * 身份证格式验证方法
 * @param string $id_card 要比较的密码
 * @return boolean 格式正确，返回true
 */
function validation_filter_id_card($id_card) { 
    if(strlen($id_card) == 18) { 
        return idcard_checksum18($id_card); 
    } elseif((strlen($id_card) == 15)) { 
        $id_card = idcard_15to18($id_card); 
        return idcard_checksum18($id_card); 
    } else { 
        return false; 
    } 
} 

// 将15位身份证升级到18位 
function idcard_15to18($idcard){ 
    if (strlen($idcard) != 15){ 
        return false; 
    }else{ 
        // 如果身份证顺序码是996 997 998 999，这些是为百岁以上老人的特殊编码 
        if (array_search(substr($idcard, 12, 3), array('996', '997', '998', '999')) !== false){ 
            $idcard = substr($idcard, 0, 6) . '18'. substr($idcard, 6, 9); 
        }else{ 
            $idcard = substr($idcard, 0, 6) . '19'. substr($idcard, 6, 9); 
        } 
    } 

    $idcard = $idcard . idcard_verify_number($idcard); 
    return $idcard; 
} 

// 计算身份证校验码，根据国家标准GB 11643-1999 
function idcard_verify_number($idcard_base) { 
    if(strlen($idcard_base) != 17) { 
        return false; 
    } 
    //加权因子 
    $factor = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2); 
    //校验码对应值 
    $verify_number_list = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'); 
    $checksum = 0; 
    for ($i = 0; $i < strlen($idcard_base); $i++) { 
        $checksum += substr($idcard_base, $i, 1) * $factor[$i]; 
    } 
    $mod = $checksum % 11; 
    $verify_number = $verify_number_list[$mod]; 
    return $verify_number; 
} 

// 18位身份证校验码有效性检查 
function idcard_checksum18($idcard){ 
    if (strlen($idcard) != 18){ return false; } 
        $idcard_base = substr($idcard, 0, 17); 
    if (idcard_verify_number($idcard_base) != strtoupper(substr($idcard, 17, 1))){ 
        return false; 
    }else{ 
        return true; 
    } 
}


function array2xml($arr, $level = 1) {
    $s = $level == 1 ? "<xml>" : '';
    foreach ($arr as $tagname => $value) {
        if (is_numeric($tagname)) {
            $tagname = $value['TagName'];
            unset($value['TagName']);
        }
        if (!is_array($value)) {
            $s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
        } else {
            $s .= "<{$tagname}>" . array2xml($value, $level + 1) . "</{$tagname}>";
        }
    }
    $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
    return $level == 1 ? $s . "</xml>" : $s;
}

function xml2array($xml) {
    if (empty($xml)) {
        return array();
    }
    $result = array();
    $xmlobj = isimplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
    if($xmlobj instanceof SimpleXMLElement) {
        $result = json_decode(json_encode($xmlobj), true);
        if (is_array($result)) {
            return $result;
        } else {
            return '';
        }
    } else {
        return $result;
    }
}

function isimplexml_load_string($string, $class_name = 'SimpleXMLElement', $options = 0, $ns = '', $is_prefix = false) {
    libxml_disable_entity_loader(true);//禁用外部实体引用
    if (preg_match('/(\<\!DOCTYPE|\<\!ENTITY)/i', $string)) {
        return false;
    }
    return simplexml_load_string($string, $class_name, $options, $ns, $is_prefix);
}

/**
* 获取当前域名
* @return string
*/
function host_url(){
    $base_url = 'http';
    if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {
        $base_url .= "s";
    }
    $base_url .= "://";

    if (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != "80") {
        $base_url .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"];
    }else{
        $base_url .= $_SERVER["SERVER_NAME"];
    }
    return $base_url;
}


/**
* 验证机器人SN码
* @param $robot_sn string 机器人码 /^(SCR{1})H(D|G)([1-9]?)([0-9]?)([0-9]{1,2})0([1-4]{1})([0-9]{5})$/
* @return true|false
*/
function is_robotsn($robot_sn = ''){
    if (preg_match("/^SCR|HSR?[A-Za-z\d]{14}$/",$robot_sn)) {
        return true;
    }
    return false;
}

/**
* 验证字符串是否为md5编码
* @param $string string 需要检验的字符串
* @return 0|1
*/
function is_md5($string = '') {
    return preg_match("/^[a-z0-9]{32}$/", $string);
}

/*
 *生成静态页面
 *$file 文件 $url 文件存储路径
*/
function create_page($file=null,$url){
    if(is_null($file)){
        return false;
    }
    
    $result = file_put_contents($url,$file);
    if($result === false){
        return false;
    }
    return true;
}

/*
 *去除扩展名
 *$file 文件
*/
function remove_extension($filename = null){
    return substr(strrchr($filename, '.'), 1);
}

/*
 *生成缩略图
 * $filename文件名称
 * $size文件大小
*/
function thumb($filename , $size){
    return str_replace(strrchr($filename,"."),"",$filename).'_'.$size.'x'.$size;
}

/**
* 验证字符串是否为生日
* @param $string string 需要检验的字符串
* @return true|false
*/
function is_birthday($str = ''){
    if (preg_match('/^\d{4}(\-|\/|.)\d{1,2}\1\d{1,2}$/', $str)) {
        return true;
    }
    return false;
}

/**
 * 通过求百分比获取健康指数
 *
 * @access  public
 * @param   int  $sum   总数
 * @param   int  $row   单个数
 * @return  int
 */
function sleep_grade($sum = 0, $row = 0){

    $cpl = round($row / $sum * 100);
    $grade = 0;

    switch ($cpl) {
        case $cpl > 60 :
            $grade = 1;
            break;
        case $cpl > 40 && $cpl <= 60 :
            $grade = 2;
            break;
        case $cpl > 20 && $cpl <= 40 :
            $grade = 3;
            break;
        case $cpl > 10 && $cpl <= 20 :
            $grade = 4;
            break;
        case $cpl <= 10 :
            $grade = 5;
            break;
        default:
            # code...
            break;
    }

    return $grade;
}
if(!function_exists('add_file_to_qiniu')){
    /**
     * 文件直传七牛云
     * @param $key
     * @param $file_path
     * @param $bucket
     * @return bool
     * @throws \Exception
     */
     function add_file_to_qiniu($key,$file_path,$bucket){
        $accessKey = config('qiniu.access_key');
        $secretKey = config('qiniu.secret_key');
        $auth = new Auth($accessKey, $secretKey);
        $token = $auth->uploadToken($bucket);
        $uploadMgr = new UploadManager();
        // 调用 UploadManager 的 putFile 方法进行文件的上传。
        list($ret, $err) = $uploadMgr->putFile($token, $key, $file_path);
        if ($err != null) {
            return false;
        }
        return true;
    }
}
if(!function_exists('update_qiniu_file')){
    /**
     * 七牛镜像空间更新文件
     * @param $key
     * @param $bucket
     * @return bool
     */
    function update_qiniu_file($key, $bucket){
        $accessKey = config('qiniu.access_key');
        $secretKey = config('qiniu.secret_key');
        $auth = new Auth($accessKey, $secretKey);
        $config = new Config();
        $bucketManager = new BucketManager($auth, $config);
        //$err = $bucketManager->prefetch($bucket, $key);
        $err = $bucketManager->delete($bucket,$key);
        if ($err) {
            return false;
        }
        //刷新文件cdn缓存
        $urls = array(
            config('qiniu.buckets')['images']['domain'] . '/' . $key,
        );
        $cdnManager = new CdnManager($auth);
        list($refreshResult, $refreshErr) = $cdnManager->refreshUrls($urls);
        if ($refreshErr != null) {
            return false;
        }
        return true;
    }
}