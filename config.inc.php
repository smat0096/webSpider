<?php
  //爬虫配置信息
  $CONFIG = array(
        'baseDL' => './download',    //保存根目录
        'baseURL' => 'http://wiki.we7shop.cn/hc/kb/category/22414/', //根页面
        'replaceGlobal' => array("{人人}" => "奇睿", '{微擎}' => '奇睿'), //全局替换
        'replaceURL' => array( "{^\/}" => "http://wiki.we7shop.cn/"), //需抓取的链接替换,用于相对地址转绝对;
        'regTitle' => '{<title>([^<]+?)<\/title>}is', //匹配页面title ,用于设置层级目录
        'replaceTitle' => array( //页面title替换
            "{[\\\/\-\r\s\n\'\"\<\>\?\:\*\|]+}" => ' ',
            "{\s奇睿商城2\.0在线帮助系统\s奇睿商城在线帮助系统}is" => '',
            "{售前问题\s}is" => '',
        ),
        'ignoreURL' => [], //需要忽略抓取链接;
        //抓取规则
        'rule' => array(
            'type' => 'index',   //类型: 主页
            'charset' => 'UTF-8', //页面编码
            'subReg' => '{<a[^>]+?href\=\"([^\"]+?\/section\/[^\"]+?)\"[^>]*?>}is', //下一层级[纵向]
            'nextReg' => '{<a[^>]+?href\=\"(https:\/\/[^\"]+?)\"[^>]*?>下一页<\/a>}is', //下一页[横向]
            'nextLength' => 0, //[next递归深度]
            'sub' => array( //下一级
                'type' => 'list', //类型: 列表页
                'nextReg' => '{<a[^>]+?href\=\"([^\"]+?\/下一页\/[^\"]+?)\"[^>]*?>}is', //下一页[横向]
                'nextLength' => 3, //[next递归深度]
                'subReg' => '{<a[^>]+?href\=\"([^\"]+?\/article\/[^\"]+?)\"[^>]*?>}is',
                'sub' => array(
                    'type' => 'article', //类型: 文章页
                    'save' => array( //保存参数
                        array(
                            'name' => 'title',
                            'reg' => '{<header\sclass\=\"article\-header\">[^<]+?<h2>([^<]+?)<\/h2>}is'
                        ),
                        array(
                            'name' => 'content', //1. data的[属性名]
                            'reg' => '{<div\sclass\=\"article\-content\">(?!<\!--<footer\sclass\=\"article\-footer\")(.*?)<\!--<footer\sclass\=\"article\-footer\"}is', //2.匹配内容
                            'replace' => array( //3.替换内容
                                '{\s\=\"\/hc\/}' => ' =\"http://wiki.we7shop.cn/hc/',
                                '{<a\s+[^>]+?>.+?<\/a>}is' => '',
                                '{<img\s[^>]+?>}is' => '',
                                '{http\:\/\/s\.we7\.cc\/}is' => ''
                            ),
                            'ignore' => array( //4.忽略条件
                                '{^.{0,300}$}is'       //例如: 最小100字 //utf8中 汉字=3字节
                            )
                        )
                    ),  
                    /*'subReg' => '{<img[^>]+?src\=\"([^\"]+?\/fox\.kf5\.com\/[^\"]+?)\"[^>]*?>}is', /**/
                    'sub' => array(
                        'save' => true, 
                        'type' => 'image' //类型: 图片
                    )
                )
            )
        ),
        //数据库配置信息
        'DBhost' => array(
          'DB_TYPE' => 'mysql',
          'DB_HOST' => 'localhost',
          'DB_NAME' => 'qrshop',
          'DB_USER' => 'root',
          'DB_PASS' => 'root',
          'DB_CHARSET' => 'utf8'
        ),
        //数据库匹配栏目规则
        'DBmatch' => array(
            'typeid' => array(
                '{^一次性扫清售前问题$}' => 9,                        //文件夹名对应字段名
                '{^奇睿商城使用要求$}' => 10,
                '{^\[new\]\s更新详解$}' => 11,
                '{^新手快速入门专题$}' => 6,
                '{^奇睿商城常见问题$}' => 7,
                '{^(应用管理|应用授权管理)$}' => 12,
                '{^系统设置$}' => 3,
                '{^积分商城$}' => 4,
                '{^短信提醒$}' => 5,
                '{^整点秒杀$}' => 13,
                '/^.{6,50}$/is' => 14 //utf8中 汉字=3字节
            )
        ),
        //数据库写入配置
        'DBtable' => array(
            'dede_arctiny' => array(     //表名 第一个为id自增的主表
                'write' => array(          //需写入 字段名 键名:数据库字段名, 键值:来源属性名或常量值; 
                    'o_url' => 'url',     // 主表不能用主键判断, 用url判断
                    'typeid' => 'typeid',  
                    'senddate' => 'time',  //每个表都添加时间戳,可以使update操作正确显示成功(原数据不变会返回影响行数0);
                    'sortrank' => 'time',  
                    'mid' => '1'   //注意: data中无此属性则使用此常量值,用于设定一些固定值, 注意防止冲突
                ),
                'return' => 'id'      //数据库主表insert操作后返回主键id给data的属性名; 
            ),
            'dede_archives' => array(  
                'write' => array(  
                    'id' => 'id',  //重要 分表的主键必须是第一个,影响 update 操作判断;
                    'title' => 'title', 
                    'senddate' => 'time', 
                    'pubdate' => 'time', 
                    'sortrank' => 'time', 
                    'writer' => '空山', 
                    'ismake' => '1', 
                    'flag' => 'h', 
                    'mid' => '1',
                    'dutyadmin' => '1',
                    'typeid' => 'typeid'
                )
            ),
            'dede_addonarticle' => array(
                'write' => array( 
                    'aid' => 'id',
                    'body' => 'content',
                    'typeid' => 'typeid',
                    'senddate' => 'time'
                )
            )
        )
  );
?>
