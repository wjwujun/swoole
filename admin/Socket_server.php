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
        $re=getRedis();
     /*   $re->set('key',1);
        $aa=$re->get('key');*/
        $arr=[
            'a'=>1,
            'b'=>2,
            'c'=>3
        ];
        $arr2=[
            'd'=>1,
            'e'=>2,
            'f'=>3
        ];
        $re->set('3',json_encode($arr));
        $re->set('4',json_encode($arr2));
        var_dump($re->get(3));
        var_dump($re->get(2));



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


