<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
$me=auth_require();$tid=(int)($me['tenant_id']??1);
$method=$_SERVER['REQUEST_METHOD'];$allowedStatus=['todo','doing','done'];
try {
    if ($method==='GET'){$s=db()->prepare("SELECT id,subject,title,description,due_date,status FROM homework WHERE tenant_id=? ORDER BY status ASC,due_date ASC,id ASC");$s->execute([$tid]);json_response($s->fetchAll());}
    if ($method==='POST'){
        $d=read_json_body();$subject=trim((string)($d['subject']??''));$title=trim((string)($d['title']??''));$desc=trim((string)($d['description']??''));$due=trim((string)($d['due_date']??''));$status=(string)($d['status']??'todo');
        if ($subject===''||$title===''||$due==='') json_response(['error'=>'subject, title und due_date sind Pflicht.'],400);
        if (!DateTime::createFromFormat('Y-m-d',$due)) json_response(['error'=>'due_date muss YYYY-MM-DD sein.'],400);
        if (!in_array($status,$allowedStatus,true)) $status='todo';
        db()->prepare("INSERT INTO homework (tenant_id,subject,title,description,due_date,status) VALUES (?,?,?,?,?,?)")->execute([$tid,$subject,$title,$desc,$due,$status]);
        json_response(['id'=>(int)db()->lastInsertId()],201);
    }
    if ($method==='PUT'||$method==='PATCH'){
        $d=read_json_body();$id=(int)($d['id']??$_GET['id']??0);if ($id<=0) json_response(['error'=>'id fehlt'],400);
        $fields=[];$params=[];
        foreach (['subject','title','description','due_date','status'] as $f){
            if (!array_key_exists($f,$d)) continue;
            if ($f==='status'&&!in_array($d[$f],$allowedStatus,true)) json_response(['error'=>'ungültiger status'],400);
            if ($f==='due_date'&&!DateTime::createFromFormat('Y-m-d',(string)$d[$f])) json_response(['error'=>'due_date muss YYYY-MM-DD sein.'],400);
            $fields[]="$f=?";$params[]=(string)$d[$f];
        }
        if (!$fields) json_response(['error'=>'Keine Felder'],400);
        $ts=(DB_DRIVER!=='sqlite')?',updated_at=CURRENT_TIMESTAMP':",updated_at=datetime('now')";
        $params[]=$tid;$params[]=$id;
        db()->prepare("UPDATE homework SET ".implode(',',$fields).$ts." WHERE tenant_id=? AND id=?")->execute($params);
        json_response(['ok'=>true]);
    }
    if ($method==='DELETE'){$id=(int)($_GET['id']??0);if ($id<=0) json_response(['error'=>'id fehlt'],400);db()->prepare("DELETE FROM homework WHERE tenant_id=? AND id=?")->execute([$tid,$id]);json_response(['ok'=>true]);}
    json_response(['error'=>'Methode nicht erlaubt'],405);
} catch (Throwable $e){json_response(['error'=>'Serverfehler','detail'=>$e->getMessage()],500);}
