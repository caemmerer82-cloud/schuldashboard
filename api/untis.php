<?php
/**
 * api/untis.php – WebUntis-Proxy für Schülerkonten.
 *
 * Korrekte Methoden für Schülerkonten:
 *   - personId + klasseId kommen direkt aus der authenticate-Antwort
 *   - Stundenplan: REST /api/public/timetable/weekly/data
 *   - Vertretungen: JSON-RPC getTimetable, nur geänderte Stunden filtern
 *   - News: /api/public/news/newsWidgetData
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$me   = auth_require();
$tid  = (int)($me['tenant_id'] ?? 1);
$action = $_GET['action'] ?? 'timetable';

$dateParam = $_GET['date'] ?? null;
$targetDate = ($dateParam && preg_match('/^\d{8}$/', $dateParam)) ? $dateParam : date('Ymd');

$ts     = mktime(0,0,0,(int)substr($targetDate,4,2),(int)substr($targetDate,6,2),(int)substr($targetDate,0,4));
$dow    = (int)date('N', $ts);
$isoDate = date('Y-m-d', $ts);

$server   = get_setting('untis_server',   '', $tid);
$school   = get_setting('untis_school',   '', $tid);
$username = get_setting('untis_username', '', $tid);
$password = get_setting('untis_password', '', $tid);

if ($server===''||$school===''||$username===''||$password==='')
    json_response(['error'=>'WebUntis-Zugangsdaten nicht konfiguriert.'], 503);

$baseUrl = 'https://'.$server.'/WebUntis';
$rpcUrl  = $baseUrl.'/jsonrpc.do?school='.urlencode($school);
$cookie  = '';

function http_post(string $url, string $body, string &$cookie, bool $captureCookie=false): string {
    $hdrs = ['Content-Type: application/json','Accept: application/json',
             'User-Agent: Mozilla/5.0','X-Requested-With: XMLHttpRequest'];
    if ($cookie!=='') $hdrs[] = 'Cookie: '.$cookie;
    if (function_exists('curl_init')) {
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$hdrs,
            CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_HEADER=>$captureCookie]);
        $raw=curl_exec($ch);$err=curl_error($ch);
        $hs=$captureCookie?curl_getinfo($ch,CURLINFO_HEADER_SIZE):0;
        curl_close($ch);
        if ($raw===false) throw new RuntimeException('curl: '.$err);
        if ($captureCookie) {
            $rh=substr($raw,0,$hs);$raw=substr($raw,$hs);
            if (preg_match('/Set-Cookie:\s*(JSESSIONID=[^;]+)/i',$rh,$m)) $cookie=$m[1].'; ';
        }
        return $raw;
    }
    $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>implode("\r\n",$hdrs),
        'content'=>$body,'ignore_errors'=>true,'timeout'=>15]]);
    $raw=@file_get_contents($url,false,$ctx);
    if ($raw===false) throw new RuntimeException('Verbindung zu WebUntis fehlgeschlagen.');
    if ($captureCookie&&isset($http_response_header))
        foreach ($http_response_header as $h)
            if (preg_match('/Set-Cookie:\s*(JSESSIONID=[^;]+)/i',$h,$m)) $cookie=$m[1].'; ';
    return $raw;
}

function http_get(string $url, string $cookie): string {
    $hdrs=['Accept: application/json','User-Agent: Mozilla/5.0',
           'X-Requested-With: XMLHttpRequest','Cookie: '.$cookie];
    if (function_exists('curl_init')) {
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$hdrs,
            CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false]);
        $raw=curl_exec($ch);$err=curl_error($ch);curl_close($ch);
        if ($raw===false) throw new RuntimeException('curl: '.$err);
        return $raw;
    }
    $ctx=stream_context_create(['http'=>['method'=>'GET','header'=>implode("\r\n",$hdrs),
        'ignore_errors'=>true,'timeout'=>15]]);
    $raw=@file_get_contents($url,false,$ctx);
    if ($raw===false) throw new RuntimeException('GET fehlgeschlagen.');
    return $raw;
}

function rpc(string $url, string $method, $params, string &$cookie): array {
    $body=json_encode(['id'=>'sd','method'=>$method,'params'=>$params,'jsonrpc'=>'2.0']);
    $raw=http_post($url,$body,$cookie);
    $d=json_decode($raw,true);
    if (!is_array($d)) throw new RuntimeException('Ungültige Antwort von WebUntis.');
    if (isset($d['error'])) throw new RuntimeException('WebUntis-Fehler: '.($d['error']['message']??'unbekannt'));
    return $d['result']??[];
}

function fmt_time(int $t): string { return sprintf('%02d:%02d',(int)($t/100),$t%100); }

try {
    // 1. Login – personId und klasseId aus Antwort lesen
    $loginBody=json_encode(['id'=>'sd','method'=>'authenticate',
        'params'=>['user'=>$username,'password'=>$password,'client'=>'SchulDashboard'],
        'jsonrpc'=>'2.0']);
    $loginRaw=http_post($rpcUrl,$loginBody,$cookie,true);
    $loginData=json_decode($loginRaw,true);

    if (!isset($loginData['result']['sessionId']))
        throw new RuntimeException('Login fehlgeschlagen – Zugangsdaten prüfen.');

    $sessionId  = $loginData['result']['sessionId'];
    $personId   = (int)($loginData['result']['personId']  ??0);
    $personType = (int)($loginData['result']['personType']??5);
    $klasseId   = (int)($loginData['result']['klasseId']  ??0);

    $cookie = 'JSESSIONID='.$sessionId.'; schoolname='.urlencode('_'.$school);

    $elType = ($personId>0) ? $personType : 1;
    $elId   = ($personId>0) ? $personId   : $klasseId;

    if ($elId<=0) throw new RuntimeException('Keine Element-ID (personId/klasseId) verfügbar.');

    $result = [];

    // ---- Stundenplan: REST Weekly API ----
    if ($action==='timetable') {
        $url=sprintf('%s/api/public/timetable/weekly/data?elementType=%d&elementId=%d&date=%s&formatId=1',
                     $baseUrl,$elType,$elId,$isoDate);
        $raw =http_get($url,$cookie);
        $data=json_decode($raw,true);

        if (isset($data['data']['error'])) {
            $msg=$data['data']['error']['data']['messageKey']??json_encode($data['data']['error']);
            throw new RuntimeException('Stundenplan-Fehler: '.$msg);
        }

        $periods  = $data['data']['result']['data']['elementPeriods']??[];
        $elements = $data['data']['result']['data']['elements']??[];

        // Element-Lookup aufbauen
        $lookup=[];
        foreach ($elements as $el) $lookup[$el['type']][$el['id']]=$el['name']??'';

        // Erste Perioden-Liste (eigener Stundenplan)
        $rawP=[];foreach($periods as $pList){$rawP=$pList;break;}

        // ---- Hausaufgaben aus Untis REST API laden ----
        // Woche berechnen (Montag bis Freitag)
        $weekStart=$isoDate; // bereits Montag der Woche
        $weekEnd=date('Y-m-d',strtotime($weekStart.' +6 days'));
        $wsNum=str_replace('-','',$weekStart);
        $weNum=str_replace('-','',$weekEnd);
        $hwUrl=$baseUrl.'/api/homeworks/lessons?startDate='.$wsNum.'&endDate='.$weNum;
        $hwRaw=http_get($hwUrl,$cookie);
        $hwData=json_decode($hwRaw,true);

        // lessonId → subject Kürzel mappen
        $lessonSubject=[];
        foreach ($hwData['data']['lessons']??[] as $l) {
            $lessonSubject[(int)$l['id']]=$l['subject']??'';
        }
        // Hausaufgaben: lessonId → [{dueDate, text}]
        $hwByLesson=[];
        foreach ($hwData['data']['homeworks']??[] as $hw) {
            $lid=(int)($hw['lessonId']??0);
            if (!$lid) continue;
            $hwByLesson[$lid][]=[
                'text'    =>$hw['text']??'',
                'dueDate' =>$hw['dueDate']??0,
                'completed'=>$hw['completed']??false,
            ];
        }
        // subject → [{dueDate, text}]
        $hwBySubject=[];
        foreach ($hwByLesson as $lid=>$hws) {
            $sub=$lessonSubject[$lid]??'';
            if (!$sub) continue;
            foreach ($hws as $hw) $hwBySubject[$sub][]=$hw;
        }

        $days=[];
        // Perioden nach lessonId+date deduplizieren:
        // Untis liefert die gleiche Stunde oft doppelt (Original + Änderung).
        // Wir behalten immer die Änderungs-Version (nicht-STANDARD cellState hat Vorrang).
        $seenLessons=[];
        foreach ($rawP as $p) {
            $date=(string)($p['date']??'');if(!$date)continue;
            $lid=(string)($p['lessonId']??'');
            $cs=$p['cellState']??'STANDARD';
            $dedupeKey=$date.'_'.$lid.'_'.($p['startTime']??0);

            // Wenn schon gesehen: nur ersetzen wenn aktuelle Version nicht-STANDARD ist
            if (isset($seenLessons[$dedupeKey])) {
                if ($cs==='STANDARD') continue; // Standard nie über Änderung schreiben
            }
            $seenLessons[$dedupeKey]=$p;
        }

        foreach ($seenLessons as $p) {
            $date=(string)($p['date']??'');
            $su=$te=$ro=$kl='–';
            foreach ($p['elements']??[] as $el) {
                // Namen direkt aus Lookup (ist ein String)
                $n=$lookup[$el['type']][$el['id']]??'';
                if ($n==='') $n='–';
                match($el['type']){3=>($su=$n),2=>($te=$n),4=>($ro=$n),1=>($kl=$n),default=>null};
            }
            $cs=$p['cellState']??'STANDARD';
            // Korrektes Mapping der cellState-Werte:
            // CANCELLED = Stunde fällt aus (Entfall)
            // FREE      = Freistunde (auch Entfall aus Schülersicht)
            // SUBSTITUTION / ROOMSUBSTITUTION = Vertretung (anderer Lehrer/Raum)
            // IRREGULAR = unregelmäßig (z.B. Stundenplanänderung)
            // ADDITIONAL = zusätzliche Stunde
            $type=match($cs){
                'CANCEL','CANCELLED','FREE'        => 'cancelled',
                'SUBSTITUTION','ROOMSUBSTITUTION'  => 'substitution',
                'IRREGULAR','ADDITIONAL'           => 'substitution',
                default                            => 'normal',
            };
            // Info-Text aus allen verfügbaren Feldern zusammensetzen
            $infoParts=array_filter([
                trim($p['substText']??''),
                trim($p['periodText']??''),
                trim($p['lessonText']??''),
                trim($p['periodInfo']??''),
            ],function($s){return $s!=='';});
            $info=implode(' · ',$infoParts);

            $days[$date]['date']=$date;
            // FOE-Lektionen ausblenden
            if ($hideFOE && $su === 'FOE') continue;

            // Hausaufgaben für dieses Fach anhängen
            $lessonHW=$hwBySubject[$su]??[];

            $days[$date]['lessons'][]=[
                'startTime'=>fmt_time((int)($p['startTime']??0)),
                'endTime'  =>fmt_time((int)($p['endTime']??0)),
                'subject'  =>$su,'teacher'=>$te,'room'=>$ro,'klasse'=>$kl,
                'type'     =>$type,'info'=>$info,
                'homeworks'=>$lessonHW,
            ];
        }
        ksort($days);
        foreach ($days as &$d) usort($d['lessons'],function($a,$b){return strcmp($a['startTime'],$b['startTime']);});
        $result=array_values($days);
    }

    // ---- Vertretungsplan: getTimetable filtern ----
    if ($action==='substitutions') {
        $ttRaw=rpc($rpcUrl,'getTimetable',['options'=>[
            'startDate'=>(int)$targetDate,'endDate'=>(int)$targetDate,
            'element'=>['type'=>$elType,'id'=>$elId],
            'showLsText'=>true,'showSubstText'=>true,'showInfo'=>true,
            'showLsNumber'=>true,'showStudentgroup'=>false,'showBooking'=>false,
            'klasseFields'=>['id','name'],'roomFields'=>['id','name'],
            'subjectFields'=>['id','name'],'teacherFields'=>['id','name'],
        ]],$cookie);

        $subs=[];
        foreach ($ttRaw as $p) {
            $code=trim((string)($p['code']??''));
            $lstext=trim((string)($p['lstext']??''));
            $substText=trim((string)($p['substText']??''));
            $info=trim((string)($p['info']??''));
            if ($code===''&&$lstext===''&&$substText===''&&$info==='') continue;
            $su=$p['su'][0]['name']??'–';
            $te=implode(', ',array_map(function($t){return $t['name']??'';},$p['te']??[]));
            $ro=$p['ro'][0]['name']??'–';
            $typeLabel=match($code){'cancelled'=>'Entfall','irregular'=>'Vertretung',default=>($lstext||$substText)?'Änderung':'Info'};
            $subs[]=['startTime'=>fmt_time((int)($p['startTime']??0)),'endTime'=>fmt_time((int)($p['endTime']??0)),'subject'=>$su,'teacher'=>$te,'room'=>$ro,'type'=>$code?:'info','typeLabel'=>$typeLabel,'text'=>implode(' · ',array_filter([$lstext,$substText,$info]))];
        }
        usort($subs,function($a,$b){return strcmp($a['startTime'],$b['startTime']);});
        $result=$subs;
    }

    // ---- Nachrichten ----
    if ($action==='news') {
        $raw=http_get($baseUrl.'/api/public/news/newsWidgetData?date='.$targetDate,$cookie);
        $data=json_decode($raw,true);
        $result=['systemMessage'=>$data['data']['systemMessage']??null,'messages'=>array_map(function($m){return ['subject'=>$m['subject']??'','text'=>strip_tags($m['text']??'')];},$data['data']['messagesOfDay']??[])];
    }

    try{rpc($rpcUrl,'logout',[],$cookie);}catch(Throwable){}
    json_response(['ok'=>true,'data'=>$result,'date'=>$targetDate]);

} catch (Throwable $e) {
    try{rpc($rpcUrl,'logout',[],$cookie);}catch(Throwable){}
    json_response(['error'=>$e->getMessage()],500);
}
