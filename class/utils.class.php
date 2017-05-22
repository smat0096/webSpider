<?php
  /** 
    file: utils.class.php 
    工具类
  */
  class K {
     public static $showTip = 'true';
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
    public static function match($regs, $str){
      $regs = self::toArray($regs);
      foreach($regs as $reg){
        if( preg_match( $reg, $str,$matches ) ){
          return array(
            'reg' => $reg,
            'str' => $str,
            'matches' => $matches
          );
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
    public static function gettext ($str='', $reg='', $replace=[], $ignore=[]){
      $result = '';
      if(preg_match( $reg ,$str, $matches)){
        $result = self::replace($replace , $matches[1]);
        $result = trim($result);
      }      
      if(count($ignore)>0){
        if(self::match($ignore, $result)) {
          return false;
        };
      }
      return $result;
    }
  /* 1 字符串处理 E */

  /* 6 文件处理 S */
    //创建文件夹
    public static function mkdir ($dir){ //默认传入UTF-8字符;
      $dirSys = self::isWindows() ? iconv('UTF-8', 'GBK', $dir) : $dir;  //文件系统编码 ,windows中文简体为GBK
      if(!file_exists($dirSys)){ //不存在则创建
          if(mkdir($dirSys)){ 
            return true;
          }else{
            return false;
          } 
      }else{
          return true; //存在则跳过
      }
    }

    /* 删除文件夹 */
    public static function rmdir($dir){ //首次需传入符合系统编码的路径: 考虑效率, 因递归获取的是符合系统编码的路径名;
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
    public static function save($data, $path, $update=false, $mode='wb'){
      $pathSys = self::isWindows() ? iconv('UTF-8', 'GBK', $path) : $path;
      if(file_exists($pathSys)){ //文件已存在
        if($update){             //执行更新
          if (!is_writable($pathSys)) return false; //文件不可写
        }else{                   //执行跳过
          return true;
        }
      }
      // 执行创建&&写入
      if (!$file = fopen($pathSys, $mode)) return false; //打开失败
      if (!fwrite($file, $data)) return false;           //写入失败
      fclose($file);       //关闭文件
      return true;
    }

    //保存提示信息
    public static function log($data, $path){
      $data .= self::isWindows() ?  "\r\n" : "\n";
      return self::save($data, $path, true, 'ab');// file_put_contents($file, $data, FILE_APPEND);
    }

    //判断操作系统
    public static function isWindows(){
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {  
            return true;  
        } else {  
            return false;
        }
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

