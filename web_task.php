<?php
class Ws{

    CONST HOST="0.0.0.0";
    CONST PORT=10086;

    public $ws=null;

    public function __construct()
    {
        $this->ws=new swoole_websocket_server(self::HOST,self::PORT);

        $this->ws->set([
            'worker_num'=>2,
            'task_worker_num'=>2,
        ]);
        $this->ws->on("open",[$this,'onOpen']);
        $this->ws->on("message",[$this,'onMessage']);
        $this->ws->on("task",[$this,'onTask']);
        $this->ws->on("finish",[$this,'onFinish']);
        $this->ws->on("close",[$this,'onClose']);
        $this->ws->start();
    }

    /*
     * 监听websocket连接事件
     * */
    public function onOpen($ws,$request){
        var_dump("websocket connect success:".$request->fd);

        //毫秒定时器
        if($request->fd==1){
            swoole_timer_tick(2000,function($timer_id){
                echo "2s timerId:{$timer_id}".PHP_EOL;
            });
        }



    }

    /*
     *监听消息事件
     * */
    public function onMessage($ws,$frame){
        echo "server push message: ".$frame->data.PHP_EOL;

        $data=[
            'task'=>1,
            'fd'=>$frame->fd,
        ];
        $ws->task($data);

        //定时器，多少秒后执行
        swoole_timer_after(5000,function() use($ws,$frame){
            $ws->push($frame->fd,"5s 后执行的定时器方法！".PHP_EOL);
        });
        $ws->push($frame->fd,"server push");
    }

    public  function onTask($serv,$taskId,$workerId,$data){
        var_dump($data);

        sleep(10);
        return "on task finish!";       //告诉work
    }

    public function onFinish($serv,$taskId,$data){
        echo "taskId:{$taskId}".PHP_EOL;
        echo "finish-data-sucess:{$data}".PHP_EOL;
    }


    /*
     * 关闭连接事件
     * */
    public function onClose($ws,$id){
        echo "client {$id} closed".PHP_EOL;
    }

}

$obj=new Ws();

