<?php
require_once __DIR__.'/jwt.php';
require_once __DIR__.'/config.php';

function base_path(string $file=''): string {
    $script = $_SERVER['SCRIPT_NAME']??'/index.php';
    $dir    = rtrim(dirname($script),'/');
    return $dir.'/'.ltrim($file,'/');
}
function auth_user(): ?array {
    static $cache=false;
    if ($cache!==false) return $cache;
    $token=jwt_from_request();
    if ($token===null){$cache=null;return null;}
    try{$cache=jwt_decode($token,JWT_SECRET);}catch(RuntimeException $e){$cache=null;}
    return $cache;
}
function auth_require(): array {
    $u=auth_user();
    if (!$u){
        if (is_api_request()){http_response_code(401);header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>'Nicht eingeloggt']);exit;}
        header('Location: '.base_path('login.php').'?next='.rawurlencode($_SERVER['REQUEST_URI']??'/'));exit;
    }
    return $u;
}
function auth_require_role(string ...$roles): array {
    $u=auth_require();
    if (!in_array($u['role'],$roles,true)){
        if (is_api_request()){http_response_code(403);header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>'Keine Berechtigung']);exit;}
        header('Location: '.base_path('index.php').'?error=forbidden');exit;
    }
    return $u;
}
function is_superadmin(): bool { return (auth_user()['role']??'')==='superadmin'; }
function is_tenant_admin(): bool { return in_array(auth_user()['role']??'',['superadmin','tenant_admin'],true); }
function auth_tenant_id(): int { return (int)(auth_user()['tenant_id']??1); }
function attempt_login(string $username,string $password,bool $remember=false): ?string {
    $username=trim($username);
    if (defined('SUPERADMIN_USER')&&defined('SUPERADMIN_PASS')&&$username===SUPERADMIN_USER&&hash_equals(SUPERADMIN_PASS,$password))
        return issue_token(build_payload(0,$username,'superadmin',null,null,$remember));
    try{
        $stmt=db()->prepare("SELECT u.id,u.username,u.password_hash,u.role,u.tenant_id,t.name AS tenant_name FROM users u LEFT JOIN tenants t ON t.id=u.tenant_id WHERE u.username=? AND u.active=1");
        $stmt->execute([$username]);$row=$stmt->fetch();
    }catch(Throwable $e){return null;}
    if (!$row||!password_verify($password,$row['password_hash'])) return null;
    try{db()->prepare("UPDATE users SET last_login=CURRENT_TIMESTAMP WHERE id=?")->execute([$row['id']]);}catch(Throwable){}
    return issue_token(build_payload((int)$row['id'],$row['username'],$row['role'],$row['tenant_id']!==null?(int)$row['tenant_id']:null,$row['tenant_name'],$remember));
}
function build_payload(int $uid,string $username,string $role,?int $tenantId,?string $tenantName,bool $long=false): array {
    $ttl=$long?(defined('JWT_TTL_LONG')?JWT_TTL_LONG:7776000):(defined('JWT_TTL')?JWT_TTL:2592000);
    return ['sub'=>$uid,'username'=>$username,'role'=>$role,'tenant_id'=>$tenantId,'tenant_name'=>$tenantName,'iat'=>time(),'exp'=>time()+$ttl];
}
function issue_token(array $payload): string {
    $ttl=$payload['exp']-$payload['iat'];
    $token=jwt_encode($payload,JWT_SECRET);
    // Auf Strato-Shared-Hosting: secure=false
    setcookie('jwt',$token,['expires'=>time()+$ttl,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
    return $token;
}
function logout(): void {
    setcookie('jwt','',['expires'=>time()-3600,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
}
function is_api_request(): bool { return str_contains($_SERVER['REQUEST_URI']??'','/api/'); }
function safe_redirect(string $url,string $fallback=''): string {
    if ($fallback==='') $fallback=base_path('index.php');
    if ($url===''||$url[0]!=='/'||str_starts_with($url,'//')) return $fallback;
    return $url;
}
