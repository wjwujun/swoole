<?php
require_once "function.php";
use Aes\Aes;
use redis\redis;

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
/*    public function getRedis(){
        $redis = Redis::instance();
        $redis->connect('122.225.58.118', 6379);
        $redis->auth('U#rNFRkk3vuCKcZ5');
        return  $redis;
    }*/

    /*
     * 监听连接进入事件
     * $fd  客户端连接的标识
     * $reactor_id 线程id
     * */
    public function onConnect($serv, $fd,$reactor_id)
    {

        //获取ip,存入集合
        $redis = Redis::instance();
        $udp_client = $serv->connection_info($fd, $reactor_id);
        $redis->sAdd('ip',$udp_client['remote_ip']);

        echo "TCP  Client_id:{$fd}  threed_id:{$reactor_id}".PHP_EOL;
    }



    /*
     * 监听数据接收事件
     *$from_id就是 线程id，$reactor_id
     * */
    public function onReceive($serv, $fd, $from_id, $data) {
        $redis = Redis::instance();
        $mes=json_decode($data,true);

        $batch=$redis->hGet("ore_batch_route",$mes['routeruuid']);  //获取批次
        if(empty($batch)){     //如果没有批次表,默认加入1号批次
            $redis->hSet('ore_batch_route',$mes['routeruuid'],1);
        }

        //查看路由是否和批次配置相同，不相同就重启
        if(restart($mes)||$redis->sIsMember('ore_restart',$mes['routeruuid'])){
            $batch=$redis->hGet("ore_batch_route",$mes['routeruuid']);  //获取批次
            $batch_info=json_decode($redis->hGet("ore_batch",$batch),true);   //获取批次所对应的配置
            $batch_info['routeruuid']=$mes['routeruuid'];
            //aes加密
            $aa=returnData($batch_info,0,1);
            $serv->send($fd,encrypt($aa));
        }
        //判断是否重启成功
        $old_data=json_decode($redis->hGet("ore_info",$mes['routeruuid']),true);
        if(isset($old_data)) {
            if (restartStatus($old_data)) {
                $aa = returnData($old_data, 0, 1);
                $serv->send($fd, encrypt($aa));
            }
        }


        $mes['last_time']=date("Y-m-d H:i:s");
        $mes['last_unix_time']=time();
        $mes['fd']=$fd;
        $mes['deal_with']=0;
        //将路由器的传入的数据存入redis。
        $redis->zAdd('ore_fd',time(),$mes['routeruuid']);  //获取请求fd存入有序集合
        $redis->hSet('ore_info',$mes['routeruuid'],json_encode($mes)); //将详细信息存入haset
        $redis->close();






        //$serv->send($fd,$data);
    }

    //监听连接关闭事件
    public function onClose($serv, $fd) {
        $redis = Redis::instance();
        $redis->sRem('fd',$fd);
        $redis->close();
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
        swoole_timer_tick(10000,function()use($serv,$taskId){
            $redis = Redis::instance();
            $info=$redis->sMembers("ore_restart");         //重启


            if($info!=false){
                if($info=='all'){           //重启所有路由
                    $data=$redis->hVals('ore_info');
                    foreach($data as $router){
                        $arr=json_decode($router,true);
                        $serv->send($arr['fd'],encrypt(returnData($arr,0,1)));

                        /*修改原状态*/
                        $arr['deal_with']=1;
                        $re=$redis->hSet('ore_info',$arr['routeruuid'],json_encode($arr));
                        //写入日志
                        restartLog($router);
                    }


                }else{
                    foreach($info as $uuid){    //批量重启

                        $router=$redis->hGet('ore_info',$uuid);
                        $arr=json_decode($router,true);
                        $serv->send($arr['fd'],encrypt(returnData($arr,0,1)));

                        /*修改原状态*/
                        $arr['deal_with']=1;
                        $re=$redis->hSet('ore_info',$arr['routeruuid'],json_encode($arr));
                        //写入日志
                        restartLog($router);
                    }
                }
            }
            $redis->del('ore_restart');
        });
    }



    /*异步任务完成时候*/
    public  function onFinish($serv,$taskId,$data){
        echo "定时任务执行完成".PHP_EOL;
    }
}


$obj=new Tcp();





