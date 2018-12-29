<?php

namespace redis;

/**
 * Class Redis
 * @package redis
 */
class Redis
{
    /**
     * redis对象
     * @var null
     */
    private  $redis = null;


    public function  getRedis (){
        if($this->redis==null){
            $this->redis = new \Redis();
            $this->redis->connect('122.225.58.118',6379);
            //密码
            $this->redis->auth('U#rNFRkk3vuCKcZ5');
            return $this->redis;
        }

        return $this->redis;
    }

}