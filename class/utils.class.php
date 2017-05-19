<?php
  /** 
    file: utils.class.php 
    工具类
  */
  class K {
    static $showTip = 'true';
     public static $showLog = 'true';

  /* 1 字符串处理 S */
    //[将字符串或数组统一为数组]
    public static function toArray($var){
      if(is_string($var)){
        $var = array($var);
      }else if(is_array($var)){
        $var = $var;
      }else{
        echo('$var必须为字符串或数组!!!');
        var_dump($var);
        exit();
      }
      return $var;
    }

    //检测匹配多个字符串与正则;
    public static function match($regs, $strs){
      $regs = self::toArray($regs);
      $strs = self::toArray($strs);
      foreach($regs as $reg){
        foreach($strs as $str){
          if( preg_match( $reg, $str,$matches ) ){
            return array(
              'reg' => $reg,
              'str' => $str,
              'matches' => $matches
            );
          }
        }
      }
      return false;
    }

    // 替换字符串,对replace的 reg=>str 格式进行批量替换;
    public static function replace($replace=[], $result){
      foreach($replace as $regs => $strs){
        $result = preg_replace($regs, $strs, $result);
      }
      return $result;
    }

    // 匹配文本并替换,  通用方法
    public static function gettext ($str='', $reg='', $replace=[]){
      $result = '';
      if(preg_match( $reg ,$str, $matches)){
        $result = self::replace($replace , $matches[1]);
        $result = trim($result);
      }
      return $result;
    }
  /* 1 字符串处理 E */

  /* 6 文件处理 S */
    //创建文件夹
    public static function mkdir ($path, $charset='GBK'){
      $pathSys = strtoupper($charset) =='GBK' ? iconv('UTF-8', 'GBK', $path) : $path;
      if(!file_exists($pathSys)){
          if(mkdir($pathSys)){
            self::tipS('建立文件夹'.$path);
            return true;
          }else{
            self::tipE('建立文件夹,请检查权限或文件名: '.$path);
            return false;
          } 
      }else{
          self::tipW('文件夹已存在: '.$path);
          return true;
      }
    }

    /* 删除文件夹 */
    public static function rmdir($dir){
      if(!file_exists($dir)) return true;
       $dh = opendir($dir);
       while ($file = readdir($dh)) {
          if ($file != "." && $file != "..") {
             $fullpath = $dir . "/" . $file;
             if (is_dir($fullpath)) {
                self::rmdir($fullpath);
             } else {
                unlink($fullpath);
             }
          }
       }
       closedir($dh);
       if (rmdir($dir)){
          return true;
       } else {
          return false;
       }
    }

    //保存文件
    public static function save($data, $path, $charset='GBK', $mode='wb'){
      $pathSys = strtoupper($charset) =='GBK' ? iconv('UTF-8', 'GBK', $path) : $path;
      if (!$file = fopen($pathSys, $mode)) {
        return false;
      }
      if (!fwrite($file, $data)) {
      }
      fclose($file);
      return true;
    }

    //保存提示信息
    public static function log($data, $path, $charset='GBK'){
      // file_put_contents($file,"\r\n".$message."\r\n",FILE_APPEND);
      $data .= strtoupper($charset) =='GBK' ?  "\r\n" : "\n";
      return self::save($data, $path, $charset, 'ab');
    }

  /* 6 文件处理 E */
  /* 10 提示 S */
    public static function tip($message,$color='black'){
      if(!self::$showTip) return;
      $now = date('Y-m-d H:i:s');
      $message = $message .' 时间: ' .$now;
      echo '<li><font color="'.$color.'">' .$message. '</font></li>';
    }
    public static function tipP($message){
      self::tip('提示: '.$message, '#666');
    }
    public static function tipS($message){
      self::tip('成功: '.$message, 'green');
    }
    public static function tipW($message){
      self::tip('警告: '.$message, 'orange');
    }
    public static function tipE($message){
      self::tip('错误: '.$message, 'red');
    }
  /* 10 提示 E */
  }

