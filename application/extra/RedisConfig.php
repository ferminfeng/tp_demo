<?php
use think\Env;
return [
    //redis配置
    'use_redis' => Env::get('redis.use_redis'),//是否使用redis 1:使用 0：不使用
    'redis_host' => Env::get('redis.redis_host'),
    'redis_port' => Env::get('redis.redis_port'),
    'timeout' => '0.1',
    'instance_id' => Env::get('redis.instance_id'), // 实例id
    'pwd' => Env::get('redis.pwd'), // 密码
];
