<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
$me  = auth_require_role('tenant_admin','superadmin');
$tid = (int)($me['tenant_id']??1);
$m   = $_SERVER['REQUEST_METHOD'];

try {
    if ($m==='GET') {
        $s=db()->prepare("SELECT kuerzel,vollname FROM faecher WHERE tenant_id=? ORDER BY kuerzel ASC");
        $s->execute([$tid]); json_response($s->fetchAll());
    }
    if ($m==='POST'||$m==='PUT') {
        $d=read_json_body();
        $k=strtoupper(trim((string)($d['kuerzel']??'')));
        $v=trim((string)($d['vollname']??''));
        if ($k===''||$v==='') json_response(['error'=>'Kürzel und Vollname sind Pflicht.'],400);
        $sql=(DB_DRIVER==='sqlite')
            ?'INSERT INTO faecher (tenant_id,kuerzel,vollname) VALUES (?,?,?) ON CONFLICT(tenant_id,kuerzel) DO UPDATE SET vollname=excluded.vollname'
            :'INSERT INTO faecher (tenant_id,kuerzel,vollname) VALUES (?,?,?) ON DUPLICATE KEY UPDATE vollname=VALUES(vollname)';
        db()->prepare($sql)->execute([$tid,$k,$v]);
        json_response(['ok'=>true,'kuerzel'=>$k]);
    }
    if ($m==='DELETE') {
        $k=strtoupper(trim((string)($_GET['kuerzel']??'')));
        if ($k==='') json_response(['error'=>'Kürzel fehlt.'],400);
        db()->prepare("DELETE FROM faecher WHERE tenant_id=? AND kuerzel=?")->execute([$tid,$k]);
        json_response(['ok'=>true]);
    }
    json_response(['error'=>'Methode nicht erlaubt'],405);
} catch (Throwable $e){json_response(['error'=>$e->getMessage()],500);}
