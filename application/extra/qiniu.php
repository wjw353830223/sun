<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/25
 * Time: 10:31
 * 七牛云存储配置（自动读取）
 */
return [
    'access_key' => 'kVNnKJYWgyeIQz9u4u_QqpghJwPW1G681R655HYL',
    'secret_key' => '8kQOa-wEYLl8Q7sb066qFgxNKcOW4GiZoB45T-pN',
    'buckets' => APP_ENV == 'prod'?  [
        'images' => ['name'=>'images','domain'=>'http://img.healthywo.com'],
        'report' => ['name'=>'monitoring-report','domain'=>'http://report.healthywo.com'],
        'ota' => ['name'=>'otafirmware','domain'=>'http://ota.healthywo.com'],
        'static' => ['name'=>'static','domain'=>'http://static.healthywo.com']
    ] :  [
        'images' => ['name'=>'images-test','domain'=>'http://p7bo7xp06.bkt.clouddn.com'],
        'report' => ['name'=>'monitoring-report','domain'=>'http://report.healthywo.com'],
        'ota' => ['name'=>'otafirmware','domain'=>'http://ota.healthywo.com'],
        'static' => ['name'=>'static-test','domain'=>'http://p7bo6lifz.bkt.clouddn.com']
    ]
];