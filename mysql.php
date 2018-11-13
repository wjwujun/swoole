<?php

    /*
     *mysql
     * */
    class  Mysql{
        public $dbSource='';
        public $dbConfig=[];
        public function __construct()
        {
            $this->dbSource=new swoole_mysql;
            $this->dbConfig=[
                'host' => '122.225.58.118',
                'port' => 3306,
                'user' => 'root',
                'password' => 'gehua1108',
                'database' => 'gh_market',
                'charset' => 'utf8', //指定字符集
            ];
        }

        public function update(){}
        public function add(){}

        public function execute($id,$username){

            $this->dbSource->connect($this->dbConfig,function($db,$result) use($id,$username){
                if($result==false){
                    var_dump($db->connect_error);
                    //todo
                };
                $sql="update  aa set `name`=".$username." where id=".$id;
                $db->query($sql,function($db,$result){

                   if($result===false){
                       var_dump($db->error);

                   }elseif($result===true){         //and update delete
                       var_dump($result);

                   }else{
                       print_r($result);
                   }

                   $db->close();

               });
                /*
                 $sql="select * from aa where id=1;";
                 $db->query($sql,function($db,$result){

                    if($result===false){
                        var_dump(111);

                    }elseif($result===true){         //and update delete
                        var_dump(222);

                    }else{
                        print_r($result);
                    }

                    $db->close();

                });*/

            });
            return true;
        }

    }

    $obj= new Mysql();
    $re=$obj->execute(1,'3333');
    var_dump($re).PHP_EOL;
