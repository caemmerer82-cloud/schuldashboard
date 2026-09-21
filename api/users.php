<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
$me=auth_require();$myId=(int)($me['sub']??0);$myRole=$me['role']??'user';$tid=(int)($me['tenant_id']??0);
$method=$_SERVER['REQUEST_METHOD'];$action=$_GET['action']??'';
try {
    if ($method==='PATCH'&&$action==='change_password'){
        if ($myRole==='superadmin') json_response(['error'=>'Das Superadmin-Passwort wird in config.php geändert.'],403);
        $d=read_json_body();$oldPw=(string)($d['old_password']??'');$newPw=(string)($d['new_password']??'');
        if ($oldPw===''||$newPw==='') json_response(['error'=>'Altes und neues Passwort sind Pflicht.'],400);
        if (strlen($newPw)<6) json_response(['error'=>'Neues Passwort muss mind. 6 Zeichen haben.'],400);
        $stmt=db()->prepare("SELECT password_hash FROM users WHERE id=?");$stmt->execute([$myId]);$row=$stmt->fetch();
        if (!$row) json_response(['error'=>'Benutzer nicht gefunden.'],404);
        if (!password_verify($oldPw,$row['password_hash'])) json_response(['error'=>'Das aktuelle Passwort ist falsch.'],403);
        db()->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($newPw,PASSWORD_BCRYPT),$myId]);
        json_response(['ok'=>true]);
    }
    if (!in_array($myRole,['tenant_admin','superadmin'],true)) json_response(['error'=>'Keine Berechtigung.'],403);
    if ($method==='GET'){$s=db()->prepare("SELECT id,username,role,active,last_login,created_at FROM users WHERE tenant_id=? ORDER BY username ASC");$s->execute([$tid]);json_response($s->fetchAll());}
    if ($method==='POST'){
        $d=read_json_body();$username=trim((string)($d['username']??''));$password=(string)($d['password']??'');$role=(string)($d['role']??'user');$active=isset($d['active'])?(int)(bool)$d['active']:1;
        if ($username==='') json_response(['error'=>'Benutzername darf nicht leer sein.'],400);
        if ($password==='') json_response(['error'=>'Passwort ist Pflicht.'],400);
        if (strlen($password)<6) json_response(['error'=>'Passwort muss mind. 6 Zeichen haben.'],400);
        if (!in_array($role,['user','tenant_admin'],true)) $role='user';
        $chk=db()->prepare("SELECT id FROM users WHERE username=?");$chk->execute([$username]);if ($chk->fetch()) json_response(['error'=>'Benutzername bereits vergeben.'],409);
        db()->prepare("INSERT INTO users (tenant_id,username,password_hash,role,active) VALUES (?,?,?,?,?)")->execute([$tid,$username,password_hash($password,PASSWORD_BCRYPT),$role,$active]);
        json_response(['id'=>(int)db()->lastInsertId()],201);
    }
    if ($method==='PUT'||$method==='PATCH'){
        $d=read_json_body();$id=(int)($d['id']??$_GET['id']??0);if ($id<=0) json_response(['error'=>'id fehlt'],400);
        $chk=db()->prepare("SELECT id FROM users WHERE id=? AND tenant_id=?");$chk->execute([$id,$tid]);if (!$chk->fetch()) json_response(['error'=>'Benutzer nicht gefunden.'],404);
        $fields=[];$params=[];
        if (isset($d['username'])){$u=trim((string)$d['username']);if ($u==='') json_response(['error'=>'Benutzername darf nicht leer sein.'],400);$chk2=db()->prepare("SELECT id FROM users WHERE username=? AND id!=?");$chk2->execute([$u,$id]);if ($chk2->fetch()) json_response(['error'=>'Benutzername bereits vergeben.'],409);$fields[]='username=?';$params[]=$u;}
        if (isset($d['password'])&&$d['password']!==''){if (strlen($d['password'])<6) json_response(['error'=>'Passwort muss mind. 6 Zeichen haben.'],400);$fields[]='password_hash=?';$params[]=password_hash($d['password'],PASSWORD_BCRYPT);}
        if (isset($d['role'])){if (!in_array($d['role'],['user','tenant_admin'],true)) json_response(['error'=>'Ungültige Rolle.'],400);$fields[]='role=?';$params[]=$d['role'];}
        if (isset($d['active'])){$fields[]='active=?';$params[]=(int)(bool)$d['active'];}
        if (!$fields) json_response(['error'=>'Keine Felder.'],400);
        $params[]=$id;db()->prepare("UPDATE users SET ".implode(',',$fields)." WHERE id=?")->execute($params);json_response(['ok'=>true]);
    }
    if ($method==='DELETE'){$id=(int)($_GET['id']??0);if ($id<=0) json_response(['error'=>'id fehlt'],400);if ($id===$myId) json_response(['error'=>'Du kannst dich nicht selbst löschen.'],400);$chk=db()->prepare("SELECT id FROM users WHERE id=? AND tenant_id=?");$chk->execute([$id,$tid]);if (!$chk->fetch()) json_response(['error'=>'Benutzer nicht gefunden.'],404);db()->prepare("DELETE FROM users WHERE id=?")->execute([$id]);json_response(['ok'=>true]);}
    json_response(['error'=>'Methode nicht erlaubt'],405);
} catch (Throwable $e){json_response(['error'=>'Serverfehler','detail'=>$e->getMessage()],500);}
