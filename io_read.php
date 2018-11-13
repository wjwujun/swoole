<?php

    /*
     *读取文件
     * __DIR__
     *
     *   swoole_async_readfile会将文件内容全部复制到内存，所以不能用于大文件的读取
     *   如果要读取超大文件，请使用swoole_async_read函数
     *   swoole_async_readfile最大可读取4M的文件，受限于SW_AIO_MAX_FILESIZE宏
     * */

    $re=swoole_async_readfile(__DIR__."/1.txt",function($filename,$filecontent){
        echo "filename:".$filename.PHP_EOL;
        echo "content: ".$filecontent.PHP_EOL;
    });
    var_dump($re);

