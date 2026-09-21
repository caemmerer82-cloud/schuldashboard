<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
$me = auth_user();
if (!$me) { http_response_code(401); echo json_encode(['error'=>'nicht eingeloggt']); exit; }
$tid = (int)($me['tenant_id'] ?? 1);

$server   = get_setting('untis_server',   '', $tid);
$school   = get_setting('untis_school',   '', $tid);
$username = get_setting('untis_username', '', $tid);
$password = get_setting('untis_password', '', $tid);

$dateParam = $_GET['date'] ?? date('Ymd');
$isoDate = substr($dateParam,0,4).'-'.substr($dateParam,4,2).'-'.substr($dateParam,6,2);

$baseUrl = 'https://'.$server.'/WebUntis';
$rpcUrl  = $baseUrl.'/jsonrpc.do?school='.urlencode($school);
$cookie  = '';

function d_post(string $url, string $body, string &$cookie, bool $cap=false): string {
    $hdrs=['Content-Type: application/json','Accept: application/json','User-Agent: Mozilla/5.0'];
    if ($cookie) $hdrs[]='Cookie: '.$cookie;
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,
        CURLOPT_HTTPHEADER=>$hdrs,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HEADER=>$cap]);
    $r=curl_exec($ch); $hs=$cap?curl_getinfo($ch,CURLINFO_HEADER_SIZE):0; curl_close($ch);
    if($cap){$rh=substr($r,0,$hs);$r=substr($r,$hs);if(preg_match('/Set-Cookie:\s*(JSESSIONID=[^;]+)/i',$rh,$m))$cookie=$m[1].'; ';}
    return $r;
}
function d_get(string $url, string $cookie): string {
    $hdrs=['Accept: application/json','User-Agent: Mozilla/5.0','X-Requested-With: XMLHttpRequest','Cookie: '.$cookie];
    $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$hdrs,CURLOPT_TIMEOUT=>15]);
    return curl_exec($ch);
}

// Login
$lr = json_decode(d_post($rpcUrl,json_encode(['id'=>'x','method'=>'authenticate',
    'params'=>['user'=>$username,'password'=>$password,'client'=>'dbg'],'jsonrpc'=>'2.0']),$cookie,true),true);
$sid=$lr['result']['sessionId']??'';
$pid=(int)($lr['result']['personId']??0);
$pt=(int)($lr['result']['personType']??5);
$kid=(int)($lr['result']['klasseId']??0);
$cookie='JSESSIONID='.$sid.'; schoolname='.urlencode('_'.$school);

// Methode 1: REST API für Hausaufgaben
$hwRest = d_get("$baseUrl/api/homeworks/lessons?startDate={$dateParam}&endDate={$dateParam}", $cookie);

// Methode 2: JSON-RPC getHomeworks
$hwRpc = d_post($rpcUrl, json_encode([
    'id'=>'hw','method'=>'getHomeworks',
    'params'=>['startDate'=>(int)$dateParam,'endDate'=>(int)($dateParam+6)],
    'jsonrpc'=>'2.0'
]), $cookie);

// Methode 3: Wochendaten – periodText/lessonText enthält manchmal HW
$url=sprintf('%s/api/public/timetable/weekly/data?elementType=%d&elementId=%d&date=%s&formatId=1',
    $baseUrl,$pt,$pid,$isoDate);
$weekly = json_decode(d_get($url,$cookie),true);
$periods=$weekly['data']['result']['data']['elementPeriods']??[];
$rawP=[];foreach($periods as $pList){$rawP=$pList;break;}
$withText = array_filter($rawP, fn($p)=>trim($p['periodText']??'')!==''||trim($p['lessonText']??'')!=='');

d_post($rpcUrl,json_encode(['id'=>'x','method'=>'logout','params'=>[],'jsonrpc'=>'2.0']),$cookie);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'personId'   => $pid,
    'personType' => $pt,
    'klasseId'   => $kid,
    'hw_rest'    => json_decode($hwRest,true),
    'hw_rpc'     => json_decode($hwRpc,true),
    'periods_with_text' => array_values($withText),
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
