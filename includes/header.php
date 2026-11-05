<?php $b = $base ?? ''; ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle ?? APP_NAME) ?> | <?= e(APP_NAME) ?></title>
<script>try{if(localStorage.getItem('stock-theme')==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}</script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="<?= $b ?>assets/css/style.css" rel="stylesheet"></head>
<body><div class="layout"><?php include __DIR__ . '/sidebar.php'; ?><main class="main">
<header class="topbar"><div class="d-flex align-items-center gap-2">
<button class="icon-btn" id="sidebarToggle" type="button" title="Collapse sidebar" aria-label="Collapse sidebar"><i class="bi bi-layout-sidebar-inset"></i></button>
<h1 class="h4 mb-0"><?= e($pageTitle ?? '') ?></h1></div>
<div class="d-flex gap-2 align-items-center">
<div class="search-wrap"><i class="bi bi-search"></i><input class="form-control form-control-sm" id="globalSearch" placeholder="Search products (AJAX)…" autocomplete="off"><div id="globalSearchResults" class="small"></div></div>
<a class="icon-btn" href="<?= $b ?>alerts.php" title="Stock alerts" aria-label="Stock alerts"><i class="bi bi-bell"></i><?php if (isset($alertsCount) && $alertsCount): ?><span class="dot"><?= (int)$alertsCount ?></span><?php endif; ?></a>
<button class="icon-btn" id="themeToggle" type="button" title="Light / dark theme" aria-label="Toggle theme"><i class="bi bi-moon-stars"></i></button>
<?php $__u = trim((string)(current_user()['name'] ?? 'U')); $__p = preg_split('/\s+/', $__u); $__ini = strtoupper(mb_substr($__p[0] ?? 'U', 0, 1) . (isset($__p[1]) ? mb_substr($__p[1], 0, 1) : '')); ?>
<span class="avatar" title="<?= e($__u) ?>"><?= e($__ini) ?></span>
</div></header>
<?php if ($m = flash('success')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= e($m) ?></div><?php endif; ?>
