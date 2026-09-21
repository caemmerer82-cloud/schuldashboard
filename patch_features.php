<?php
/**
 * EINMAL-PATCH: Drei neue Features
 *  1. FOE-Filter (Einstellung in Verwaltung)
 *  2. HW → Kanban-Button im Stundenplan-Popup (untis.js + stundenplan.php)
 *  3. Klausuren/Tests im Stundenplan anzeigen
 *
 * Aufruf: https://schule.familiecaemmerer.de/patch_features.php
 * Nach dem Ausführen bitte löschen!
 */

define('BASE', __DIR__);
$results = [];

function p(string $label, bool $ok, string $extra = ''): void {
    global $results;
    $results[] = [$ok ? '✅' : '❌', $label, $extra];
}

function applyPatch(string $file, string $label, string $old, string $new): void {
    $path = BASE . '/' . $file;
    if (!file_exists($path)) { p($label, false, "Datei fehlt: $file"); return; }
    $src = file_get_contents($path);
    if (strpos($src, $new) !== false) { p("$label (bereits gepatcht)", true); return; }
    if (strpos($src, $old) === false) { p($label, false, "Suchmuster nicht gefunden"); return; }
    file_put_contents($path, str_replace($old, $new, $src));
    p($label, true);
}

// ─────────────────────────────────────────────────────────
// 1. api/settings.php – hide_foe zu allowedKeys
// ─────────────────────────────────────────────────────────
applyPatch(
    'api/settings.php',
    'settings.php: hide_foe in allowedKeys',
    "'untis_server','untis_school','untis_username','untis_password'",
    "'untis_server','untis_school','untis_username','untis_password','hide_foe'"
);

// ─────────────────────────────────────────────────────────
// 2. api/untis.php – FOE-Filter laden & anwenden
// ─────────────────────────────────────────────────────────
// 2a. Setting am Anfang der Funktion laden
applyPatch(
    'api/untis.php',
    'untis.php: hideFOE Setting laden',
    '// Stundenplan-Daten' . "\n" . '        $ttRaw=rpc(',
    '// FOE-Filter' . "\n" . '        $hideFOE = (get_setting(\'hide_foe\',\'0\',$tid) === \'1\');' . "\n\n" . '        // Stundenplan-Daten' . "\n" . '        $ttRaw=rpc('
);

// 2b. FOE-Lektion überspringen (kommt direkt vor "Hausaufgaben für dieses Fach")
applyPatch(
    'api/untis.php',
    'untis.php: FOE-Lektion überspringen',
    '// Hausaufgaben für dieses Fach anhängen' . "\n" . '            $lessonHW=$hwBySubject[$su]??[];',
    '// FOE-Lektionen ausblenden' . "\n" . '            if ($hideFOE && $su === \'FOE\') continue;' . "\n\n" . '            // Hausaufgaben für dieses Fach anhängen' . "\n" . '            $lessonHW=$hwBySubject[$su]??[];'
);

// ─────────────────────────────────────────────────────────
// 3. verwaltung.php – FOE-Filter Checkbox
// ─────────────────────────────────────────────────────────
// 3a. HTML-Sektion nach dem Modul-Bereich einfügen
applyPatch(
    'verwaltung.php',
    'verwaltung.php: FOE-Filter Sektion HTML',
    '<!-- ===== 2. Kanban-Spalten =====',
    '<!-- ===== 1b. Stundenplan-Optionen ===== -->' . "\n" . '    <section class="settings-form" style="margin-top:0">' . "\n" . '        <h2>🎓 Stundenplan-Optionen</h2>' . "\n" . '        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px">' . "\n" . '            <input type="checkbox" id="cbHideFOE" onchange="saveHideFOE(this.checked)" style="width:18px;height:18px;cursor:pointer">' . "\n" . '            <span>FOE-Stunden im Stundenplan ausblenden</span>' . "\n" . '        </label>' . "\n" . '        <div id="foe-msg" class="vw-ok" style="margin-top:8px"></div>' . "\n" . '    </section>' . "\n\n" . '    <!-- ===== 2. Kanban-Spalten ====='
);

// 3b. JS-Funktion saveHideFOE hinzufügen
applyPatch(
    'verwaltung.php',
    'verwaltung.php: saveHideFOE Funktion',
    'function saveModules()',
    'async function saveHideFOE(val) {' . "\n" . '        const r = await fetch(\'api/settings.php\', { method:\'POST\', headers:{\'Content-Type\':\'application/json\'}, body: JSON.stringify({key:\'hide_foe\', value: val ? \'1\' : \'0\'}) });' . "\n" . '        const el = document.getElementById(\'foe-msg\');' . "\n" . '        el.textContent = r.ok ? \'Gespeichert ✓\' : \'Fehler\';' . "\n" . '        setTimeout(() => el.textContent = \'\', 2500);' . "\n" . '    }' . "\n\n" . '    function saveModules()'
);

// 3c. Checkbox beim Seitenstart befüllen
// Wir hängen die Initialisierung an den bestehenden fetch-Block an
// Es gibt bereits ein fetch('api/settings.php').then(...) für andere Settings
// Wir fügen DAVOR eine eigene Initialisierung ein
applyPatch(
    'verwaltung.php',
    'verwaltung.php: FOE-Checkbox initialisieren',
    '    window.addEventListener(\'load\', function() {',
    '    window.addEventListener(\'load\', function() {' . "\n" . '        // FOE-Status laden' . "\n" . '        fetch(\'api/settings.php\').then(r=>r.json()).then(s=>{ const cb=document.getElementById(\'cbHideFOE\'); if(cb) cb.checked=(s.hide_foe===\'1\'); }).catch(()=>{});'
);

// ─────────────────────────────────────────────────────────
// 4. assets/js/untis.js – HW→Kanban + Klausuren
// ─────────────────────────────────────────────────────────

// 4a. eventsCache und loadEvents() nach dem stores-Array einfügen
applyPatch(
    'assets/js/untis.js',
    'untis.js: eventsCache + loadEvents()',
    'let stores  = { tt: [], subst: [] };',
    'let stores  = { tt: [], subst: [] };' . "\n" . 'let eventsCache = null;' . "\n" . 'async function loadEvents() {' . "\n" . '    if (eventsCache) return eventsCache;' . "\n" . '    try { const r = await fetch(\'api/events.php\'); eventsCache = await r.json(); }' . "\n" . '    catch(e) { eventsCache = []; }' . "\n" . '    return eventsCache;' . "\n" . '}'
);

// 4b. Hilfsfunktion getKlausurenForDay und addHWtoKanban vor openPopup einfügen
applyPatch(
    'assets/js/untis.js',
    'untis.js: getKlausurenForDay + addHWtoKanban',
    '    // Popup öffnen',
    '    // Klausuren eines Tages ermitteln' . "\n" . '    function getKlausurenForDay(events, ymd) {' . "\n" . '        const ds = ymd.slice(0,4)+\'-\'+ymd.slice(4,6)+\'-\'+ymd.slice(6,8);' . "\n" . '        return (events||[]).filter(function(e) {' . "\n" . '            return [\'klausur\',\'test\',\'pruefung\'].indexOf((e.event_type||"").toLowerCase()) >= 0' . "\n" . '                && e.event_date === ds;' . "\n" . '        });' . "\n" . '    }' . "\n\n" . '    // Hausaufgabe ins Kanban übertragen' . "\n" . '    window.addHWtoKanban = async function(text, subject, dueISO, btn) {' . "\n" . '        btn.disabled = true; btn.textContent = \'…\';' . "\n" . '        try {' . "\n" . '            const r = await fetch(\'api/homework.php\', {' . "\n" . '                method: \'POST\',' . "\n" . '                headers: {\'Content-Type\':\'application/json\'},' . "\n" . '                body: JSON.stringify({ subject: subject, title: text, description: \'\', due_date: dueISO || new Date().toISOString().slice(0,10), status: \'todo\' })' . "\n" . '            });' . "\n" . '            if (r.ok) { btn.textContent = \'✓\'; btn.style.background = \'#16a34a\'; }' . "\n" . '            else { btn.textContent = \'✗\'; btn.style.background = \'#dc2626\'; btn.disabled = false; }' . "\n" . '        } catch(e) { btn.textContent = \'✗\'; btn.style.background = \'#dc2626\'; btn.disabled = false; }' . "\n" . '    };' . "\n\n" . '    // Popup öffnen'
);

// 4c. Events in renderGrid laden (nach ensureLookup)
applyPatch(
    'assets/js/untis.js',
    'untis.js: Events in renderGrid laden',
    '    async function renderGrid(days, storeKey) {' . "\n" . '        await ensureLookup();',
    '    async function renderGrid(days, storeKey) {' . "\n" . '        await ensureLookup();' . "\n" . '        const ttEvents = await loadEvents();'
);

// 4d. Klausur-Badge im Spalten-Header (nach "const th = document.createElement('th');")
// Der aktuelle Code setzt th.textContent = dayLabel; – wir ersetzen das
applyPatch(
    'assets/js/untis.js',
    'untis.js: Klausur-Badge im Tag-Header',
    '        th.textContent = dayLabel;',
    '        const klausuren = getKlausurenForDay(ttEvents, y);' . "\n" . '        if (klausuren.length) {' . "\n" . '            th.innerHTML = \'<div>\' + dayLabel + \'</div>\' + klausuren.map(function(k) {' . "\n" . '                return \'<span class="untis-klausur-badge" title="\' + (k.title||\'Klausur\') + \'">📝 \' + esc(k.title||\'Klausur\') + \'</span>\';' . "\n" . '            }).join(\'\');' . "\n" . '        } else {' . "\n" . '            th.textContent = dayLabel;' . "\n" . '        }'
);

// 4e. Kanban-Button in der HW-Reihe des Popups (untis.js)
// Aktuell: hwRows = hws.map(hw => { ... return `<div...>...${esc(hw.text||'')}...${due?...:''}...</div>`; }).join('');
// Wir fügen einen Button in die Reihe ein
applyPatch(
    'assets/js/untis.js',
    'untis.js: Kanban-Button im HW-Popup',
    '                    ${due?`<span style="font-size:11px;color:#7c3aed;white-space:nowrap">fällig ${due}</span>`:\'\'}' . "\n" . '                </div>' . "\n" . '            </div>`;' . "\n" . '        }).join(\'\');',
    '                    ${due?`<span style="font-size:11px;color:#7c3aed;white-space:nowrap">fällig ${due}</span>`:\'\'}' . "\n" . '                    <button onclick="addHWtoKanban(this.dataset.text,this.dataset.sub,this.dataset.due,this)" data-text="${encodeURIComponent(hw.text||\'\')}\"' . "\n" . '                        data-sub="${encodeURIComponent(resolveFach(item.l.subject||\'\')||item.l.subject||\'\')}\"' . "\n" . '                        data-due="${hw.dueDate?String(hw.dueDate).replace(/^(\\d{4})(\\d{2})(\\d{2})$/,\'$1-$2-$3\'):\'\'}"' . "\n" . '                        style="font-size:10px;padding:2px 8px;background:#7c3aed;color:#fff;border:none;border-radius:4px;cursor:pointer;flex-shrink:0">+ Kanban</button>' . "\n" . '                </div>' . "\n" . '            </div>`;' . "\n" . '        }).join(\'\');'
);

// ─────────────────────────────────────────────────────────
// 5. stundenplan.php – HW→Kanban + Klausuren
// ─────────────────────────────────────────────────────────

// 5a. addHWtoKanban Funktion nach der esc()-Funktion einfügen
applyPatch(
    'stundenplan.php',
    'stundenplan.php: addHWtoKanban + Klausuren-Hilfsfunktionen',
    '    function esc(s) { return String(s).replace(/&/g,\'&amp;\').replace(/</g,\'&lt;\').replace(/>/g,\'&gt;\').replace(/"/g,\'&quot;\'); }',
    '    function esc(s) { return String(s).replace(/&/g,\'&amp;\').replace(/</g,\'&lt;\').replace(/>/g,\'&gt;\').replace(/"/g,\'&quot;\'); }' . "\n\n" . '    async function addHWtoKanban(text, subject, dueISO, btn) {' . "\n" . '        btn.disabled = true; btn.textContent = \'…\';' . "\n" . '        try {' . "\n" . '            const r = await fetch(\'api/homework.php\', { method:\'POST\', headers:{\'Content-Type\':\'application/json\'},' . "\n" . '                body: JSON.stringify({ subject, title: text, description: \'\', due_date: dueISO || new Date().toISOString().slice(0,10), status: \'todo\' }) });' . "\n" . '            if (r.ok) { btn.textContent = \'✓\'; btn.style.background = \'#16a34a\'; }' . "\n" . '            else { btn.textContent = \'✗\'; btn.style.background = \'#dc2626\'; btn.disabled = false; }' . "\n" . '        } catch(e) { btn.textContent = \'✗\'; btn.style.background = \'#dc2626\'; btn.disabled = false; }' . "\n" . '    }' . "\n\n" . '    let spEventsCache = null;' . "\n" . '    async function loadSPEvents() {' . "\n" . '        if (spEventsCache) return spEventsCache;' . "\n" . '        try { const r = await fetch(\'api/events.php\'); spEventsCache = await r.json(); } catch(e) { spEventsCache = []; }' . "\n" . '        return spEventsCache;' . "\n" . '    }' . "\n" . '    function spKlausurenForDay(events, ymd) {' . "\n" . '        const ds = ymd.slice(0,4)+\'-\'+ymd.slice(4,6)+\'-\'+ymd.slice(6,8);' . "\n" . '        return (events||[]).filter(function(e) { return [\'klausur\',\'test\',\'pruefung\'].indexOf((e.event_type||"").toLowerCase())>=0 && e.event_date===ds; });' . "\n" . '    }'
);

// 5b. Events in renderTable laden
applyPatch(
    'stundenplan.php',
    'stundenplan.php: Events in renderTable laden',
    '    async function renderTable(days) {' . "\n" . '        lessonStore = [];',
    '    async function renderTable(days) {' . "\n" . '        lessonStore = [];' . "\n" . '        const spEvents = await loadSPEvents();'
);

// 5c. Klausur-Badge im Tages-Header (wo thDay.textContent = label steht)
applyPatch(
    'stundenplan.php',
    'stundenplan.php: Klausur-Badge im Tag-Header',
    '        thDay.textContent = label;',
    '        const spKlaus = spKlausurenForDay(spEvents, y);' . "\n" . '        if (spKlaus.length) {' . "\n" . '            thDay.innerHTML = \'<div>\'+label+\'</div>\'+spKlaus.map(function(k){ return \'<div class="sp-klausur-badge">📝 \'+esc(k.title||\'Klausur\')+\'</div>\'; }).join(\'\');' . "\n" . '        } else { thDay.textContent = label; }'
);

// 5d. Kanban-Button in der HW-Reihe (stundenplan.php)
// Aktuell in stundenplan.php: hwRows = hws.map(hw => { return `<div...>...${esc(hw.text||'')}...${due?...:''}...</div>`; }).join('');
applyPatch(
    'stundenplan.php',
    'stundenplan.php: Kanban-Button im HW-Popup',
    '                ${due?`<span style="font-size:11px;color:#7c3aed;white-space:nowrap">fällig ${due}</span>`:\'\'}' . "\n" . '            </div>' . "\n" . '        </div>`;' . "\n" . '        }).join(\'\');',
    '                ${due?`<span style="font-size:11px;color:#7c3aed;white-space:nowrap">fällig ${due}</span>`:\'\'}' . "\n" . '                <button onclick="addHWtoKanban(decodeURIComponent(this.dataset.text),decodeURIComponent(this.dataset.sub),this.dataset.due,this)"' . "\n" . '                    data-text="${encodeURIComponent(hw.text||\'\')}\" data-sub="${encodeURIComponent((window.FAECHER_MAP&&window.FAECHER_MAP[l.subject])||l.subject||\'\')}\"' . "\n" . '                    data-due="${hw.dueDate?String(hw.dueDate).replace(/^(\\d{4})(\\d{2})(\\d{2})$/,\'$1-$2-$3\'):\'\'}"' . "\n" . '                    style="font-size:10px;padding:2px 8px;background:#7c3aed;color:#fff;border:none;border-radius:4px;cursor:pointer;flex-shrink:0">+ Kanban</button>' . "\n" . '            </div>' . "\n" . '        </div>`;' . "\n" . '        }).join(\'\');'
);

// ─────────────────────────────────────────────────────────
// 6. assets/css/styles.css – Klausur-Badge CSS
// ─────────────────────────────────────────────────────────
$cssPath = BASE . '/assets/css/styles.css';
if (file_exists($cssPath)) {
    $css = file_get_contents($cssPath);
    $newCss = '.untis-klausur-badge, .sp-klausur-badge {' . "\n" .
              '    display: inline-block;' . "\n" .
              '    background: #dc2626;' . "\n" .
              '    color: #fff;' . "\n" .
              '    font-size: 10px;' . "\n" .
              '    font-weight: 700;' . "\n" .
              '    padding: 2px 6px;' . "\n" .
              '    border-radius: 4px;' . "\n" .
              '    margin-top: 3px;' . "\n" .
              '    white-space: nowrap;' . "\n" .
              '    overflow: hidden;' . "\n" .
              '    text-overflow: ellipsis;' . "\n" .
              '    max-width: 160px;' . "\n" .
              '    vertical-align: middle;' . "\n" .
              '}';
    if (strpos($css, '.untis-klausur-badge') === false) {
        file_put_contents($cssPath, $css . "\n\n" . $newCss . "\n");
        p('styles.css: Klausur-Badge CSS', true);
    } else {
        p('styles.css: Klausur-Badge CSS (bereits vorhanden)', true);
    }
} else {
    p('styles.css', false, 'Datei nicht gefunden');
}

// ─────────────────────────────────────────────────────────
// addHWtoKanban in untis.js braucht decodeURIComponent für dataset.text
// Fügen wir die Umwandlung in der Funktion selbst durch:
// ─────────────────────────────────────────────────────────
applyPatch(
    'assets/js/untis.js',
    'untis.js: addHWtoKanban mit decode',
    'window.addHWtoKanban = async function(text, subject, dueISO, btn) {',
    'window.addHWtoKanban = async function(rawText, rawSubject, dueISO, btn) {' . "\n" . '        const text = decodeURIComponent(rawText);' . "\n" . '        const subject = decodeURIComponent(rawSubject);'
);

?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Patch-Ergebnisse</title>
<style>
body { font-family: monospace; max-width: 900px; margin: 2em auto; background: #1e1e2e; color: #cdd6f4; padding: 20px; border-radius: 8px; }
h1 { color: #cba6f7; margin-bottom: 1.5em; }
table { width: 100%; border-collapse: collapse; }
tr { border-bottom: 1px solid #313244; }
td { padding: 7px 10px; vertical-align: top; }
td:first-child { width: 30px; text-align: center; font-size: 18px; }
.ok { color: #a6e3a1; }
.err { color: #f38ba8; }
.note { font-size: 12px; color: #a6adc8; }
hr { border-color: #45475a; margin: 2em 0; }
.warn { color: #f9e2af; background: #313244; padding: 10px; border-radius: 6px; }
</style>
</head>
<body>
<h1>🔧 Patch-Ergebnisse</h1>
<table>
<?php foreach ($results as [$icon, $label, $extra]): ?>
<tr class="<?= $icon === '✅' ? 'ok' : 'err' ?>">
    <td><?= $icon ?></td>
    <td>
        <?= htmlspecialchars($label) ?>
        <?php if ($extra): ?><br><span class="note"><?= htmlspecialchars($extra) ?></span><?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
<hr>
<div class="warn">⚠️ Bitte diese Datei jetzt löschen: <code>patch_features.php</code></div>
</body>
</html>
