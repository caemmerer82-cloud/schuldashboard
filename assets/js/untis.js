/**
 * untis.js – Stundenplan & Vertretungsplan als Wochengitter
 * Popup via <dialog>-Element (kein HTML-Escaping-Problem)
 * Vertretungsplan: nur geänderte Zellen werden angezeigt
 */
(function () {
    'use strict';

    const BLOCKS = [
        { label:'Block 1', start:'07:40', end:'08:20' },
        { label:'Block 2', start:'08:25', end:'09:45' },
        { label:'Block 3', start:'10:05', end:'11:25' },
        { label:'Block 4', start:'11:45', end:'13:05' },
        { label:'Mittag',  start:'13:05', end:'14:05' },
        { label:'Block 5', start:'14:05', end:'15:25' },
    ];
    const DAYS       = ['Mo','Di','Mi','Do','Fr'];
    const REFRESH_MS = 10 * 60 * 1000;

    // ---- Datum-Utils ----
    const esc    = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const toYMD  = d => d.getFullYear()*10000+(d.getMonth()+1)*100+d.getDate();
    const toDate = y => { const s=String(y); return new Date(+s.slice(0,4),+s.slice(4,6)-1,+s.slice(6,8)); };
    const todayN = () => toYMD(new Date());
    const isToday= y => String(y) === String(todayN());
    const addD   = (y,n) => { const d=toDate(y); d.setDate(d.getDate()+n); return toYMD(d); };
    const monday = y => { const d=toDate(y), w=d.getDay()||7; d.setDate(d.getDate()-(w-1)); return toYMD(d); };
    const shortD = y => toDate(y).toLocaleDateString('de-DE',{day:'2-digit',month:'2-digit'});
    const longD  = y => toDate(y).toLocaleDateString('de-DE',{weekday:'long',day:'2-digit',month:'2-digit',year:'numeric'});
    const kw     = y => { const d=toDate(y), dt=new Date(Date.UTC(d.getFullYear(),d.getMonth(),d.getDate())); dt.setUTCDate(dt.getUTCDate()+4-(dt.getUTCDay()||7)); return Math.ceil((((dt-new Date(Date.UTC(dt.getUTCFullYear(),0,1)))/86400000)+1)/7); };
    const toMin  = hm => { const [h,m]=hm.split(':').map(Number); return h*60+m; };

    function blockOf(l) {
        const ls=toMin(l.startTime), le=toMin(l.endTime);
        let best=-1, bestOv=0;
        BLOCKS.forEach((b,i)=>{ const ov=Math.max(0,Math.min(le,toMin(b.end))-Math.max(ls,toMin(b.start))); if(ov>bestOv){bestOv=ov;best=i;} });
        return best;
    }

    // ---- Lehrer/Fächer-Lookup ----
    let lehrerMap={}, faecherMap={}, lookupLoaded=false;
    async function ensureLookup() {
        if (lookupLoaded) return;
        try {
            const [lr,fr]=await Promise.all([fetch('api/lehrer.php'),fetch('api/faecher.php')]);
            if (lr.ok) { const d=await lr.json(); d.forEach(l=>{ lehrerMap[l.kuerzel]=(l.anrede?l.anrede+' ':'')+l.nachname; }); }
            if (fr.ok) { const d=await fr.json(); d.forEach(f=>{ faecherMap[f.kuerzel]=f.vollname; }); }
        } catch(e){}
        lookupLoaded=true;
    }
    const resolveFach   = k => faecherMap[k]||faecherMap[k?.toUpperCase()]||k;

    // ---- AGs-Cache ----
    let agCache = [], agLoaded = false;
    async function ensureAGs() {
        if (agLoaded) return;
        try {
            const r = await fetch('api/ags.php?aktiv=1');
            if (r.ok) agCache = await r.json();
        } catch(e) {}
        agLoaded = true;
    }
    // AGs für einen bestimmten Wochentag (1=Mo…5=Fr) und Datum (YYYYMMDD)
    function agsForDay(wochentag, dateYMD) {
        const iso = String(dateYMD).replace(/^(\d{4})(\d{2})(\d{2})$/, '$1-$2-$3');
        return agCache.filter(a =>
            a.wochentag === wochentag &&
            a.von_datum <= iso &&
            a.bis_datum >= iso
        );
    }
    const resolveLehrer = k => lehrerMap[k] ||lehrerMap[k?.toUpperCase()]||k;

    // ---- Hausaufgaben ----
    let hwCache=[], hwLoaded=false;
    async function ensureHW() {
        if (hwLoaded) return;
        try { const r=await fetch('api/homework.php'); if(r.ok) hwCache=await r.json(); } catch(e){}
        hwLoaded=true;
    }
    const hwFor = sub => {
        if (!sub) return [];
        // Suche in beide Richtungen:
        // 1. Kürzel (z.B. "GES") gegen HW-Fach (z.B. "GES" oder "Geschichte")
        // 2. Aufgelöster Name (z.B. "Geschichte") gegen HW-Fach
        const kuerzel  = sub.toLowerCase();
        const vollname = resolveFach(sub).toLowerCase();
        return hwCache.filter(h => {
            if (h.status === 'done') return false;
            const hf = h.subject.toLowerCase();
            return hf.includes(kuerzel) || hf.includes(vollname)
                || kuerzel.includes(hf) || vollname.includes(hf);
        });
    };

    // ---- Popup via <dialog> ----
    // Einmalig im DOM anlegen
    const dlg = document.createElement('dialog');
    dlg.id = 'untis-dlg';
    // Style via CSS #untis-dlg
    dlg.innerHTML = `
        <div style="padding:22px 24px;font-family:inherit">
            <button id="untis-dlg-close" style="position:absolute;top:12px;right:14px;background:none;border:none;font-size:22px;cursor:pointer;color:#94a3b8;line-height:1">✕</button>
            <div id="untis-dlg-body"></div>
        </div>`;
    document.body.appendChild(dlg);
    document.getElementById('untis-dlg-close').onclick = () => dlg.close();
    dlg.addEventListener('click', e => { if(e.target===dlg) dlg.close(); });

    // Getrennte Lesson-Stores für Stundenplan und Vertretungsplan
    // (beide rufen renderGrid auf – gleicher globaler Array würde Indizes vermischen)
    const stores = { tt: [], subst: [] };

    function openPopup(idx, storeKey) {
        const store = stores[storeKey] || stores.tt;
        const item = store[idx];
        if (!item) return;
        const { l, blockLabel, dateYMD, hws } = item;
        const displayFach   = resolveFach(l.subject);
        const displayLehrer = resolveLehrer(l.teacher);

        const badge = l.type==='cancelled'
            ? '<span style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:2px 9px;border-radius:4px;font-size:11px;font-weight:700">Entfall</span>'
            : l.type==='substitution'
            ? '<span style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;padding:2px 9px;border-radius:4px;font-size:11px;font-weight:700">Vertretung</span>'
            : '<span style="background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;padding:2px 9px;border-radius:4px;font-size:11px;font-weight:700">Regulär</span>';

        const row = (label, val) => val && val!=='–'
            ? `<div style="display:flex;gap:10px;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:13px">
                 <span style="min-width:70px;font-weight:700;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.04em;padding-top:1px">${label}</span>
                 <span style="color:#0f172a;flex:1">${val}</span>
               </div>` : '';

        let hwHTML = '';
        if (hws.length) {
            const rows = hws.map(hw => {
                const due = (hw.due_date||hw.dueDate)
                    ? (() => { const d=String(hw.due_date||hw.dueDate); return d.length===8 ? d.slice(6,8)+'.'+d.slice(4,6)+'.' : new Date(d+'T00:00:00').toLocaleDateString('de-DE',{day:'2-digit',month:'2-digit'}); })()
                    : '';
                return `<div style="padding:5px 0;border-bottom:1px solid #ede9fe;font-size:12px">
                    <div style="display:flex;justify-content:space-between;align-items:baseline">
                        <b style="color:#5b21b6">${esc(hw.text||hw.title||'')}</b>
                        ${due?`<span style="font-size:11px;color:#7c3aed;white-space:nowrap;margin-left:8px">fällig ${due}</span>`:''}
                    </div>
                    ${hw.description?`<div style="color:#64748b;margin-top:2px">${esc(hw.description)}</div>`:''}
                </div>`;
            }).join('');
            hwHTML = `<div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:10px;margin-top:8px">
                <div style="font-size:10px;font-weight:800;color:#7c3aed;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">📚 Hausaufgaben</div>
                ${rows}
            </div>`;
        }

        document.getElementById('untis-dlg-body').innerHTML = `
            <div style="font-size:20px;font-weight:800;color:#1e3a5f;padding-right:24px">${esc(displayFach)}</div>
            <div style="font-size:12px;color:#64748b;margin-top:2px;margin-bottom:14px">${esc(blockLabel)} · ${esc(l.startTime)}–${esc(l.endTime)} · ${longD(dateYMD)}</div>
            ${row('Status', badge)}
            ${row('Raum', esc(l.room))}
            ${row('Lehrer', esc(displayLehrer))}
            ${l.info ? row('Hinweis', `<span style="color:#dc2626;font-weight:600">${esc(l.info)}</span>`) : ''}
            ${hwHTML}`;

        dlg.showModal();
    }

    // ---- Gitter rendern ----
    function renderGrid(days, el, mon, isSubst, storeKey) {
        stores[storeKey] = []; // Store für diesen Aufruf leeren
        const byDate = {};
        for (const d of days) byDate[String(d.date)] = d.lessons||[];

        const wdates = Array.from({length:5}, (_,i)=>String(addD(mon,i)));

        // Blöcke mit Änderungen pro Tag
        const changedBlocks = {};
        wdates.forEach(y=>{
            changedBlocks[y]=new Set((byDate[y]||[]).filter(l=>l.type!=='normal'||l.info).map(blockOf));
        });

        // Tabelle
        let h = '<div class="untis-grid-wrap"><table class="untis-grid">';
        h += '<colgroup><col style="width:90px">';
        for (let i=0;i<5;i++) h += `<col style="width:${Math.floor((100-7)/5)}%">`;
        h += '</colgroup>';

        // Kopf
        h += '<thead><tr><th class="untis-th-block">Zeiten</th>';
        wdates.forEach((y,i)=>{
            const t=isToday(y), c=isSubst&&changedBlocks[y]?.size>0;
            h += `<th class="untis-th-day${t?' untis-today':''}${c?' untis-has-changes':''}">
                <div class="untis-day-name">${DAYS[i]}${c?' ⚡':''}</div>
                <div class="untis-day-date">${shortD(y)}</div>
            </th>`;
        });
        h += '</tr></thead><tbody>';

        // Zeilen
        BLOCKS.forEach((block, bi) => {
            const isMittag = block.label==='Mittag';
            const rowCls = isMittag ? 'untis-mittag-row' : (bi%2===0 ? 'untis-row-even' : 'untis-row-odd');
            h += `<tr class="${rowCls}">`;
            h += `<td class="untis-td-block">
                <div class="untis-block-label">${esc(block.label)}</div>
                <div class="untis-block-time">${esc(block.start)}</div>
                <div class="untis-block-time">${esc(block.end)}</div>
            </td>`;

            wdates.forEach(y => {
                const lessons = (byDate[y]||[]).filter(l=>blockOf(l)===bi);
                const hasChg  = isSubst && changedBlocks[y]?.has(bi);
                let inner = '';

                // Tag-Index (1=Mo…5=Fr) aus weekdates ermitteln
                const wochentag = (wdates.indexOf(y) + 1); // 0-basiert +1
                // Block 5: AGs aus Datenbank anzeigen (Untis kennt sie nicht)
                const isBlock5 = block.label === 'Block 5';
                const dayAGs   = isBlock5 ? agsForDay(wochentag, y) : [];

                if (isMittag) {
                    inner = '<span style="color:#e2e8f0;font-size:16px;display:block;text-align:center;padding:4px 0">–</span>';
                } else if (isBlock5 && dayAGs.length > 0 && lessons.length === 0) {
                    // Nur AGs – keine Untis-Stunde in Block 5
                    dayAGs.forEach(ag => {
                        inner += `<div class="untis-cell" style="background:#f0fdf4;border-color:#86efac;border-left-color:#16a34a;cursor:default">
                            <div class="untis-cell-subject" style="color:#15803d">🎨 ${esc(ag.name)}</div>
                            <div class="untis-cell-detail">${ag.raum?esc(ag.raum):''}${ag.leitung?' · '+esc(ag.leitung):''}</div>
                        </div>`;
                    });
                } else if (lessons.length === 0) {
                    // Block 5 leer: eventuell AG vorhanden?
                    if (isBlock5 && dayAGs.length > 0) {
                        dayAGs.forEach(ag => {
                            inner += `<div class="untis-cell" style="background:#f0fdf4;border-color:#86efac;border-left-color:#16a34a;cursor:default">
                                <div class="untis-cell-subject" style="color:#15803d">🎨 ${esc(ag.name)}</div>
                                <div class="untis-cell-detail">${ag.raum?esc(ag.raum):''}${ag.leitung?' · '+esc(ag.leitung):''}</div>
                            </div>`;
                        });
                    } else {
                        inner = '<span class="untis-cell-free">·</span>';
                    }
                } else {
                    lessons.forEach(l => {
                        const changed = l.type!=='normal' || !!l.info;
                        if (isSubst && !changed) return; // Vertretungsplan: nur Abweichungen

                        // Zellklasse: cancelled / substitution / info (lila) / normal
                        const cCls = l.type==='cancelled'    ? 'untis-cell-cancelled'
                                   : l.type==='substitution' ? 'untis-cell-subst'
                                   : l.info                  ? 'untis-cell-info' : '';

                        // Badge oben in der Zelle
                        const badge = l.type==='cancelled'
                            ? '<span class="untis-cell-badge untis-cell-badge-cancelled">Entfall</span>'
                            : l.type==='substitution'
                            ? '<span class="untis-cell-badge untis-cell-badge-subst">Vertretung</span>'
                            : l.info
                            ? '<span class="untis-cell-badge untis-cell-badge-info">ℹ Info</span>' : '';

                        // Info-Text direkt sichtbar (max. 40 Zeichen, Rest per Popup)
                        const infoText = l.info
                            ? `<div class="untis-cell-info-text" title="${l.info.replace(/"/g,"'")}">${esc(l.info.length>38?l.info.slice(0,38)+'…':l.info)}</div>` : '';

                        // Hausaufgaben: nur die am Tag dieser Stunde fälligen (dueDate === Datum der Zelle)
                        const ymdNum = parseInt(y, 10);
                        const hws = (l.homeworks||[]).filter(h => !h.completed && h.dueDate === ymdNum);
                        const hwB = hws.length ? `<span class="untis-hw-badge">📚 ${hws.length}</span>` : '';

                        // Lesson im Array speichern, nur Index ins DOM
                        const idx = stores[storeKey].length;
                        stores[storeKey].push({ l, blockLabel:block.label, dateYMD:y, hws });

                        const displayFach    = resolveFach(l.subject);
                        const displayLehrer  = resolveLehrer(l.teacher);
                        inner += `<div class="untis-cell ${cCls}" data-idx="${idx}" data-store="${storeKey}">
                            <div class="untis-cell-subject">${esc(displayFach)}</div>
                            <div class="untis-cell-detail">${esc(l.room)}${l.teacher&&l.teacher!=='–'?' · '+esc(displayLehrer):''}</div>
                            ${infoText}${badge}${hwB}
                        </div>`;
                    });
                }

                const cls = `untis-td-day${isToday(y)?' untis-today':''}${hasChg?' untis-has-change':''}`;
                h += `<td class="${cls}">${inner}</td>`;
            });
            h += '</tr>';
        });
        h += '</tbody></table></div>';
        el.innerHTML = h;

        // Klick-Handler
        el.querySelectorAll('.untis-cell[data-idx]').forEach(cell => {
            cell.addEventListener('click', () => {
                const idx   = parseInt(cell.dataset.idx, 10);
                const store = stores[cell.dataset.store];
                if (store && store[idx]) {
                    const ymdNum = parseInt(store[idx].dateYMD, 10);
                    store[idx].hws = (store[idx].l.homeworks||[]).filter(h => !h.completed && h.dueDate === ymdNum);
                }
                openPopup(idx, cell.dataset.store);
            });
        });
    }

    function setLabel(el, mon) {
        if (el) el.textContent = `KW ${kw(mon)} · ${shortD(mon)} – ${shortD(addD(mon,4))}`;
    }

    // ---- Stundenplan ----
    let ttMon = monday(todayN());
    const ttEl  = document.getElementById('untis-tt-content');
    const ttLbl = document.getElementById('untis-tt-label');

    async function loadTT(silent=false) {
        if (!ttEl) return;
        if (!silent) ttEl.innerHTML='<p class="untis-empty">Wird geladen…</p>';
        try {
            const r=await fetch(`api/untis.php?action=timetable&date=${ttMon}`);
            const j=await r.json();
            if (!r.ok||j.error){ttEl.innerHTML=`<p class="untis-empty">⚠️ ${esc(j.error||'Fehler')}</p>`;return;}
            await Promise.all([ensureHW(), ensureLookup(), ensureAGs()]);
            renderGrid(j.data||[], ttEl, ttMon, false, 'tt');
            setLabel(ttLbl, ttMon);
        } catch(e){ttEl.innerHTML=`<p class="untis-empty">⚠️ ${esc(e.message)}</p>`;}
    }

    // ---- Vertretungsplan ----
    let substMon = monday(todayN());
    const substEl  = document.getElementById('untis-subst-content');
    const substLbl = document.getElementById('untis-subst-label');

    async function loadSubst(silent=false) {
        if (!substEl) return;
        if (!silent) substEl.innerHTML='<p class="untis-empty">Wird geladen…</p>';
        try {
            const r=await fetch(`api/untis.php?action=timetable&date=${substMon}`);
            const j=await r.json();
            if (!r.ok||j.error){substEl.innerHTML=`<p class="untis-empty">⚠️ ${esc(j.error||'Fehler')}</p>`;return;}
            const data=j.data||[];
            const hasAny=data.some(d=>(d.lessons||[]).some(l=>l.type!=='normal'||l.info));
            if (!hasAny){
                substEl.innerHTML='<p class="untis-empty" style="padding:28px 0;font-size:15px">✅ Keine Abweichungen diese Woche.</p>';
                setLabel(substLbl, substMon); return;
            }
            await Promise.all([ensureHW(), ensureLookup()]);
            renderGrid(data, substEl, substMon, true, 'subst');
            setLabel(substLbl, substMon);
        } catch(e){substEl.innerHTML=`<p class="untis-empty">⚠️ ${esc(e.message)}</p>`;}
    }

    // ---- Navigation ----
    document.getElementById('untis-tt-prev')?.addEventListener('click',    ()=>{ttMon=monday(addD(ttMon,-7));    loadTT();});
    document.getElementById('untis-tt-next')?.addEventListener('click',    ()=>{ttMon=monday(addD(ttMon,7));     loadTT();});
    document.getElementById('untis-subst-prev')?.addEventListener('click', ()=>{substMon=monday(addD(substMon,-7));loadSubst();});
    document.getElementById('untis-subst-next')?.addEventListener('click', ()=>{substMon=monday(addD(substMon,7)); loadSubst();});

    // ---- Auto-Refresh ----
    setInterval(()=>{loadTT(true);loadSubst(true);}, REFRESH_MS);
    document.addEventListener('visibilitychange',()=>{ if(!document.hidden){loadTT(true);loadSubst(true);} });

    loadTT(); loadSubst();
})();
