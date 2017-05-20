<?php
  /** 
    file: spider.class.php 
    爬虫类
  */
  $dir = dirname(__FILE__);
  require_once($dir."/utils.class.php");
  require_once($dir."/db.class.php");
  require_once($dir."/curl.class.php");
  class  Spider {
    private $config;                               //基本配置
    private $db;                               //数据库对象
    private $done = [];                               //已下载链接
    private $result = [];                               //已下载内容
    private $baseURL;                               //起始页面
    private $baseDL;                               //下载根目录
    private $record;                               //初始记录
    private $options = array(                               //运行配置
        'ifDownload' => true,                     //是否保存本地
        'clearDL' => true,                               //是否清空下载目录
        'updateDL' => true,                               //是否覆盖本地相同文件
        'ifUpload' => true,                     //是否上传数据库
        'clearDB' => true,                               //TOFIXED 是否清空数据库
        'updateDB' => true,                               //是否覆盖数据库相同记录
    );

  /* 1 初始化 S */
    // 构造方法 初始化内部变量 
    function __construct($config) {
      $this->config = $config;
      $this->baseURL = $config['baseURL'];
      $this->baseDL = $config['baseDL'];
      $this->ignoreURL = $config['ignoreURL'];
      $this->errorTxt = $this->baseDL.'/error.txt';
      $this->detailTxt = $this->baseDL.'/detail.txt';
      $this->record = array( //遍历路径记录
        'currURL' => $this->baseURL,//当前URL
        'currDIR' => $this->baseDL, //当前文件夹路径
        'currFolder' => $this->baseDL, //当前文件夹名
        'subURL'=>[],   //sub历史url
        'subDIR'=>[], // sub历史dir
        'subFolder'=>[], //sub历史文件夹名
        'nextNum'=>0   //next递归次数;
      );
    }
    //初始化运行环境
    //配置运行参数 options
    private function init($options=[]){
      set_time_limit(0);//设置允许脚本运行的时间，单位为秒 ,如果设置为0，没有时间方面的限制。
     // ob_end_clean();
      echo str_pad('', 1024); // 设置足够大，受output_buffering影响
      //验证url合法性
      if(!preg_match('/^https?:\/\/[a-zA-Z0-9\-\.]+/i', $this->baseURL)){
        K::tipE('<i color="red" id="over">致命: 执行失败!目标站点URL地址不合法!</i>');
        exit;
      };
      $this->options = array_merge($this->options, $options);
      if($this->options['ifUpload']){
        $this->db = new Db($this->config['DBhost']);
      }
      if($this->options['ifDownload']){
        $this->initDownload();
      }
    }

    //[开始抓取,动态配置参数]
    function start($options = []){
      $this->curl = new KS_curl();
      $this->init($options);
      $this->go($this->config['rule'],$this->record);
      $this->end();
    }
    private function end(){
      $this->curl = null;
      unset($this->curl);
      K::tip('<center><font size="30" color="red" id="over">全部抓取结束</font></center>');
    }
  /* 1 初始化 E */
  /* 2 递归筛选逻辑 S */
    // 主流程
    private function go($rule, $record ) {
      //前置检测
      if( !$this->pre_go($rule, $record ) ) return;
      //获取页面内容
      if( !$string = $this->getString($rule, $record ) ) return;
      //保存/上传内容
      if(isset($rule['save'])){
        $this->save($rule, $record, $string);
      }
      //获取sub页[子级页面], 建立文件夹
      if(isset($rule['subReg'])) {
        $this->getSub($rule, $record, $string);
      }
      //获取next页[兄弟页面]
      if(isset($rule['nextReg'])) {
        if(isset($rule['nextLength']) && $rule['nextLength'] > 0 && $rule['nextLength'] < $record['nextNum']){ 
          K::tipW('超出 nextLength，停止抓取nextReg, URL: '.$url, 'red' );
        }else{
          $this->getNext($rule, $record, $string);
        }
      }
    }

    //预检测
    private function pre_go($rule, $record ) {
      flush();
      //休眠一秒,防止反爬虫
      //sleep(1);
      //
      //判断是否已抓取
      $url = $record['currURL'];
      if(in_array($url, $this->done)){
        K::tipW('该链接已抓取过了, URL: '.$url);
        return false;
      }
      array_push($this->done, $url);
      return true;
    }

    //获取sub页[子级页面],建立文件夹
    private function getSub($rule, $record,  $string){
      $url = $record['currURL'];
      $dir = $record['currDIR'];
      if(preg_match_all($rule['subReg'], $string, $links_result_array)){
        $links_result = $this->fillLink($links_result_array[1]);
        $totalnum = count($links_result);
        if($totalnum == 0) {
          K::tipW('subReg页过滤后数量为0');
          return;
        };
        $folder = $record['currFolder'];
        $title = $this->getPageTitle($string);
        if(!$title) {
          $this->logError('没有获取到页面标题或在忽略列表中, URL: '.$url);
          return;
        }else{
          $currDIR = $dir.'/'.$title;
          if($this->options['ifDownload']) K::mkdir($currDIR, $this->config['charsetDL']);
          $record = $this->set_record($record,array(
            'currDIR' => $currDIR, 
            'currFolder' => $title, 
            'subURL'=>$url, 
            'subDIR'=>$dir, 
            'subFolder'=>$folder 
          ));
        }
      
        K::tipS( '匹配链接 URL : <font color="#000">'.$url.'</font> 类型 <b>'.$rule['type'].'</b>  匹配 <b>subReg</b> 链接:'. $totalnum. '个 | 文件夹名:<font color="blue">'.$title.'</font>');
        echo '<hr align="center"  style= "border:1 dotted #666666" /><br>';
        
        //获取子页面
        foreach($links_result as $suburl){
          $record = $this->set_record($record,array(
            'currURL' => $suburl
          ));
          $this->go ($rule['sub'], $record);
        }
        echo('<br><hr><br>');
      }else{
        $this->logError('无subReg匹配，请检查该页面,URL: '.$url );
      }
    }

    //获取next页[兄弟页面]
    private function getNext($rule, $record, $string){
      $url = $record['currURL'];
      $dir = $record['currDIR'];
      echo('<br><hr><br>');
      if(preg_match_all( $rule['nextReg'], $string, $links_result_array)){
        $links_result = $this->fillLink($links_result_array[1]);
        $totalnum = count($links_result);
        if($totalnum == 0) {
          K::tipW('nextReg页过滤后数量为0');
          return;
        };
        K::tipP( '<b>'.$rule['type'].'</b>成功匹配<b>nextReg</b>链接:'. $totalnum. '个 --- URL : '.$url);

        $nextNum = $record['nextNum']++;
        $record = $this->set_record($record,array(
          'nextNum' => $nextNum
        ));

        foreach($links_result as $nexturl){
          $record = $this->set_record($record,array(
            'currURL' => $nexturl
          ));
          $this->go( $rule, $record );
        }
      } else {
        K::tipW('无匹配 nextReg， URL: '.$url);
      }
    }
  /* 2 递归筛选逻辑 E */
  /* 3 保存||上传 */
    private function save($rule, $record, $string){
      $data = $this->getFileData($rule, $record, $string);
      if(!$data) return;
      if($this->options['ifDownload']) {
        $this->download($rule, $record, $data['string']);
      }
      if($this->options['ifUpload']){
        $this->upload($rule, $record, $data['array']);
      }
      echo '<br>';
      flush();
    }
  /* 4 上传数据库 S */
    //上传数据库主函数
    private function upload($rule, $record, $data){
      $data = $this->matchDBData($record, $data);
      if( $data ){
        $this->uploadDB($data);
      }
    }

    //匹配并拼接数据
    private function matchDBData($record, $data){
      $FolderName = $record['currFolder'];
      foreach($this->config['DBmatch'] as $filed_name => $match_array){
        $matched = false;
        foreach($match_array as $FolderNameReg => $filed_value){
          if(preg_match($FolderNameReg, $FolderName)){
            $data[$filed_name] = $filed_value;
            $matched = true;
            break;
          }
        }
        if(!$matched) {
          $this->logError('没有匹配到数据库规则,取消写入, FolderName: '.$FolderName);
          return false;
        }
      }
      $data['time'] = time();
      return $data;
    }

    //上传到数据库,需随时修改
    private function uploadDB($data){
      $DBtables = $this->config['DBtable'];
      $action ='insert';
      $index1 = 0;
      $db = $this->db;
      foreach( $DBtables as $table_name => $table_rule){
        $isMain = $index1 ? false : true; //是否第一个主表
        $index1 ++ ;
        $index2 = 0;
        $updata = [];
        $key_name  = '';
        $key_value = '';
        if(isset($table_rule['write'])){
          foreach($table_rule['write'] as $fied_name => $data_name){
            $isKey  = $index2 ? false : true;  //是否各个表的主键
            $index2++;
            //注意: data没有属性名为$data_name的值, 则使用$data_name为值,用于设定一些固定值
            $fied_value = isset($data[$data_name]) ? $data[$data_name] : $data_name;
            if($isKey){
              $key_name  = $fied_name;
              $key_value = $fied_value;
              if($isMain){
                //单条数据, 根据使用场景可更换为 select 多条数据模式;
                $find = $db->table($table_name)->field('id')->where($key_name."='{$key_value}'")->find();
                if($find){
                  $mainId = $find['id']; //存在该数据,则获取id;
                  if($this->options['updateDB']){
                    $action = 'update';
                  }else{
                    K::tipW('该数据已存在,跳过 '.$key_name.':[.'.$key_value.'] ['.$mainId.']');
                    return;
                  }
                }
              }
            }
            $updata[$fied_name] = $fied_value;
          }
        }
        $db = $db->table($table_name)->data($updata);
        switch($action){
          case 'insert': 
            $return = $isMain ? 'id' : 'line';//主表则返回id,副表返回影响行数
            $return = $db->insert($return); 
            if($isMain) $mainId = $return; //insert 主表返回id
            break;
          case 'update': 
            $return = $db->where($key_name."='{$key_value}'")->update(); //update 都返回 line
            break;
        }
        $message = "DB操作: [ {$action} ],table:[{$table_name}][{$key_name}][{$key_value}]返回值:[{$return}] mainId:[{$mainId}]";
        if($return){
          if(isset($table_rule['return'])) { //目前仅设置主表需返回id值
            $data[$table_rule['return']] = $mainId;
          }
          K::tipS($message);
          K::log( $message, $this->baseDL."/log.txt");
        }else{
          $this->logError($message);
        }
      }
    }
  /* 4 上传数据库 E */
  /* 5 保存本地 S  */
    //初始化根目录
    private function initDownload(){
      if(!$this->options['ifDownload']) return;
      if($this->options['clearDL']){
        if(K::rmdir($this->baseDL)){
          K::tipS( "清空根目录".$this->baseDL);
        }else{
          K::tipE( "清空根目录".$this->baseDL);
        };
      }
      //新建保存目录//
      K::mkdir($this->baseDL);
    }

    // 获取页面title,用于设置保存目录
    private function getPageTitle ($str=''){
      $replace = array_merge($this->config['replaceGlobal'], $this->config['replaceTitle']);
      $pageTitle = K::gettext($str, $this->config['regTitle'],  $replace);
      return $pageTitle;
    }

    // 获取文件保存路径
    private function getFilePath($url, $dir){ 
      $name = basename($url); 
      $path = $dir.'/'.$name;
      return $path;
    }

    // 保存文件
    private function download($rule, $record, $data){
      //获取文件名及其后缀 ,如果此文件存在，则不再获取
      $path = $this->getFilePath($record['currURL'], $record['currDIR']);
      if(!$path) return;
      $charSys = $this->config['charsetDL'];
      $pathSys = strtoupper($charSys) =='GBK' ? iconv('UTF-8', 'GBK', $path) : $path;
      if(file_exists($pathSys)){
        if($this->options['updateDL']){
          K::tipP('文件已存在,执行覆盖');
        }else{
          K::tipP('文件已存在,执行跳过');
          return;
        }
      }else{
          K::tipP('文件不存在,执行创建');
      }
      if(K::save($data,$path,$charSys)){
        K::tipS("写入文件 ".$path);
      }else{
        $this->logError("写入文件 ".$path);
      };
      flush();
    }
  /* 5 保存本地 E  */
  /* 6 获取数据 S */
    // 通过 curl 获取页面内容
    private function getString($rule, $record){
      $url = $record['currURL'];
      $dir = $record['currDIR'];
      $string = $this->curl->get($url);
      if($string) {
        K::tipS('进入页面'.$url);
      }else{
        K::tipE('进入页面'.$url);
        return false;
      }
      //是否转码
      if(isset($this->config['charset']) && strtoupper($this->config['charset']) =='GBK'){
        $string = iconv('GBK', 'UTF-8', $string);
      }
      return $string;
    }
    // 获取所需数据
    private function getFileData($rule,$record, $stringData){ 
      //获取文件内容;
      $url = $record['currURL'];
      $string = '{{ url || '.$url.' }}'."\r\n";
      $array = array(
        'url' => $url
      );
      $save = $rule['save'];
      if(is_array($save)){
        foreach($save as $key => $value){
          if(isset($value['replace'])){
            $replace = $value['replace'];
          }else{
            $replace = [];
          }
          if(isset($value['ignore'])){
            $ignore = $value['ignore'];
          }else{
            $ignore = [];
          }
          $reg = $value['reg'];
          $replace = array_merge($this->config['replaceGlobal'], $replace);
          $name = $value['name'];
          $text = K::gettext($stringData, $value['reg'], $replace, $ignore);
          if(!$text) return false;
          $string .= '{{ '.$name.' || '.$text.' }}'."\r\n";
          $array[$name] = $text;
        }
      }else {
        $string = $stringData;
        $array['data'] = $stringData;
      }
      return array('string' => $string, 'array' => $array);
    }
  /* 6 获取数据 E */
  /* 7 替换筛选 S */
    // 替换链接
    private function fillLink($links_result){
      $links_result = array_unique($links_result);
      sort($links_result);
      foreach($links_result as $key => $url){
        if(K::match($this->ignoreURL,$url)){
          K::tipW('fillLink 中判断该链接为忽略类, URL: '.$url);
          array_splice($links_result,$key,1);
        }
      }
      $links_result = K::replace($this->config['replaceURL'] , $links_result);
      foreach($links_result as $key => $url){
        if (in_array($url, $this->done)){
          K::tipW('fillLink 中判断该链接为已抓取, URL: '.$url);
          array_splice($links_result,$key,1);
        }
      }
      return $links_result;
    }
  /* 7 替换筛选 E */
  /* 8 工具函数 S */
    //打印并记录错误信息
    private function logError($message){
      K::tipE($message);
      K::log($message, $this->errorTxt);
    }

    //设置跟踪记录
    private function set_record($record,$array) {
      foreach($array as $key => $value ){
        if(is_array($record[$key])){
          array_push($record[$key],$value);
        }else if(is_string($record[$key]) || is_numeric($record[$key])){
          $record[$key] = $value;
        }else{
          K::tipE('set_record: '.$key.' => '.$value);
        }
      }
      return $record;
    }
  /* 8 工具函数 E */
  /* 析构方法，在对象结束之前自动销毁资源释放内存 */
    function __destruct(){}
  }

