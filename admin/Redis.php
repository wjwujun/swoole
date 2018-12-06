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
    private static $redis = null;

    /**
     * Redis constructor.
     */
    private function __construct(){
        self::instance();
    }

    /**
     * clone
     */
    private function __clone(){}

    /**
     * 获取redis对象
     * @return null|\Redis
     */
    public static function instance()
    {

        if(!self::$redis || !(self::$redis instanceof \Redis)){
            self::$redis = new \Redis();
            self::$redis->connect('122.225.58.118',6379);
            //密码
            self::$redis->auth('U#rNFRkk3vuCKcZ5');

        }
        return self::$redis;
    }

}