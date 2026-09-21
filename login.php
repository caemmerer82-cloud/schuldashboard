<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/auth.php';
if (auth_user()){header('Location: '.base_path('index.php'));exit;}
$error='';$next=isset($_GET['next'])?safe_redirect($_GET['next']):base_path('index.php');
if ($_SERVER['REQUEST_METHOD']==='POST'){
    $username=trim((string)($_POST['username']??''));
    $password=(string)($_POST['password']??'');
    $remember=!empty($_POST['remember']);
    if ($username===''||$password===''){$error='Bitte Benutzername und Passwort eingeben.';}
    else{
        $token=attempt_login($username,$password,$remember);
        if($token!==null){header('Location: '.$next);exit;}
        sleep(1);$error='Ungültiger Benutzername oder Passwort.';
    }
}
?><!DOCTYPE html>
<html lang="de"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Anmelden – Schul-Dashboard</title>
<link rel="stylesheet" href="assets/css/styles.css">
<style>
.remember-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: -4px 0 12px;
    font-size: 13px;
    color: var(--text-muted);
    cursor: pointer;
}
.remember-row input[type=checkbox] {
    width: 16px; height: 16px;
    accent-color: var(--primary);
    cursor: pointer;
}
</style>
</head>
<body class="login-body">
<main class="login-wrap"><div class="login-card">
<div class="login-logo">📚</div>
<h1 class="login-title">Schul-Dashboard</h1>
<p class="login-subtitle">Bitte melde dich an</p>
<?php if ($error!==''): ?>
<div class="alert alert-danger"><?=htmlspecialchars($error)?></div>
<?php endif; ?>
<form method="post" class="login-form" autocomplete="on">
<input type="hidden" name="next" value="<?=htmlspecialchars($next)?>">
<label>Benutzername
    <input type="text" name="username" autofocus autocomplete="username"
        value="<?=htmlspecialchars($_POST['username']??'')?>" maxlength="100" required>
</label>
<label>Passwort
    <input type="password" name="password" autocomplete="current-password" required>
</label>
<label class="remember-row">
    <input type="checkbox" name="remember" value="1" <?=!empty($_POST['remember'])?'checked':''?>>
    Angemeldet bleiben (90 Tage)
</label>
<button type="submit" class="btn btn-primary btn-full">Anmelden</button>
</form>
</div></main>
</body></html>
