<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/25
 * Time: 10:34
 * 银联配置
 */
return [
   /* 'mer_id' =>  '777290058158474',
   'cert' => [
       'sign_cert_path' => '/home/wwwroot/sun/public/certs/acp_test_sign.pfx',//签名证书路径 实名认证需要绝对路径
       'sign_cert_pwd' => '000000',//签名证书密码
       'encrypt_cert_path' => '/home/wwwroot/sun/public/certs/acp_test_enc.cer',//敏感信息加密证书路径 实名认证需要绝对路径
       'middle_cert_path' => '/home/wwwroot/sun/public/certs/acp_test_middle.cer',//验签中级证书路径 实名认证需要绝对路径
       'root_cert_path' => '/home/wwwroot/sun/public/certs/acp_test_root.cer',//验签根证书路径 实名认证需要绝对路径
       'validate_cert_dir' => '/home/wwwroot/sun/public/certs/',//验签证书路径
   ],
   'log' => [
       'log_file_path' => '../data/logs/unipay',
       'log_level' => 'info',
   ],
   'acp_sdk_ini_name' =>'acp_test_sdk.ini',*/
    //'acp_sdk_ini_name' =>  APP_ENV == 'prod'? 'acp_prod_sdk.ini':'acp_test_sdk.ini',
    'mer_id' => '898111948160974', //商户号
    'cert' => [
        'sign_cert_path' => '/home/wwwroot/sun/public/certs/prod/acp_prod_sign.pfx',//从cfca获取到的私钥证书 读权限
        'sign_cert_pwd' => '20180515',//签名证书密码
        'encrypt_cert_path' => '/home/wwwroot/sun/public/certs/prod/acp_prod_enc.cer',//敏感信息加密证书路径
        'middle_cert_path' => '/home/wwwroot/sun/public/certs/prod/acp_prod_middle.cer',//验签中级证书
        'root_cert_path' => '/home/wwwroot/sun/public/certs/prod/acp_prod_root.cer',//验签根证书
        'validate_cert_dir' => '/home/wwwroot/sun/public/certs/prod/',//验签证书路径
    ],
    'log' =>[
        'log_file_path' => '../data/logs/unipay',//日志打印路径 linux注意要有写权限
        'log_level' => 'info',//日志级别 debug级别会打印密钥，生产请用info或以上级别
    ],
    'acp_sdk_ini_name' =>'acp_prod_sdk.ini',
];