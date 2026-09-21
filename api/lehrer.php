<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
$me  = auth_require_role('tenant_admin','superadmin');
$tid = (int)($me['tenant_id']??1);
$m   = $_SERVER['REQUEST_METHOD'];

try {
    if ($m==='GET') {
        $s=db()->prepare("SELECT kuerzel,anrede,vorname,nachname FROM lehrer WHERE tenant_id=? ORDER BY kuerzel ASC");
        $s->execute([$tid]); json_response($s->fetchAll());
    }
    if ($m==='POST'||$m==='PUT') {
        $d=read_json_body();
        $k=strtoupper(trim((string)($d['kuerzel']??'')));
        $a=trim((string)($d['anrede']??''));
        $v=trim((string)($d['vorname']??''));
        $n=trim((string)($d['nachname']??''));
        if ($k===''||$n==='') json_response(['error'=>'Kürzel und Nachname sind Pflicht.'],400);
        $sql=(DB_DRIVER==='sqlite')
            ?'INSERT INTO lehrer (tenant_id,kuerzel,anrede,vorname,nachname) VALUES (?,?,?,?,?) ON CONFLICT(tenant_id,kuerzel) DO UPDATE SET anrede=excluded.anrede,vorname=excluded.vorname,nachname=excluded.nachname'
            :'INSERT INTO lehrer (tenant_id,kuerzel,anrede,vorname,nachname) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE anrede=VALUES(anrede),vorname=VALUES(vorname),nachname=VALUES(nachname)';
        db()->prepare($sql)->execute([$tid,$k,$a,$v,$n]);
        json_response(['ok'=>true,'kuerzel'=>$k]);
    }
    if ($m==='DELETE') {
        $k=strtoupper(trim((string)($_GET['kuerzel']??'')));
        if ($k==='') json_response(['error'=>'Kürzel fehlt.'],400);
        db()->prepare("DELETE FROM lehrer WHERE tenant_id=? AND kuerzel=?")->execute([$tid,$k]);
        json_response(['ok'=>true]);
    }
    json_response(['error'=>'Methode nicht erlaubt'],405);
} catch (Throwable $e){json_response(['error'=>$e->getMessage()],500);}
