<?php
  /** 
    file: spider.class.php 
    爬虫类
  */
  $dir = dirname(__FILE__);
  require_once($dir."/utils.class.php");
  require_once($dir."/db.class.php");
  class  Spider {
    private $config;                               //基本配置
    private $db;                               //数据库对象
    private $done = [];                               //已下载链接
    private $result = [];                               //已下载内容
    private $baseURL;                               //起始页面
    private $download;                               //下载目录
    private $options = array(                               //运行配置
        'clear' => false,                               //是否清空下载目录
        'rewriteDB' => true,                               //是否覆盖数据库相同记录
        'rewriteFile' => false,                               //是否覆盖本地相同文件
        'ifDownload' => true,                     //是否保存本地
        'ifUploadDB' => true,                     //是否上传数据库
    );

  /* 1 初始化 S */
    // 构造方法 初始化内部变量 
    function __construct($config) {
      $this->config = $config;
      $this->baseURL = $config['base'];
      $this->download = $config['download'];
      $this->ignore = $config['rule']['ignore'];
      $this->errorTxt = $this->download.'/error.txt';
      $this->detailTxt = $this->download.'/detail.txt';
      $this->init();
    }
    //初始化运行环境
    function init(){
      set_time_limit(0);
     // ob_end_clean();
      echo str_pad('', 1024); // 设置足够大，受output_buffering影响
      //验证curl模块
      if(!function_exists('curl_init')){
        K::tipE('<i color="red" id="over">致命: 执行失败!请先安装curl扩展!</i>');
        exit;
      }
      //验证url合法性
      if(!preg_match('/^https?:\/\/[a-zA-Z0-9\-\.]+/i', $this->baseURL)){
        K::tipE('<i color="red" id="over">致命: 执行失败!目标站点URL地址不合法!</i>');
        exit;
      };
    }
    //配置运行参数 options
    private function pre_options($options){
      $this->options = array_merge($this->options, $options);
      if($this->options['ifUploadDB']){
        $this->db = new Db($this->config['DBhost']);
      }
      if($this->options['ifDownload']){
        $this->initDownloadFolder();
      }
    }

    //[开始抓取,动态配置参数]
    function start($options = []){
      $this->pre_options($options);
      $record = array( //遍历路径记录
        'currUrl' => $this->baseURL,//当前URL
        'currDir' => $this->download, //当前文件夹路径
        'currFolder' => $this->download, //当前文件夹名
        'subUrl'=>[],   //sub历史url
        'subDir'=>[], // sub历史dir
        'subFolder'=>[], //sub历史文件夹名
        'nextNum'=>0   //next递归次数;
      );
      $this->go($this->config['rule'],$record);
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
      $url = $record['currUrl'];
      if(in_array($url, $this->done)){
        K::tipW('该链接已抓取过了, URL: '.$url);
        return false;
      }
      array_push($this->done, $url);
      return true;
    }

    //获取sub页[子级页面],建立文件夹
    private function getSub($rule, $record,  $string){
      $url = $record['currUrl'];
      $dir = $record['currDir'];
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
          $this->logError('没有获取到页面标题, URL: '.$url);
          return;
        }else{
          $currDir = $dir.'/'.$title;
          if($this->options['ifDownload']) K::mkdir($currDir);
          $record = $this->set_record($record,array(
            'currDir' => $currDir, 
            'currFolder' => $title, 
            'subUrl'=>$url, 
            'subDir'=>$dir, 
            'subFolder'=>$folder 
          ));
        }
      
        K::tipS( '匹配链接 URL : <font color="#000">'.$url.'</font> 类型 <b>'.$rule['type'].'</b>  匹配 <b>subReg</b> 链接:'. $totalnum. '个 | 文件夹名:<font color="blue">'.$title.'</font>');
        echo '<hr align="center"  style= "border:1 dotted #666666" /><br>';
        
        //获取子页面
        foreach($links_result as $suburl){
          $record = $this->set_record($record,array(
            'currUrl' => $suburl
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
      $url = $record['currUrl'];
      $dir = $record['currDir'];
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
            'currUrl' => $nexturl
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
      if($this->options['ifDownload']) {
        $this->download($rule, $record, $data['string']);
      }
      if($this->options['ifUploadDB']){
        $this->upload($rule, $record, $data['array']);
      }
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
      $index1 = 0;
      $index2 = 0;
      $db = $this->db;
      foreach( $DBtables as $table_name => $table_rule){
        $index1++;
        $db = $db->table($table_name);
        $write_data = [];
        $export_name = '';
        if(isset($table_rule['write'])){
          $sql = '';
          foreach($table_rule['write'] as $fied_name => $data_name){
            $isKey = $index2 ? false : true;
            $index2++;
            //注意: data没有属性名为$data_name的值, 则使用$data_name为值,用于设定一些固定值
            $fied_value = isset($data[$data_name]) ? $data[$data_name] : $data_name;
            if($isKey){
              $res = $db->where($fied_name."='{$fied_value}'")->count('id');
              if($res){
                K::tipW('该数据已存在,跳过 '.$fied_name.':[.'.$fied_value.'] ['.$res.']');
                return;
              }
            }
            $write_data[$fied_name] = $fied_value;
          }
        }
        $db = $db->table($table_name)->data($write_data);
        if(isset($table_rule['return'])){ //可返回 [自增 id] 或 [影响行数 line]
          $return = $table_rule['return'];
          $returnAs = $table_rule['returnAs'];
          $return = $db->insert($return);
          $data[$returnAs] = $return;
        }else{
          $return = $db->insert('line');
        }
        if($return){
          $message = $data['url'].'写入DB数据,table:['.$table_name.'] title:['.$data['title'].'] return:['.$return.']';
          K::tipS($message);
          K::log( $message, $this->download."/log.txt");
        }else{
          $this->logError($data['url'].'写入DB数据,table:['.$table_name.']['.$data['title'].'] return:['.$return.']');
        }
      }
    }
  /* 4 上传数据库 E */
  /* 5 保存本地 S  */
    //初始化根目录
    private function initDownloadFolder(){
      if(!$this->options['ifDownload']) return;
      if($this->options['clear']){
        if(K::rmdir($this->download)){
          K::tipS( "清空".$this->download);
        }else{
          K::tipE( "清空".$this->download);
        };
      }
      //新建保存目录//
      K::mkdir($this->download);
    }

    // 获取页面title,用于设置保存目录
    private function getPageTitle ($str=''){
      $replace = array_merge($this->config['replaceGlobal'], $this->config['replacePageTitle']);
      $pageTitle = K::gettext($str, $this->config['pageTitle'],  $replace);
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
      $path = $this->getFilePath($record['currUrl'], $record['currDir']);
      if(!$path) return;;
      if(!K::save($data,$path)){
        $this->logError("写入文件 ".$path);
      };
      echo('<br>');
      flush();
    }
  /* 5 保存本地 E  */
  /* 6 获取数据 S */
    // 通过 curl 获取内容
    private function getCurl($url, $cookie="", $post=""){
      //初始化curl
      $curl = curl_init($url);
      curl_setopt_array($curl, array(
        CURLOPT_TIMEOUT => '30', //超时 30s
        CURLOPT_USERAGENT =>"Mozilla/5.0 (Windows NT 6.1) AppleWebKit/534.30 (KHTML, like Gecko) Chrome/12.0.742.122 Safari/534.30", //user-agent头
        CURLOPT_RETURNTRANSFER => TRUE, //返回文件流
        CURLOPT_HEADER => 0, //关闭头文件数据流输出
        //CURLOPT_COOKIEJAR => $cookie //设置Cookie信息保存在指定的文件中 
        //CURLOPT_POST => 1,  //是否以POST方式提交
        //CURLOPT_POSTFIELDS : http_build_query($post)//要提交的信息 
        CURLOPT_SSL_VERIFYPEER => FALSE, //禁止服务端验证
        CURLOPT_FOLLOWLOCATION => 3, //允许重定向,避免302;
        // CURLOPT_HTTPHEADER => array( //设置 token
        //   "cache-control: no-cache",
        //   "postman-token: c13c9f1a-fce6-7ec8-4c91-3f13bd233284"
        // )
      ));
      //请求栏目url
      $exec = curl_exec($curl);
      //HTTP 状态码
      $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      //耗时
      $totaltime = curl_getinfo($curl, CURLINFO_TOTAL_TIME);
      if($httpStatus == 200 && $exec){
        K::tipS( '进入页面，  URL: ' . $url .' 耗时:'. $totaltime .'秒');
      }else{
        $this->logError('进入页面， URL : '. $url.' 错误码:[ '. $httpStatus.' ]');
        return false;
      }
      curl_close($curl);
      flush();
      return $exec;
    }
    // 通过 getCurl 获取页面内容
    private function getString($rule, $record){
      $url = $record['currUrl'];
      $dir = $record['currDir'];
      $exec = $this->getCurl($url);
      if(!$exec) return false;
      //是否转码
      if(isset($this->config['charset']) && strtoupper($this->config['charset']) =='GBK'){
        $exec = iconv('GBK', 'UTF-8', $exec);
      }
      return $exec;
    }
    // 获取所需数据
    private function getFileData($rule,$record, $stringData){ 
      //获取文件内容;
      $url = $record['currUrl'];
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
          $reg = $value['reg'];
          $replace = array_merge($this->config['replaceGlobal'], $replace);
          $name = $value['name'];
          $text = K::gettext($stringData, $value['reg'], $replace);
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
        if(K::match($this->ignore,$url)){
          K::tipW('fillLink 中判断该链接为忽略类, URL: '.$url);
          array_splice($links_result,$key,1);
        }
      }
      $links_result = K::replace($this->config['replaceLink'] , $links_result);
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

