    <?php
class SqlServer{

    private $objDB=null;

    public function __construct()
    {
        if($this->objDB==null){
            $strDbHost = '122.225.58.124';  // 数据库服务器地址
            $strDbUser = 'sa';  // 数据库用户
            $strDbPass = 'AUX_gehua@123P@ssw0rd2017';  // 数据库密码
            $strDbName = 'RouterApp';       // 数据库名称
            $strDsn = "sqlsrv:Server=$strDbHost;Database=$strDbName;";  // 连接数据库的字符串定义
            $objDB = new PDO($strDsn, $strDbUser, $strDbPass);   // 生成pdo对象
            $this->objDB=$objDB;
        }
    }

    //查询数据单条记录
    function find($table,$where){
        //SYS_URLMac 路由表dbo.UP_Batch';
        $find_sql="SELECT * FROM  UP_Batch where UB_ID=15;";
        return $this->objDB->query($find_sql);
    }

    //查询多条记录
    function select($table,$where){
        //SYS_URLMac 路由表dbo.UP_Batch';
        //$select_sql='SELECT * FROM '.$table.' where '.$where;
        $select_sql='SELECT * FROM dbo.UP_Batch';
        return $this->objDB->query($select_sql);
    }


    //添加数据
    function add($table){
        $add_sql='insert into  '.$table.'(id,userName,sex,age) values(1,2,3,4)';
        return $this->objDB->query($add_sql);
    }


    //修改数据
    function edit($table,$where){
        $update_sql='update '.$table.' set '.$where.'  where id =1';
        return $this->objDB->query($update_sql);
    }

    //删除数据
    function del($table,$where){
        $del_sql='delete from  '.$table.' where '.$where;
        return  $this->objDB->query($del_sql);
    }

}


