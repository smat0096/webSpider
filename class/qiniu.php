<?php
/**
 * 微赞七牛批量转换链接
 * 陈望华
 */

// 存在图片的数据库表
// ims_ewei_shop_goods   			商品内容表[缩略图/缩略图集/内容]    
// ims_ewei_shop_groups_goods       团购商品表[缩略图/缩略图集/内容]
// ims_ewei_shop_poster             采集海报表[背景图片]
// ims_ewei_shop_postera            活动海报表[背景图片]
// ims_ewei_shop_qa_adv             幻灯管理表[缩略图]
// ims_ewei_shop_sns_adv 			论坛广告表[缩略图]


include 'qiniu/sdk/Db.class.php';
include 'qiniu/sdk/autoload.php';
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
set_time_limit(300);					//设置响应时间

$Db_Host='127.0.0.1';					//数据库地址
$Db_Name='zanmi';						//数据库名称
$Db_User='root';						//数据库用户名
$Db_Pass='root';						//数据库密码

$Pic_Host='http://image.qiruiw.com/';	//七牛空间绑定的域名
$accessKey = 'cg3IyRJ4dh6TJi57BU1K8QS5LvPVNMER2UccXJ73';
$secretKey = 'BH_hS_JhAjMgm5keJivv8E9hCxxKdCJMotD9YQk4';
$bucket = 'letaocz';					// 要上传的空间
$UpStyle = 'fw';						//上传是否带图片样式[填写样式名称],不填写则不带

//执行Sql语句 'SELECT COUNT('id') FROM `ims_ewei_shop_goods`' 查询内容数量
$Count= 350;							//内容数量数据
$DeletePic=false;    					//上传后是否删除本地源文件
$sleep=1; 								//每执行一个间隔时间

$startId = empty($_GET['startId']) ? 0 : intval($_GET['startId']);
$d=new Db('mysql',$Db_Host,$Db_Name,$Db_User,$Db_Pass);
$data=$d->table('ims_ewei_shop_goods')->field('id,thumb,thumb_url,detail_logo,content')->order('id desc')->limit("{$startId},1")->select();
$data=$data[0];
$data['thumb_url']=unserialize($data['thumb_url']);	//缩略图反序列化
preg_match_all('/<[img|IMG].*?src=[\'|\"](.*?(?:[\.gif|\.jpg|\.png|\.jpeg|\.bmp]))[\'|\"].*?[\/]?>/',$data['content'],$contentArr);   //正则匹配内容中的图片

// 处理主图				
if( $PicPath=handle($data['thumb'],$data['id']) ){
	if( $thumbPath=upload($PicPath,$data['id']) ){
		$data['thumb']=$thumbPath;
		$DeletePic ? unlink($PicPath) : '';  
	}
}
// 处理主图连接
$thumb_url=array();
foreach ($data['thumb_url'] as $key => $value) {
	if( $PicPath=handle($value,$data['id']) ){
		if( $thumbPath=upload($PicPath,$data['id']) ){
			$thumb_url[]=$thumbPath;
			$DeletePic ? unlink($PicPath) : '';  
		}else{
			$thumb_url[]=$value;
		}
	}else{
		$thumb_url[]=$value;
	}
}
$data['thumb_url']=$thumb_url;

//处理 detail_logo
if( !empty($data['detail_logo']) ){
	if( $PicPath=handle($data['detail_logo'],$data['id']) ){
		if( $LogoPath=upload($PicPath,$data['id']) ){
			$data['detail_logo']=$LogoPath;
			$DeletePic ? unlink($PicPath) : '';  
		}
	}
}


// 处理内容中的图片
$contentText=$data['content'];
foreach ($contentArr[1] as $key => $value) {
	if( $PicPath=handle($value,$data['id']) ){
		if( $Path=upload($PicPath,$data['id']) ){
			$content[]=$Path;
			$contentText=str_ireplace($value,$Path,$contentText,$r);
			$data['content'] = $r>0 ? $contentText : setlog('replace',$data['id'],$value);
			$DeletePic ? unlink($PicPath) : '';  
		}
	}
}

update($data);
$startId=$startId+1;
if($startId<$Count){
	sleep($sleep);
	echo "<script>location.href='http://0735.qiruiw.com/qiniu.php?startId={$startId}'</script>";
}else{
	echo "完成!";
}


/**
 * 修改数据
 * @param  $data 要修改的数据
 * @return Bool
 */
function update($data){
	global $Db_Host,$Db_Name,$Db_User,$Db_Pass;
	$id=$data['id'];
	unset($data['id']);
	$_data=array(
		'thumb'=>$data['thumb'],
		'thumb_url'=>serialize($data['thumb_url']),
		'content'=>$data['content'],
		'detail_logo'=>$data['detail_logo']
	);
	$d=new Db('mysql',$Db_Host,$Db_Name,$Db_User,$Db_Pass);
	if( $d->table('ims_ewei_shop_goods')->data($_data)->where('id='.$id)->update() ){
		setlog('shopSuccess',$id,'');
		return true;
	}else{
		$_data['id']=$id;
		setlog('update',$id,'',serialize($_data));
		return false;
	}
}

/**
 * 处理并判断图片是否存在
 * @param  $str  图片地址或路径 
 * @return String
 */
function handle($str,$shop_id){
	if( !file_exists($str) ){
		$PicPath='';
		if( mb_substr($str,0,7) == 'http://' ){
			// 判断图片是否以是站点链接
			if( mb_substr($str,0,23) =='http://0735.qiruiw.com/' ){
				$PicPath= str_replace('http://0735.qiruiw.com/','',$str);
			}else{
				setlog('0735',$shop_id,$str);
				return false;
			}
		}elseif( mb_substr($str,0,6) == 'images' ){
			// 判断图片路径是否以images开头 如果是则加 attachment
			$PicPath='attachment/'.$str;
		}

		if( file_exists($PicPath) ){
			return  $PicPath;
		}else{
			setlog('PicPath',$shop_id,$str);
			return false;
		}
	}
	return $str;
}

/**
 * 七牛上传图片
 * @param  $str 	 要上传的图片的本地路径
 * @param  $shop_id  商品Id
 * @return String
 */
function upload($str,$shop_id){
	global $Pic_Host,$accessKey,$secretKey,$bucket;
	// 构建鉴权对象
	$auth = new Auth($accessKey, $secretKey);
	$token = $auth->uploadToken($bucket); 		// 生成上传 Token
	// 上传到七牛后保存的文件名
	$PicInfo=pathinfo($str,PATHINFO_EXTENSION);	//图片后缀
	if( !empty($PicInfo) ){
		$key = 'shopid_'.$shop_id.'_'.time().'_'.mt_rand(0,100000).'.'.$PicInfo;
		// 初始化 UploadManager 对象并进行文件的上传
		$uploadMgr = new UploadManager();
		// 调用 UploadManager 的 putFile 方法进行文件的上传
		list($ret, $err) = $uploadMgr->putFile($token, $key,$str);
	}else{
		setlog('image',$shop_id,$str);
		return false;
	}
	if($err === null){
		setlog('success',$shop_id,$str);
		if( !empty($UpStyle) ){
			return $Pic_Host.$ret['key'].'-'.$UpStyle;
		}else{
			return $Pic_Host.$ret['key'];
		}
	}else{
		setlog('upload',$shop_id,$str);
		return false;
	}
}


/**
 *	写入错误操作日志
 *  @param  $type    错误类型
 *  错误类型:
 *   	upload    	上传出错
 *   	image     	图片错误
 *   	PicPath   	图片路径错误
 *   	success     上传成功
 *   	0735   		图片链接非
 *   	replace 	内容替换失败
 * @param  $shop_id  商品ID
 * @param  $PicPath  图片路径
 * @return Null
 */
function setlog($type,$shop_id,$PicPath,$Content=''){
	if($type == 'update'){
		file_put_contents("./qiniu/log/{$shop_id}.txt",$Content);
	}else{
		switch ($type) {
			case 'upload':
				$Text="图片上传出错!商品Id:{$shop_id},图片地址:{$PicPath}\r\n";
				break;
			case 'image':
				$Text="图片错误!商品Id:{$shop_id},图片地址:{$PicPath}\r\n";
				break;
			case 'PicPath':
				$Text="图片路径错误!商品Id:{$shop_id},图片地址:{$PicPath}\r\n";
				break;
			case '0735':
				$Text="图片非站点图片!商品Id:{$shop_id},图片地址:{$PicPath}\r\n";
				break;
			case 'replace':
				$Text="图片替换失败!商品Id:{$shop_id},图片地址:{$PicPath}\r\n";
				break;		
			case 'success':
				$Text="商品Id:{$shop_id} 下的图片:{$PicPath} 上传成功!\r\n";
				break;
			case 'shopSuccess':
				$Text="商品Id:{$shop_id}数据修改成功!\r\n";
				break;
		}
		file_put_contents("./qiniu/log/{$type}.txt",$Text,FILE_APPEND);
	}
}













