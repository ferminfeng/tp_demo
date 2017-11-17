<?php
use think\Env;
return [
    //微信支付JsApi配置
    'js_app_id' => Env::get('WxPayJsApi.app_id'),
    'js_mch_id' => Env::get('WxPayJsApi.mch_id'),
    'js_key' => Env::get('WxPayJsApi.key'),
    'js_app_secret' => Env::get('WxPayJsApi.app_secret'),

    //微信支付App配置
    'app_app_id' => Env::get('WxPayApp.app_id'),
    'app_mch_id' => Env::get('WxPayApp.mch_id'),
    'app_key' => Env::get('WxPayApp.key'),
    'app_app_secret' => Env::get('WxPayApp.app_secret'),
];
