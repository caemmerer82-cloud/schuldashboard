<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/auth.php';
$me  = auth_require();
$tid = (int)($me['tenant_id'] ?? 1);
$untisActive = trim(get_setting('untis_server', '', $tid)) !== '';
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stundenplan</title>
<style>
/* ---- Reset & Fullscreen ---- */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
    width: 100vw; height: 100vh;
    overflow: hidden;
    background: #f0f4ff;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #0f172a;
}

/* ---- Outer layout ---- */
#app {
    display: flex;
    flex-direction: column;
    width: 100vw;
    height: 100vh;
    padding: 10px;
    gap: 8px;
}

/* ---- Header ---- */
#header {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}
#header h1 {
    font-size: 1.1em;
    font-weight: 800;
    color: #1e3a5f;
    white-space: nowrap;
}
.nav-btn {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 4px 12px;
    font: inherit;
    font-size: .85em;
    font-weight: 600;
    cursor: pointer;
    color: #1e3a5f;
    white-space: nowrap;
}
.nav-btn:hover { background: #eff6ff; border-color: #2563eb; }
#kw-label {
    font-size: .85em;
    font-weight: 700;
    color: #1e3a5f;
    text-align: center;
    flex: 1;
}
#refresh-info {
    font-size: .75em;
    color: #94a3b8;
    white-space: nowrap;
}
#close-btn {
    background: none;
    border: none;
    font-size: 1.4em;
    cursor: pointer;
    color: #94a3b8;
    line-height: 1;
    padding: 0 4px;
}
#close-btn:hover { color: #0f172a; }

/* ---- Tabellen-Wrapper: füllt restlichen Platz ---- */
#grid-wrap {
    flex: 1;
    overflow: hidden;
    /* Skalierung via CSS transform – wird per JS gesetzt */
}

/* ---- Tabelle ---- */
#plan-table {
    border-collapse: separate;
    border-spacing: 0;
    /* Breite/Höhe werden per JS auf eine feste Basisgröße gesetzt,
       dann via transform skaliert */
    width: 1400px;   /* Basis-Breite */
    transform-origin: top left;
    table-layout: fixed;
}

/* Spalten */
.col-zeit { width: 82px; }

/* Kopfzeile */
.th-zeit {
    background: #1e3a5f;
    color: #fff;
    text-align: center;
    font-size: .75em;
    font-weight: 700;
    padding: 6px 6px;
    border-right: 3px solid #2563eb;
    border-bottom: 2px solid #2563eb;
    white-space: nowrap;
}
.th-day {
    background: #2563eb;
    color: #fff;
    text-align: center;
    font-size: .95em;
    font-weight: 800;
    padding: 6px 4px;
    border-left: 1px solid #1d4ed8;
    border-bottom: 2px solid #2563eb;
}
.th-day.today { background: #0284c7; }
.th-day-name  { font-size: 1em; letter-spacing: .01em; }
.th-day-date  { font-size: .78em; opacity: .85; }

/* Block-Zellen links */
.td-zeit {
    text-align: right;
    padding: 4px 7px;
    border-right: 3px solid #2563eb;
    border-bottom: 1px solid #cbd5e1;
    vertical-align: middle;
    white-space: nowrap;
}
.zt-label { font-weight: 800; font-size: .72em; color: #1e3a5f; }
.zt-time  { font-size: .65em; color: #64748b; line-height: 1.5; }

/* Tages-Zellen */
.td-day {
    border-left: 1px solid #cbd5e1;
    border-bottom: 1px solid #cbd5e1;
    padding: 3px 4px;
    vertical-align: top;
}
/* Alternierende Zeilen */
.row-even .td-day   { background: #f0f4ff; }
.row-even .td-zeit  { background: #e2e8f8; }
.row-odd  .td-day   { background: #ffffff; }
.row-odd  .td-zeit  { background: #eef2f9; }
.row-even .td-day.today { background: #dbeafe; }
.row-odd  .td-day.today { background: #e0f2fe; }
/* Mittag */
.row-mittag .td-day  { background: #f0fdf4 !important; border-bottom: 2px solid #86efac !important; }
.row-mittag .td-zeit { background: #dcfce7 !important; border-right-color: #16a34a !important; }
.row-mittag .zt-label { color: #15803d !important; font-style: italic; }
.row-mittag .zt-time  { color: #4ade80 !important; }

/* Stunden-Karten */
.card {
    border-radius: 4px;
    padding: 3px 5px;
    margin-bottom: 2px;
    font-size: .72em;
    border-left: 3px solid #2563eb;
    background: #dbeafe;
    border: 1px solid #93c5fd;
    border-left-width: 3px;
    overflow: hidden;
}
.card.cancelled { background: #fee2e2 !important; border-color: #fca5a5 !important; border-left-color: #dc2626 !important; }
.card.subst     { background: #fef3c7 !important; border-color: #fcd34d !important; border-left-color: #f59e0b !important; }
.card.info-card { background: #ede9fe !important; border-color: #c4b5fd !important; border-left-color: #7c3aed !important; }
.card-subject { font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.card.cancelled .card-subject { text-decoration: line-through; color: #dc2626; }
.card-detail  { font-size: .88em; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.card-badge {
    display: inline-block; font-size: .78em; font-weight: 700;
    padding: 0 4px; border-radius: 2px; margin-top: 1px;
    text-transform: uppercase; letter-spacing: .04em;
}
.badge-subst    { background: #f59e0b; color: #fff; }
.badge-cancelled{ background: #dc2626; color: #fff; }
.badge-info     { background: #7c3aed; color: #fff; }
.card-info-text { font-size: .82em; color: #6d28d9; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.card-free { color: #e2e8f0; font-size: 1em; text-align: center; padding: 4px 0; }

/* Lade-/Fehler-Anzeige */
#status {
    position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%);
    background: rgba(255,255,255,.95); border-radius: 12px;
    padding: 20px 28px; box-shadow: 0 8px 24px rgba(0,0,0,.15);
    font-size: 1em; color: #1e3a5f; font-weight: 600; text-align: center;
    display: none;
}
@keyframes upop { from{transform:scale(.93);opacity:0} to{transform:scale(1);opacity:1} }
</style>
</head>
<body>
<div id="app">
    <div id="header">
        <button class="nav-btn" id="btn-prev">‹ Vorwoche</button>
        <h1>🕒 Stundenplan</h1>
        <span id="kw-label">–</span>
        <button class="nav-btn" id="btn-next">Nächste Woche ›</button>
        <button class="nav-btn" id="btn-heute">Heute</button>
        <span id="refresh-info">🔄 stündlich</span>
        <button id="close-btn" title="Schließen" onclick="window.close()">✕</button>
    </div>
    <div id="grid-wrap">
        <table id="plan-table"><tbody id="plan-body"></tbody></table>
    </div>
</div>
<div id="status">Wird geladen…</div>

<script>
const BLOCKS = [
    { label:'Block 1', start:'07:40', end:'08:20' },
    { label:'Block 2', start:'08:25', end:'09:45' },
    { label:'Block 3', start:'10:05', end:'11:25' },
    { label:'Block 4', start:'11:45', end:'13:05' },
    { label:'Mittag',  start:'13:05', end:'14:05' },
    { label:'Block 5', start:'14:05', end:'15:25' },
];
const DAYS = ['Mo','Di','Mi','Do','Fr'];
const BASE_W = 1400; // Basis-Tabellenbreite in px

const esc = s => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
const toYMD  = d => d.getFullYear()*10000+(d.getMonth()+1)*100+d.getDate();
const toDate = y => { const s=String(y); return new Date(+s.slice(0,4),+s.slice(4,6)-1,+s.slice(6,8)); };
const todayN = () => toYMD(new Date());
const isToday= y => String(y)===String(todayN());
const addD   = (y,n) => { const d=toDate(y); d.setDate(d.getDate()+n); return toYMD(d); };
const monday = y => { const d=toDate(y),w=d.getDay()||7; d.setDate(d.getDate()-(w-1)); return toYMD(d); };
const shortD = y => toDate(y).toLocaleDateString('de-DE',{day:'2-digit',month:'2-digit'});
const kw     = y => { const d=toDate(y),dt=new Date(Date.UTC(d.getFullYear(),d.getMonth(),d.getDate())); dt.setUTCDate(dt.getUTCDate()+4-(dt.getUTCDay()||7)); return Math.ceil((((dt-new Date(Date.UTC(dt.getUTCFullYear(),0,1)))/86400000)+1)/7); };
const toMin  = hm => { const [h,m]=hm.split(':').map(Number); return h*60+m; };

function blockOf(l) {
    const ls=toMin(l.startTime), le=toMin(l.endTime);
    let best=-1, bestOv=0;
    BLOCKS.forEach((b,i)=>{ const ov=Math.max(0,Math.min(le,toMin(b.end))-Math.max(ls,toMin(b.start))); if(ov>bestOv){bestOv=ov;best=i;} });
    return best;
}

// Lehrer/Fächer-Lookup
let lehrerMap={}, faecherMap={};
async function loadLookups() {
    try {
        const [lr,fr] = await Promise.all([fetch('api/lehrer.php'), fetch('api/faecher.php')]);
        if (lr.ok) { const d=await lr.json(); d.forEach(l=>{ lehrerMap[l.kuerzel]=(l.anrede?l.anrede+' ':'')+l.nachname; }); }
        if (fr.ok) { const d=await fr.json(); d.forEach(f=>{ faecherMap[f.kuerzel]=f.vollname; }); }
    } catch(e) {}
}
const resF = k => faecherMap[k]||faecherMap[k?.toUpperCase()]||k||'–';

// AGs
let agCache=[];
async function loadAGs() {
    try { const r=await fetch('api/ags.php?aktiv=1'); if(r.ok) agCache=await r.json(); } catch(e){}
}
function agsForDay(wochentag, dateYMD) {
    const iso=String(dateYMD).replace(/^(\d{4})(\d{2})(\d{2})$/,'$1-$2-$3');
    return agCache.filter(a=>a.wochentag===wochentag&&a.von_datum<=iso&&a.bis_datum>=iso);
}
const resL = k => lehrerMap[k] ||lehrerMap[k?.toUpperCase()]||k||'–';

// Viewport-Skalierung
function scaleTable() {
    const wrap  = document.getElementById('grid-wrap');
    const table = document.getElementById('plan-table');
    const wrapW = wrap.clientWidth;
    const wrapH = wrap.clientHeight;

    // zoom statt transform: skaliert Layout UND Click-Koordinaten korrekt.
    // transform:scale() verschiebt nur die Darstellung, Klick-Targets bleiben
    // am ursprünglichen Ort – zoom hat dieses Problem nicht.
    const scaleW = wrapW / BASE_W;
    const scaleH = wrapH / (table.offsetHeight || 600);
    const scale  = Math.min(scaleW, scaleH, 1.6);

    table.style.transform = 'none'; // transform zurücksetzen
    table.style.zoom = scale;
}

// Tabelle rendern
function renderTable(data) {
    lessonStore = []; // Store leeren vor neuem Render
    const byDate = {};
    for (const d of data) byDate[String(d.date)] = d.lessons||[];
    const wdates = Array.from({length:5},(_,i)=>String(addD(curMonday,i)));

    const table = document.getElementById('plan-table');

    // Kopfzeile
    let html = '<thead><tr>';
    html += `<th class="th-zeit col-zeit">Zeiten</th>`;
    wdates.forEach((y,i) => {
        const t = isToday(y);
        html += `<th class="th-day${t?' today':''}">
            <div class="th-day-name">${DAYS[i]}</div>
            <div class="th-day-date">${shortD(y)}</div>
        </th>`;
    });
    html += '</tr></thead><tbody>';

    // Zeilen
    BLOCKS.forEach((block, bi) => {
        const isMittag = block.label === 'Mittag';
        const rowCls = isMittag ? 'row-mittag' : (bi%2===0 ? 'row-even' : 'row-odd');
        html += `<tr class="${rowCls}">`;
        html += `<td class="td-zeit col-zeit">
            <div class="zt-label">${esc(block.label)}</div>
            <div class="zt-time">${esc(block.start)}</div>
            <div class="zt-time">${esc(block.end)}</div>
        </td>`;

        wdates.forEach(y => {
            const t = isToday(y);
            const lessons = (byDate[y]||[]).filter(l=>blockOf(l)===bi);
            let inner = '';

            if (isMittag) {
                inner = '<div class="card-free">–</div>';
            } else if (!lessons.length) {
                const wt2 = wdates.indexOf(y)+1;
                const isB5 = block.label==='Block 5';
                const dayAGs = isB5 ? agsForDay(wt2, y) : [];
                if (dayAGs.length) {
                    dayAGs.forEach(ag => {
                        inner += `<div class="card" style="background:#f0fdf4;border-color:#86efac;border-left-color:#16a34a">
                            <div class="card-subject" style="color:#15803d">🎨 ${esc(ag.name)}</div>
                            <div class="card-detail">${ag.raum?esc(ag.raum):''}${ag.leitung?' · '+esc(ag.leitung):''}</div>
                        </div>`;
                    });
                } else {
                    inner = '<div class="card-free">·</div>';
                }
            } else {
                lessons.forEach(l => {
                    const cCls = l.type==='cancelled' ? 'cancelled' : l.type==='substitution' ? 'subst' : l.info ? 'info-card' : '';
                    const badge = l.type==='cancelled'
                        ? '<span class="card-badge badge-cancelled">Entfall</span>'
                        : l.type==='substitution'
                        ? '<span class="card-badge badge-subst">Vertretung</span>'
                        : l.info ? '<span class="card-badge badge-info">ℹ Info</span>' : '';
                    const infoText = l.info
                        ? `<div class="card-info-text" title="${esc(l.info)}">${esc(l.info.length>35?l.info.slice(0,35)+'…':l.info)}</div>` : '';
                    // Hausaufgaben: nur die am Tag dieser Stunde fälligen
                    const ymdNum = parseInt(y, 10); // z.B. 20260904
                    const dueTodayHW = (l.homeworks||[]).filter(h =>
                        !h.completed && h.dueDate === ymdNum
                    );
                    const hwBadge = dueTodayHW.length
                        ? `<span class="card-badge" style="background:#7c3aed;color:#fff">📚 ${dueTodayHW.length}</span>` : '';

                    const idx = lessonStore.length;
                    lessonStore.push({ l, blockLabel: block.label, dateYMD: y, hws: dueTodayHW });
                    inner += `<div class="card ${cCls}" data-idx="${idx}" style="cursor:pointer">
                        <div class="card-subject">${esc(resF(l.subject))}</div>
                        <div class="card-detail">${esc(l.room)} · ${esc(resL(l.teacher))}</div>
                        ${infoText}${badge}${hwBadge}
                    </div>`;
                });
            }
            html += `<td class="td-day${t?' today':''}">${inner}</td>`;
        });
        html += '</tr>';
    });
    html += '</tbody>';
    table.innerHTML = html;

    // Klick-Handler auf Stunden-Karten (lessonStore wurde beim HTML-Aufbau befüllt)
    table.querySelectorAll('.card[data-idx]').forEach(card => {
        card.addEventListener('click', () => openPopup(parseInt(card.dataset.idx, 10)));
    });

    scaleTable();
}

// ---- Lesson-Store für Popup ----
let lessonStore = [];

function openPopup(idx) {
    const item = lessonStore[idx];
    if (!item) return;
    const { l, blockLabel, dateYMD, hws } = item;

    // Bestehendes Popup entfernen
    document.getElementById('sp-popup')?.remove();

    const badge = l.type === 'cancelled'
        ? '<span style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700">Entfall</span>'
        : l.type === 'substitution'
        ? '<span style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700">Vertretung</span>'
        : '<span style="background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700">Regulär</span>';

    const row = (label, val) => val && val !== '–'
        ? `<div style="display:flex;gap:10px;padding:5px 0;border-bottom:1px solid #f1f5f9;font-size:13px">
             <span style="min-width:65px;font-weight:700;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.04em">${label}</span>
             <span style="color:#0f172a;flex:1">${val}</span>
           </div>` : '';

    const infoRow = l.info
        ? `<div style="display:flex;gap:10px;padding:5px 0;border-bottom:1px solid #f1f5f9;font-size:13px">
             <span style="min-width:65px;font-weight:700;color:#64748b;font-size:11px;text-transform:uppercase">Hinweis</span>
             <span style="color:#dc2626;font-weight:600;flex:1">${esc(l.info)}</span>
           </div>` : '';

    // Hausaufgaben-Block
    let hwHTML = '';
    if (hws && hws.length) {
        const hwRows = hws.map(hw => {
            const due = hw.dueDate ? (String(hw.dueDate).slice(6,8)+'.'+String(hw.dueDate).slice(4,6)+'.') : '';
            const txt = (hw.text||'').replace(/\n/g,'<br>');
            return `<div style="padding:5px 0;border-bottom:1px solid #ede9fe;font-size:12px">
                <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px">
                    <span style="font-weight:700;color:#5b21b6;flex:1">${esc(hw.text||'')}</span>
                    ${due?`<span style="font-size:11px;color:#7c3aed;white-space:nowrap">fällig ${due}</span>`:''}
                </div>
            </div>`;
        }).join('');
        hwHTML = `<div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:10px;margin-top:8px">
            <div style="font-size:10px;font-weight:800;color:#7c3aed;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">📚 Hausaufgaben</div>
            ${hwRows}
        </div>`;
    }

    const overlay = document.createElement('div');
    overlay.id = 'sp-popup';
    // position:fixed funktioniert nicht korrekt innerhalb von transform-Elementen.
    // Wir hängen das Overlay direkt an <body> und setzen es absolut über alles.
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(15,23,42,.45);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;box-sizing:border-box';
    overlay.innerHTML = `
        <div style="background:#fff;border-radius:14px;box-shadow:0 24px 48px rgba(15,23,42,.3);padding:22px 24px;max-width:380px;width:100%;position:relative;animation:upop .15s ease">
            <button id="sp-popup-close" style="position:absolute;top:12px;right:14px;background:none;border:none;font-size:22px;cursor:pointer;color:#94a3b8;line-height:1">✕</button>
            <div style="font-size:19px;font-weight:800;color:#1e3a5f;padding-right:24px">${esc(resF(l.subject))}</div>
            <div style="font-size:12px;color:#64748b;margin-top:2px;margin-bottom:12px">${esc(blockLabel)} · ${esc(l.startTime)}–${esc(l.endTime)}</div>
            ${row('Status', badge)}
            ${row('Raum', esc(l.room))}
            ${row('Lehrer', esc(resL(l.teacher)))}
            ${infoRow}
            ${hwHTML}
        </div>`;
    // Transform am table deaktivieren damit position:fixed korrekt funktioniert
    const planTable = document.getElementById('plan-table');
    const savedTransform = planTable ? planTable.style.transform : '';
    if (planTable) planTable.style.transform = 'none';

    const closePopup = () => {
        overlay.remove();
        if (planTable) planTable.style.transform = savedTransform;
        document.removeEventListener('keydown', onKey);
    };

    document.body.appendChild(overlay);
    document.getElementById('sp-popup-close').addEventListener('click', closePopup);
    overlay.addEventListener('click', e => { if (e.target === overlay) closePopup(); });
    const onKey = e => { if (e.key === 'Escape') closePopup(); };
    document.addEventListener('keydown', onKey);
}

// Laden
let curMonday = monday(todayN());

async function load(silent=false) {
    const status = document.getElementById('status');
    if (!silent) status.style.display='block';
    try {
        const r = await fetch(`api/untis.php?action=timetable&date=${curMonday}`);
        const j = await r.json();
        if (!r.ok || j.error) {
            status.textContent = '⚠️ ' + (j.error||'Fehler beim Laden');
            status.style.display = 'block';
            return;
        }
        renderTable(j.data||[]);
        status.style.display = 'none';
        // KW-Label
        document.getElementById('kw-label').textContent =
            `KW ${kw(curMonday)} · ${shortD(curMonday)} – ${shortD(addD(curMonday,4))}`;
    } catch(e) {
        status.textContent = '⚠️ ' + e.message;
        status.style.display = 'block';
    }
}

// Navigation
document.getElementById('btn-prev').addEventListener('click', () => { curMonday=monday(addD(curMonday,-7)); load(); });
document.getElementById('btn-next').addEventListener('click', () => { curMonday=monday(addD(curMonday,7));  load(); });
document.getElementById('btn-heute').addEventListener('click',() => { curMonday=monday(todayN());           load(); });

// Skalierung bei Fenster-Resize
window.addEventListener('resize', scaleTable);

// Stündlicher Auto-Refresh
setInterval(() => load(true), 60 * 60 * 1000);

// Init
Promise.all([loadLookups(), loadAGs()]).then(() => load());
</script>
</body>
</html>
