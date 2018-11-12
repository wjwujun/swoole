<?php
class Http{

    CONST HOST="0.0.0.0";
    CONST PORT=8080;

    public $http=null;

    public function __construct()
    {
        $this->http=new swoole_http_server(self::HOST,self::PORT);

        //设置静态资源目录,设置了以后，会先走这一步，然后走onRequest方法
        $this->http->set([
            'enable_static_handler'=>true,
            'document_root'=>'/root/ws_server/demo/static',

        ]);

        $this->http->on("request",[$this,'onRequest']);
        $this->http->start();
    }

    /*
     * 监听http连接事件
     * */
    function onRequest($request,$response){
        $response->end("<h1>this is http server</h1>");
    }

}

$obj=new Http();
















