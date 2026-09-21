<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/auth.php';
$me=auth_require();
try{db();}catch(Throwable $e){http_response_code(500);echo '<h1>Datenbank fehlgeschlagen</h1><pre>'.htmlspecialchars($e->getMessage()).'</pre>';exit;}

$tenantId     =(int)($me['tenant_id']??1);
$role         =$me['role']??'user';
$tenantName   =$me['tenant_name']??'';
$isTenantAdmin=in_array($role,['tenant_admin','superadmin'],true);
$isSuperAdmin =($role==='superadmin');
$canDelete    =$isTenantAdmin;
$tid = $tenantId; // Alias

// Module-Einstellungen
$moduleAktiv = json_decode(get_setting('module_aktiv',
    '["hausaufgaben","termine","stundenplan","vertretungsplan","busplan"]', $tenantId), true) ?: [];
$showHausaufgaben    = in_array('hausaufgaben',    $moduleAktiv);
$showTermine         = in_array('termine',         $moduleAktiv);
$showStundenplan     = in_array('stundenplan',     $moduleAktiv);
$showVertretungsplan = in_array('vertretungsplan', $moduleAktiv);
$showBusplan         = in_array('busplan',         $moduleAktiv);

// Kanban-Spalten
$kanbanSpalten = json_decode(get_setting('kanban_spalten',
    '{"todo":{"label":"Offen","aktiv":true},"doing":{"label":"In Arbeit","aktiv":true},"done":{"label":"Erledigt","aktiv":true}}',
    $tenantId), true) ?: ['todo'=>['label'=>'Offen','aktiv'=>true],'doing'=>['label'=>'In Arbeit','aktiv'=>true],'done'=>['label'=>'Erledigt','aktiv'=>true]];

$childName=trim(get_setting('child_name','',$tenantId));
$urlStd   =trim(get_setting('url_stundenplan','',$tenantId));
$urlVer   =trim(get_setting('url_vertretungsplan','',$tenantId));
$urlBus   =trim(get_setting('url_busfahrplan','',$tenantId));

// Untis konfiguriert?
$untisServer=trim(get_setting('untis_server','',$tenantId));
$untisActive=($untisServer!=='');

function iframe_src_for(string $url,string $key): string {
    $path=parse_url($url,PHP_URL_PATH)??'';$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
    if ($ext==='pdf') return 'api/proxy.php?key='.rawurlencode($key).'&v='.substr(md5($url),0,8).'#zoom=page-width';
    return $url;
}
?><!DOCTYPE html>
<html lang="de"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Schul-Dashboard<?php echo $childName!=='' ? ' – '.htmlspecialchars($childName) : ''; ?></title>
<link rel="stylesheet" href="assets/css/styles.css">
</head><body>
<header class="topbar"><div class="topbar-inner">
    <h1><span class="logo">📚</span> Schul-Dashboard<?php echo $childName!=='' ? ' – '.htmlspecialchars($childName) : ''; ?>
        <?php if($tenantName):?><span class="tenant-badge"><?=htmlspecialchars($tenantName)?></span><?php endif;?>
    </h1>
    <nav>
        <?php if($isSuperAdmin):?><a href="admin.php" class="btn btn-ghost">🛡️ Super-Admin</a><?php endif;?>
        <?php if($isTenantAdmin):?><a href="verwaltung.php" class="btn btn-ghost">📋 Verwaltung</a><?php endif;?>
        <a href="settings.php" class="btn btn-ghost">⚙️ Einstellungen</a>
        <span class="topbar-user">👤 <?=htmlspecialchars($me['username'])?></span>
        <a href="logout.php" class="btn btn-ghost">Abmelden</a>
    </nav>
</div></header>

<main class="dashboard">

<!-- Hausaufgaben Kanban -->
<?php if($showHausaufgaben): ?>
<section class="widget widget-kanban" aria-labelledby="kanban-title">
    <div class="widget-header"><h2 id="kanban-title">🗒️ Hausaufgaben</h2><button class="btn btn-primary" id="btn-new-homework">+ Neue Aufgabe</button></div>
    <div class="filter-bar">
        <label class="filter-label"><span>Fach:</span><select id="hw-filter-subject" class="filter-select"><option value="">Alle</option></select></label>
        <div class="filter-group" id="hw-filter-urgency" role="group" aria-label="Dringlichkeit">
            <span class="filter-label-text">Dringlichkeit:</span>
            <button type="button" class="chip chip-filter active" data-urgency="all">Alle</button>
            <button type="button" class="chip chip-filter chip-overdue" data-urgency="urgency-overdue">überfällig</button>
            <button type="button" class="chip chip-filter chip-urgent" data-urgency="urgency-urgent">morgen fällig</button>
            <button type="button" class="chip chip-filter chip-soon" data-urgency="urgency-soon">bald fällig</button>
            <button type="button" class="chip chip-filter chip-ok" data-urgency="urgency-ok">noch Zeit</button>
            <button type="button" class="chip chip-filter chip-done" data-urgency="urgency-done">erledigt</button>
        </div>
    </div>
    <div class="kanban" id="kanban">
        <?php foreach(['todo','doing','done'] as $kk):
    $kcfg=$kanbanSpalten[$kk]??['label'=>['todo'=>'Offen','doing'=>'In Arbeit','done'=>'Erledigt'][$kk],'aktiv'=>true];
    if(!($kcfg['aktiv']??true)) continue;
    $klabel=htmlspecialchars($kcfg['label']??$kk);
    $dots=['todo'=>'dot-todo','doing'=>'dot-doing','done'=>'dot-done'];
?>
    <div class="kanban-column" data-status="<?=$kk?>"><header><span class="dot <?=$dots[$kk]?>"></span> <?=$klabel?> <span class="count" data-count-for="<?=$kk?>">0</span></header><div class="kanban-cards" data-drop="<?=$kk?>"></div></div>
<?php endforeach;?>
    </div>
</section>
<?php endif; ?>

<!-- Termine -->
<?php if($showTermine): ?>
<section class="widget widget-events" aria-labelledby="events-title">
    <div class="widget-header"><h2 id="events-title">📅 Termine &amp; Klausuren</h2><button class="btn btn-primary" id="btn-new-event">+ Neuer Termin</button></div>
    <div class="filter-bar">
        <div class="filter-group" id="ev-filter-type" role="group" aria-label="Kategorien">
            <span class="filter-label-text">Kategorie:</span>
            <button type="button" class="chip chip-filter active" data-type="all">Alle</button>
            <button type="button" class="chip chip-filter chip-exam" data-type="klausur">Klausur</button>
            <button type="button" class="chip chip-filter chip-test" data-type="test">Test</button>
            <button type="button" class="chip chip-filter chip-pruefung" data-type="pruefung">Prüfung</button>
            <button type="button" class="chip chip-filter chip-veranstaltung" data-type="veranstaltung">Veranstaltung</button>
            <button type="button" class="chip chip-filter chip-sonstiges" data-type="sonstiges">Sonstiges</button>
        </div>
    </div>
    <ul class="event-list" id="event-list"></ul>
</section>
<?php endif; ?>

</main>

<?php if($untisActive): ?>

<?php if($showStundenplan): ?>
<div class="untis-full-row">
<section class="widget widget-untis-full" aria-labelledby="untis-stundenplan-title">
    <div class="widget-header">
        <h2 id="untis-stundenplan-title">🕒 Stundenplan</h2>
        <a href="stundenplan.php" target="_blank" class="btn btn-ghost" style="white-space:nowrap">⛶ Vollbild</a>
        <div class="untis-nav">
            <button class="btn btn-ghost" id="untis-tt-prev">‹ Vorwoche</button>
            <span class="untis-date-label" id="untis-tt-label">–</span>
            <button class="btn btn-ghost" id="untis-tt-next">Nächste Woche ›</button>
        </div>
    </div>
    <div id="untis-tt-content"><p class="untis-empty">Wird geladen…</p></div>
    <div class="untis-legend">
        <span class="untis-legend-item"><span class="untis-legend-dot" style="background:#dbeafe;border-left:4px solid #2563eb"></span> Regulär</span>
        <span class="untis-legend-item"><span class="untis-legend-dot" style="background:#fef3c7;border-left:4px solid #f59e0b"></span> Vertretung</span>
        <span class="untis-legend-item"><span class="untis-legend-dot" style="background:#fee2e2;border-left:4px solid #dc2626"></span> Entfall</span>
        <span class="untis-legend-item" style="margin-left:auto;color:#94a3b8;font-size:11px">🔄 Auto-Update alle 10 min</span>
    </div>
</section>
</div>
<?php endif; /* showStundenplan */ ?>

<?php if($showVertretungsplan): ?>
<div class="untis-full-row">
<section class="widget widget-untis-full" aria-labelledby="untis-subst-title">
    <div class="widget-header">
        <h2 id="untis-subst-title">🔁 Vertretungsplan</h2>
        <div class="untis-nav">
            <button class="btn btn-ghost" id="untis-subst-prev">‹ Vorwoche</button>
            <span class="untis-date-label" id="untis-subst-label">–</span>
            <button class="btn btn-ghost" id="untis-subst-next">Nächste Woche ›</button>
        </div>
    </div>
    <div id="untis-subst-content"><p class="untis-empty">Wird geladen…</p></div>
    <div class="untis-legend">
        <span class="untis-legend-item"><span class="untis-legend-dot" style="background:#fef3c7;border-left:4px solid #f59e0b"></span> Vertretung / Änderung</span>
        <span class="untis-legend-item"><span class="untis-legend-dot" style="background:#fee2e2;border-left:4px solid #dc2626"></span> Entfall</span>
        <span class="untis-legend-item">⚡ = Tag mit Änderungen</span>
    </div>
</section>
</div>
<?php endif; /* showVertretungsplan */ ?>

<?php else: /* kein Untis – Iframe-Fallback */ ?>

<?php if($showStundenplan): ?>
<div class="untis-full-row">
<section class="widget widget-untis-full" aria-labelledby="stundenplan-title">
    <div class="widget-header"><h2 id="stundenplan-title">🕒 Stundenplan</h2>
        <?php if($urlStd!==''):?><a href="<?=htmlspecialchars($urlStd)?>" target="_blank" rel="noopener" class="btn btn-ghost">↗ Öffnen</a><?php endif;?>
    </div>
    <?php if($urlStd!==''):?>
        <iframe src="<?=htmlspecialchars(iframe_src_for($urlStd,'stundenplan'))?>" loading="lazy" title="Stundenplan" style="width:100%;min-height:420px;border:1px solid var(--border);border-radius:var(--radius-sm)"></iframe>
    <?php else:?><p class="empty">Keine URL hinterlegt.</p><?php endif;?>
</section>
</div>
<?php endif; /* showStundenplan */ ?>

<?php if($showVertretungsplan): ?>
<div class="untis-full-row">
<section class="widget widget-untis-full" aria-labelledby="vertretung-title">
    <div class="widget-header"><h2 id="vertretung-title">🔁 Vertretungsplan</h2>
        <?php if($urlVer!==''):?><a href="<?=htmlspecialchars($urlVer)?>" target="_blank" rel="noopener" class="btn btn-ghost">↗ Öffnen</a><?php endif;?>
    </div>
    <?php if($urlVer!==''):?>
        <iframe src="<?=htmlspecialchars(iframe_src_for($urlVer,'vertretungsplan'))?>" loading="lazy" title="Vertretungsplan" style="width:100%;min-height:420px;border:1px solid var(--border);border-radius:var(--radius-sm)"></iframe>
    <?php else:?><p class="empty">Keine URL hinterlegt.</p><?php endif;?>
</section>
</div>
<?php endif; /* showVertretungsplan */ ?>

<?php endif; /* untisActive */ ?>

<?php if($showBusplan): ?>
<div class="untis-full-row">
<section class="widget widget-untis-full" aria-labelledby="bus-title">
    <div class="widget-header"><h2 id="bus-title">🚌 Busfahrplan</h2>
        <?php if($urlBus!==''):?><a href="<?=htmlspecialchars($urlBus)?>" target="_blank" rel="noopener" class="btn btn-ghost">↗ Öffnen</a><?php endif;?>
    </div>
    <?php if($urlBus!==''):?>
        <iframe src="<?=htmlspecialchars(iframe_src_for($urlBus,'busfahrplan'))?>" loading="lazy" title="Busfahrplan" style="width:100%;min-height:420px;border:1px solid var(--border);border-radius:var(--radius-sm)"></iframe>
    <?php else:?><p class="empty">Keine URL hinterlegt.<?php echo $isTenantAdmin ? ' Bitte unter <a href="settings.php">Einstellungen</a> eintragen.' : ''; ?></p><?php endif;?>
</section>
</div>
<?php endif; /* showBusplan */ ?>

<!-- Dialog: Hausaufgabe -->
<dialog id="dlg-homework"><form method="dialog" class="modal-form" id="form-homework">
    <h3 id="dlg-homework-title">Neue Hausaufgabe</h3><input type="hidden" name="id" value="">
    <label>Fach<input type="text" name="subject" required maxlength="100" placeholder="z.B. Mathe, Deutsch"></label>
    <label>Titel<input type="text" name="title" required maxlength="255" placeholder="z.B. Arbeitsblatt S. 42"></label>
    <label>Beschreibung (optional)<textarea name="description" rows="3" placeholder="Details…"></textarea></label>
    <div class="row">
        <label>Abgabedatum<input type="date" name="due_date" required></label>
        <label>Status<select name="status"><option value="todo">Offen</option><option value="doing">In Arbeit</option><option value="done">Erledigt</option></select></label>
    </div>
    <menu class="modal-actions">
        <button type="button" class="btn btn-danger" data-action="delete" hidden>Löschen</button>
        <span class="spacer"></span>
        <button type="button" class="btn btn-ghost" data-action="cancel">Abbrechen</button>
        <button type="submit" class="btn btn-primary">Speichern</button>
    </menu>
</form></dialog>

<!-- Dialog: Termin -->
<dialog id="dlg-event"><form method="dialog" class="modal-form" id="form-event">
    <h3 id="dlg-event-title">Neuer Termin</h3><input type="hidden" name="id" value="">
    <label>Art des Termins<select name="event_type" required><option value="klausur">Klausur</option><option value="test">Test</option><option value="pruefung">Prüfung</option><option value="veranstaltung">Veranstaltung</option><option value="sonstiges">Sonstiges</option></select></label>
    <label>Titel<input type="text" name="title" required maxlength="255" placeholder="z.B. Mathe-Klausur Kapitel 4"></label>
    <label>Datum<input type="date" name="event_date" required></label>
    <label>Bemerkungen (optional)<textarea name="notes" rows="3" placeholder="Themen, Raum…"></textarea></label>
    <menu class="modal-actions">
        <button type="button" class="btn btn-danger" data-action="delete" hidden>Löschen</button>
        <span class="spacer"></span>
        <button type="button" class="btn btn-ghost" data-action="cancel">Abbrechen</button>
        <button type="submit" class="btn btn-primary">Speichern</button>
    </menu>
</form></dialog>

<script>window.KANBAN_SPALTEN = <?php
$ks_out=[];
foreach($kanbanSpalten as $k=>$v){
    $ks_out[]=['key'=>$k,'label'=>$v['label']??$k,'aktiv'=>(bool)($v['aktiv']??true)];
}
echo json_encode($ks_out);
?>;
    window.DASHBOARD_CAN_DELETE=<?php echo $canDelete ? 'true' : 'false'; ?>;</script>
<script src="assets/js/app.js?v=1788459433" defer></script>
<?php if($untisActive): ?>
<script src="assets/js/untis.js?v=1788459433" defer></script>
<?php endif; ?>
</body></html>
