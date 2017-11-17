<?php
use think\Env;
return [
    //微博分享SDK
    'wb_app_id' => Env::get('WbShare.app_id'),
    'wb_app_secret' => Env::get('WbShare.app_secret'),

    //微信分享SDK
    'wx_app_id' => Env::get('WxShare.app_id'),
    'wx_app_secret' => Env::get('WxShare.app_secret'),
];
