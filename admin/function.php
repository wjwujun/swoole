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

/*异常信息重启*/
function reload($mes){
    if($mes['poolsta']['fan']>4000)return true;
    if($mes['poolsta']['pll']<500||$mes['poolsta']['pll']>700)return true;
    if($mes['poolsta']['core'][0]=='close'||$mes['poolsta']['core'][1]=='close') return true;
    if($mes['compute']['5s']==0||$mes['compute']['60s']==0||$mes['compute']['avg']==0)return true;
    foreach($mes['poolsta']['temperature'] as $v){
        if($v>80) return true;
    }
    return false;
}
/*aes加密*/
function encrypt($data){
    return openssl_encrypt($data, 'AES-128-ECB', 'rNFRkk3vuCKcZ5', 0, '');
}

/*aes解密*/
function decrypt($data){
    return openssl_encrypt($data, 'AES-128-ECB', 'rNFRkk3vuCKcZ5', 0, '');
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

