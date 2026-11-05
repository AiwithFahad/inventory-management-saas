<?php $b = $base ?? ''; $role = current_user()['role'] ?? ''; ?>
<nav class="sidebar d-flex flex-column p-3">
  <a class="brand text-white text-decoration-none fs-5 fw-bold mb-4" href="<?= $b ?>dashboard.php"><span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2.5 21 7.5v9l-9 5-9-5v-9l9-5Z" stroke="#fff" stroke-width="1.8"/><path d="M3.2 7.6 12 12.4l8.8-4.8M12 12.4v9" stroke="#fff" stroke-width="1.8"/></svg></span><span>StockPro</span></a>
  <?php foreach ([
    ['dashboard','dashboard.php','bi-speedometer2','Dashboard',ROLE_ALL],
    ['products','products.php','bi-box-seam','Products',ROLE_ALL],
    ['stock','stock.php','bi-arrow-left-right','Stock Movements',ROLE_STOCK],
    ['warehouses','warehouses.php','bi-building','Warehouses',ROLE_MANAGE],
    ['suppliers','suppliers.php','bi-truck','Suppliers',ROLE_PURCHASE],
    ['purchases','purchases.php','bi-cart-plus','Purchases',ROLE_PURCHASE],
    ['customers','customers.php','bi-people','Customers',ROLE_SALES],
    ['sales','sales.php','bi-receipt','Sales',ROLE_SALES],
    ['alerts','alerts.php','bi-bell','Alerts & Reorder',ROLE_ALL],
    ['finance','finance.php','bi-cash-stack','Finance',ROLE_MONEY],
    ['reports','reports.php','bi-bar-chart','Reports',ROLE_ALL],
    ['employees','employees.php','bi-person-badge','Employees',ROLE_OWNER],
    ['subscription','subscription.php','bi-credit-card','Subscription',ROLE_OWNER],
    ['settings','settings.php','bi-gear','Settings',ROLE_OWNER],
  ] as [$key,$file,$icon,$label,$roles]): if (!in_array($role, $roles, true)) continue; ?>
  <a class="nav-link <?= ($active ?? '') === $key ? 'active' : '' ?>" href="<?= $b ?><?= $file ?>"><i class="bi <?= $icon ?>"></i> <span class="lbl"><?= $label ?></span></a>
  <?php endforeach; ?>
  <?php $__u = trim((string)(current_user()['name'] ?? 'U')); $__p = preg_split('/\s+/', $__u); $__ini = strtoupper(mb_substr($__p[0] ?? 'U', 0, 1) . (isset($__p[1]) ? mb_substr($__p[1], 0, 1) : '')); ?>
  <div class="sidebar-user"><span class="avatar sm"><?= e($__ini) ?></span><span class="who small text-white-50"><?= e(current_user()['name'] ?? '') ?><br><?= e(str_replace('_',' ', current_user()['role'] ?? '')) ?></span></div>
  <a class="nav-link" href="<?= $b ?>auth/logout.php"><i class="bi bi-box-arrow-right"></i> <span class="lbl">Logout</span></a>
</nav>
