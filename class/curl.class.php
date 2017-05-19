<?php
  /** 
    file: curl.class.php 
    curl类
    在php.ini 中配置开启。(PHP 4 >= 4.0.2) 取消面的注释 
    extension=php_curl.dll
    在Linux下面，需要重新编译PHP了，编译时，你需要打开编译参数——在configure命令上加上“–with-curl” 参数
  */
  class KS_curl {
    private $defalts = array(
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0, //自动判断使用哪个版本  
        CURLOPT_TIMEOUT => '30', //超时 30s
        CURLOPT_USERAGENT =>"Mozilla/5.0 (Windows NT 6.1) AppleWebKit/534.30 (KHTML, like Gecko) Chrome/12.0.742.122 Safari/534.30", //user-agent头
        CURLOPT_RETURNTRANSFER => TRUE, //返回原生的（Raw）输出
        CURLOPT_HEADER => 0, //关闭头文件数据流输出
        CURLOPT_SSL_VERIFYPEER => FALSE, //禁止服务端验证
        CURLOPT_FOLLOWLOCATION => 3, //允许重定向,避免302;
        CURLOPT_ENCODING => "",//HTTP请求头中"Accept-Encoding: "的值。支持的编码有"identity"，"deflate"和"gzip"。如果为空字符串""，请求头会发送所有支持的编码类型。
        CURLOPT_SSL_VERIFYPEER => false, //禁止服务端验证,针对 https
        CURLINFO_HEADER_OUT => true //启用时追踪句柄的请求字符串
    );
    private  $curl; 
    private  $headers=array(
      "cache-control: no-cache",
     // "API-RemoteIP: " . $_SERVER['REMOTE_ADDR']
    );

    function __construct($option=[]) {
      //验证curl模块
      if(!function_exists('curl_init')){
        echo '执行失败!请先安装curl扩展!';
        exit;
      }
      $this->curl = curl_init();
      curl_setopt_array($this->curl, $this->defalts);
      if(isset($options['mobile'])){ //手机端
        curl_setopt( $curl, CURLOPT_USERAGENT , 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_3_2 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8H7 Safari/6533.18.5' );
      }
      if(isset($options['cookie'])){ //连接时读取的cookie信息
        curl_setopt( $curl, CURLOPT_COOKIE , $options['cookie'] );
      }
      if(isset($options['post'])){  //设置post提交方式
        $post = $options['post'];
        curl_setopt( $curl, CURLOPT_POST , 1 ); //是否以POST方式提交
        if(is_array($post)){  //数据需要数组格式
          curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
          curl_setopt( $curl, CURLOPT_POSTFIELDS , http_build_query($post) ); //要提交的信息 
        }else{
          echo "post数据格式错误";
          exit;
        }
      }

      $headers = $this->headers;
      //设置header头
      if(isset($options['headers'])){ //设置 headers
        $headers = array_merge($options['headers']);
      }
      if(isset($options['token'])){ //设置 token
        $headers[] = "Authorization: OAuth2 ".$options['token']; 
      }
      curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers );
    }
    function __destruct(){
      curl_close($this->curl);
    }
    public function set($array){
      curl_setopt_array($this->curl,$array);
    }
    public function get($url){
      $result = curl_setopt($this->curl, CURLOPT_URL, $url); //设置url
      $result = curl_exec($this->curl); //执行curl
      $httpStatus = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);//HTTP 状态码
      if($httpStatus == 200){
        return $result;
      }else{
        echo $url.'curl错误: '.curl_error($curl);
        return false;
      }
    }

  }

