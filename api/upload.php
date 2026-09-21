<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
$me=auth_require_role('tenant_admin','superadmin');$tid=(int)($me['tenant_id']??1);
$key=$_GET['key']??'';$allowedKeys=['busfahrplan','stundenplan','vertretungsplan'];
if (!in_array($key,$allowedKeys,true)) json_response(['error'=>'Ungültiger key.'],400);
if ($_SERVER['REQUEST_METHOD']!=='POST') json_response(['error'=>'Nur POST erlaubt.'],405);
if (empty($_FILES['file'])) json_response(['error'=>'Keine Datei hochgeladen.'],400);
$file=$_FILES['file'];
if ($file['error']!==UPLOAD_ERR_OK) json_response(['error'=>'Upload-Fehler '.$file['error']],500);
if ($file['size']>10*1024*1024) json_response(['error'=>'Datei zu groß (max. 10 MB).'],400);
$finfo=new finfo(FILEINFO_MIME_TYPE);$mimeType=$finfo->file($file['tmp_name']);
if ($mimeType!=='application/pdf') json_response(['error'=>'Nur PDF-Dateien erlaubt.'],400);
$uploadDir=__DIR__.'/../files/'.$tid.'/';
if (!is_dir($uploadDir)){mkdir($uploadDir,0755,true);file_put_contents($uploadDir.'.htaccess',"Options -Indexes\n<FilesMatch \"\\.php$\">\nDeny from all\n</FilesMatch>\n");}
$filename=$key.'.pdf';$destPath=$uploadDir.$filename;
if (!move_uploaded_file($file['tmp_name'],$destPath)) json_response(['error'=>'Datei konnte nicht gespeichert werden.'],500);
$https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||(int)($_SERVER['SERVER_PORT']??80)===443;
$baseUrl=($https?'https':'http').'://'.$_SERVER['HTTP_HOST'];
$script=$_SERVER['SCRIPT_NAME']??'/api/upload.php';$appDir=rtrim(dirname(dirname($script)),'/');
$fileUrl=$baseUrl.$appDir.'/files/'.$tid.'/'.$filename;
set_setting('url_'.$key,$fileUrl,$tid);
json_response(['ok'=>true,'url'=>$fileUrl,'msg'=>'Erfolgreich hochgeladen.']);
