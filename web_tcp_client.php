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
/*        fwrite(STDOUT,"请输入消息:");
        $msg=trim(fgets((STDIN)));*/
        $msg=[
            "routeruuid"=>"f8d325e43655dc1b56ec88d1f7b87cf2",
            "poolinfo"=> [
                "poolurl0"=>"stratum+tcp://sz.ss.btc.com:1800",
                "poolurl1"=>"stratum+tcp://sz.ss.btc.com:433",
                "poolurl2"=>"stratum+tcp://sz.ss.btc.com:25",
                "poolusr0"=>"123456",
                "poolusr1"=>"123456",
                "poolusr2"=>"123456",
                "poolpasswd0"=>"",
                "poolpasswd1"=>"",
                "poolpasswd2"=> ""
            ],
            "poolsta"=>[
                "fan"=> "3000",         //风扇大于4000重启
                "pll"=> "550",          //500-700正常，不是就重启
                "core"=>[              //  任意一个close重启
                    0=>"open",
                    1=>"open",
                ],
                "temperature"=>         //大于80重启
                    [
                        0=>"66",
                        1=>"55",
                        2=>"70",
                        3=>"50",
                        4=>"46",
                        5=>"52",
                    ]
                ],
           "compute"=>[         //任意一个为0重启
                "5s"=>"138.2G",
                "60s"=>"130.2G",
                "avg"=>"135.5G",
            ]

            ];
        $msg=json_encode($msg);

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



