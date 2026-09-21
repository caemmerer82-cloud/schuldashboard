<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
$me=auth_require();$tid=(int)($me['tenant_id']??1);
$method=$_SERVER['REQUEST_METHOD'];$allowedTypes=['klausur','test','pruefung','veranstaltung','sonstiges'];
try {
    if ($method==='GET'){$s=db()->prepare("SELECT id,event_type,title,event_date,notes FROM events WHERE tenant_id=? ORDER BY event_date ASC,id ASC");$s->execute([$tid]);json_response($s->fetchAll());}
    if ($method==='POST'){
        $d=read_json_body();$type=(string)($d['event_type']??'sonstiges');$title=trim((string)($d['title']??''));$date=trim((string)($d['event_date']??''));$notes=trim((string)($d['notes']??''));
        if ($title===''||$date==='') json_response(['error'=>'title und event_date sind Pflicht.'],400);
        if (!DateTime::createFromFormat('Y-m-d',$date)) json_response(['error'=>'event_date muss YYYY-MM-DD sein.'],400);
        if (!in_array($type,$allowedTypes,true)) $type='sonstiges';
        db()->prepare("INSERT INTO events (tenant_id,event_type,title,event_date,notes) VALUES (?,?,?,?,?)")->execute([$tid,$type,$title,$date,$notes]);
        json_response(['id'=>(int)db()->lastInsertId()],201);
    }
    if ($method==='PUT'||$method==='PATCH'){
        $d=read_json_body();$id=(int)($d['id']??$_GET['id']??0);if ($id<=0) json_response(['error'=>'id fehlt'],400);
        $fields=[];$params=[];
        foreach (['event_type','title','event_date','notes'] as $f){
            if (!array_key_exists($f,$d)) continue;
            if ($f==='event_type'&&!in_array($d[$f],$allowedTypes,true)) json_response(['error'=>'ungültiger event_type'],400);
            if ($f==='event_date'&&!DateTime::createFromFormat('Y-m-d',(string)$d[$f])) json_response(['error'=>'event_date muss YYYY-MM-DD sein.'],400);
            $fields[]="$f=?";$params[]=(string)$d[$f];
        }
        if (!$fields) json_response(['error'=>'Keine Felder'],400);
        $params[]=$tid;$params[]=$id;
        db()->prepare("UPDATE events SET ".implode(',',$fields)." WHERE tenant_id=? AND id=?")->execute($params);
        json_response(['ok'=>true]);
    }
    if ($method==='DELETE'){$id=(int)($_GET['id']??0);if ($id<=0) json_response(['error'=>'id fehlt'],400);db()->prepare("DELETE FROM events WHERE tenant_id=? AND id=?")->execute([$tid,$id]);json_response(['ok'=>true]);}
    json_response(['error'=>'Methode nicht erlaubt'],405);
} catch (Throwable $e){json_response(['error'=>'Serverfehler','detail'=>$e->getMessage()],500);}
