<?php
/**
 * PDF-/Datei-Proxy.
 *
 * Zweck: Viele Hoster senden X-Frame-Options: SAMEORIGIN oder CSP-Header,
 * die ein direktes <iframe src="..."> verhindern. Dieser Proxy lädt die
 * konfigurierte Datei serverseitig und streamt sie ohne blockierende
 * Header weiter, sodass das Widget PDFs (und andere einbettbare Dateien)
 * anzeigen kann.
 *
 * Sicherheit: Statt eine beliebige URL anzunehmen (SSRF-Risiko), akzeptiert
 * der Proxy nur einen Schlüssel aus einer Whitelist und liest die zugehörige
 * URL aus den Einstellungen.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
auth_require();

$ALLOWED_KEYS = [
    'stundenplan'     => 'url_stundenplan',
    'vertretungsplan' => 'url_vertretungsplan',
    'busfahrplan'     => 'url_busfahrplan',
];

$key = isset($_GET['key']) ? (string)$_GET['key'] : '';
if (!isset($ALLOWED_KEYS[$key])) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unbekannter Schlüssel.';
    exit;
}

$url = trim(get_setting($ALLOWED_KEYS[$key], ''));
if ($url === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Keine URL hinterlegt.';
    exit;
}

$scheme = parse_url($url, PHP_URL_SCHEME);
if ($scheme !== 'http' && $scheme !== 'https') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nur http/https erlaubt.';
    exit;
}

// Content-Type aus Dateiendung ableiten (Fallback: PDF)
$path = parse_url($url, PHP_URL_PATH) ?? '';
$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml',
    'txt'  => 'text/plain; charset=utf-8',
    'html' => 'text/html; charset=utf-8',
    'htm'  => 'text/html; charset=utf-8',
];
$contentType = $mimeMap[$ext] ?? 'application/pdf';

$serverType = null;
$serverLen  = null;
$body       = null;
$status     = 0;
$fetchError = null;

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_MAXREDIRS       => 5,
        CURLOPT_CONNECTTIMEOUT  => 10,
        CURLOPT_TIMEOUT         => 30,
        CURLOPT_USERAGENT       => 'Schul-Dashboard/1.0',
        CURLOPT_HEADERFUNCTION  => function ($c, $header) use (&$serverType, &$serverLen) {
            if (stripos($header, 'Content-Type:') === 0) {
                $serverType = trim(substr($header, 13));
            } elseif (stripos($header, 'Content-Length:') === 0) {
                $serverLen = trim(substr($header, 15));
            }
            return strlen($header);
        },
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $fetchError = curl_error($ch);
    } else {
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    }
    curl_close($ch);
} elseif (ini_get('allow_url_fopen')) {
    // Fallback ohne curl: file_get_contents + $http_response_header
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'follow_location' => 1,
            'max_redirects' => 5,
            'timeout'       => 30,
            'header'        => "User-Agent: Schul-Dashboard/1.0\r\n",
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        $err = error_get_last();
        $fetchError = $err['message'] ?? 'Unbekannter Fehler';
    } else {
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
                    $status = (int)$m[1];
                } elseif (stripos($h, 'Content-Type:') === 0) {
                    $serverType = trim(substr($h, 13));
                } elseif (stripos($h, 'Content-Length:') === 0) {
                    $serverLen = trim(substr($h, 15));
                }
            }
        }
    }
} else {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Weder PHP-curl noch allow_url_fopen verfügbar. Bitte in der php.ini aktivieren.';
    exit;
}

if ($fetchError !== null || $body === false || $body === null) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Fehler beim Laden: ' . ($fetchError ?? 'leere Antwort');
    exit;
}

if ($status && ($status < 200 || $status >= 400)) {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Quelle antwortete mit HTTP ' . $status;
    exit;
}

// Content-Type: wenn der Server einen plausiblen sendet, den nehmen;
// sonst Fallback anhand der Dateiendung.
if ($serverType && (
    stripos($serverType, 'application/pdf') !== false ||
    stripos($serverType, 'image/')          !== false ||
    stripos($serverType, 'text/plain')      !== false
)) {
    header('Content-Type: ' . $serverType);
} else {
    header('Content-Type: ' . $contentType);
}

// Inline statt Download, damit das iframe rendert.
$filename = basename($path) ?: ($key . '.pdf');
header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');

if ($serverLen !== null && ctype_digit($serverLen)) {
    header('Content-Length: ' . $serverLen);
}

echo $body;
