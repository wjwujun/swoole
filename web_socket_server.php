<?php
class Ws{

    CONST HOST="0.0.0.0";
    CONST PORT=10086;

    public $ws=null;

    public function __construct()
    {
        $this->ws=new swoole_websocket_server(self::HOST,self::PORT);

        $this->ws->on("open",[$this,'onOpen']);
        $this->ws->on("message",[$this,'onMessage']);
        $this->ws->on("close",[$this,'onClose']);
        $this->ws->start();
    }

    /*
     * 监听websocket连接事件
     * */
    function onOpen($ws,$request){
        var_dump("websocket connect success:".$request->fd);
    }

    /*
     *监听消息事件
     * */
    function onMessage($ws,$frame){
        echo "server push message: ".$frame->data.PHP_EOL;
        $ws->push($frame->fd,"server push");
    }

    /*
     * 关闭连接事件
     * */
    function onClose($ws,$id){
        echo "client {$id} closed".PHP_EOL;
    }

}

$obj=new Ws();

