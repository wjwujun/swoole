<?php
require_once 'function.php';

class Ws {

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

    /*获取redis实列*/
    public function getRedis(){
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->auth('U#rNFRkk3vuCKcZ5');
        return  $redis;
    }

    /*
     * 监听websocket连接事件
     * */
    function onOpen($ws,$request){

        var_dump("websocket connect success:".$request->fd).PHP_EOL;



    }




    /*
     *监听消息事件
     * */
    function onMessage($ws,$frame,$data){
        echo "server push message: ".$frame->data.PHP_EOL;

   /*     $redis=$this->getRedis();
        $error=[
            "uuid"=>"f8d325e43655dc1b56ec88d1f7b87cf2",
            "pll"=>"650",
            "fan"=>"3500",
            "reload"=>0  //0是重启，1是不重启
        ];

        $redis->hSet('update',"f8d325e43655dc1b56ec88d1f7b87cf2",json_encode($error));
        $redis->close();
*/

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


