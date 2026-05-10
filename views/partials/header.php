<?php
if (!isset($page)) $page = '';
$user     = Auth::user();
$appName  = Security::e(EnvLoader::get('APP_NAME','Sari-POS'));
$pusherJs = json_encode((new PusherService())->getJsConfig(), JSON_HEX_TAG);
?>
<!DOCTYPE html>
<html lang="fil">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= Security::e($pageTitle ?? 'Dashboard') ?> — <?= $appName ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/sari-pos/public/css/app.css?v=3">
<script>window.PUSHER_CONFIG = <?= $pusherJs ?>;</script>
</head>
<body>
<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <i class="fas fa-store-alt"></i>
    <div>
      <span class="brand-name"><?= $appName ?></span>
      <span class="brand-sub">Sari-sari Store</span>
    </div>
  </div>
  <ul class="sidebar-menu">
    <li class="menu-label">Main</li>
    <li><a href="/sari-pos/index.php?page=dashboard" class="<?= $page==='dashboard'?'active':'' ?>"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a></li>
    <li><a href="/sari-pos/index.php?page=pos" class="<?= $page==='pos'?'active':'' ?>"><i class="fas fa-cash-register"></i><span>Point of Sale</span></a></li>
    <li class="menu-label">Inventory</li>
    <li><a href="/sari-pos/index.php?page=products" class="<?= $page==='products'?'active':'' ?>"><i class="fas fa-boxes"></i><span>Mga Produkto</span></a></li>
    <li><a href="/sari-pos/index.php?page=transactions" class="<?= $page==='transactions'?'active':'' ?>"><i class="fas fa-receipt"></i><span>Transactions</span></a></li>
    <li class="menu-label">Customers</li>
    <li><a href="/sari-pos/index.php?page=customers" class="<?= $page==='customers'?'active':'' ?>"><i class="fas fa-users"></i><span>Utang / Customers</span></a></li>
    <li class="menu-label">Finance</li>
    <li><a href="/sari-pos/index.php?page=expenses" class="<?= $page==='expenses'?'active':'' ?>"><i class="fas fa-wallet"></i><span>Gastos / Expenses</span></a></li>
    <?php if($user['role']==='admin'): ?>
    <li><a href="/sari-pos/index.php?page=reports" class="<?= $page==='reports'?'active':'' ?>"><i class="fas fa-chart-bar"></i><span>Ad-hoc Reports</span></a></li>
    <li class="menu-label">Admin</li>
    <li><a href="/sari-pos/index.php?page=users" class="<?= $page==='users'?'active':'' ?>"><i class="fas fa-user-shield"></i><span>Users</span></a></li>
    <?php endif; ?>
  </ul>
  <div class="sidebar-user">
    <div class="su-avatar"><?= strtoupper(substr($user['username'],0,1)) ?></div>
    <div class="su-info">
      <span class="su-name"><?= Security::e($user['full_name'] ?: $user['username']) ?></span>
      <span class="su-role badge-<?= $user['role'] ?>"><?= Security::e($user['role']) ?></span>
    </div>
    <a href="/sari-pos/index.php?page=logout" class="su-logout" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</nav>
<div class="main-wrap">
  <header class="topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('closed')">
      <i class="fas fa-bars"></i>
    </button>
    <h1 class="topbar-title"><?= Security::e($pageTitle ?? 'Dashboard') ?></h1>
    <div class="topbar-right">
      <div class="rt-badge">
        <span class="rt-dot" id="rtDot"></span>
        <span id="rtLabel">Connecting...</span>
      </div>
      <span class="topbar-clock" id="topClock"></span>
    </div>
  </header>
  <div id="toasts" class="toast-stack"></div>
  <div class="page-content">