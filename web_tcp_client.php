<?php

class TcpClient{


    CONST HOST="127.0.0.1";
    CONST PORT=10084;

    public $tcp_client=null;
    public  function __construct()
    {

        //创建client对象
        $this->tcp_client = new swoole_client(SWOOLE_SOCK_TCP);

        if(!$this->tcp_client->connect(self::HOST,self::PORT)){
            echo "tcp_client 连接失败!";
            exit;
        }

        //php cli常量
        fwrite(STDOUT,"请输入消息:");
        $msg=trim(fgets((STDIN)));

        //发送消息给tcp服务器
        $this->tcp_client->send($msg);

        //接收来自server的数据
        $re=$this->tcp_client->recv();
        if($re){
            echo $re.PHP_EOL;
        }
    }
}
$obj=new TcpClient();




