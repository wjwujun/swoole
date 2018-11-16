<?php

require_once 'MyRedis.php';
require_once 'Function.php';

class Ws {

    //ws server
    CONST HOST = "0.0.0.0";
    CONST PORT = 8800;

    //db server
    CONST DB_HOST = "127.0.0.1";
    CONST DB_PORT = 3306;
    CONST DB_USER = "root";
    CONST DB_PASSWORD = "gehua1108";
    CONST DB_DATABASE = "gh_market";
    CONST DB_CHARSET = "utf8";
    CONST DB_TIMEOUT = 20;

    //redis server
    CONST RDS_HOST = "127.0.0.1";
    CONST RDS_PORT = 6379;
    CONST RDS_PWD = "U#rNFRkk3vuCKcZ5";
    CONST RDS_PULISH_SRV = "ghm";
    CONST RDS_DB_NO = 2;
    CONST RDS_CDB_NO = 0;
    const RDS_PULISH_COUNT = 100;

    //redis keys
    CONST RDS_KEY_TRADE_AREA = "swoole:trade:area";  //交易区数据
    CONST RDS_KEY_CLIENT_PUTUP_BUY = "swoole:client:putup:buy";  //委托记录买
    CONST RDS_KEY_CLIENT_PUTUP_SELL = "swoole:client:putup:sell";  //委托记录卖
    CONST RDS_KEY_CLIENT_RECORD = "swoole:client:record";  //交易记录
    CONST RDS_KEY_CLIENT_KLINE = "swoole:client:kline";  //k线图数据
    CONST RDS_KEY_CLIENT_DEEP_BUY = "swoole:client:deep:buy"; //深度图数据买
    CONST RDS_KEY_CLIENT_DEEP_SELL = "swoole:client:deep:sell"; //深度图数据卖
    CONST RDS_KEY_PUBLISH_RECORD = "swoole:publish:record";  //交易记录redis广播的
    CONST RDS_KEY_PLOCK_RECORD_LIST = "swoole:plock:record:list";  //交易记录锁列表
    CONST RDS_KEY_PLOCK_RECORD_MD5 = "swoole:plock:record:md5";  //交易记录锁md5
    CONST RDS_KEY_PLOCK_PUTUP_BUY_LIST = "swoole:plock:putup:buy:list";
    CONST RDS_KEY_PLOCK_PUTUP_BUY_MD5 = "swoole:plock:putup:buy:md5";
    CONST RDS_KEY_PLOCK_PUTUP_SELL_LIST = "swoole:plock:putup:sell:list";
    CONST RDS_KEY_PLOCK_PUTUP_SELL_MD5 = "swoole:plock:putup:sell:md5";
    CONST RDS_KEY_PRICE_LAST = "swoole:price_last";
    CONST RDS_KEY_FDS = "swoole:fds";


    //其他配置
    CONST KLINE_PEX_LIST = ["1min","5min","15min","30min","60min","1D","5D","1W","1M"];
    CONST MARKET_ID_ARR = [1,2];
    CONST MARKET_RECORD_COUNT = 45;
    CONST MARKET_PUTUP_BUY_COUNT = 10;
    CONST MARKET_PUTUP_SELL_COUNT = 10;
    CONST PUSH_TICK = 1000;
    CONST RDS_SYN_TICK = 1000;

    //数据
    protected $putup_arr = [];
    protected $market_arr = [];
    protected $market_arr_old = [];
    protected $trade_area_lock = 1;

    //变量和锁
    protected $record_arr_md5 = [];
    protected $record_arr_list = [];
    protected $putup_arr_md5 = [];
    protected $putup_arr_list = [];

    //内存中的变量
    protected $price_last = [];
    protected $fds = [];
    protected $deep_buy = [];
    protected $deep_sell = [];


    //server obj
    public $ws = null;

    public function __construct() {


        $this->ws = new swoole_websocket_server(self::HOST, self::PORT);

        $this->ws -> set([
            'worker_num' => 1,
            'daemonize' => 0,
            'heartbeat_idle_time' => 30,
            'heartbeat_check_interval' => 30,
        ]);

        $this->ws->on("start", [$this, 'onStart']);
        $this->ws->on("open", [$this, 'onOpen']);
        $this->ws->on("message", [$this, 'onMessage']);
        $this->ws->on("workerstart", [$this, 'onWorkerStart']);
        $this->ws->on("request", [$this, 'onRequest']);
        $this->ws->on("task", [$this, 'onTask']);
        $this->ws->on("finish", [$this, 'onFinish']);
        $this->ws->on("close", [$this, 'onClose']);

        $this->ws->start();

    }


    /**
     * @param $server
     */
    public function onStart($server) {
        swoole_set_process_name("ghm_swoole_master");
    }



    /**
     * @param $server
     * @param $worker_id
     */
    public function onWorkerStart($server,  $worker_id) {


        //初始化数据
        $this->initRedis($server, $worker_id);

        //更新redis后监听
        $this->listenPublish();

        //监听后设置一个定时任务,定时更新内存中的数据
        $this->trckToClient();

        //同步内存中的价格信息到redis
        $this->trckToRedis();



    }



    public function generateClientPutup($mid,$type,$fds){

        $client = new swoole_redis;
        $client->__construct($options = [ 'password' => self::RDS_PWD]);

        $client->connect(self::RDS_HOST, self::RDS_PORT, function (swoole_redis $client, $result) use($mid,$type,$fds) {

            if ($result === false) {return ee("can not connect redis server.");}

            $redis_key = "hash_market_{$mid}_{$type}";

            $client->hvals($redis_key, function (swoole_redis $client, $result) use($mid,$type,$redis_key,$fds) {

                if($result){

                    if($type == "buy") {

                        $buys = $result;
                        $effectiveBuys = [];

                        foreach ($buys as $k => $row) {
                            $row1 = json_decode($row, true);

                            $effectiveBuys[] = $row1;
                            $sortPrice[$k] = $row1['price'];
                            $sortTimeS[$k] = $row1['create_time'];
                            $sortMicroS[$k] = $row1['microS'];

                        }

                        if ($effectiveBuys) {
                            array_multisort($sortPrice, SORT_DESC,
                                $sortTimeS, SORT_ASC,
                                $sortMicroS, SORT_ASC,
                                $effectiveBuys);
                        }

                        $pbit = $this->market_arr[$mid]["price_bit"];
                        $abit = $this->market_arr[$mid]["amount_bit"];
                        $list_all = putup_price_merge($effectiveBuys,$pbit,$abit);
                        $list_all = getFormatPutup2($list_all,false,$pbit,$abit);
                        $list_all = array_reverse(getFormatDeep($list_all,$pbit,$abit));

                        $this->deep_buy[$mid] = $list_all;
                        $list = array_slice($list_all,0,self::MARKET_PUTUP_BUY_COUNT);

                    }


                    if($type == "sell") {

                        $sells = $result;
                        $effectiveSells = [];

                        foreach ($sells as $k => $row) {
                            $row1 = json_decode($row,true);
                            $effectiveSells[] = $row1;
                            $sortPrice[$k] = $row1['price'];
                            $sortTimeS[$k] = $row1['create_time'];
                            $sortMicroS[$k] = $row1['microS'];
                        }

                        if ($effectiveSells){
                            array_multisort($sortPrice, SORT_ASC,
                                $sortTimeS, SORT_ASC,
                                $sortMicroS, SORT_ASC,
                                $effectiveSells);
                        }

                        $pbit = $this->market_arr[$mid]["price_bit"];
                        $abit = $this->market_arr[$mid]["amount_bit"];
                        $list_all = putup_price_merge($effectiveSells,$pbit,$abit);
                        $list_all = getFormatPutup2($list_all,false,$pbit,$abit);
                        $list_all = getFormatDeep($list_all,$pbit,$abit);

                        $this->deep_sell[$mid] = $list_all;


                        $list = array_slice($list_all,0,self::MARKET_PUTUP_SELL_COUNT);

                    }

                    $client->close();
                    //异步更新redis
                    $this->async_update_redis_tick($list, $type, $mid, $fds, "233");

                }else{
                    //echo "[+].获取{$redis_key}数据失败，为空.".PHP_EOL;
                    $this->async_update_redis_tick([], "{$type}", $mid, $fds, "233");
                }


            });

        });


    }


    /**
     *监听reids的广播
     */
    public function listenPublish(){
        /*redis绑定订阅信息*/

        $server = $this->ws;

        $client = new swoole_redis;
        $client->__construct($options = [ 'password' => self::RDS_PWD, ]);
        $client->on('message', function (swoole_redis $client, $result) use ($server) {
            return $this->onRecievePublishMsg($client, $result, $server);
        });


        $client->connect(self::RDS_HOST, self::RDS_PORT, function (swoole_redis $client, $result) {
            ee("[-].开始监听redis广播， " . self::RDS_PULISH_SRV . " 频道.");
            $client->subscribe(self::RDS_PULISH_SRV);
        });
    }






    /**
     *从redis里拿数据，定时推送给客户端
     */
    public  function  trckToClient(){

        $redis = $this->getCoroRedis();

        swoole_timer_tick(self::PUSH_TICK, function () use($redis){

            $redis->set(self::RDS_KEY_FDS,json_encode($this->fds));

            $this->pushToClient($redis);

        });


    }




    /**
     *从redis里拿数据，定时推送给客户端
     */
    public  function  trckToRedis(){

        swoole_timer_tick(self::RDS_SYN_TICK, function (){
            $this->pushToRedis();
        });

    }


    /**
     * 同步交易市场信息到内存到redis
     */
    public function pushToRedis(){



        $client = new swoole_redis;
        $client->__construct($options = [ 'password' => self::RDS_PWD, ]);

        $client->connect(self::RDS_HOST, self::RDS_PORT, function (swoole_redis $client, $result) {

            if ($result === false) {return ee("can not connect redis server.");}

            $client->select(self::RDS_DB_NO, function (swoole_redis $client, $result) {

                $client->get(self::RDS_KEY_TRADE_AREA, function (swoole_redis $client, $result) {


                    if($result == json_encode($this->market_arr)){

                        //var_dump("========================");
                        $client->close();
                    }else{

                        $this->market_arr = get_cny_price($this->market_arr);

                        $newJson = json_encode($this->market_arr);


                        $client->set(self::RDS_KEY_TRADE_AREA, $newJson, function (swoole_redis $client, $result) {

                            $redis_key = self::RDS_KEY_TRADE_AREA;

                            ee("[-].更新{$redis_key}成功.同步market_arr到redis.");

                            $client->close();


                        });
                    }

                });


            });
        });



    }


    /**
     *  推送交易市场信息到客户端
     */
    public  function push_trade_area_to_client(){

        $fds_data = getFormatTradeArea1($this->market_arr);
        $fds = getFdsListAll($this->market_arr);



        if( !compareTradeArea($this->market_arr,$this->market_arr_old)) {

            $fds_arr = array_keys($this->fds);
            $fds_all = array_unique(array_merge($fds_arr,$fds));
            $this->pushClientByFds($fds_data, "trade_area", $fds_all);
            $this->market_arr_old = $this->market_arr;
        }

    }







    /**把redis的数据推送打客户端
     * @param $coro_redis
     */
    public  function pushToClient($redis){

        //数据库获取所有的交易市场
        $market_list = getMarketListBy($this->market_arr,"trade_area");

        $this->trade_area_lock = 0;

        if(count($market_list) > 0) {

            //获取交易市场对应的最新数据
            //k线路图,委托记录，挂单记录，交易对数据
            foreach ($market_list as $key => $val) {

                $mid = $key;
                $fds = $val;

                if (!$val) $fds = [0];

                //k线图,交易记录，判断record是否更新
                //$push_key_lock = self::RDS_KEY_PLOCK_RECORD.":{$mid}";
                //$lockjson = $redis->get($push_key_lock);

                $push_key_last = self::RDS_KEY_PUBLISH_RECORD.":{$mid}";
                $lastjson = $redis->lindex($push_key_last, 0);

                $record_lock_md5 = md5($lastjson);
                if ($this->record_arr_md5[$mid] != $record_lock_md5 && $lastjson) {   //record更新，则更新k线图，交易记录,交易对价格

                    $this->trade_area_lock = 1;

                    $last_arr = json_decode($lastjson, true);

                    $price = $last_arr["price"];
                    $count = $last_arr["count"];
                    $time = $last_arr["time"];
                    $direction = $last_arr["direction"];
                    $order_id = $last_arr["order_id"];

                    $redis->set(self::RDS_KEY_PRICE_LAST.":".$mid,$price);


                    //异步更新k线路图并推送给客户端
                    $sql = "INSERT INTO swoole_kline_{$mid} (trade_price, trade_count,trade_time,order_id) VALUES ('" . $price . "', '" . $count . "','" . $time . "','" . $order_id . "')";
                    $this->async_update_mysql_tick($sql, "kline");

                    //异步更新redis并推送给客户端（循环）
                    foreach (self::KLINE_PEX_LIST as $key => $val) {
                        $this->async_update_redis_tick($lastjson, "kline", $mid, $fds, $val);
                    }

                    //异步更新交易记录并推送给客户端
                    $sql = "INSERT INTO swoole_record_{$mid} (price,count,time,order_id,direction) VALUES ('" . $price . "', '" . $count . "','" . $time . "','" . $order_id . "','" . $direction . "')";
                    $this->async_update_mysql_tick($sql, "record");

                    //异步更新redis并推送给客户端（循环）
                    $this->async_update_redis_tick($lastjson, "record", $mid, $fds, $val);

                    //更新交易区信息
                    $taData["market_id"] = $mid;
                    $taData["price"] = $price;
                    $taData["count"] = $count;

                    $this->async_update_redis_tick(json_encode($taData), "trade_area", $mid, $fds, $val);

                    $this->record_arr_md5[$mid] = $record_lock_md5;
                    $this->record_arr_list[$mid] = $lastjson;

                    $redis->set(self::RDS_KEY_PLOCK_RECORD_MD5.":".$mid,$record_lock_md5);
                    $redis->set(self::RDS_KEY_PLOCK_RECORD_LIST.":".$mid,$lastjson);


                    //$redis->set($push_key_lock, $lastjson);

                }



                //更新委托记录

                $putup_type_arr = ["buy","sell"];
                foreach ($putup_type_arr as $type){
                    $putup_key = "hash_market_{$mid}_{$type}";
                    $redis->select(self::RDS_CDB_NO);
                    $newList = $redis->hvals($putup_key);
                    $redis->select(self::RDS_DB_NO);
                    $newJsonMD5 = md5(json_encode($newList));
                    $oldJsonMD5 = $this->putup_arr_md5[$type][$mid];
                    if ($newJsonMD5 != $oldJsonMD5 && $newList) {
                        $this->generateClientPutup($mid, $type, $fds);
                        $this->putup_arr_md5[$type][$mid] = $newJsonMD5;
                        $this->putup_arr_list[$type][$mid] = $newList;

                        if($type == "buy") {

                            $redis->set(self::RDS_KEY_PLOCK_PUTUP_BUY_MD5.":".$mid,$newJsonMD5);
                            $redis->set(self::RDS_KEY_PLOCK_PUTUP_BUY_LIST.":".$mid,json_encode($newList));

                        }else{

                            $redis->set(self::RDS_KEY_PLOCK_PUTUP_SELL_MD5.":".$mid,$newJsonMD5);
                            $redis->set(self::RDS_KEY_PLOCK_PUTUP_SELL_LIST.":".$mid,json_encode($newList));

                        }

                    }


                }

            }


        }else{
            ee("[+] 没有获取到交易市场信息.");
        }


        if($this->trade_area_lock){
            $this->push_trade_area_to_client();
        }






    }


    /** 异步更新musql
     * @param $sql
     */
    public  function  async_update_mysql_tick($sql,$type){

        $db = new swoole_mysql();
        $server = array(
            'host' => self::DB_HOST,
            'port' => self::DB_PORT,
            'user' => self::DB_USER,
            'password' => self::DB_PASSWORD,
            'database' => self::DB_DATABASE,
            'charset' => self::DB_CHARSET, //指定字符集
            'timeout' => self::DB_TIMEOUT,  // 可选：连接超时时间（非查询超时时间），默认为SW_MYSQL_CONNECT_TIMEOUT（1.0）
        );

        $db->connect($server, function ($db, $r) use ($sql,$type) {
            if ($r === false) return ee("fail to connect db.{$db->connect_errno},{$db->connect_error},{$sql}");
            $db->query($sql, function(swoole_mysql $db, $r) use ($sql,$type) {
                if ($r === false) {
                    ee("[+].执行sql出错.{$db->connect_errno},{$db->connect_error},{$sql}");
                }
                elseif ($r === true ) {

                    ee("[-].异步更新mysql成功.({$type})");

                }
                $db->close();
            });
        });
    }


    /** 异步更新redis并推送最新数据到客户端
     * @param $lastjson
     * @param $type
     * @param $fds
     */
    public  function async_update_redis_tick($lastjson,$type,$mid,$fds,$timeT=""){



        $client = new swoole_redis;
        $client->__construct($options = [ 'password' => self::RDS_PWD, ]);

        $client->connect(self::RDS_HOST, self::RDS_PORT, function (swoole_redis $client, $result) use ($lastjson,$type,$mid,$fds,$timeT) {

            if ($result === false) {return ee("can not connect redis server.");}



            $client->select(self::RDS_DB_NO, function (swoole_redis $client, $result) use($lastjson,$type,$mid,$fds,$timeT) {

                //k线路图
                if ($type == "kline") {

                    $redis_key = self::RDS_KEY_CLIENT_KLINE.":{$mid}:{$timeT}";

                    $client->get($redis_key, function (swoole_redis $client, $result) use ($redis_key, $lastjson, $type, $mid, $fds, $timeT) {


                        //new
                        $myarr = json_decode($lastjson, true);

                        $price = $myarr["price"];
                        $count = $myarr["count"];
                        $time =  $myarr["time"];
                        $near_time = getNearTime($time, $timeT);

                        //old
                        $dataArr = json_decode($result, true);
                        if (count($dataArr) > 0) {
                            $last_index = count($dataArr) - 1;
                        } else {
                            $last_index = 0;
                        }
                        $last = $dataArr[$last_index];

                        //判断
                        if ((int)$near_time == (int)$last["time"]) {
                            if ($price < $last["low"]) $last["low"] = $price;
                            if ($price > $last["high"]) $last["high"] = $price;
                            $last["close"] = $price;
                            $last["volume"] += $count;
                            $dataArr[$last_index] = $last;
                        } else {
                            $last["time"] = $near_time;
                            $last["high"] = $price;
                            $last["low"] = $price;
                            $last["close"] = $price;
                            $last["volume"] = $count;
                            $dataArr[$last_index + 1] = $last;

                        }

                        if ($timeT == "1min") {

                            $kline_data = getFormatKline1($dataArr, true);
                            $this->pushClientByFds($kline_data, "kline", $fds);

                        }

                        $newArr = $dataArr;
                        $newJson = json_encode($newArr);

                        $client->set($redis_key, $newJson, function (swoole_redis $client, $result) use ($redis_key) {
                            ee("[-].异步更新k线图redis成功.({$redis_key})");
                            $client->close();
                        });


                    });

                }


                //交易记录
                if ($type == "record") {

                    $redis_key = self::RDS_KEY_CLIENT_RECORD.":{$mid}";

                    $client->get($redis_key, function (swoole_redis $client, $result) use ($redis_key, $lastjson, $type, $mid, $fds, $timeT) {

                        //new
                        $myarr = json_decode($lastjson, true);

                        $last["price"] = $myarr["price"];
                        $last["count"] = $myarr["count"];
                        $last["direction"] = $myarr["direction"];
                        $last["time"] = $myarr["time"];

                        $fds_data = getFormatRecord1([$last], true);

                        $this->pushClientByFds($fds_data, "record", $fds);

                        //old
                        $dataArr = json_decode($result, true);
                        array_push($dataArr, $last);

                        if(count($dataArr) > self::MARKET_RECORD_COUNT) array_shift($dataArr);

                        $newJson = json_encode($dataArr);

                        $client->set($redis_key, $newJson, function (swoole_redis $client, $result) use ($redis_key) {
                            ee("[-].异步更新k线图redis成功.({$redis_key})");
                            $client->close();
                        });


                    });


                }


                //委托记录(买)
                if ($type == "buy" || $type=="sell") {
                    $list = $lastjson;

                    //$formatList = hash2putup($list);
                    $deepList = putup_deep($list);

                    if($type == "buy"){
                        $this->pushClientByFds($deepList, "putup_buy", $fds);
                        $this->pushClientByFds($this->deep_buy[$mid], "deep_buy", $fds);
                    }else{
                        $this->pushClientByFds($deepList, "putup_sell", $fds);
                        $this->pushClientByFds($this->deep_sell[$mid], "deep_sell", $fds);
                    }




                    if($type == "buy"){
                        $redis_key = self::RDS_KEY_CLIENT_PUTUP_BUY.":".$mid;
                        $redis_key1 = self::RDS_KEY_CLIENT_DEEP_BUY.":".$mid;
                        $deepjson = json_encode( $this->deep_buy[$mid] );
                    }else{
                        $redis_key = self::RDS_KEY_CLIENT_PUTUP_SELL.":".$mid;
                        $redis_key1 = self::RDS_KEY_CLIENT_DEEP_SELL.":".$mid;
                        $deepjson = json_encode( $this->deep_sell[$mid] );
                    }

                    $newJson = json_encode($deepList);

                    $client->set($redis_key, $newJson, function (swoole_redis $client, $result) use ($redis_key,$redis_key1,$deepjson) {

                        $client->set($redis_key1, $deepjson, function (swoole_redis $client, $result) use ($redis_key1) {

                            ee("[-].异步更新redis成功.({$redis_key1})");
                            $client->close();
                        });

                    });

                }

                //委托记录(卖)
                if ($type == "trade_area") {

                    //new
                    $myarr = json_decode($lastjson, true);

                    $price = $myarr["price"];
                    $count = $myarr["count"];
                    $market_id = $myarr["market_id"];

                    foreach ($this->market_arr as $key => $val) {

                        if ($this->market_arr[$key]["ta_id"] == $market_id) {

                            $this->market_arr[$key]["price"] = $price;  //现价

                            if($price < $this->market_arr[$key]["low"]) $this->market_arr[$key]["low"] = $price; //最低价
                            if($price > $this->market_arr[$key]["high"]) $this->market_arr[$key]["high"] = $price; //最高价

                            $open = $this->price_last[$market_id];

                            $this->market_arr[$key]["per"] = ( ($price - $open) / $open ) * 100;  //涨幅

                            $this->market_arr[$key]["total24"] += $count;
                            $this->price_last[$mid] = $price;

                        }

                    }

                    //var_dump($this->market_arr);

                    $client->close();

                }

            });

        });
    }







    /**发送到客户端
     * @param $data
     * @param $type
     * @param $fds
     */
    public function  pushClientByFds($data,$type,$fds){

        if(count($fds)){

            $rdata["type"] = $type;
            $rdata["data"] = $data;
            $jsonData = json_encode($rdata);
            foreach ($fds as $key => $val){

                if($val) $this->ws->push($val,$jsonData);
            }

        }else{
            ee("[+].获取客户端连接数出错，客户端连接为空");
        }

    }




    /**
     * request回调
     * @param $request
     * @param $response
     */
    public function onRequest($request, $response) {
        var_dump($request);
    }



    /**
     * @param $serv
     * @param $taskId
     * @param $workerId
     * @param $data
     */
    public function onTask($serv, $taskId, $workerId, $data) {

    }



    /**
     * @param $serv
     * @param $taskId
     * @param $data
     */
    public function onFinish($serv, $taskId, $data) {}

    /**
     * 监听ws连接事件
     * @param $ws
     * @param $request
     */
    public function onOpen($ws, $request) {

        $fd = $request->fd;

        $this->fds[$fd] = $fd;

        /******************手动绑定***********/

        $rDataJson = enJson("", 1, "connect successed.");
        return $this->ws->push($fd, $rDataJson);

    }



    /**
     * 监听ws消息事件
     * @param $ws
     * @param $frame
     */
    public function onMessage($ws, $frame) {

        return $this->onRecieveClientMsg($ws,$frame);

    }


    /**
     * close
     * @param $ws
     * @param $fd
     */
    public function onClose($ws, $fd) {

        echo "[-].客户端[{$fd}]断开连接.\n";
        $this->async_redis_market_unband($fd);

    }








    /**
     * [+] 事件的处理函数
     */


    /**
     * 当收到redis发布的新信息的时候
     */
    public function onRecievePublishMsg($client,$result,$server){

        //判断数据的正误
        //不同类型进入不同逻辑处理
        //根据不同的频道推送给想要的客户端
        //处理完了后异步更新redis和mysql


        if ($result[0] === 'message') {

            $jsondata = $result[2];

            if(isJson($jsondata)){

                $data = json_decode($jsondata,true);
                $mid = $data["mi_id"];  //交易市场id

                //格式化一下
                $newJsonArr["t"] = date("Y-m-d H:i:s",$data["create_time"]);
                $newJsonArr["price"] = $data["price"];
                $newJsonArr["direction"] = $data["type"];
                $newJsonArr["count"] = $data["decimal"];
                $newJsonArr["time"] = $data["create_time"];
                $newJsonArr["order_id"] = $data["order_no"];
                $newJsonArr["mid"] = $mid;

                $newJson = json_encode($newJsonArr);

                $this->async_publish_lpush(self::RDS_KEY_PUBLISH_RECORD.":{$mid}",$newJson);

            }else{

                var_dump($jsondata);
                return ee("[+].收到的广播数据不是json.".$jsondata);

            }


        }

    }


    /**
     * 当收到客户端请求的时候
     */
    public function onRecieveClientMsg($ws,$frame){


        /**************手动绑定交易市场*********/


        $fd = $frame->fd;
        $jsonRecieve = $frame->data;
        var_dump(json_decode($jsonRecieve,true));

        if(!isJson($jsonRecieve)){
            $rDataJson = enJson("", 0, "request not json.");
            return $this->ws->push($fd, $rDataJson);
        }

        $data = json_decode($jsonRecieve,true);
        $type = $data["cmd"]?$data["cmd"]:0;


        //绑定交易市场
        if("band" == $type){

            $market_id = $data["mid"];
            $market_ids = array_column($this->market_arr,"ta_id");

            if(in_array($market_id,$market_ids)){
                return $this->async_redis_market_band($fd, $market_id);
            }else{
                $rDataJson = enJson("", 0, "market not exist..");
                return $this->ws->push($fd, $rDataJson);
            }

        }

        //ping
        if("ping" == $type){

            $rDataJson = json_encode(["type"=>"pong","t"=>time()]);
            return $this->ws->push($fd, $rDataJson);

        }


        //获取mid
        $redis = $this->getCoroRedis();
        $mid = getMidByFd($this->market_arr,$fd);


        if($mid < 1) {
            $rDataJson = enJson("", 0, "please band market.");
            return $this->ws->push($fd, $rDataJson);
        }



        $rDataJson = enJson("", 0, "request fail.");
        return $this->ws->push($fd, $rDataJson);



    }



    /**
     * 具体某个方法
    /*
    初始化，把数据导入redis
     */
    public function initRedis(){

        //为什么要用同步方法，因为要先导入redis后再监听
        $coro_mysql = $this->getCoroMysql();
        $coro_redis = $this->getCoroRedis();

        //填充交易区数据
        $sql = "select * from v_trade_area";
        $res = attachTid( $coro_mysql->query($sql));

        $this->market_arr = get_test_price($res);

        //获取表数据
        foreach ($this->market_arr as $key => $val){
            $tmid = $val["ta_id"];
            $kline_tblist[$tmid] = "swoole_kline_".$tmid;
            $record_tblist[$tmid] = "swoole_record_".$tmid;
            $kline_rtblist[$tmid] = self::RDS_KEY_CLIENT_KLINE.":".$tmid;
            $record_rtblist[$tmid] = self::RDS_KEY_CLIENT_RECORD.":".$tmid;
        }


        //创建不存在的表
        foreach ($this->market_arr as $key => $val){
            $market_id = $val["ta_id"];
            $this->checkTable($market_id,"kline");
            $this->checkTable($market_id,"record");
        }


        //把k线路图数据拿入redis
        $pxlist = self::KLINE_PEX_LIST;

        foreach ($kline_tblist as $key => $val){

            for($k=0;$k<count($pxlist);$k++){

                $dbname = $kline_tblist[$key];
                $rdbname = $kline_rtblist[$key];
                $pxname = $pxlist[$k];

                $sql = getKlineSql($dbname,$pxname);
                $res = $coro_mysql->query($sql);

                if(is_array($res)){

                    $res_all = $res;
                    $jr_all = json_encode($res_all);
                    $redis_key = $rdbname.':'.$pxname;
                    $coro_redis->set($redis_key,$jr_all);

                    ee("[-].导入redis成功.(".$redis_key.").");
                }else{
                    var_dump($res);
                    ee("[+].导入redis失败.".$redis_key.")");
                }

            }

        }



        //交易记录加入redis
        foreach ($record_tblist as $key => $val){

            $dbname = $record_tblist[$key];
            $rdbname = $record_rtblist[$key];

            $sql = "select price,count,direction,time from {$dbname} ORDER BY time DESC limit 0,".self::MARKET_RECORD_COUNT."";

            $res = $coro_mysql->query($sql);

            if(is_array($res)){

                //$res_all = floatFormatList(array_reverse($res));
                $res_all = array_reverse($res);
                $jr_all = json_encode($res_all);
                $redis_key = $rdbname;
                $coro_redis->set($redis_key,$jr_all);
                $this->price_last[$key] = $res?$res[count($res)-1]["price"]:0;
                ee("[-].导入redis成功.(".$redis_key.").");
            }else{
                ee("[+].导入redis失败.".$redis_key.")");
            }

        }

        //更新交易市场价格和锁
        foreach ($this->market_arr as $key => $val){

            //更新交易区价格
            $mid = $val["ta_id"];
            $this->async_init_redis_24price_tick($mid);

            //更新锁(交易记录)
            $md5 = $coro_redis->get(self::RDS_KEY_PLOCK_RECORD_MD5.":".$mid);
            $this->record_arr_md5[$mid] = $md5;
            $list = $coro_redis->get(self::RDS_KEY_PLOCK_RECORD_LIST.":".$mid);
            $this->record_arr_list[$mid] = json_decode($list,true);

            //委托记录买
            $md5 = $coro_redis->get(self::RDS_KEY_PLOCK_PUTUP_BUY_MD5.":".$mid);
            $this->putup_arr_md5["buy"][$mid] = $md5;
            $list = $coro_redis->get(self::RDS_KEY_PLOCK_PUTUP_BUY_LIST.":".$mid);
            $this->putup_arr_list["buy"][$mid] = json_decode($list,true);

            //委托记录卖
            $md5 = $coro_redis->get(self::RDS_KEY_PLOCK_PUTUP_SELL_MD5.":".$mid);
            $this->putup_arr_md5["sell"][$mid] = $md5;
            $list = $coro_redis->get(self::RDS_KEY_PLOCK_PUTUP_SELL_LIST.":".$mid);
            $this->putup_arr_list["sell"][$mid] = json_decode($list,true);

        }

        //关闭redis和mysql
        $coro_redis->close();
        $coro_mysql->close();


        ee("[-].导入redis完成.");


    }





    /**
     * @param $market_id 交易市场id
     * @param $data //推送给客户端的数据
     */
    public function pushDataToAllClint($market_id,$data,$type,$fd=0,$msg="ok."){



        $rData["type"] = $type;
        $rData["market_id"] = $market_id;
        $rData["data"] = $data;
        $rData["code"] = 1;
        $rData["msg"] = $msg;

        $rDataJson = json_encode($rData);

        if($fd){
            $this->ws->push($fd, $rDataJson);
            ee("push client [".$fd."] message.");
        }else{


            //推送所有链接
            /*foreach($this->ws->connections as $fd) {
                $this->ws->push($fd, $rDataJson);
                ee("push client [".$fd."] message.");
            }*/


            //推送给指定交易市场的客户端

            $this->pushDataToMarket($market_id,$rDataJson);

        }


    }




    //推送
    public  function  pushDataToMarket233($market_id,$rDataJson){

        $client = new swoole_redis;
        $client->__construct($options = [ 'password' => self::RDS_PWD, ]);
        $client->connect(self::RDS_HOST, self::RDS_PORT, function (swoole_redis $client, $result) use ($market_id,$rDataJson) {

            if ($result === false) {return ee("can not connect redis server.");}

            $client->get(self::RDS_KEY_TRADE_AREA, function (swoole_redis $client, $result) use($market_id,$rDataJson) {



                if(isJson($result)) {
                    $myarr = json_decode($result, true);

                    if(count($myarr) > 0) {

                        if(array_key_exists($market_id,$myarr)) {

                            $market_fds = $myarr[$market_id];
                            foreach ($market_fds as $key => $val) {
                                $fd = $key;
                                if ($fd > 0) $this->ws->push($fd, $rDataJson);
                            }
                        }
                    }

                    $client->close();

                }

            });
        });




    }









    /**
     * [+] 验证相关，检查mysql，或redis是否存在(在更新或查询的时候需要)
     */



    /**
     * @param $market_id 交易市场id
     * @param $type 表类型
     */
    public  function checkTable($market_id,$type){

        if(in_array($type,["kline","record","putup"]) || $market_id){


            if($type === "kline"){

                $table = "swoole_".$type."_".$market_id;

                $sql = "CREATE TABLE IF NOT EXISTS `".$table."`  (
                          `kl_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'k线图主键id',
                          `trade_time` varchar(16) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0' COMMENT 'k线图时间戳',
                          `trade_price` decimal(20, 10) UNSIGNED NOT NULL DEFAULT 0.000000 COMMENT '交易价格',
                          `trade_count` decimal(10, 5) UNSIGNED NOT NULL DEFAULT 0.00000 COMMENT '购买数量',
                          `order_id` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0',
                          PRIMARY KEY (`kl_id`) USING BTREE
                        ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;";

            }


            if($type === "record"){

                $table = "swoole_".$type."_".$market_id;

                $sql = "CREATE TABLE IF NOT EXISTS `".$table."`  (
                          `rc_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '\r\n',
                          `price` decimal(20, 10) UNSIGNED NOT NULL DEFAULT 0.00000000,
                          `direction` int(1) NOT NULL DEFAULT 0,
                          `time` varchar(16) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0',
                          `count` decimal(10, 5) UNSIGNED NOT NULL DEFAULT 0.00000 COMMENT '购买数量',
                          `order_id` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0',
                          PRIMARY KEY (`rc_id`) USING BTREE
                        ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;";


            }



            if($type === "putup"){

                $table = "swoole_".$type."_".$market_id;

                $sql = "CREATE TABLE IF NOT EXISTS `".$table."`  (
                          `pu_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                          `price` decimal(20, 10) UNSIGNED NOT NULL DEFAULT 0.000000,
                          `count` decimal(10, 5) UNSIGNED NOT NULL DEFAULT 0.00000,
                          `direction` int(1) UNSIGNED NOT NULL DEFAULT 0,
                          `time` varchar(16) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0',
                          `status` int(1) UNSIGNED NOT NULL DEFAULT 0,
                          `order_id` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0',
                          PRIMARY KEY (`pu_id`) USING BTREE
                        ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;";


            }

            $this->async_update_mysql_tick($sql,"检查或创建{$table}表");

            //$this->async_mysql_update($market_id,$type,$sql,$table,"check");

        }else{

            ee("[+].创建不存在表失败.{$market_id},{$type}");

        }


    }






    /**
     * @param $market_id  交易市场id
     * @param $type 更新类型
     * @param $key
     * @param $val
     */
    public  function  async_publish_lpush($redis_key,$redis_val){

        $client = new swoole_redis;
        $client->__construct($options = [ 'password' => self::RDS_PWD, ]);
        $client->connect(self::RDS_HOST, self::RDS_PORT, function (swoole_redis $client, $result) use ($redis_key,$redis_val) {

            if ($result === false) {return ee("can not connect redis server.");}

            $client->select(self::RDS_DB_NO, function (swoole_redis $client, $result) use ($redis_key,$redis_val) {

                $client->llen($redis_key, function (swoole_redis $client, $length) use ($redis_key,$redis_val) {

                    $client->lpush($redis_key,$redis_val, function (swoole_redis $client, $result) use ($redis_key,$redis_val,$length) {

                        ee("[-].收到广播后加入队列成功.");

                        if($length >= self::RDS_PULISH_COUNT){

                            $client->rpop($redis_key, function (swoole_redis $client, $result) use ($redis_key,$redis_val) {
                                $client->close();
                            });

                        }else{
                            $client->close();
                        }

                    });

                });


            });


        });
    }



    /**异步推送到客户端
     * @param $market_id    交易市场id
     * @param $type 类型
     * @param $redis_key    键
     * @param string $action    操作
     * @param string $timeT  时间分辨率
     */
    public  function  async_redis_push($market_id,$type,$redis_key,$action="undefined",$timeT="1min",$fd,$argv=0){
        $client = new swoole_redis;
        $client->__construct($options = [ 'password' => self::RDS_PWD, ]);
        $client->connect(self::RDS_HOST, self::RDS_PORT, function (swoole_redis $client, $result) use ($market_id,$type,$redis_key,$action,$timeT,$fd,$argv) {

            if ($result === false) {return ee("can not connect redis server.");}

            $client->select(self::RDS_DB_NO, function (swoole_redis $client, $result) use($market_id,$type,$redis_key,$action,$timeT,$fd,$argv) {

                $client->get($redis_key, function (swoole_redis $client, $result) use ($market_id, $type, $redis_key, $action, $timeT, $fd, $argv) {

                    //var_dump($result);
                    //new
                    $myarr = json_decode($result, true);

                    if ($type === "kline") {
                        $arrData = getFormatKline1($myarr);
                    } else if ($type === "record") {
                        $arrData = getFormatRecord1($myarr);
                    } else if ($type === "putup") {
                        $arrData = getFormatPutup1($myarr);
                    } else if ($type === "trade_area") {

                        $exp = $argv;

                        if ($exp) {
                            $arrData = getTaByExp($myarr, $exp);
                            $arrData = getFormatTradeArea1($arrData);
                        } else {
                            $arrData = [];
                        }

                    } else {
                        $arrData = $myarr;
                    }

                    $client->close();


                    $this->pushDataToAllClint($market_id, $arrData, $type, $fd);


                });
            });
        });
    }





    /*
     * 异步绑定交易市场
     */
    /**
     * @param $fd 客户端id
     * @param $market_id 交易市场id
     */

    public  function  async_redis_market_band($fd,$market_id){


        //去除所有的
        foreach ($this->market_arr as $key => $val) {

            if (is_array($this->market_arr[$key]["fds"])) {
                $this->market_arr[$key]["fds"] = delValInArr($fd, $this->market_arr[$key]["fds"]);
            }

        }

        //加入
        foreach ($this->market_arr as $key => $val) {
            if ($this->market_arr[$key]["ta_id"] == $market_id) {
                $this->market_arr[$key]["fds"][] = $fd;
            }
        }

        //删除’所有的‘
        if(isset($this->fds[$fd])) unset($this->fds[$fd]);

        $rDataJson = enJson("", 1, "market [{$market_id}] band successfull..");
        return $this->ws->push($fd, $rDataJson);



    }




    /*
 * 异步解绑交易市场
 */
    /**
     * @param $fd 客户端id
     * @param $market_id 交易市场id
     */

    public  function  async_redis_market_unband($fd){

        //去除所有的
        foreach ($this->market_arr as $key => $val) {

            if (is_array($this->market_arr[$key]["fds"])) {

                $this->market_arr[$key]["fds"] = delValInArr($fd, $this->market_arr[$key]["fds"]);

            }

        }

        if(isset($this->fds[$fd])) unset($this->fds[$fd]);

    }


    /** 异步初始化redis
     * @param $mid
     * @param $lists
     */
    public  function async_init_redis_24price_tick($mid){

        $client = new swoole_redis;
        $client->__construct($options = [ 'password' => self::RDS_PWD, ]);

        $client->connect(self::RDS_HOST, self::RDS_PORT, function (swoole_redis $client, $result) use ($mid) {

            if ($result === false) {return ee("can not connect redis server.");}

            $client->select(self::RDS_DB_NO, function (swoole_redis $client, $result) use($mid) {

                $kline_redis_key = self::RDS_KEY_CLIENT_KLINE.":{$mid}:1min";

                $client->get($kline_redis_key, function (swoole_redis $client, $kline_result) use ($mid) {

                    if (isJson($kline_result)) {


                        $kline_arr = json_decode($kline_result,true);


                        //获取交易市场常规数据
                        foreach ($this->market_arr as $takey => $taval){

                            $market_id = $taval["ta_id"];

                            //if(!$market_id) continue;

                            $market_name = $taval["name"];

                            if($mid == $market_id){

                                //时间参数
                                $time  = time(); //当前时间戳 1539054071
                                $time0 = $time - (($time + 28800) % 86400); //0点的时间戳
                                $time24 = $time - 86400; //24小时前的的时间戳

                                //默认参数
                                $price = 0;
                                $high = 0;
                                $low = 99999999999999999;
                                $per = 0;
                                $total24 = 0;
                                $open = 0;
                                $open_lock = 0;

                                foreach ($kline_arr as $kkey => $kval){

                                    $ktime = (int)$kval["time"];

                                    if($ktime >= $time24 && $ktime <= $time){  //时间范围，大范围

                                        $price = $kval["close"];  //刷新最新
                                        $total24 += (float)$kval["volume"]; //24小时成交量

                                        if(!$open_lock) $open = $price;

                                        if($ktime >= $time0){  //时间范围，小范围

                                            if($price < $low) $low = $price;
                                            if($price > $high) $high = $price;
                                            $open_lock = 1;

                                        }

                                    }

                                    $this->market_arr[$takey]['price'] = $price;
                                    $this->market_arr[$takey]['high'] = $high;
                                    $this->market_arr[$takey]['low'] = $low;
                                    $this->market_arr[$takey]['per'] = $per;
                                    $this->market_arr[$takey]['total24'] = $total24;
                                    $this->market_arr[$takey]['open'] = $open;

                                }

                            }
                        }


                    } else {

                        if($kline_result){
                            ee("[+].更新交易市场价格的时候，获取到的k线路图信息不是json.");
                        }else{
                            ee("[+].更新交易市场价格的时候，获取的k线图数据为空.");
                        }

                        var_dump($kline_result);

                    }


                });
            });
        });








        foreach ($this->market_arr as $key => $val){

            $market_id = $val["ta_id"];

            if($market_id == $mid){




            }


        }

        //var_dump($lists);



    }








    /**
     * [+] 获取同步mysql、redis对象
     */


    /**
     * 获取同步mysql对象
     */
    public  function getCoroMysql(){
        //协程mysql
        $mysql = new Swoole\Coroutine\MySQL();
        $mysql->connect([
            'host' => self::DB_HOST,
            'port' => self::DB_PORT,
            'user' => self::DB_USER,
            'password' => self::DB_PASSWORD,
            'database' => self::DB_DATABASE,
            'charset' => self::DB_CHARSET, //指定字符集
            'timeout' => self::DB_TIMEOUT,  // 可选：连接超时时间（非查询超时时间），默认为SW_MYSQL_CONNECT_TIMEOUT（1.0）
        ]);

        return $mysql;

    }



    /**
     * 获取同步redis对象
     */
    public  function  getCoroRedis(){

        //协程redis
        $redis = new Swoole\Coroutine\Redis();
        $redis->connect(self::RDS_HOST, self::RDS_PORT);
        $redis->auth(self::RDS_PWD);
        $redis->select(self::RDS_DB_NO);

        return $redis;


    }

}

new Ws();




/**
 * 1.open为0
 * 2.挂单记录，委托记录，从redis里面那，需要有个userid
 * 3.
 */



/**
 * 字段补齐
 * 委托记录加user_id
 * 成交记录有手续废
 * 查询效率比较低
 * 交易对如何变化
 * 挂单流水号
 *
 */




