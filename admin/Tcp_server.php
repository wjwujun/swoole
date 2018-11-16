<?php

class Tcp{


    CONST HOST="0.0.0.0";
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
            'task_worker_num'=>1     //异步task的进程数
        ]);

        $this->tcp->on('connect',[$this,'onConnect']);
        $this->tcp->on('receive',[$this,'onReceive']);
        $this->tcp->on('WorkerStart',[$this,'onWorkerStart']);      //此事件在Worker进程/Task进程启动时发生
        $this->tcp->on('task',[$this,'onTask']);                    //异步任务
        $this->tcp->on('finish',[$this,'onFinish']);                //异步任务完成的时候
        $this->tcp->on('close',[$this,'onClose']);                  //连接关闭的时候

        //启动服务器
        $this->tcp->start();

    }

    public function onWorkerStart($serv,$worker_id){
        // 只有当worker_id为0时才添加定时器,避免重复添加
        if( $worker_id == 0 ) {
            $str="异步任务开始执行时间".date("Y-m-d H:i:s");
            $serv->task($str);
        }
    }
    public  function onTask($serv,$taskId,$workerId,$data){
        var_dump($data);
        swoole_timer_tick(1000,function()use($serv,$taskId){
            echo date("Y-m-d H:i:s").PHP_EOL;
        });

    }

    public  function onFinish($serv,$taskId,$data){
        echo "定时任务执行完成".PHP_EOL;
    }


    /*
     * 监听连接进入事件
     * $fd  客户端连接的标识
     * $reactor_id 线程id
     * */
    public function onConnect($serv, $fd,$reactor_id)
    {
        echo "TCP  Client_id:{$fd}  threed_id:{$reactor_id}".PHP_EOL;

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
