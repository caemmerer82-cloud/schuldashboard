<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/auth.php';
$me = auth_require();
$tid = (int)($me['tenant_id'] ?? 1);

// Hausaufgaben laden
$hw = db()->prepare("SELECT id, subject, title, status FROM homework WHERE tenant_id=? AND status != 'done' ORDER BY subject");
$hw->execute([$tid]);
$hausaufgaben = $hw->fetchAll();

// Fächer laden
$fq = db()->prepare("SELECT kuerzel, vollname FROM faecher WHERE tenant_id=? ORDER BY kuerzel");
$fq->execute([$tid]);
$faecher = $fq->fetchAll();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'hausaufgaben' => $hausaufgaben,
    'faecher'      => $faecher,
    'hinweis'      => 'hwFor vergleicht subject aus Hausaufgaben mit Kürzel/Vollname aus Fächer-Tabelle'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
