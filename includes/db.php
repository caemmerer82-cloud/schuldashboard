<?php
require_once __DIR__.'/config.php';

function db(): PDO {
    static $pdo=null;
    if ($pdo!==null) return $pdo;
    $options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
    if (DB_DRIVER==='sqlite'){
        $dir=dirname(DB_SQLITE_PATH);if(!is_dir($dir))mkdir($dir,0775,true);
        $pdo=new PDO('sqlite:'.DB_SQLITE_PATH,null,null,$options);
        $pdo->exec('PRAGMA foreign_keys = ON');init_sqlite_schema($pdo);
    } else {
        $pdo=new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',DB_HOST,DB_PORT,DB_NAME,DB_CHARSET),DB_USER,DB_PASS,$options);
    }
    return $pdo;
}
function init_sqlite_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenants (id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL UNIQUE,active INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL DEFAULT (datetime('now')))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER NOT NULL,username TEXT NOT NULL UNIQUE,password_hash TEXT NOT NULL,role TEXT NOT NULL DEFAULT 'user',active INTEGER NOT NULL DEFAULT 1,last_login TEXT,created_at TEXT NOT NULL DEFAULT (datetime('now')),FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS homework (id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER NOT NULL,subject TEXT NOT NULL,title TEXT NOT NULL,description TEXT,due_date TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'todo',created_at TEXT NOT NULL DEFAULT (datetime('now')),updated_at TEXT NOT NULL DEFAULT (datetime('now')),FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS events (id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER NOT NULL,event_type TEXT NOT NULL,title TEXT NOT NULL,event_date TEXT NOT NULL,notes TEXT,created_at TEXT NOT NULL DEFAULT (datetime('now')),FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS lehrer (
        tenant_id INTEGER NOT NULL,
        kuerzel   TEXT NOT NULL,
        vorname   TEXT,
        nachname  TEXT NOT NULL,
        PRIMARY KEY (tenant_id, kuerzel),
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS faecher (
        tenant_id INTEGER NOT NULL,
        kuerzel   TEXT NOT NULL,
        vollname  TEXT NOT NULL,
        PRIMARY KEY (tenant_id, kuerzel),
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ags (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        tenant_id  INTEGER NOT NULL,
        name       TEXT NOT NULL,
        raum       TEXT,
        leitung    TEXT,
        wochentag  INTEGER NOT NULL DEFAULT 1,
        von_datum  TEXT NOT NULL,
        bis_datum  TEXT NOT NULL,
        aktiv      INTEGER NOT NULL DEFAULT 1,
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS event_notes (
        event_id   INTEGER NOT NULL,
        tenant_id  INTEGER NOT NULL,
        content    TEXT NOT NULL,
        updated_at TEXT NOT NULL DEFAULT (datetime('now')),
        PRIMARY KEY (event_id),
        FOREIGN KEY (event_id)  REFERENCES events(id)  ON DELETE CASCADE,
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (tenant_id INTEGER NOT NULL,skey TEXT NOT NULL,svalue TEXT,PRIMARY KEY (tenant_id,skey),FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE)");
}
function json_response($data,int $status=200): void {
    http_response_code($status);header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
function read_json_body(): array {
    $raw=file_get_contents('php://input');if($raw===''||$raw===false)return[];
    $data=json_decode($raw,true);return is_array($data)?$data:[];
}
function get_setting(string $key,string $default='',?int $tenantId=null): string {
    if ($tenantId===null) $tenantId=_current_tenant_id();
    $stmt=db()->prepare('SELECT svalue FROM settings WHERE tenant_id=? AND skey=?');
    $stmt->execute([$tenantId,$key]);$v=$stmt->fetchColumn();
    return ($v===false||$v===null)?$default:(string)$v;
}
function set_setting(string $key,string $value,?int $tenantId=null): void {
    if ($tenantId===null) $tenantId=_current_tenant_id();
    $sql=(DB_DRIVER==='sqlite')?'INSERT INTO settings (tenant_id,skey,svalue) VALUES (?,?,?) ON CONFLICT(tenant_id,skey) DO UPDATE SET svalue=excluded.svalue':'INSERT INTO settings (tenant_id,skey,svalue) VALUES (?,?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)';
    db()->prepare($sql)->execute([$tenantId,$key,$value]);
}
function ensure_tenant_settings(int $tenantId): void {
    $defaults=['url_stundenplan'=>'','url_vertretungsplan'=>'','url_busfahrplan'=>'','child_name'=>'','untis_server'=>'','untis_school'=>'','untis_username'=>'','untis_password'=>'','kanban_spalten'=>'','module'=>''];
    $sql=(DB_DRIVER==='sqlite')?'INSERT OR IGNORE INTO settings (tenant_id,skey,svalue) VALUES (?,?,?)':'INSERT IGNORE INTO settings (tenant_id,skey,svalue) VALUES (?,?,?)';
    $stmt=db()->prepare($sql);foreach($defaults as $k=>$v)$stmt->execute([$tenantId,$k,$v]);
}
function _current_tenant_id(): int {
    static $tid=null;if($tid!==null)return $tid;
    try{require_once __DIR__.'/jwt.php';$token=jwt_from_request();if($token){$p=jwt_decode($token,JWT_SECRET);$tid=(int)($p['tenant_id']??1);}} catch(Throwable $e){}
    return $tid??1;
}
