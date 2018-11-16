<?php



    $redisClient=new swoole_redis;
    $redisClient->connect('127.0.0.1',6379,function(swoole_redis $redisClient,$result)
    {
        echo "connect success!".PHP_EOL;
        var_dump($result);
        //设置
        $redisClient->set("aa",time(),function(swoole_redis $redisClient,$result){
            var_dump($result);

        });

        //获取
/*        $redisClient->get("aa",function(swoole_redis $redisClient,$result){
            var_dump($result);
            $redisClient->close();

        });*/

    });





