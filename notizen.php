<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/auth.php';
$me  = auth_require();
$tid = (int)($me['tenant_id']??1);
$eid = (int)($_GET['event_id']??0);

if ($eid<=0) {
    header('Location: index.php'); exit;
}

// Termin laden
$stmt=db()->prepare("SELECT e.*,en.content AS notiz_content,en.updated_at AS notiz_updated FROM events e LEFT JOIN event_notes en ON en.event_id=e.id AND en.tenant_id=e.tenant_id WHERE e.id=? AND e.tenant_id=?");
$stmt->execute([$eid,$tid]);
$ev=$stmt->fetch();
if (!$ev) { header('Location: index.php'); exit; }

$canEdit = in_array($me['role']??'',['tenant_admin','superadmin'],true);

$typeLabel=['klausur'=>'Klausur','test'=>'Test','pruefung'=>'Prüfung','veranstaltung'=>'Veranstaltung','sonstiges'=>'Sonstiges'][$ev['event_type']]??$ev['event_type'];
$datum=date('d.m.Y',strtotime($ev['event_date']));
?><!DOCTYPE html>
<html lang="de"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Notizen – <?=htmlspecialchars($ev['title'])?> – Schul-Dashboard</title>
<link rel="stylesheet" href="assets/css/styles.css">
<style>
.notiz-wrap  { max-width:860px; margin:0 auto; padding:20px; }
.notiz-card  { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); padding:24px; }
.notiz-meta  { display:flex; align-items:center; gap:10px; margin-bottom:18px; flex-wrap:wrap; }
.notiz-title { font-size:22px; font-weight:800; color:#1e3a5f; }
.notiz-info  { font-size:13px; color:var(--text-muted); }
.notiz-editor {
    width:100%; min-height:420px; font:inherit; font-size:14px; line-height:1.7;
    padding:14px 16px; border:2px solid var(--border); border-radius:var(--radius-sm);
    resize:vertical; color:var(--text); background:var(--surface);
    box-sizing:border-box;
}
.notiz-editor:focus { outline:none; border-color:var(--primary); }
.notiz-actions { display:flex; align-items:center; gap:10px; margin-top:14px; }
.notiz-saved  { font-size:12px; color:var(--text-muted); }
.notiz-saved.ok  { color:var(--success); }
.notiz-saved.err { color:var(--danger); }
.notiz-readonly { white-space:pre-wrap; font-size:14px; line-height:1.7; color:var(--text); padding:14px 16px; background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius-sm); min-height:120px; }
</style>
</head><body>
<header class="topbar"><div class="topbar-inner">
    <h1><span class="logo">📝</span> Notizen</h1>
    <nav>
        <a href="index.php" class="btn btn-ghost">← Dashboard</a>
        <a href="logout.php" class="btn btn-ghost">Abmelden</a>
    </nav>
</div></header>

<main class="notiz-wrap">
<div class="notiz-card">
    <div class="notiz-meta">
        <span class="event-type-badge type-<?=htmlspecialchars($ev['event_type'])?>" style="font-size:13px;padding:3px 10px"><?=htmlspecialchars($typeLabel)?></span>
        <span class="notiz-title"><?=htmlspecialchars($ev['title'])?></span>
        <span class="notiz-info">📅 <?=htmlspecialchars($datum)?></span>
        <?php if($ev['notes']):?>
            <span class="notiz-info">· <?=htmlspecialchars($ev['notes'])?></span>
        <?php endif;?>
    </div>

    <?php if($canEdit): ?>
    <label style="font-size:13px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:6px">Notizen zum Termin</label>
    <textarea class="notiz-editor" id="notiz-editor" placeholder="Hier können Notizen zum Termin eingetragen werden – z.B. Ergebnisse, Aufgaben, Erinnerungen…"><?=htmlspecialchars($ev['notiz_content']??'')?></textarea>
    <div class="notiz-actions">
        <button class="btn btn-primary" id="btn-save">💾 Speichern</button>
        <button class="btn btn-ghost" id="btn-clear" onclick="if(confirm('Notizen wirklich leeren?'))document.getElementById('notiz-editor').value=''">Leeren</button>
        <span class="notiz-saved" id="save-status">
            <?php if($ev['notiz_updated']):?>
                Zuletzt gespeichert: <?=date('d.m.Y H:i',strtotime($ev['notiz_updated']))?>
            <?php endif;?>
        </span>
    </div>
    <?php else: ?>
    <label style="font-size:13px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:6px">Notizen zum Termin</label>
    <div class="notiz-readonly"><?=htmlspecialchars($ev['notiz_content']??'')?:('<span style="color:var(--text-muted);font-style:italic">Keine Notizen vorhanden.</span>')?></div>
    <?php endif;?>
</div>
</main>

<?php if($canEdit): ?>
<script>
const eid   = <?=$eid?>;
const btn   = document.getElementById('btn-save');
const editor= document.getElementById('notiz-editor');
const status= document.getElementById('save-status');
let dirty   = false;

editor.addEventListener('input',()=>{ dirty=true; status.textContent='Nicht gespeichert'; status.className='notiz-saved'; });

btn.addEventListener('click', async()=>{
    btn.disabled=true;
    try {
        const r=await fetch(`api/notizen.php?event_id=${eid}`,{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({content:editor.value})
        });
        const j=await r.json();
        if(!r.ok) throw new Error(j.error||'Fehler');
        const now=new Date().toLocaleString('de-DE',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
        status.textContent='✅ Gespeichert: '+now;
        status.className='notiz-saved ok';
        dirty=false;
    } catch(e){
        status.textContent='❌ '+e.message;
        status.className='notiz-saved err';
    } finally { btn.disabled=false; }
});

// Warnung beim Verlassen bei ungespeicherten Änderungen
window.addEventListener('beforeunload',e=>{
    if(dirty){ e.preventDefault(); e.returnValue=''; }
});

// Auto-Save alle 60 Sekunden
setInterval(()=>{ if(dirty) btn.click(); }, 60000);
</script>
<?php endif;?>
</body></html>
