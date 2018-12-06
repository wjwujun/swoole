    <?php
    /*
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
                1=>"close",
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

    ];*/
    use redis\redis;



    /*异常信息重启*/
    function restart($mes){
        $redis = Redis::instance();
        $batch=$redis->hGet('ore_batch_route',$mes['routeruuid']);//路由批次
        $temp_info=$redis->hGet('ore_batch',$batch); //路由批次信息
        $info=json_decode($temp_info,true);

        if($mes['poolsta']['fan']!=$info['poolsta']['fan']) return true;
        if($mes['poolsta']['pll']!=$info['poolsta']['pll']) return true;
        if($mes['poolsta']['core'][0]!=$info['poolsta']['core'][0]) return true;
        if($mes['poolsta']['core'][1]!=['poolsta']['core'][1]) return true;
        if($mes['compute']['5s']!=['compute']['5s']) return true;

        foreach($mes['poolsta']['temperature'] as $k=>$v){
            if($v!=$info['poolsta']['temperature'][$k]) return true;
        }
        return false;
    }


    /*aes加密*/
    function encrypt($data){
        //机密后字符串
        $str=openssl_encrypt($data, 'AES-128-ECB', 'rNFRkk3vuCKcZgt5', 1, '');

        //拼接5位数
        $zero='';
        for($i=0;$i<(5-strlen(strlen($data)));$i++){
            $zero=$zero.'0';
        }
        return  $zero.strlen($data).$str;
    }

    /*aes解密*/
    function decrypt($data){
        return openssl_decrypt($data, 'AES-128-ECB', 'rNFRkk3vuCKcZgt5', 1, '');
    }

    /*数据库查询*/
    function getMysql(){
        // 数据库服务器地址
        $strDbHost = '122.225.58.124';
        // 数据库用户
        $strDbUser = 'sa';
        // 数据库密码
        $strDbPass = 'AUX_gehua@123P@ssw0rd2017';
        // 数据库名称
        $strDbName = 'RouterApp';
        // 连接数据库的字符串定义
        $strDsn = "sqlsrv:Server=$strDbHost;Database=$strDbName;";
        // 生成pdo对象
        $objDB = new PDO($strDsn, $strDbUser, $strDbPass);
        //SYS_URLMac 路由表
        //UP_Batch   批次
        foreach ($objDB->query('SELECT * FROM dbo.UP_Batch') as $row) {
            var_dump($row);
        }
    }


    /*返回数据结构*/
    function returnData($data=[],$reload=0,$restart=0){
        $data=[
            "data"=>$data,
            "reload"=>$reload,  //机器重启,0不重启，1是重启
            "restart"=>$restart,  //程序重启,0不重启，1是重启
        ];

        return json_encode($data);
    }


    /*判断重启是否成功*/
    function restartStatus($old_data){
        if($old_data['deal_with']==1||$old_data['deal_with']==2){
            if(time()-$old_data['last_unix_time']<40){
                return true;
            }
        }
        return false;
    }

    /*判断修改是否成功*/
    function updateStatus($update_data,$mes){
        foreach($update_data['poolinfo'] as $k=>$v){
           if($v!=$mes['poolinfo'][$k]) return true;
        }
        if($update_data['poolsta']['pll']!=$mes['poolsta']['pll']) return true;

        return false;
    }

    /*重启日志记录*/
    function restartLog($data){
        file_put_contents('log/restart/'.date('Y-m-d').'_restart.txt',date('Y-m-d H:i:s').'--'.$data.PHP_EOL,FILE_APPEND);
    }

    /*修改日志记录*/
    function updateLog($data){
        file_put_contents('log/update/'.date('Y-m-d').'_reload.txt',date('Y-m-d H:i:s').'--'.$data.PHP_EOL,FILE_APPEND);
    }



