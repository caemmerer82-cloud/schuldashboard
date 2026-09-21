<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/auth.php';
$me = auth_require_role('tenant_admin','superadmin');
$tid = (int)($me['tenant_id'] ?? 1);
?><!DOCTYPE html>
<html lang="de"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Verwaltung – Schul-Dashboard</title>
<link rel="stylesheet" href="assets/css/styles.css">
<style>
/* ---- Layout ---- */
.vw-sections { display:flex; flex-direction:column; gap:24px; }
.vw-grid2    { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
@media(max-width:900px){ .vw-grid2{ grid-template-columns:1fr; } }

/* ---- Tabellen ---- */
.vw-table { width:100%; border-collapse:collapse; font-size:13px; margin-top:12px; }
.vw-table th { background:#1e3a5f; color:#fff; padding:8px 10px; text-align:left; font-size:12px; }
.vw-table td { padding:7px 10px; border-bottom:1px solid var(--border); vertical-align:middle; }
.vw-table tr:hover td { background:var(--surface-2); }

/* ---- Formulare ---- */
.vw-form { display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; margin-top:12px; }
.vw-form label { display:flex; flex-direction:column; gap:3px; font-size:12px; color:var(--text-muted); font-weight:600; }
.vw-form input, .vw-form select { font:inherit; padding:7px 9px; border:1px solid var(--border); border-radius:var(--radius-sm); }
.vw-form input         { width:120px; }
.vw-form input.wide    { width:180px; }
.vw-form input.xwide   { width:220px; }
.vw-form input.narrow  { width:80px; }
.vw-form input[type=date]{ width:150px; }
.vw-form select        { width:140px; }

/* ---- Feedback ---- */
.vw-err { color:var(--danger);  font-size:12px; margin-top:4px; min-height:18px; }
.vw-ok  { color:var(--success); font-size:12px; margin-top:4px; min-height:18px; }

/* ---- Buttons ---- */
.btn-del-sm  { background:none; border:none; cursor:pointer; color:var(--danger);  font-size:15px; padding:2px 6px; border-radius:4px; }
.btn-edit-sm { background:none; border:none; cursor:pointer; color:var(--primary); font-size:15px; padding:2px 6px; border-radius:4px; }
.btn-del-sm:hover  { background:#fee2e2; }
.btn-edit-sm:hover { background:#eff6ff; }

/* ---- Modul-Karten ---- */
.module-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:10px; margin-top:12px; }
.module-card {
    border:2px solid var(--border); border-radius:8px; padding:12px 14px;
    display:flex; align-items:center; gap:10px; cursor:pointer;
    transition:border-color .15s, background .15s;
    user-select:none;
}
.module-card.active   { border-color:var(--primary); background:#eff6ff; }
.module-card input    { width:16px; height:16px; accent-color:var(--primary); cursor:pointer; }
.module-card-label    { font-size:13px; font-weight:700; flex:1; }
.module-card-icon     { font-size:18px; }

/* ---- Kanban-Spalten ---- */
.kanban-col-row { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid var(--border); }
.kanban-col-row:last-child { border-bottom:none; }
.kanban-col-key  { font-size:11px; font-weight:700; color:var(--text-muted); width:60px; }
.kanban-col-input{ font:inherit; padding:6px 9px; border:1px solid var(--border); border-radius:var(--radius-sm); flex:1; }
.kanban-col-check{ display:flex; align-items:center; gap:5px; font-size:12px; color:var(--text-muted); }
</style>
</head><body>
<header class="topbar"><div class="topbar-inner">
    <h1><span class="logo">📋</span> Verwaltung
        <?php if($me['tenant_name']??''):?>
            <span class="tenant-badge"><?=htmlspecialchars($me['tenant_name'])?></span>
        <?php endif;?>
    </h1>
    <nav>
        <a href="index.php"    class="btn btn-ghost">← Dashboard</a>
        <a href="settings.php" class="btn btn-ghost">⚙️ Einstellungen</a>
        <a href="logout.php"   class="btn btn-ghost">Abmelden</a>
    </nav>
</div></header>

<main class="settings-page" style="max-width:1200px;">
<div class="vw-sections">

    <!-- ===== 1. Dashboard-Module ===== -->
    <section class="settings-form">
        <h2>🧩 Dashboard-Module</h2>
        <p class="hint">Wähle, welche Bereiche auf dem Dashboard angezeigt werden sollen.</p>
        <div class="module-grid" id="module-grid">
            <?php
            $modules = [
                ['key'=>'hausaufgaben',    'icon'=>'🗒️', 'label'=>'Hausaufgaben'],
                ['key'=>'termine',         'icon'=>'📅', 'label'=>'Termine'],
                ['key'=>'stundenplan',     'icon'=>'🕒', 'label'=>'Stundenplan'],
                ['key'=>'vertretungsplan', 'icon'=>'🔁', 'label'=>'Vertretungsplan'],
                ['key'=>'busplan',         'icon'=>'🚌', 'label'=>'Busfahrplan'],
            ];
            $aktiv = json_decode(get_setting('module_aktiv', '["hausaufgaben","termine","stundenplan","vertretungsplan","busplan"]', $tid), true) ?: [];
            foreach ($modules as $m):
                $checked = in_array($m['key'], $aktiv) ? 'checked' : '';
            ?>
            <label class="module-card <?=$checked?'active':''?>" id="mc-<?=htmlspecialchars($m['key'])?>">
                <input type="checkbox" value="<?=htmlspecialchars($m['key'])?>" <?=$checked?> onchange="saveModules()">
                <span class="module-card-icon"><?=$m['icon']?></span>
                <span class="module-card-label"><?=htmlspecialchars($m['label'])?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <div id="module-msg" class="vw-ok" style="margin-top:8px"></div>
    </section>

    <!-- ===== 1b. Stundenplan-Optionen ===== -->
    <section class="settings-form" style="margin-top:0">
        <h2>🎓 Stundenplan-Optionen</h2>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px">
            <input type="checkbox" id="cbHideFOE" onchange="saveHideFOE(this.checked)" style="width:18px;height:18px;cursor:pointer">
            <span>FOE-Stunden im Stundenplan ausblenden</span>
        </label>
        <div id="foe-msg" class="vw-ok" style="margin-top:8px"></div>
    </section>

    <!-- ===== 2. Kanban-Spalten ===== -->
    <section class="settings-form">
        <h2>📋 Kanban-Spalten</h2>
        <p class="hint">Beschriftung und Sichtbarkeit der drei Hausaufgaben-Spalten anpassen.</p>
        <?php
        $kanban = json_decode(get_setting('kanban_spalten',
            '{"todo":{"label":"Offen","aktiv":true},"doing":{"label":"In Arbeit","aktiv":true},"done":{"label":"Erledigt","aktiv":true}}',
            $tid), true);
        $defaults = ['todo'=>'Offen','doing'=>'In Arbeit','done'=>'Erledigt'];
        ?>
        <div style="margin-top:12px;" id="kanban-cols">
        <?php foreach (['todo','doing','done'] as $k): ?>
            <?php $cfg=$kanban[$k]??[]; ?>
            <div class="kanban-col-row">
                <span class="kanban-col-key"><?=strtoupper($k)?></span>
                <input class="kanban-col-input" type="text"
                    id="kc-<?=$k?>"
                    value="<?=htmlspecialchars($cfg['label']??$defaults[$k])?>"
                    placeholder="<?=htmlspecialchars($defaults[$k])?>"
                    maxlength="40">
                <label class="kanban-col-check">
                    <input type="checkbox" id="ka-<?=$k?>" <?=($cfg['aktiv']??true)?'checked':''?>>
                    Anzeigen
                </label>
            </div>
        <?php endforeach; ?>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;align-items:center;">
            <button class="btn btn-primary" onclick="saveKanban()">💾 Spalten speichern</button>
            <span id="kanban-msg" class="vw-ok"></span>
        </div>
    </section>

    <!-- ===== 3. Lehrer + Fächer ===== -->
    <div class="vw-grid2">

        <!-- Lehrer -->
        <section class="settings-form">
            <h2>👩‍🏫 Lehrerkürzel</h2>
            <p class="hint">Kürzel werden im Stundenplan durch den vollen Namen ersetzt.</p>
            <div class="vw-form">
                <label>Kürzel
                    <input type="text" id="l-kuerzel" maxlength="20" placeholder="MUC" style="width:90px;text-transform:uppercase">
                </label>
                <label>Anrede
                    <select id="l-anrede" style="width:100px">
                        <option value="">–</option>
                        <option value="Herr">Herr</option>
                        <option value="Frau">Frau</option>
                    </select>
                </label>
                <label>Vorname (optional)
                    <input type="text" id="l-vorname" maxlength="100" placeholder="Max">
                </label>
                <label>Nachname *
                    <input type="text" id="l-nachname" maxlength="100" placeholder="Müller" class="wide">
                </label>
                <button class="btn btn-primary" id="l-btn-add" style="align-self:flex-end">Speichern</button>
            </div>
            <div id="l-msg" class="vw-err"></div>
            <table class="vw-table">
                <thead><tr><th>Kürzel</th><th>Anrede</th><th>Vorname</th><th>Nachname</th><th></th></tr></thead>
                <tbody id="l-tbody"><tr><td colspan="4" style="color:var(--text-muted);padding:16px">Wird geladen…</td></tr></tbody>
            </table>
        </section>

        <!-- Fächer -->
        <section class="settings-form">
            <h2>📚 Fächerkürzel</h2>
            <p class="hint">Kürzel werden im Stundenplan durch den Fachnamen ersetzt.</p>
            <div class="vw-form">
                <label>Kürzel
                    <input type="text" id="f-kuerzel" maxlength="20" placeholder="GES" style="width:90px;text-transform:uppercase">
                </label>
                <label>Vollständiger Fachname *
                    <input type="text" id="f-vollname" maxlength="100" placeholder="Geschichte" class="xwide">
                </label>
                <button class="btn btn-primary" id="f-btn-add" style="align-self:flex-end">Speichern</button>
            </div>
            <div id="f-msg" class="vw-err"></div>
            <table class="vw-table">
                <thead><tr><th>Kürzel</th><th>Fachname</th><th></th></tr></thead>
                <tbody id="f-tbody"><tr><td colspan="3" style="color:var(--text-muted);padding:16px">Wird geladen…</td></tr></tbody>
            </table>
        </section>

    </div><!-- /vw-grid2 -->

    <!-- ===== 4. AGs ===== -->
    <section class="settings-form">
        <h2>🎨 AGs – Block 5 (14:05–15:25)</h2>
        <p class="hint">AGs werden im Stundenplan in Block 5 am jeweiligen Wochentag angezeigt, solange das aktuelle Datum im eingetragenen Zeitraum liegt.</p>

        <div class="vw-form" id="ag-form">
            <label>AG-Name *
                <input type="text" id="ag-name" maxlength="100" placeholder="Schach-AG" class="xwide">
            </label>
            <label>Wochentag *
                <select id="ag-wochentag">
                    <option value="1">Montag</option>
                    <option value="2">Dienstag</option>
                    <option value="3">Mittwoch</option>
                    <option value="4">Donnerstag</option>
                    <option value="5">Freitag</option>
                </select>
            </label>
            <label>Raum
                <input type="text" id="ag-raum" maxlength="50" placeholder="Raum 101" class="narrow">
            </label>
            <label>Leitung
                <input type="text" id="ag-leitung" maxlength="100" placeholder="Herr Müller" class="wide">
            </label>
            <label>Von *
                <input type="date" id="ag-von">
            </label>
            <label>Bis *
                <input type="date" id="ag-bis">
            </label>
            <div style="display:flex;gap:6px;align-self:flex-end;">
                <button class="btn btn-primary" id="ag-btn-add">Speichern</button>
                <button class="btn btn-ghost"   id="ag-btn-cancel" style="display:none">Abbrechen</button>
            </div>
        </div>
        <div id="ag-msg" class="vw-err"></div>

        <table class="vw-table">
            <thead><tr>
                <th>Name</th><th>Wochentag</th><th>Raum</th><th>Leitung</th>
                <th>Von</th><th>Bis</th><th>Aktiv</th><th></th>
            </tr></thead>
            <tbody id="ag-tbody">
                <tr><td colspan="8" style="color:var(--text-muted);padding:16px">Wird geladen…</td></tr>
            </tbody>
        </table>
    </section>

</div><!-- /vw-sections -->
</main>

<script>
const esc = s => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

// ================================================================
// 1. MODULE
// ================================================================
async async function saveHideFOE(val) {
        const r = await fetch('api/settings.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({key:'hide_foe', value: val ? '1' : '0'}) });
        const el = document.getElementById('foe-msg');
        el.textContent = r.ok ? 'Gespeichert ✓' : 'Fehler';
        setTimeout(() => el.textContent = '', 2500);
    }

    function saveModules() {
    // Aktive Module aus Checkboxen lesen
    const aktiv = [...document.querySelectorAll('.module-card input[type=checkbox]')]
        .filter(cb => cb.checked).map(cb => cb.value);
    // Karten visuell aktualisieren
    document.querySelectorAll('.module-card').forEach(card => {
        const cb = card.querySelector('input');
        card.classList.toggle('active', cb.checked);
    });
    const r = await fetch('api/settings.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({key:'module_aktiv', value: JSON.stringify(aktiv)})
    });
    const msg = document.getElementById('module-msg');
    if (r.ok) {
        msg.textContent = '✅ Gespeichert – Änderungen nach Seiten-Reload sichtbar.';
        setTimeout(() => msg.textContent = '', 4000);
    } else {
        msg.className = 'vw-err';
        msg.textContent = '❌ Fehler beim Speichern.';
    }
}

// ================================================================
// 2. KANBAN-SPALTEN
// ================================================================
async function saveKanban() {
    const data = {};
    for (const k of ['todo','doing','done']) {
        data[k] = {
            label: document.getElementById('kc-'+k).value.trim() || {todo:'Offen',doing:'In Arbeit',done:'Erledigt'}[k],
            aktiv: document.getElementById('ka-'+k).checked,
        };
    }
    const r = await fetch('api/settings.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({key:'kanban_spalten', value: JSON.stringify(data)})
    });
    const msg = document.getElementById('kanban-msg');
    if (r.ok) {
        msg.className = 'vw-ok';
        msg.textContent = '✅ Gespeichert – nach Seiten-Reload sichtbar.';
        setTimeout(() => msg.textContent = '', 4000);
    } else {
        msg.className = 'vw-err';
        msg.textContent = '❌ Fehler beim Speichern.';
    }
}

// ================================================================
// 3. LEHRER
// ================================================================
async function loadLehrer() {
    const r = await fetch('api/lehrer.php'); const d = await r.json();
    const tb = document.getElementById('l-tbody');
    if (!d.length) { tb.innerHTML = '<tr><td colspan="4" style="color:var(--text-muted);padding:16px">Noch keine Einträge.</td></tr>'; return; }
    tb.innerHTML = d.map(l => `<tr>
        <td><strong>${esc(l.kuerzel)}</strong></td>
        <td>${esc(l.anrede||'')}</td>
        <td>${esc(l.vorname||'')}</td>
        <td>${esc(l.nachname)}</td>
        <td><button class="btn-del-sm" onclick="delLehrer('${esc(l.kuerzel)}')" title="Löschen">✕</button></td>
    </tr>`).join('');
}
window.delLehrer = async k => {
    if (!confirm(`Lehrerkürzel „${k}" wirklich löschen?`)) return;
    await fetch(`api/lehrer.php?kuerzel=${encodeURIComponent(k)}`, {method:'DELETE'});
    loadLehrer();
};
document.getElementById('l-kuerzel').addEventListener('input', function(){ this.value = this.value.toUpperCase(); });
document.getElementById('l-btn-add').addEventListener('click', async () => {
    const msg = document.getElementById('l-msg');
    const k = document.getElementById('l-kuerzel').value.trim().toUpperCase();
    const a = document.getElementById('l-anrede').value;
    const v = document.getElementById('l-vorname').value.trim();
    const n = document.getElementById('l-nachname').value.trim();
    if (!k || !n) { msg.className='vw-err'; msg.textContent = 'Kürzel und Nachname sind Pflicht.'; return; }
    const r = await fetch('api/lehrer.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({kuerzel:k,anrede:a,vorname:v,nachname:n})});
    const j = await r.json();
    if (!r.ok) { msg.className='vw-err'; msg.textContent = j.error; return; }
    msg.className='vw-ok'; msg.textContent = 'Gespeichert.';
    document.getElementById('l-kuerzel').value = '';
    document.getElementById('l-anrede').value = '';
    document.getElementById('l-vorname').value = '';
    document.getElementById('l-nachname').value = '';
    loadLehrer(); setTimeout(() => msg.textContent = '', 3000);
});

// ================================================================
// 4. FÄCHER
// ================================================================
async function loadFaecher() {
    const r = await fetch('api/faecher.php'); const d = await r.json();
    const tb = document.getElementById('f-tbody');
    if (!d.length) { tb.innerHTML = '<tr><td colspan="3" style="color:var(--text-muted);padding:16px">Noch keine Einträge.</td></tr>'; return; }
    tb.innerHTML = d.map(f => `<tr>
        <td><strong>${esc(f.kuerzel)}</strong></td>
        <td>${esc(f.vollname)}</td>
        <td><button class="btn-del-sm" onclick="delFach('${esc(f.kuerzel)}')" title="Löschen">✕</button></td>
    </tr>`).join('');
}
window.delFach = async k => {
    if (!confirm(`Fachkürzel „${k}" wirklich löschen?`)) return;
    await fetch(`api/faecher.php?kuerzel=${encodeURIComponent(k)}`, {method:'DELETE'});
    loadFaecher();
};
document.getElementById('f-kuerzel').addEventListener('input', function(){ this.value = this.value.toUpperCase(); });
document.getElementById('f-btn-add').addEventListener('click', async () => {
    const msg = document.getElementById('f-msg');
    const k = document.getElementById('f-kuerzel').value.trim().toUpperCase();
    const v = document.getElementById('f-vollname').value.trim();
    if (!k || !v) { msg.className='vw-err'; msg.textContent = 'Kürzel und Fachname sind Pflicht.'; return; }
    const r = await fetch('api/faecher.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({kuerzel:k,vollname:v})});
    const j = await r.json();
    if (!r.ok) { msg.className='vw-err'; msg.textContent = j.error; return; }
    msg.className='vw-ok'; msg.textContent = 'Gespeichert.';
    document.getElementById('f-kuerzel').value = '';
    document.getElementById('f-vollname').value = '';
    loadFaecher(); setTimeout(() => msg.textContent = '', 3000);
});

// ================================================================
// 5. AGs
// ================================================================
const WOCHENTAGE = ['','Montag','Dienstag','Mittwoch','Donnerstag','Freitag'];
let _ags = [], editAgId = null;

async function loadAGs() {
    const r = await fetch('api/ags.php'); const d = await r.json();
    _ags = d;
    const tb = document.getElementById('ag-tbody');
    if (!d.length) { tb.innerHTML = '<tr><td colspan="8" style="color:var(--text-muted);padding:16px">Noch keine AGs eingetragen.</td></tr>'; return; }
    tb.innerHTML = d.map(a => `<tr>
        <td><strong>${esc(a.name)}</strong></td>
        <td>${WOCHENTAGE[a.wochentag]||a.wochentag}</td>
        <td>${esc(a.raum||'–')}</td>
        <td>${esc(a.leitung||'–')}</td>
        <td>${esc(a.von_datum)}</td>
        <td>${esc(a.bis_datum)}</td>
        <td>
            <label style="display:flex;align-items:center;gap:5px;cursor:pointer">
                <input type="checkbox" ${a.aktiv?'checked':''} style="width:15px;height:15px"
                    onchange="toggleAG(${a.id},this.checked)">
                ${a.aktiv?'Ja':'Nein'}
            </label>
        </td>
        <td style="white-space:nowrap">
            <button class="btn-edit-sm" onclick="editAG(${a.id})" title="Bearbeiten">✏️</button>
            <button class="btn-del-sm"  onclick="delAG(${a.id},'${esc(a.name)}')" title="Löschen">✕</button>
        </td>
    </tr>`).join('');
}

window.editAG = id => {
    const a = _ags.find(x => x.id === id); if (!a) return;
    editAgId = id;
    document.getElementById('ag-name').value     = a.name;
    document.getElementById('ag-wochentag').value= a.wochentag;
    document.getElementById('ag-raum').value     = a.raum    || '';
    document.getElementById('ag-leitung').value  = a.leitung || '';
    document.getElementById('ag-von').value      = a.von_datum;
    document.getElementById('ag-bis').value      = a.bis_datum;
    document.getElementById('ag-btn-add').textContent    = 'Änderung speichern';
    document.getElementById('ag-btn-cancel').style.display = '';
    document.getElementById('ag-name').focus();
};

window.toggleAG = async (id, aktiv) => {
    await fetch('api/ags.php', {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id, aktiv:aktiv?1:0})});
    loadAGs();
};

window.delAG = async (id, name) => {
    if (!confirm(`AG „${name}" wirklich löschen?`)) return;
    await fetch(`api/ags.php?id=${id}`, {method:'DELETE'});
    loadAGs();
};

document.getElementById('ag-btn-cancel').addEventListener('click', () => {
    editAgId = null;
    ['ag-name','ag-raum','ag-leitung','ag-von','ag-bis'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('ag-btn-add').textContent    = 'Speichern';
    document.getElementById('ag-btn-cancel').style.display = 'none';
});

document.getElementById('ag-btn-add').addEventListener('click', async () => {
    const msg  = document.getElementById('ag-msg');
    const name = document.getElementById('ag-name').value.trim();
    const wt   = parseInt(document.getElementById('ag-wochentag').value);
    const raum = document.getElementById('ag-raum').value.trim();
    const leit = document.getElementById('ag-leitung').value.trim();
    const von  = document.getElementById('ag-von').value;
    const bis  = document.getElementById('ag-bis').value;

    if (!name||!von||!bis) { msg.className='vw-err'; msg.textContent='Name, Von- und Bis-Datum sind Pflicht.'; return; }
    if (von > bis)          { msg.className='vw-err'; msg.textContent='Bis-Datum muss nach Von-Datum liegen.';  return; }

    const body = {name, wochentag:wt, raum, leitung:leit, von_datum:von, bis_datum:bis, aktiv:1};
    if (editAgId) body.id = editAgId;

    const r = await fetch('api/ags.php', {method:editAgId?'PUT':'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
    const j = await r.json();
    if (!r.ok) { msg.className='vw-err'; msg.textContent = j.error; return; }

    msg.className='vw-ok'; msg.textContent = 'Gespeichert.';
    editAgId = null;
    ['ag-name','ag-raum','ag-leitung','ag-von','ag-bis'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('ag-btn-add').textContent    = 'Speichern';
    document.getElementById('ag-btn-cancel').style.display = 'none';
    loadAGs(); setTimeout(() => msg.textContent = '', 3000);
});

// ================================================================
// Init
// ================================================================
loadLehrer(); loadFaecher(); loadAGs();
</script>
</body></html>
