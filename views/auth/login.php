<?php
// views/auth/login.php
$appName = Security::e(EnvLoader::get('APP_NAME','Sari-POS'));
Security::setHeaders();
$csrfToken = Security::csrfToken();
?>
<!DOCTYPE html>
<html lang="fil">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — <?= $appName ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/public/css/app.css?v=2">
</head>
<body class="auth-body">
<div class="auth-bg">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="auth-icon"><i class="fas fa-store-alt"></i></div>
      <h1><?= $appName ?></h1>
      <p>Sari-sari Store Management System</p>
    </div>
    <form id="loginForm" novalidate>
      <input type="hidden" name="csrf_token" id="csrfInput" value="<?= Security::e($csrfToken) ?>">
      <div class="form-group">
        <label><i class="fas fa-user"></i> Username</label>
        <input type="text" name="username" id="loginUser" class="form-input" placeholder="Enter username"
               autocomplete="username" required maxlength="60">
      </div>
      <div class="form-group">
        <label><i class="fas fa-lock"></i> Password</label>
        <div class="input-suffix">
          <input type="password" name="password" id="loginPass" class="form-input" placeholder="Enter password"
                 autocomplete="current-password" required maxlength="100">
          <button type="button" class="suffix-btn" id="eyeBtn" onclick="togglePw()">
            <i class="fas fa-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>
      <div id="loginErr" class="alert alert-danger" style="display:none"></div>
      <button type="submit" class="btn btn-primary btn-block" id="loginBtn">
        <i class="fas fa-sign-in-alt"></i> Mag-login
      </button>
    </form>
    <p class="auth-hint">
      <i class="fas fa-info-circle"></i>
      Default: <strong>admin</strong> / <strong>Admin@123</strong>
    </p>
  </div>
</div>
<script>
function togglePw(){
  const p=document.getElementById('loginPass'),i=document.getElementById('eyeIcon');
  p.type=p.type==='password'?'text':'password';
  i.className=p.type==='password'?'fas fa-eye':'fas fa-eye-slash';
}
document.getElementById('loginForm').addEventListener('submit',async function(e){
  e.preventDefault();
  const btn=document.getElementById('loginBtn'), err=document.getElementById('loginErr');
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Signing in...';
  err.style.display='none';
  try{
    const fd=new FormData(this);
    const r=await fetch('/sari-pos/index.php?page=login',{method:'POST',body:fd});
    const d=await r.json();
    if(d.success){ window.location.href=d.redirect||'/index.php?page=dashboard'; }
    else{ err.textContent=d.message; err.style.display='block'; btn.disabled=false; btn.innerHTML='<i class="fas fa-sign-in-alt"></i> Mag-login'; }
  }catch(ex){
    err.textContent='Network error. Subukan ulit.'; err.style.display='block';
    btn.disabled=false; btn.innerHTML='<i class="fas fa-sign-in-alt"></i> Mag-login';
  }
});
</script>
</body>
</html>
