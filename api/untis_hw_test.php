<?php
// Gibt den fertigen timetable-Response aus und zeigt ob homeworks drin sind
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
$me = auth_user();
if (!$me) { http_response_code(401); echo '{"error":"nicht eingeloggt"}'; exit; }

// Normalen timetable-Endpunkt aufrufen
$date = $_GET['date'] ?? date('Ymd');
// monday of that week
$ts = strtotime(substr($date,0,4).'-'.substr($date,4,2).'-'.substr($date,6,2));
$mon = date('Ymd', strtotime('monday this week', $ts));

// Gleichen Code wie untis.php verwenden aber Ausgabe abfangen
ob_start();
$_GET['action'] = 'timetable';
$_GET['date'] = $mon;
include __DIR__ . '/untis.php';
$raw = ob_get_clean();

$data = json_decode($raw, true);

// Nur MATE-Lektionen mit homeworks zeigen
$mateWithHW = [];
foreach ($data['data'] ?? [] as $day) {
    foreach ($day['lessons'] ?? [] as $l) {
        if ($l['subject'] === 'MATE') {
            $mateWithHW[] = [
                'date'      => $day['date'],
                'start'     => $l['startTime'],
                'subject'   => $l['subject'],
                'homeworks' => $l['homeworks'] ?? 'NICHT VORHANDEN',
            ];
        }
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'monday' => $mon,
    'mate_lessons' => $mateWithHW,
    'raw_first_100_chars' => substr($raw, 0, 200),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
