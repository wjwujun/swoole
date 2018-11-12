<?php

class Tcp{


    CONST HOST="127.0.0.1";
    CONST PORT=10084;

    public $tcp=null;
    public  function __construct()
    {

        //创建Server对象，监听 127.0.0.1:9501端口
        $this->tcp = new swoole_server(self::HOST,self::PORT);


        //配置
        $this->tcp->set([
            'worker_num'=>8,  //worker进程数，建议开启cpu的1-4倍
            'max_request'=>10000,  //每个进程处理的最大连接数
        ]);

        $this->tcp->on('connect',[$this,'onConnect']);
        $this->tcp->on('receive',[$this,'onReceive']);
        $this->tcp->on('close',[$this,'onClose']);

        //启动服务器
        $this->tcp->start();

    }


    /*
     * 监听连接进入事件
     * $fd  客户端连接的标识
     * $reactor_id 线程id
     * */
    public function onConnect($serv, $fd,$reactor_id)
    {
        echo "Client_id:{$fd}  threed_id:{$reactor_id}".PHP_EOL;
    }

    /*
     * 监听数据接收事件
     *$from_id就是 线程id，$reactor_id
     * */
    public function onReceive($serv, $fd, $from_id, $data) {
         $serv->send($fd, "Server: ".$data);
    }

    //监听连接关闭事件
    public function onClose($serv, $fd) {
        echo "Client: Close {$fd}".PHP_EOL;
    }

}

$obj=new Tcp();



