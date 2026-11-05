<?php $b = $base ?? ''; ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle ?? APP_NAME) ?> | <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= $b ?>assets/css/style.css" rel="stylesheet"></head>
<body><div class="layout"><?php include __DIR__ . '/sidebar.php'; ?><main class="main p-4">
<header class="d-flex justify-content-between align-items-center mb-4"><h1 class="h4 mb-0"><?= e($pageTitle ?? '') ?></h1>
<div class="d-flex gap-2 align-items-center"><input class="form-control form-control-sm" id="globalSearch" placeholder="Search products (AJAX)…" style="min-width:200px"><div id="globalSearchResults" class="small"></div>
<button class="btn btn-outline-secondary btn-sm" id="themeToggle" type="button"><i class="bi bi-moon"></i></button></div></header>
<?php if ($m = flash('success')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= e($m) ?></div><?php endif; ?>
