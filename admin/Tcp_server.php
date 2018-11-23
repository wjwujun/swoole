<?php
require_once "function.php";
use Aes\Aes;
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

    /*获取redis实列*/
    public function getRedis(){
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->auth('U#rNFRkk3vuCKcZ5');
        return  $redis;
    }

    /*
     * 监听连接进入事件
     * $fd  客户端连接的标识
     * $reactor_id 线程id
     * */
    public function onConnect($serv, $fd,$reactor_id)
    {
        $redis=$this->getRedis();
        //获取ip,存入集合
        /*$udp_client = $serv->connection_info($fd, $reactor_id);
        $redis->sAdd('ip',$udp_client['remote_ip']);*/

        echo "TCP  Client_id:{$fd}  threed_id:{$reactor_id}".PHP_EOL;
    }



    /*
     * 监听数据接收事件
     *$from_id就是 线程id，$reactor_id
     * */
    public function onReceive($serv, $fd, $from_id, $data) {

        $mes=json_decode($data,true);
        /*$udp_client = $serv->connection_info($fd, $from_id);*/

        $mes['last_time']=date("Y-m-d H:i:s");
        $mes['fd']=$fd;
        if(restart($mes)){        //重启
            $serv->send($fd,returnData($mes['routeruuid']));
        }
        //将路由器的传入的数据存入redis。
        /*
        $redis=$this->getRedis();
        $redis->zAdd('fd',time(),$mes['routeruuid']);  //获取请求fd存入有序集合
                获取信息 zrange key 0 10
                返回集合中元素个数  zrange zset1 0 -1
        $redis->hSet('info',$mes['routeruuid'],json_encode($mes)); //将详细信息存入haset
                获取所有key  hkeys info
                  获取单个信心  hget info key
        $redis->hSet('update',$mes['routeruuid'],json_encode($mes)); //将修改信息存入haset
        $redis->close();
        */
        $redis=$this->getRedis();
        $error=[
        ];
        $re=returnData("f8d325e43655dc1b56ec88d1f7b87cf1,",1,0,1,$error);
        $redis->hSet('update',"f8d325e43655dc1b56ec88d1f7b87cf1",$re);
        $redis->close();


        //aes加密
        $re=encrypt($data);
/*        var_dump($data);
        var_dump(strlen($re));
        var_dump('---------------------------');*/


//        $serv->send($fd,$re);


    }

    //监听连接关闭事件
    public function onClose($serv, $fd) {
        echo "Client: Close {$fd}".PHP_EOL;
    }



    /*work启动时候*/
    public function onWorkerStart($serv,$worker_id){
        // 只有当worker_id为0时才添加定时器,避免重复添加
        if( $worker_id == 0 ) {
            $str="异步任务开始执行时间".date("Y-m-d H:i:s");
            $serv->task($str);
        }
    }

    /*异步任务*/
    public  function onTask($serv,$taskId,$workerId,$data){
        swoole_timer_tick(5000,function()use($serv,$taskId){
            $redis=$this->getRedis();

            //$data=$redis->hGet('update','f8d325e43655dc1b56ec88d1f7b87cf2');
            //获取所有
            $data=$redis->hVals('update');

            if($data!=false){
                foreach($data as $v){
                    $fd=json_decode($v,true);
                    $re=$serv->send($fd['fd'],encrypt($v));

                }
            }
            $redis->close();
        });
    }

    /*异步任务完成时候*/
    public  function onFinish($serv,$taskId,$data){
        echo "定时任务执行完成".PHP_EOL;
    }
}


$obj=new Tcp();





