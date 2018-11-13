<?php

    /*
     *写文件
     * __DIR__
     * 参数1为文件的名称
     * 参数2为要写入到文件的内容，最大可写入4M
     * 参数3为写入成功后的回调函数，可选
     * 参数4为写入的选项，可以使用FILE_APPEND表示追加到文件末尾
     * */

    $content="hahah this is your father".PHP_EOL;
    $re=swoole_async_writefile(__DIR__."/1.log",$content,function($filename){
            //写成功以后的回调函数
            echo "写入成功".PHP_EOL;
    },FILE_APPEND);
    var_dump($re);

