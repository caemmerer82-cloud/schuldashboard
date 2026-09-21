/* Schul-Dashboard – Frontend Logik */
(() => {
    'use strict';

    const API = {
        homework: 'api/homework.php',
        events:   'api/events.php',
    };

    // ---------- Utilities ----------
    const $ = sel => document.querySelector(sel);
    const $$ = sel => Array.from(document.querySelectorAll(sel));

    const DAY = 86400000;

    function today() {
        const d = new Date();
        d.setHours(0, 0, 0, 0);
        return d;
    }

    function parseDate(s) {
        // s = "YYYY-MM-DD"
        if (!s) return null;
        const [y, m, d] = s.split('-').map(n => parseInt(n, 10));
        if (!y || !m || !d) return null;
        return new Date(y, m - 1, d);
    }

    function formatDateDE(s) {
        const d = parseDate(s);
        if (!d) return s;
        return d.toLocaleDateString('de-DE', {
            weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric'
        });
    }

    function daysUntil(s) {
        const due = parseDate(s);
        if (!due) return Infinity;
        return Math.round((due.getTime() - today().getTime()) / DAY);
    }

    function urgencyClass(hw) {
        if (hw.status === 'done') return 'urgency-done';
        const diff = daysUntil(hw.due_date);
        if (diff < 0)  return 'urgency-overdue';
        if (diff <= 1) return 'urgency-urgent';   // heute oder morgen
        if (diff <= 3) return 'urgency-soon';
        return 'urgency-ok';
    }

    function dueLabel(hw) {
        const diff = daysUntil(hw.due_date);
        const base = formatDateDE(hw.due_date);
        if (hw.status === 'done')       return base;
        if (diff < 0)                   return `${base} (überfällig)`;
        if (diff === 0)                 return `${base} (heute!)`;
        if (diff === 1)                 return `${base} (morgen!)`;
        if (diff <= 7)                  return `${base} (in ${diff} Tagen)`;
        return base;
    }

    async function api(url, method = 'GET', body = null) {
        const opts = { method, headers: {} };
        if (body !== null) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const r = await fetch(url, opts);
        if (!r.ok) {
            let msg = `HTTP ${r.status}`;
            try { const j = await r.json(); if (j.error) msg = j.error; } catch (_) {}
            throw new Error(msg);
        }
        if (r.status === 204) return null;
        return r.json();
    }

    // ---------- Kanban ----------
    let homework = [];
    const hwFilter = {
        subject: '',            // '' = alle
        urgencies: new Set(),   // leer = alle
    };

    async function loadHomework() {
        homework = await api(API.homework);
        updateSubjectFilterOptions();
        renderKanban();
    }

    function updateSubjectFilterOptions() {
        const sel = $('#hw-filter-subject');
        if (!sel) return;
        const subjects = Array.from(new Set(
            homework.map(h => (h.subject || '').trim()).filter(Boolean)
        )).sort((a, b) => a.localeCompare(b, 'de'));
        const prev = hwFilter.subject;
        sel.innerHTML = '';
        const optAll = document.createElement('option');
        optAll.value = '';
        optAll.textContent = 'Alle';
        sel.appendChild(optAll);
        for (const s of subjects) {
            const o = document.createElement('option');
            o.value = s;
            o.textContent = s;
            sel.appendChild(o);
        }
        // Auswahl beibehalten, falls Fach noch existiert
        sel.value = subjects.includes(prev) ? prev : '';
        hwFilter.subject = sel.value;
    }

    function hwMatchesFilter(hw) {
        if (hwFilter.subject && (hw.subject || '').trim() !== hwFilter.subject) return false;
        if (hwFilter.urgencies.size > 0 && !hwFilter.urgencies.has(urgencyClass(hw))) return false;
        return true;
    }

    // Kanban-Spalten-Labels aus PHP-Variable (gesetzt in index.php)
    function applyKanbanLabels() {
        const spalten = window.KANBAN_SPALTEN || [];
        for (const s of spalten) {
            const col = document.querySelector(`.kanban-column[data-status="${s.key}"]`);
            if (col) {
                // Sichtbarkeit
                col.style.display = s.aktiv ? '' : 'none';
                // Label im Header
                const h = col.querySelector('header');
                if (h) {
                    // Text-Node nach dem Dot-Span aktualisieren
                    const dot = h.querySelector('.dot');
                    const count = h.querySelector('.count');
                    if (dot && count) {
                        h.innerHTML = '';
                        h.appendChild(dot);
                        h.appendChild(document.createTextNode(' ' + s.label + ' '));
                        h.appendChild(count);
                    }
                }
            }
        }
    }

    function renderKanban() {
        const visible = homework.filter(hwMatchesFilter);
        const byStatus = { todo: [], doing: [], done: [] };
        for (const hw of visible) {
            (byStatus[hw.status] || byStatus.todo).push(hw);
        }

        // Sortierung je Spalte
        const urgencyRank = { 'urgency-overdue': 0, 'urgency-urgent': 1, 'urgency-soon': 2, 'urgency-ok': 3, 'urgency-done': 4 };
        for (const s of ['todo', 'doing']) {
            byStatus[s].sort((a, b) => {
                const ra = urgencyRank[urgencyClass(a)] ?? 9;
                const rb = urgencyRank[urgencyClass(b)] ?? 9;
                if (ra !== rb) return ra - rb;
                return a.due_date.localeCompare(b.due_date);
            });
        }
        byStatus.done.sort((a, b) => b.due_date.localeCompare(a.due_date));

        applyKanbanLabels();
        for (const status of ['todo', 'doing', 'done']) {
            const col = document.querySelector(`[data-drop="${status}"]`);
            if (!col || col.style.display === 'none') return;
            col.innerHTML = '';
            const items = byStatus[status];
            const DONE_LIMIT = 3;
            if (status === 'done' && items.length > DONE_LIMIT) {
                items.slice(0, DONE_LIMIT).forEach(hw => col.appendChild(buildCard(hw)));
                const more = document.createElement('button');
                more.className = 'btn btn-ghost mehr-anzeigen';
                more.textContent = `+ ${items.length - DONE_LIMIT} weitere erledigte Aufgaben`;
                more.addEventListener('click', () => {
                    col.removeChild(more);
                    items.slice(DONE_LIMIT).forEach(hw => col.appendChild(buildCard(hw)));
                });
                col.appendChild(more);
            } else {
                items.forEach(hw => col.appendChild(buildCard(hw)));
            }
            const count = document.querySelector(`[data-count-for="${status}"]`);
            if (count) count.textContent = items.length;
        }
    }

    function buildCard(hw) {
        const el = document.createElement('article');
        el.className = `card ${urgencyClass(hw)}`;
        el.draggable = true;
        el.dataset.id = hw.id;

        const top = document.createElement('div');
        top.className = 'card-top';
        const subj = document.createElement('span');
        subj.className = 'subject-tag';
        subj.textContent = hw.subject;
        top.appendChild(subj);
        el.appendChild(top);

        const title = document.createElement('div');
        title.className = 'card-title';
        title.textContent = hw.title;
        el.appendChild(title);

        if (hw.description) {
            const desc = document.createElement('div');
            desc.className = 'card-desc';
            desc.textContent = hw.description;
            el.appendChild(desc);
        }

        const footer = document.createElement('div');
        footer.className = 'card-footer';
        const due = document.createElement('span');
        due.className = 'due';
        due.textContent = '📅 ' + dueLabel(hw);
        footer.appendChild(due);

        const next = nextStatus(hw.status);
        if (next) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-ghost';
            btn.style.padding = '2px 8px';
            btn.style.fontSize = '12px';
            btn.textContent = next.label;
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                await updateHomework(hw.id, { status: next.value });
            });
            footer.appendChild(btn);
        }
        el.appendChild(footer);

        // Bearbeiten (Klick öffnet Dialog)
        el.addEventListener('click', () => openHomeworkDialog(hw));

        // Drag & Drop
        el.addEventListener('dragstart', (e) => {
            el.classList.add('dragging');
            e.dataTransfer.setData('text/plain', String(hw.id));
            e.dataTransfer.effectAllowed = 'move';
        });
        el.addEventListener('dragend', () => el.classList.remove('dragging'));

        return el;
    }

    function nextStatus(status) {
        if (status === 'todo')  return { value: 'doing', label: '→ In Arbeit' };
        if (status === 'doing') return { value: 'done',  label: '✓ Erledigt' };
        if (status === 'done')  return { value: 'todo',  label: '↺ Zurück' };
        return null;
    }

    // Drag-Zonen einrichten
    function initKanbanDnD() {
        $$('.kanban-cards').forEach(zone => {
            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                zone.classList.add('drop-target');
                e.dataTransfer.dropEffect = 'move';
            });
            zone.addEventListener('dragleave', () => zone.classList.remove('drop-target'));
            zone.addEventListener('drop', async (e) => {
                e.preventDefault();
                zone.classList.remove('drop-target');
                const id = parseInt(e.dataTransfer.getData('text/plain'), 10);
                const status = zone.dataset.drop;
                if (!id || !status) return;
                await updateHomework(id, { status });
            });
        });
    }

    async function updateHomework(id, patch) {
        await api(API.homework, 'PUT', { id, ...patch });
        await loadHomework();
    }

    // ---------- Homework Dialog ----------
    const dlgHw   = $('#dlg-homework');
    const formHw  = $('#form-homework');

    function openHomeworkDialog(hw = null) {
        formHw.reset();
        formHw.elements.id.value = hw ? hw.id : '';
        $('#dlg-homework-title').textContent = hw ? 'Hausaufgabe bearbeiten' : 'Neue Hausaufgabe';
        const delBtn = formHw.querySelector('[data-action="delete"]');
        delBtn.hidden = !hw || !window.DASHBOARD_CAN_DELETE;

        if (hw) {
            formHw.elements.subject.value     = hw.subject;
            formHw.elements.title.value       = hw.title;
            formHw.elements.description.value = hw.description || '';
            formHw.elements.due_date.value    = hw.due_date;
            formHw.elements.status.value      = hw.status;
        } else {
            // Standard: heute + 1 Tag
            const t = new Date(); t.setDate(t.getDate() + 1);
            formHw.elements.due_date.value = t.toISOString().slice(0, 10);
            formHw.elements.status.value   = 'todo';
        }
        dlgHw.showModal();
    }

    formHw.addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = formHw.elements;
        const payload = {
            subject:     f.subject.value.trim(),
            title:       f.title.value.trim(),
            description: f.description.value.trim(),
            due_date:    f.due_date.value,
            status:      f.status.value,
        };
        try {
            const id = f.id.value ? parseInt(f.id.value, 10) : null;
            if (id) {
                await api(API.homework, 'PUT', { id, ...payload });
            } else {
                await api(API.homework, 'POST', payload);
            }
            dlgHw.close();
            await loadHomework();
        } catch (err) {
            alert('Fehler beim Speichern: ' + err.message);
        }
    });

    formHw.querySelector('[data-action="cancel"]').addEventListener('click', () => dlgHw.close());
    formHw.querySelector('[data-action="delete"]').addEventListener('click', async () => {
        const id    = parseInt(formHw.elements.id.value, 10);
        if (!id) return;
        const fach  = formHw.elements.subject.value || '';
        const titel = formHw.elements.title.value   || '';
        const label = fach ? `„${fach}: ${titel}"` : `„${titel}"`;
        if (!confirm(`Hausaufgabe ${label} wirklich unwiderruflich löschen?`)) return;
        try { await api(`${API.homework}?id=${id}`, 'DELETE'); dlgHw.close(); await loadHomework(); }
        catch (err) { alert('Fehler beim Löschen: ' + err.message); }
    });

    $('#btn-new-homework').addEventListener('click', () => openHomeworkDialog());

    // ---------- Events ----------
    let events = [];
    const evFilter = {
        types: new Set(), // leer = alle
    };

    async function loadEvents() {
        events = await api(API.events);
        renderEvents();
    }

    function evMatchesFilter(ev) {
        if (evFilter.types.size === 0) return true;
        return evFilter.types.has(ev.event_type);
    }

    const TYPE_LABELS = {
        klausur:       'Klausur',
        test:          'Test',
        pruefung:      'Prüfung',
        veranstaltung: 'Veranstaltung',
        sonstiges:     'Sonstiges',
    };

    // Termine: Sichtbarkeits-Status
    let evShowAll = false;
    let evShowPast = false;

    function getVisibleEvents() {
        const t = today();
        const twoWeeksAhead = new Date(t); twoWeeksAhead.setDate(t.getDate() + 14);
        const filtered = events.filter(evMatchesFilter);
        if (evShowAll) return evShowPast ? filtered : filtered.filter(ev => { const d=parseDate(ev.event_date); return !d||d>=t; });
        // Standardansicht: nächste 2 Wochen + mind. 5 Termine gesamt
        const upcoming = filtered.filter(ev => { const d=parseDate(ev.event_date); return d&&d>=t; });
        const inTwoWeeks = upcoming.filter(ev => parseDate(ev.event_date)<=twoWeeksAhead);
        if (upcoming.length<=5) return upcoming.slice(0,5);
        return inTwoWeeks.length>=5 ? inTwoWeeks : upcoming.slice(0,5);
    }

    function renderEvents() {
        const list = $('#event-list');
        list.innerHTML = '';
        const t = today();
        const filtered = events.filter(evMatchesFilter);
        const visible = getVisibleEvents();

        if (!filtered.length) {
            const li = document.createElement('li');
            li.className = 'empty';
            li.style.cssText = 'padding:16px;color:var(--text-muted);text-align:center;list-style:none;';
            li.textContent = 'Noch keine Termine angelegt.';
            list.appendChild(li);
            return;
        }
        if (!visible.length) {
            const li = document.createElement('li');
            li.className = 'empty';
            li.style.cssText = 'padding:16px;color:var(--text-muted);text-align:center;list-style:none;';
            li.textContent = 'Keine Termine in den nächsten 2 Wochen.';
            list.appendChild(li);
        }

        for (const ev of visible) {
            const d = parseDate(ev.event_date);
            const li = document.createElement('li');
            li.className = `event type-${ev.event_type}${d && d < t ? ' past' : ''}`;
            li.addEventListener('click', () => openEventDialog(ev));

            const date = document.createElement('div');
            date.className = 'event-date';
            if (d) {
                date.innerHTML =
                    `${d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })}` +
                    `<small>${d.toLocaleDateString('de-DE', { weekday: 'short' })}</small>`;
            } else { date.textContent = ev.event_date; }
            li.appendChild(date);

            const body = document.createElement('div');
            const title = document.createElement('div');
            title.className = 'event-title';
            title.textContent = ev.title;
            body.appendChild(title);
            if (ev.notes) {
                const n = document.createElement('div');
                n.className = 'event-notes';
                n.textContent = ev.notes;
                body.appendChild(n);
            }
            li.appendChild(body);

            const right = document.createElement('div');
            right.style.cssText = 'display:flex;flex-direction:column;align-items:flex-end;gap:4px;';
            const badge = document.createElement('span');
            badge.className = 'event-type-badge';
            badge.textContent = TYPE_LABELS[ev.event_type] || ev.event_type;
            right.appendChild(badge);
            // Notizen-Link
            const noteLink = document.createElement('a');
            noteLink.href = `notizen.php?event_id=${ev.id}`;
            noteLink.className = 'btn btn-ghost';
            noteLink.style.cssText = 'font-size:11px;padding:2px 7px;';
            noteLink.textContent = '📝 Notizen';
            noteLink.addEventListener('click', e => e.stopPropagation());
            right.appendChild(noteLink);
            li.appendChild(right);

            list.appendChild(li);
        }

        // "Mehr anzeigen"-Button
        const upcoming = filtered.filter(ev => { const d=parseDate(ev.event_date); return d&&d>=t; });
        if (!evShowAll && upcoming.length > visible.length) {
            const li = document.createElement('li');
            li.style.cssText = 'list-style:none;padding:8px 0;';
            const btn = document.createElement('button');
            btn.className = 'btn btn-ghost mehr-anzeigen';
            btn.textContent = `+ ${upcoming.length - visible.length} weitere Termine anzeigen`;
            btn.addEventListener('click', () => { evShowAll=true; renderEvents(); });
            li.appendChild(btn);
            list.appendChild(li);
        }
        // Bei "alle anzeigen": vergangene Termine ein-/ausblenden
        if (evShowAll) {
            const past = filtered.filter(ev => { const d=parseDate(ev.event_date); return d&&d<t; });
            const li = document.createElement('li');
            li.style.cssText = 'list-style:none;padding:4px 0;display:flex;gap:8px;flex-wrap:wrap;';
            if (past.length) {
                const btnP = document.createElement('button');
                btnP.className = 'btn btn-ghost';
                btnP.style.fontSize = '12px';
                btnP.textContent = evShowPast ? '🙈 Vergangene ausblenden' : `📅 ${past.length} vergangene Termine anzeigen`;
                btnP.addEventListener('click', () => { evShowPast=!evShowPast; renderEvents(); });
                li.appendChild(btnP);
            }
            const btnL = document.createElement('button');
            btnL.className = 'btn btn-ghost';
            btnL.style.fontSize = '12px';
            btnL.textContent = '↑ Weniger anzeigen';
            btnL.addEventListener('click', () => { evShowAll=false; evShowPast=false; renderEvents(); });
            li.appendChild(btnL);
            list.appendChild(li);
        }
    }

    // ---------- Event Dialog ----------
    const dlgEv  = $('#dlg-event');
    const formEv = $('#form-event');

    function openEventDialog(ev = null) {
        formEv.reset();
        formEv.elements.id.value = ev ? ev.id : '';
        $('#dlg-event-title').textContent = ev ? 'Termin bearbeiten' : 'Neuer Termin';
        formEv.querySelector('[data-action="delete"]').hidden = !ev || !window.DASHBOARD_CAN_DELETE;
        if (ev) {
            formEv.elements.event_type.value = ev.event_type;
            formEv.elements.title.value      = ev.title;
            formEv.elements.event_date.value = ev.event_date;
            formEv.elements.notes.value      = ev.notes || '';
        } else {
            formEv.elements.event_type.value = 'klausur';
            formEv.elements.event_date.value = new Date().toISOString().slice(0, 10);
        }
        dlgEv.showModal();
    }

    formEv.addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = formEv.elements;
        const payload = {
            event_type: f.event_type.value,
            title:      f.title.value.trim(),
            event_date: f.event_date.value,
            notes:      f.notes.value.trim(),
        };
        try {
            const id = f.id.value ? parseInt(f.id.value, 10) : null;
            if (id) {
                await api(API.events, 'PUT', { id, ...payload });
            } else {
                await api(API.events, 'POST', payload);
            }
            dlgEv.close();
            await loadEvents();
        } catch (err) {
            alert('Fehler beim Speichern: ' + err.message);
        }
    });

    formEv.querySelector('[data-action="cancel"]').addEventListener('click', () => dlgEv.close());
    formEv.querySelector('[data-action="delete"]').addEventListener('click', async () => {
        const id    = parseInt(formEv.elements.id.value, 10);
        if (!id) return;
        const titel = formEv.elements.title.value      || '';
        const datum = formEv.elements.event_date.value || '';
        const label = titel ? `„${titel}"${datum ? ' am ' + datum : ''}` : 'diesen Termin';
        if (!confirm(`Termin ${label} wirklich unwiderruflich löschen?`)) return;
        try { await api(`${API.events}?id=${id}`, 'DELETE'); dlgEv.close(); await loadEvents(); }
        catch (err) { alert('Fehler beim Löschen: ' + err.message); }
    });

    $('#btn-new-event').addEventListener('click', () => openEventDialog());

    // ---------- Filter UI ----------
    function wireChipGroup(containerSel, dataAttr, stateSet, onChange) {
        const container = document.querySelector(containerSel);
        if (!container) return;
        const buttons = Array.from(container.querySelectorAll('.chip-filter'));
        const allBtn = buttons.find(b => b.dataset[dataAttr] === 'all');

        function sync() {
            const allActive = stateSet.size === 0;
            if (allBtn) allBtn.classList.toggle('active', allActive);
            for (const btn of buttons) {
                if (btn === allBtn) continue;
                btn.classList.toggle('active', stateSet.has(btn.dataset[dataAttr]));
            }
        }

        for (const btn of buttons) {
            btn.addEventListener('click', () => {
                const val = btn.dataset[dataAttr];
                if (val === 'all') {
                    stateSet.clear();
                } else if (stateSet.has(val)) {
                    stateSet.delete(val);
                } else {
                    stateSet.add(val);
                }
                sync();
                onChange();
            });
        }
        sync();
    }

    wireChipGroup('#hw-filter-urgency', 'urgency', hwFilter.urgencies, renderKanban);
    wireChipGroup('#ev-filter-type',    'type',    evFilter.types,    renderEvents);

    const subjectSel = $('#hw-filter-subject');
    if (subjectSel) {
        subjectSel.addEventListener('change', () => {
            hwFilter.subject = subjectSel.value;
            renderKanban();
        });
    }

    // ---------- Init ----------
    initKanbanDnD();
    loadHomework().catch(err => console.error(err));
    loadEvents().catch(err   => console.error(err));

    // Alle 5 Minuten neu laden (Tageswechsel, Mehrgeräte-Nutzung)
    setInterval(() => {
        loadHomework().catch(() => {});
        loadEvents().catch(() => {});
    }, 5 * 60 * 1000);
})();
