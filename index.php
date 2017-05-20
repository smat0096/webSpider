<!doctype html>
<html>
<head>
<title>爬虫0.1版</title>
<meta charset="utf-8"/>
<style type="text/css">
    form > *{
        margin-bottom: 6px;
    }
    label{
        display:inline-block;
        width:80px;
    }
    input[type="text"]{
        width : 900px;
    }
</style>
</head>
<body>
<h4>请输入栏目url</h4>
<h4><font color="blue">范例站点：<a href="http://wiki.we7shop.cn/hc/kb/section/86740/" target="_blank">人人商城</a></font></h4>
<form method="post" class="sub">
<label for="">目标站点: </label><input type="text" name="base"  placeholder="目标站点"/> <br>
<input type="submit" name="submit" value="开抓" />
</form>
</body>
</html>
<?php
header('Content-Type:text/html;charset=utf-8');
date_default_timezone_set('PRC');
$dir = dirname(__FILE__);
require($dir."/config.inc.php");
require($dir."/class/spider.class.php");
if(isset($_POST['submit'])){
    // foreach($CONFIG as $key => $val){
    //     if(isset($_POST[$key]) && !empty($_POST[$key])){
    //         $CONFIG[$key] =   $_POST[$key];
    //         if($key == 'titletrim'){
    //             $CONFIG[$key] = explode(',',$_POST[$key]);
    //         }
    //     }
    // }
    // echo "<br><pre>";
    // //var_dump($CONFIG);
    // echo "</pre><br>";
    ?>

    <script>
        //滚动
       var gundong = setInterval(function(){
            //document.body.scrollTop = document.body.scrollHeight;
            if(document.getElementById('over')){ clearInterval(gundong); alert("抓取结束");}
        }, 2000);
        
        //补填表单;
        var form = document.getElementsByTagName('form')[0];
        var config = <?php echo json_encode($CONFIG); ?> ;        
        form.base.value = config.baseURL;
        // for(var key in config){
        //     if(key == 'titletrim'){config[key] = config[key].join(',')}
        //     form[key].value = config[key];
        // }
    </script>

    <?php
      $spider = new Spider($CONFIG);
      $spider->start();
} 

?>
