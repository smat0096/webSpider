<?php 
class Db
{
	private $ErrorInfo='';		 //错误信息
	private $Config=array( 	 //数据库链接信息
    'DB_TYPE' => 'mysql',
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'qrshop',
    'DB_USER' => 'root',
    'DB_PASS' => 'root',
    'DB_CHARSET' => 'utf8'
  );
	private $options= array('table'=>'','field'=>'*','where'=>'','limit'=>'','order'=>'','group'=>'','data'=>'','dataAll'=>'');  //条件
	private $db='';				 //db信息
	private $sql;				 //Sql语句
	private static $Model;
	private $table='';
	
	function __construct($Config = []){	
		$this->Config = array_merge($this->Config, $Config);//数据库链接信息
		$this->connect();
	}

    function __destruct(){
      $this->db = null;
    }

	/*链接数据库*/
	private function connect(){
		if(!$this->db){
			$setConfig=array(
				PDO::MYSQL_ATTR_INIT_COMMAND=>"SET NAMES {$this->Config['DB_CHARSET']}",  //设置数据库编码
				PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
			);
			try {
				$this->db=new PDO($this->Config['DB_TYPE'].':dbname='.$this->Config['DB_NAME'].';host='.$this->Config['DB_HOST'],$this->Config['DB_USER'],$this->Config['DB_PASS'],$setConfig);
			} catch (PDOException $e) {
				exit($e->getmessage());
			}
		}
	}

	/*条件初始化*/
	private function Initialise_Options(){
		$this->options= array('table'=>'','field'=>'*','where'=>'','limit'=>'','order'=>'','group'=>'','data'=>'','dataAll'=>'');
	}

	/*获取错误信息*/
	function getError(){
		return $this->ErrorInfo;
	}

	/*获取最后一次执行的Sql语句*/
	function getSql(){
		return $this->sql;
	}

	/*获取的表字段*/
	function field($field)
	{
		$this->options['field']=$field;
		return $this;
	}

	/*要操作的数据库表*/
	function table($tableName)
	{
		$this->table=$tableName;
		$this->options['table']=" FROM ".$tableName;
		return $this;
	}

	/**
	 * where条件 
	 */
	function where($where)
	{	
		if(is_array($where)){
			$whereText='';
			foreach ($where as $val) {
				$whereText.=empty($whereText)?' WHERE '.$val['key'].$val['compart'].$this->db->quote($val['value']):' and '.$val['key'].$val['compart'].$this->db->quote($val['value']);
			}
			$this->options['where']=$whereText;
		}else{
			$this->options['where']=" WHERE ".$where;
		}
		return $this;
	}

	/*group 分组*/
	function group($group){
		$this->options['group']=" GROUP BY ".$group;
		return $this;
	}

	/*order 排序*/
	function order($order)
	{
		$this->options['order']=" ORDER BY ".$order;
		return $this;
	}

	/*limit 输出限制*/
	function limit($limit)
	{
		$this->options['limit']=" LIMIT ".$limit;
		return $this;
	}

	/*data数据*/
	function data(Array $data){
		$arr=array();
		foreach ($data as $key => $value) {
			$arr[$key]=$this->db->quote($value);
		}
		$this->options['data']=$arr;
		return $this;
	}

	/**
	 * 查询一条数据 field/table/where/order
	 * 参数1:数据输出的类型 assoc/num
	 * 参数2:是否输出sql语句 
	 * Return Array or String
	 */
	function find($ContentType='assoc',$return=false){
		$sql= "SELECT ".$this->options['field'] .$this->options['table'].$this->options['where'].$this->options['order']." LIMIT 0,1;"; 
		if(!$return){
			$this->sql=$sql;
			$this->Initialise_Options();
			if($RowObjict=$this->db->query($sql,$this->ContentType($ContentType))){
				if($RowObjict->rowCount()){ 
					return $RowObjict->fetch();
				}else{
					return false;
				}
			}else{
				$this->ErrorInfo=$this->db->errorInfo();
				return '错误:'.$this->ErrorInfo;
			}
		}else{
			return $sql;
		}
	}
	
	/**
	 * 统计数据 table/where/group/order
	 * @param  $count 要统计的字段名称
	 * @return [type]        [description]
	 */
	function count($count){
		$sql="SELECT count(distinct({$count})) count ".$this->options['table'].$this->options['where'].$this->options['group'].$this->options['order'].';';
		if($count=$this->query($sql)){
			return $count[0]['count'];
		}else{
			return false;
		}
	}

	/**
	 * 数据查询 可执行 field/table/where/group/order/limit
	 * 参数1:数据输出的类型 assoc/num
	 * 参数2:是否输出sql语句 
	 * Return Array or String
	 */
	function select($ContentType='assoc',$return=false){
		$sql= "SELECT ".$this->options['field'] .$this->options['table'].$this->options['where'].$this->options['group'].$this->options['order'].$this->options['limit'].';'; 
		if(!$return){
			return $this->query($sql,$ContentType);
		}else{
			return $sql;
		}
	}

	/**
	 * 添加数据 table/data
	 * 参数: id:返回添加的数据的id line:操作所影响的行数
	 * Return Bool
	 */
	function insert($return='id'){
		if(!empty($this->options['data']) and !empty($this->options['table'])){
			$desc_field=$this->descField();
			$data_field=array_keys($this->options['data']);
			$field=array_intersect($desc_field,$data_field);
			$arr=array();
			foreach ($field as $key => $value) {
				$arr[$value]=$this->options['data'][$value];
			}
			$key='`'.implode('`,`',array_keys($arr)).'`';
			$value=implode(',',array_values($arr));
			$sql="INSERT INTO {$this->table}({$key}) VALUES($value);";
			$this->Initialise_Options();
			
			$this->sql=$sql;
			return $this->exec_query($this->sql,$return);
		}else{
			$this->errorInfo='Data数据 或 Table表不能为空!';
			return false;
		}
	}

	/**
	 * 执行添加/删除/修改
	 * @param  $sql    要执行的Sql语句
	 * @param  $return 返回类型
	 *         		id:返回添加的数据的id [默认]
	 *         		line:操作所影响的行数
	 * @return Bool
	 */
	function exec_query($sql,$return='line'){
		if($return=='id'){	//返回添加的数据的id
			if($this->db->exec($sql)){
				return $this->db->lastInsertId();
			}else{
				$this->errorInfo=$this->db->errorInfo();
				return false;
			}
		}elseif($return=='line'){	//返回操作所影响的行数
			return $this->db->exec($sql);
		}
	}

	/**
	 * 修改数据 table/where/data
	 * Return Bool 
	 */
	function update($return=false){
		if(!empty($this->options['data']) and !empty($this->options['where']) and !empty($this->options['table'])){
			$desc_field=$this->descField();
			$data_field=array_keys($this->options['data']);
			$field=array_intersect($desc_field,$data_field);
			$data='';
			foreach ($field as $key => $value) {
				$data.=empty($data)?'`'.$value.'`='.$this->options['data'][$value]:',`'.$value.'`='.$this->options['data'][$value];
			}
			$sql="UPDATE {$this->table} SET {$data} {$this->options['where']};";
			$this->Initialise_Options();
			if(!$return){
				$lines = $this->db->exec($sql);
				if($lines){
					$this->sql=$sql;
					return $lines;
				}else{
					$this->errorInfo=$this->db->errorInfo();
					return false;
				}
			}else{
				return $sql;
			}
		}else{
			$this->errorInfo='where条件 或 Data数据 或 Table表不能为空!';
			return false;
		}
		
	} 

	/**
	 * 删除数据 table/where
	 * Return Bool
	 */
	function delete($return=false){
		if(!empty($this->options['where']) and !empty($this->options['table'])){
			$sql="DELETE FROM {$this->table} {$this->options['where']};";
			$this->Initialise_Options();
			if(!$return){
				if($this->db->exec($sql)){
					$this->sql=$sql;
					return true;
				}else{
					$this->errorInfo=$this->db->ErrorInfo();
					return false;
				}
			}else{
				return $sql;
			}
		}else{
			$this->errorInfo='where条件 或 Table表不能为空!';
			return false;
		}
		
	}

	/**
	 * 事物提交
	 * 参数1:Sql语句[每条sql语句最后记得加上 ; ]
	 * Return Bool
	 */
	function Transaction($sql){
		$SqlArr=explode(';',$sql);
		if(mb_substr($sql,-1)==';') array_pop($SqlArr);
		try {
			$this->db->beginTransaction();

			foreach ($SqlArr as $val) {
				$a=$this->db->prepare($val);
				$a->execute();
			}

			return $this->db->commit();
		} catch (pdoException $e) {
			$this->db->rollBack();
			$this->errorInfo=$e->getMessage();
			return false;
		}
	}

	/**
	 * 获取的表字段
	 * 参数1:表名称[可选]
	 * @return Array
	 */
	private function descField($table=''){
		$table= empty($table)?$this->table:$table;
		if($RowObjict=$this->db->query("desc {$table}")){
			$arr=array();
			foreach ($RowObjict as $key => $value) {
				$arr[]=$value['Field'];
			}
			return $arr;
		}else{
			$this->ErrorInfo=$this->db->errorInfo();
		}
	}


	/**
	 * 执行sql查询操作
	 * Return Array
	 */
	function query($sql,$ContentType='assoc'){
		if($RowObjict=$this->db->query($sql,$this->ContentType($ContentType))){
			$this->sql=$sql;
			$this->Initialise_Options();
			$arr=array();
			// echo $sql.'<br>';
			foreach ($RowObjict as $key => $value) {
				$arr[]=$value;
			}
			return $arr;
		}else{
			$this->ErrorInfo=$this->db->errorInfo();
		}
	}

	/*获取pdo获取模式*/
	private function ContentType($ContentType){
		switch ($ContentType) {
			case 'assoc':
				return PDO::FETCH_ASSOC;
				break;

			case 'num':
				return PDO::FETCH_NUM;
				break;

			case 'both':
				return PDO::FETCH_BOTH;
				break;

			default:
				return PDO::FETCH_ASSOC;
				break;
		}
	}

}
