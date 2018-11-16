<?php

    //创建内存表
    $table=new swoole_table(1024);

    //内存表增加一行
    $table->column('id',$table::TYPE_INT,4);
    $table->column('name',$table::TYPE_STRING,64);
    $table->column('age',$table::TYPE_INT,3);

    $table->create();

    $table->set('haha',['id'=>1,'name'=>"大哥",'age'=>20]);
    //另一种方案
    $table['hehe']=[
        'id'=>2,
        'name'=>"二哥",
        'age'=>10
    ];
    //自增操作
    $table->incr('hehe',age,20);
    //自减操作
    $table->decr('hehe',age,2);

    $re=$table->get('haha');
    $re2=$table['hehe'];
    //var_dump($re);
    var_dump($re2);





