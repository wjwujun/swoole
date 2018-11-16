<?php

/*获取redis实列*/
function getRedis(){
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->auth('U#rNFRkk3vuCKcZ5');
    return  $redis;
}
