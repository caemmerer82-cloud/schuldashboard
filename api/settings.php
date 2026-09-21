<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
$me=auth_require_role('tenant_admin','superadmin');$tid=(int)($me['tenant_id']??1);
$allowed=['url_stundenplan','url_vertretungsplan','url_busfahrplan','child_name','module_aktiv','kanban_spalten','untis_server','untis_school','untis_username','untis_password','hide_foe'];
$method=$_SERVER['REQUEST_METHOD'];
try {
    if ($method==='GET'){$out=[];foreach($allowed as $k)$out[$k]=get_setting($k,'',$tid);json_response($out);}
    if ($method==='POST'||$method==='PUT'){
        $d=read_json_body();
        // Format A: {"key":"module_aktiv","value":"..."} (von verwaltung.php)
        if (isset($d['key'],$d['value'])&&in_array($d['key'],$allowed)){
            set_setting($d['key'],(string)$d['value'],$tid);
            json_response(['ok'=>true]);
        }
        // Format B: {"module_aktiv":"...","child_name":"..."} (von settings.php)
        foreach($allowed as $k) if(array_key_exists($k,$d)) set_setting($k,(string)$d[$k],$tid);
        json_response(['ok'=>true]);
    }
    json_response(['error'=>'Methode nicht erlaubt'],405);
} catch (Throwable $e){json_response(['error'=>'Serverfehler','detail'=>$e->getMessage()],500);}
