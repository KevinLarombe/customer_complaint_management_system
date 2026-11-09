<?php
$pageTitle = $pageTitle ?? 'Complaint Resolution System';
$currentRole = $_SESSION['role'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#111111">
<title><?= e($pageTitle) ?> · ISP Support</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(app_url('style.css')) ?>">
</head>
<body>
<header class="topbar">
  <a class="brand" href="<?= e(app_url('index.php')) ?>">
    <span class="brand-mark">CR</span>
    <span class="brand-copy"><strong>COMPLAINT RESOLUTION</strong><small>ISP CUSTOMER SUPPORT</small></span>
  </a>
  <nav class="top-nav" aria-label="Account navigation">
    <?php if (!empty($_SESSION['user_id'])): ?>
      <span class="user-chip"><span class="user-dot"></span><?= e($_SESSION['username'] ?? '') ?><span class="role-label"><?= e(str_replace('_', ' ', $currentRole)) ?></span></span>
      <a class="logout-link" href="<?= e(app_url('logout.php')) ?>">Sign out <span aria-hidden="true">↗</span></a>
    <?php endif; ?>
  </nav>
</header>
<main class="container">
