    <?php




    /*异常信息重启*/
    function restart($mes,$info){

        if($mes['poolinfo']['poolurl0']!=$info['poolinfo']['poolurl0']) return '1';
        if($mes['poolinfo']['poolurl1']!=$info['poolinfo']['poolurl1']) return '11';
        if($mes['poolinfo']['poolurl2']!=$info['poolinfo']['poolurl2'])return '12';
        if($mes['poolinfo']['poolusr0']!=$info['poolinfo']['poolusr0'])return '13';
        if($mes['poolinfo']['poolusr1']!=$info['poolinfo']['poolusr1'])return '14';
        if($mes['poolinfo']['poolusr2']!=$info['poolinfo']['poolusr2'])return '15';
        if($mes['poolinfo']['poolpasswd0']!=$info['poolinfo']['poolpasswd0'])return '16';
        if($mes['poolinfo']['poolpasswd1']!=$info['poolinfo']['poolpasswd1'])return '17';
        if($mes['poolinfo']['poolpasswd2']!=$info['poolinfo']['poolpasswd2'])return '18';
        if($mes['poolsta']['pll']!=$info['poolsta']['pll']) return '3';


        /*if($mes['compute']['5s']==0) return '5';
        if($mes['poolsta']['fan']<=2000) return '2';
        if($mes['poolsta']['fan']>=4000) return '22';
        if($mes['poolsta']['core'][0]=='close'||$mes['poolsta']['core'][1]=='close') return '4';
        foreach($mes['poolsta']['temperature'] as $k=>$v){
            if($v>80) return '6';
        }*/
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
        //查询数据
        $select_sql='SELECT * FROM dbo.UP_Batch';
        //添加数据
        $add_sql='insert into  dbo.UP_Batch (id,userName,sex,age) values(1,2,3,4)';
        //修改数据
        $update_sql='update dbo.UP_Batch set age=18  where id =1';
        //删除数据
        $del_sql='delete from  dbo.UP_Batch where id =1';

        $objDB->query($add_sql);

    }


    /*返回数据结构*/
    function returnData($data=[],$reload=0,$restart=0){
        $data=[
            "data"=>$data,
            "reload"=>$reload,  //程序重启,0不重启，1是重启
            "restart"=>$restart,  //机器重启,0不重启，1是重启
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



