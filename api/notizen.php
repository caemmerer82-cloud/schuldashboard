<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
$me  = auth_require();
$tid = (int)($me['tenant_id']??1);
$m   = $_SERVER['REQUEST_METHOD'];

$eid = (int)($_GET['event_id']??0);
if ($eid<=0) json_response(['error'=>'event_id fehlt.'],400);

// Sicherstellen dass der Termin zum Mandanten gehört
function checkEventOwnership(int $eid, int $tid): void {
    $s=db()->prepare("SELECT id FROM events WHERE id=? AND tenant_id=?");
    $s->execute([$eid,$tid]);
    if (!$s->fetch()) json_response(['error'=>'Termin nicht gefunden.'],404);
}

try {
    if ($m==='GET') {
        checkEventOwnership($eid,$tid);
        $s=db()->prepare("SELECT content,updated_at FROM event_notes WHERE event_id=? AND tenant_id=?");
        $s->execute([$eid,$tid]);
        $row=$s->fetch();
        json_response($row?:['content'=>'','updated_at'=>null]);
    }
    if ($m==='POST'||$m==='PUT') {
        checkEventOwnership($eid,$tid);
        $d=read_json_body();
        $content=(string)($d['content']??'');
        if (DB_DRIVER==='sqlite') {
            $sql='INSERT INTO event_notes (event_id,tenant_id,content,updated_at) VALUES (?,?,?,datetime(\'now\'))
                  ON CONFLICT(event_id) DO UPDATE SET content=excluded.content,updated_at=datetime(\'now\')';
        } else {
            $sql='INSERT INTO event_notes (event_id,tenant_id,content) VALUES (?,?,?)
                  ON DUPLICATE KEY UPDATE content=VALUES(content),updated_at=CURRENT_TIMESTAMP';
        }
        db()->prepare($sql)->execute([$eid,$tid,$content]);
        json_response(['ok'=>true]);
    }
    json_response(['error'=>'Methode nicht erlaubt'],405);
} catch (Throwable $e){json_response(['error'=>$e->getMessage()],500);}
