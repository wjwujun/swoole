<?php





//var dump 简写
function dd($data){
    var_dump($data);
    sleep(33);
}







//日志答应与记录
function ee($msg){
	$log = date("Y-m-d H:i:s",time())."  ".$msg.PHP_EOL;
    echo $log;
    $log_file = __DIR__.'/log/server/'.date("Ym").date("d")."_access.log";
    
    //var_dump($log_file);
    swoole_async_writefile($log_file, $log, function($filename){}, FILE_APPEND);

}


//封装json
function enJson($data="",$code=0,$msg=""){
    return json_encode(["data"=>$data,"code"=>$code,"msg"=>$msg]);
}




//时间分组相关
function getTimeGroupDataArr($to){



    return  [
        "1min"=>[
            "form"=>$to-86400, //60*60*24=86400
            "second"=>60,
            "day_ca"=>0
        ],
        "5min"=>[
            "form"=>$to-432000, //60*60*24*5
            "second"=>60*5,
            "day_ca"=>0
        ],
        "15min"=>[
            "form"=>$to-1296000,//60*60*24*15
            "second"=>60*15,
            "day_ca"=>0
        ],
        "30min"=>[
            "form"=>$to-2592000,//60*60*24*30
            "second"=>60*30,
            "day_ca"=>0
        ],
        "60min"=>[
            "form"=>$to-5184000,//60*60*24*30*2
            "second"=>60*60,
            "day_ca"=>0
        ],
        "1D"=>[
            "form"=>$to-31536000,//60*60*24*365
            "second"=>60*60*24,
            "day_ca"=>28800
        ],
        "5D"=>[
            "form"=>$to-157680000,//60*60*24*365*5
            "second"=>60*60*24*5,
            "day_ca"=>28800
        ],
        "1W"=>[
            "form"=>$to-220752000,//60*60*24*365*7
            "second"=>60*60*24*7,
            "day_ca"=>28800
        ],
        "1M"=>[
            "form"=>$to-220752000,//60*60*24*365*7
            "second"=>60*60*24*30,
            "day_ca"=>28800
        ],
    ];





}







//获取k线图sql语句
function getKlineSql($table,$time){


	//允许的时间分辨率
	$timeArr = ["1min","5min","15min","30min","60min","1D","5D","1W","1M"];
	if(!in_array($time, $timeArr))  return JJ(0,"","分辨率错误,只能输入以下(".implode(",", $timeArr).")几种.");


	//开始时间和结束时间
	$to = time();


    $otherArr = getTimeGroupDataArr($to);


	$from = $otherArr[$time]["form"];
	$second = $otherArr[$time]["second"];
	$day_ca = $otherArr[$time]["day_ca"];




	$sql = "SELECT
		unix_timestamp ( date_sub(FROM_UNIXTIME(b.`trade_time`),interval( (b.`trade_time` + ".$day_ca.") % ".$second.") second) ) AS time,
		truncate( MAX(b.trade_price),8 ) AS high,
		truncate( MIN(b.trade_price),8 ) AS low,
		truncate( b.trade_price,8 ) AS close,
		sum(trade_count) AS volume
	FROM 
		(select * from ".$table." as a where a.trade_time > ".$from." and a.trade_time <= ".$to." ORDER BY a.trade_time DESC) as b
	GROUP BY time";


	return $sql;



}


/**
 * 获取k线图数据
 * @param $listk线路图数据
 */
function getFormatKline2($list,$last=0){


    $list_count = count($list);

    if($last && $list_count > 1){
        $last_index_1 = $list_count -1;
        $last_index_2 = $list_count -2;
        $open = (float)$list[$last_index_2]["close"];
        $pList[0] = $list[$last_index_1];
    }else{
        $open = 0;
        $pList = $list;
    }

    for($i=0;$i<count($pList);$i++){
        $nList["o"][$i] = $open;
        $nList["c"][$i] = (float)$pList[$i]["close"];
        $nList["h"][$i] = (float)$pList[$i]["high"];
        $nList["l"][$i] = (float)$pList[$i]["low"];
        $nList["v"][$i] = (float)$pList[$i]["volume"];
        $nList["t"][$i] = (int)$pList[$i]["time"] * 1000;
        $open = $pList[$i]["close"];

    }


    return $nList;



}



//格式化k线图数据
function getFormatKline1($list,$last=false,$pbit=2,$abit=2){

    $list_count = count($list);

    if(!$list_count) return [];

    if($last && $list_count > 1){
        $last_index_1 = $list_count -1;
        $last_index_2 = $list_count -2;
        $open = $list[$last_index_2]["close"];
        $pList[0] = $list[$last_index_1];
    }else{
        $open = 0;
        $pList = $list;
    }

    for($i=0;$i<count($pList);$i++){
        $nList[$i]["open"] = decimal_format($open,$pbit);
        $nList[$i]["close"] = decimal_format($pList[$i]["close"],$pbit);
        $nList[$i]["high"] = decimal_format($pList[$i]["high"],$pbit);
        $nList[$i]["low"] = decimal_format($pList[$i]["low"],$pbit);
        $nList[$i]["volume"] =decimal_format($pList[$i]["volume"],$abit);
        $nList[$i]["time"] = (int)$pList[$i]["time"] * 1000;
        $open = $pList[$i]["close"];
    }

    return $nList;

}




//格式化交易记录
function getFormatRecord1($list,$last=false,$pbit=2,$abit=2){


    foreach ($list as $key => $val){


        $list[$key]["price"] = decimal_format($list[$key]["price"],$pbit);
        $list[$key]["count"] = decimal_format($list[$key]["count"],$abit);
        $list[$key]["time"] = $list[$key]["time"]*1000;
        $list[$key]["direction"] = (int)$list[$key]["direction"];;

    }

    if($last){
        return $list[count($list)-1];
    }else{
        return $list;
    }
}




//格式化委托记录
function getFormatPutup1($list,$last=false,$pbit=2,$abit=2){

    foreach ($list as $key => $val){

        $list[$key]["price"] = decimal_format($list[$key]["price"],$pbit);
        $list[$key]["count"] = decimal_format($list[$key]["count"],$abit);
        $list[$key]["time"] = (int)$list[$key]["time"]*1000;

    }

    if($last){
        return $list[count($list)-1];
    }else{
        return $list;
    }

}



//浮点格式化
function floatFormatList($list){


    foreach ($list as $key => $val){

        foreach ( $val as $ckey => $cval){
            $nlist[$key][$ckey] = (float)$cval;
        }

    }

    return $nlist;
}















//返回信息
function JJ($code=0,$data="",$msg="",$noData=true){
	return json(["code"=>$code,"data"=>$data,"msg"=>$msg,"noData"=>$noData]);
}


//判断是不是json
function isJson($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}



//获取最近的时间
function getNearTime($time,$timeT){

    $timeGroupArr = getTimeGroupDataArr($time);

    $seconds = $timeGroupArr[$timeT]["second"];
    $day_ca = $timeGroupArr[$timeT]["day_ca"];

    $yu  = ($time + $day_ca) % $seconds;

    return $time - $yu;


}




//封装json_encode加判断
function jsonEncode($arr){

    if(is_array($arr)){
        return json_encode($arr);
    }else {
        return json_encode([]);
    }
}


//封装json_decode加判断
function jsonDecode($string){
    if(isJson($string)){
        return json_decode($string,true);
    }else{
        return [];
    }

}



function getMarketListBy($arr,$type){

    $nArr = [];

    if($type == "market" && count($arr) > 0){

        foreach ($arr as $key => $val) {
            if(count($val) > 0){
                $nArr[$key] = $val;
            }else{
            }

        }

    }

    if($type == "trade_area" && count($arr) > 0){

        foreach ($arr as $key => $val) {

            $nArr[$val["ta_id"]] = $val["fds"];

        }

    }

    return $nArr;
}




function getFdsListBy($arr,$type){

    $nArr = [];

    if(is_array($arr)){

        if($type == "market" && count($arr) > 0){
            foreach ($arr as $key => $val) {
                $nArr[$key] = $val;
            }
        }

    }


    return $nArr;
}





function getFdsListAll($market_arr){

    $fds = [0];

    foreach ($market_arr as $key => $val){

        if(is_array($val["fds"])){

            $fds = array_merge($fds,$val["fds"]);

        }

    }

    return array_unique($fds);

}



function getMidByFd($market,$fd){


    foreach ($market as $key => $val){

        $market_id = $val["ta_id"];
        $fds_arr = $val["fds"];

        if(is_array($fds_arr) && in_array($fd,$fds_arr)) return $market_id;

    }

    return 0;


}


/**
 * @param $string
 * @param $chart
 */
function isChartBegin($string,$chart){

    if( strpos($string, $chart) === 0){
        return true;
    }else{
        return false;
    }

}


function isChartEnd($string,$chart){


    if(strrchr($string,$chart) == $chart){
        return true;
    }else{
        return false;
    }
}






function getTaByExp($arr,$exp){

    $nArr = [];

    var_dump($arr);




    $all = $exp == "*" && strlen($exp) == 1;
    $left = isChartBegin($exp,"*") && strlen($exp) > 1;
    $right = isChartEnd($exp,"*") && strlen($exp) > 1;
    $many = strpos($exp, ",") > 0;


    //var_dump($all,$left,$right,$many);



    //查询所有
    if($all){

        $nArr = $arr;

    }


    if($left){  //查询结尾（开头*）  *btc

        $coin = str_replace("*","",$exp);

        foreach ($arr as $key => $val){

            $coin_redis = $val["name"];
            if(isChartEnd($coin_redis,$coin)){
                $nArr[] = $val;
            }

        }

    }else if($right){    //查询开头（结尾*）  btc*


        $coin = str_replace("*","",$exp);

        foreach ($arr as $key => $val){

            $coin_redis = $val["name"];
            if(isChartBegin($coin_redis,$coin)){
                $nArr[] = $val;
            }

        }

    }else if($many){  //查询多个（，）

        $coin_arr = explode(",",$exp);

        foreach ($arr as $key => $val){

            $coin_redis = $val["name"];

            if(in_array($coin_redis,$coin_arr)){
                $nArr[] = $val;
            }

        }

    }



    return $nArr;


}



/**
 * 格式化涨幅
 * @param  [type]  $list [description]
 * @param  boolean $last [description]
 * @return [type]        [description]
 */
/**
 * 格式化涨幅
 * @param  [type]  $list [description]
 * @param  boolean $last [description]
 * @return [type]        [description]
 */
function getFormatTradeArea1($list,$last=false,$pbit=2,$abit=2){

    foreach ($list as $key => $val){

        unset($list[$key]["fds"]);
        unset($list[$key]["open"]);

        $list[$key]["per"] = decimal_format($list[$key]["per"],2);
        $list[$key]["total24"] = decimal_format($list[$key]["total24"],$pbit);
        $list[$key]["high"] = decimal_format($list[$key]["high"],$pbit);
        $list[$key]["low"] = decimal_format($list[$key]["low"],$pbit);


        $list[$key]["g"] = explode("_",$list[$key]["name"])[1];
        $list[$key]["n"] = explode("_",$list[$key]["name"])[0];

        $list[$key]["name"] = str_replace("_","/",$list[$key]["name"]);

        $nList[] = $list[$key];

    }

    if($last){
        return $list[count($list)];
    }else{

        return $nList;

    }

}


/**
 * @param $price 价格
 */
function getFormatPrcie($price){

    $int_price = (int)$price;

    if($int_price > 2){
        $np = sprintf("%.2f",$price);
    }else{
        $np = sprintf("%.8f",$price);
    }


    //echo $np;
    return $np;

}


/** 删除数组中的指定元素
 * @param $val
 * @param $arr
 */
function delValInArr($val,$arr){

    foreach ($arr as $k => $v){
        if($v == $val) unset($arr[$k]);
    }

    return $arr;

}


/** 比较交易市场
 * @param $new
 * @param $old
 * @return bool
 */
function compareTradeArea($new,$old){


    foreach ($new as $key => $val){
        unset($new[$key]["fds"]);
    }

    foreach ($old as $key => $val){
        unset($old[$key]["fds"]);
    }

    if($new == $old){
        return true;
    }else{
        return false;
    }


}



/**
 * 获取当前成交价格 币种/USDT
 */
function get_time_price($fromcoin,$tocoin = 'usdt',$market_arr,$USD_CNY){


    $fromcoin = strtoupper($fromcoin);
    $tocoin = strtoupper($tocoin);
    $coin = $fromcoin.'_'.$tocoin;


    //返回合适的数据
    foreach ($market_arr as $key=>$val) {

        if ($val["name"] == $coin){

            return $val['price']*$USD_CNY;
        }
    }

}


/**
 * 获取人民币价格
 */
function get_cny_price($market_arr){

    $USD_CNY = MyRedis::get('USD_CNY');

    foreach ($market_arr as $key=>$val){

        //if(!$val["ta_id"]) continue;

        $fromcoin_arr = explode("_",$val["name"]);
        $fromcoin = $fromcoin_arr[1];
        $market_arr[$key]["cny"] = get_time_price($fromcoin,"usdt",$market_arr,$USD_CNY);

    }


    return $market_arr;

}





/*
 * 获取测试价格数据
 */

function get_test_price($market_arr){

    foreach ($market_arr as $key => $val) {


        /*
        sdt_btc = 0.000019
        sdt_usdt = 0.13
        sdt_eth = 0.000607
        btc_usdt = 6742.93
        eth_usdt = 213.93
        eth_btc = 0.031726
        zec_btc = 0.0169659
        zec_usdt = 114.40
        zec_eth = 0.534754
        */

        if($val["name"] == "SDT_BTC") $market_arr[$key]["price"] = 0.000019;
        if($val["name"] == "SDT_USDT") $market_arr[$key]["price"] = 0.13;
        if($val["name"] == "SDT_ETH") $market_arr[$key]["price"] = 0.000607;
        if($val["name"] == "BTC_USDT") $market_arr[$key]["price"] = 6742.93;
        if($val["name"] == "ETH_USDT") $market_arr[$key]["price"] = 213.93;
        if($val["name"] == "ETH_BTC") $market_arr[$key]["price"] = 0.031726;
        if($val["name"] == "ZEC_BTC") $market_arr[$key]["price"] = 0.0169659;
        if($val["name"] == "ZEC_USDT") $market_arr[$key]["price"] = 114.40;
        if($val["name"] == "ZEC_ETH") $market_arr[$key]["price"] = 0.534754;


    }

    return $market_arr;

}


/**
 * 将redis中的委托记录转成前端需要的数据
 * @param $list
 * @return array
 */
function hash2putup($list){


    if($list){

        //{"type":"putup","market_id":1,"direction":1,"price":"490.0000000000","count":"100","time":"1540988297","order_id":"15409882975bd99d8955a1d3.55347200"}

        foreach ($list as $key => $val){
            $nList[$key]["price"] = sprintf("%.4f",$val["price"]);
            $nList[$key]["count"] = sprintf("%.4f",$val["decimal"]);
            $nList[$key]["time"] = $val["create_time"];

        }

    }else{
        $nList = [];
    }


    return $nList;

}


/**
 * 合并hash中的价格
 * @param $list
 * @return array
 */
function putup_price_merge($list,$pbit=2,$abit=2){

    $nList = [];
    $nnList = [];

    foreach ($list as $key => $value){

        $price = decimal_format($value["price"],$pbit);

        if(isset($nList[$price])){
            $nList[$price]["total"] = decimal_format($nList[$price]["total"] + $value["total"],$abit);
            $nList[$price]["decimal"] = decimal_format($nList[$price]["decimal"] + $value["decimal"],$abit);

        }else{
            $nList[$price] = $value;
        }

    }

    foreach ($nList as $key => $value){
        $nnList[] = $value;
    }

    return $nnList;

}


/**
 * 挂单记录带深度
 * @param $nList
 * @return array
 */
function putup_deep($nList){
    $arr_count = array_column($nList,"count");
    $total = array_sum($arr_count);

    foreach ($nList as $key => $value){

        $value["per"] = sprintf('%.2f', ($value["count"] / $total) );
        $nnList[] = $value;

    }

    return $nnList;
}


function putup2deep(){

    return [];

}



function decimal_format($number, $n, $isRepate = true, $type = 0){
    if ($type == 2) {//进1
        $p = pow(10, $n);
        $number = ceil($number * $p) / $p;
    } elseif ($type == 3) {//舍去
        $p = pow(10, $n);
        $number = floor($number * $p) / $p;
    } else {
        $p = pow(10, $n);
        $number = round($number * $p) / $p;
    }
    if ($isRepate == TRUE) {
        return sprintf('%.' . $n . 'f', $number);
    } else {
        if(stripos($number, "e") || stripos($number, "E")){ //处理科学计数法
            return strval(sctonum($number,$n));
        }else{
            return strval($number);
        }
    }
}



/**
 * 格式化挂单记录
 * @param  [type]  $list [description]
 * @param  boolean $last [description]
 * @return [type]        [description]
 */
function getFormatPutup2($list,$last=false,$pbit=2,$abit=2){

    foreach ($list as $key => $val){

        $list[$key]["price"] = decimal_format($list[$key]["price"],$pbit);
        $list[$key]["count"] = decimal_format($list[$key]["decimal"],$pbit);
        $list[$key]["time"] = (int)$list[$key]["create_time"]*1000;
        //$list[$key]["per"] = $list[$key]["per"];

    }

    if($last){
        return $list[count($list)-1];
    }else{
        return $list;

    }

}


/**
 * 格式化深度信息
 * @param $list
 */
function getFormatDeep($list,$pbit=2,$abit=2){

    if(!$list) return [];

    //$all_v = array_column($list,"decimal");
    //$total = array_sum($all_v);

    foreach ($list as $key => $val){

        $price = decimal_format($val["price"],$pbit);
        $count = decimal_format($val["decimal"],$abit);


        //$per = sprintf("%.2f",$count / $total);

        $nn["price"] = $price;
        $nn["count"] = $count;
        //$nn["time"] = $val["create_time"];

        $nnList[] = $nn;

    }

    return $nnList;

}



//交易区数据关联id
function attachTid($market_arr){

    foreach ($market_arr as $key => $val){
        $nList[$val["ta_id"]] = $val;
    }

    return $nList;

}
