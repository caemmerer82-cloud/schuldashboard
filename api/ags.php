<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
$me  = auth_require();
$tid = (int)($me['tenant_id'] ?? 1);
$m   = $_SERVER['REQUEST_METHOD'];

// GET: alle AGs (optionaler Filter ?wochentag=1-5, ?aktiv=1, ?datum=YYYY-MM-DD)
// POST/PUT: AG anlegen/ändern
// DELETE: AG löschen

try {
    if ($m === 'GET') {
        $where  = ['tenant_id = ?'];
        $params = [$tid];

        if (isset($_GET['wochentag'])) {
            $where[] = 'wochentag = ?';
            $params[] = (int)$_GET['wochentag'];
        }
        // Nur aktive AGs die am gegebenen Datum laufen
        if (isset($_GET['datum'])) {
            $datum = $_GET['datum'];
            $where[] = 'aktiv = 1';
            $where[] = 'von_datum <= ?';
            $where[] = 'bis_datum >= ?';
            $params[] = $datum;
            $params[] = $datum;
        } elseif (isset($_GET['aktiv'])) {
            $where[] = 'aktiv = ?';
            $params[] = (int)$_GET['aktiv'];
        }

        $sql = 'SELECT id,name,raum,leitung,wochentag,von_datum,bis_datum,aktiv
                FROM ags WHERE '.implode(' AND ',$where).'
                ORDER BY wochentag ASC, name ASC';
        $s = db()->prepare($sql);
        $s->execute($params);
        json_response($s->fetchAll());
    }

    // Schreiben nur für Admins
    auth_require_role('tenant_admin', 'superadmin');

    if ($m === 'POST') {
        $d = read_json_body();
        $name  = trim((string)($d['name']     ?? ''));
        $raum  = trim((string)($d['raum']     ?? ''));
        $leit  = trim((string)($d['leitung']  ?? ''));
        $wt    = (int)($d['wochentag']        ?? 1);
        $von   = trim((string)($d['von_datum'] ?? ''));
        $bis   = trim((string)($d['bis_datum'] ?? ''));
        $aktiv = isset($d['aktiv']) ? (int)(bool)$d['aktiv'] : 1;

        if ($name === '') json_response(['error' => 'Name ist Pflicht.'], 400);
        if ($wt < 1 || $wt > 5) json_response(['error' => 'Wochentag muss 1–5 sein.'], 400);
        if (!$von || !$bis) json_response(['error' => 'Von- und Bis-Datum sind Pflicht.'], 400);

        db()->prepare('INSERT INTO ags (tenant_id,name,raum,leitung,wochentag,von_datum,bis_datum,aktiv) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$tid,$name,$raum,$leit,$wt,$von,$bis,$aktiv]);
        json_response(['id' => (int)db()->lastInsertId()], 201);
    }

    if ($m === 'PUT' || $m === 'PATCH') {
        $d  = read_json_body();
        $id = (int)($d['id'] ?? 0);
        if ($id <= 0) json_response(['error' => 'id fehlt'], 400);

        // Sicherstellen dass AG zum Mandanten gehört
        $chk = db()->prepare('SELECT id FROM ags WHERE id=? AND tenant_id=?');
        $chk->execute([$id, $tid]);
        if (!$chk->fetch()) json_response(['error' => 'AG nicht gefunden.'], 404);

        $fields = []; $params = [];
        foreach (['name','raum','leitung','von_datum','bis_datum'] as $f) {
            if (isset($d[$f])) { $fields[] = "$f=?"; $params[] = trim((string)$d[$f]); }
        }
        if (isset($d['wochentag'])) {
            $wt = (int)$d['wochentag'];
            if ($wt<1||$wt>5) json_response(['error'=>'Wochentag muss 1–5 sein.'],400);
            $fields[] = 'wochentag=?'; $params[] = $wt;
        }
        if (isset($d['aktiv'])) { $fields[] = 'aktiv=?'; $params[] = (int)(bool)$d['aktiv']; }
        if (!$fields) json_response(['error' => 'Keine Felder.'], 400);

        $params[] = $id;
        db()->prepare('UPDATE ags SET '.implode(',',$fields).' WHERE id=?')->execute($params);
        json_response(['ok' => true]);
    }

    if ($m === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_response(['error' => 'id fehlt'], 400);
        db()->prepare('DELETE FROM ags WHERE id=? AND tenant_id=?')->execute([$id, $tid]);
        json_response(['ok' => true]);
    }

    json_response(['error' => 'Methode nicht erlaubt'], 405);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
