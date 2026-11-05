<?php $b = $base ?? ''; $role = current_user()['role'] ?? ''; ?>
<nav class="sidebar d-flex flex-column p-3">
  <a class="brand text-white text-decoration-none fs-5 fw-bold mb-4" href="<?= $b ?>dashboard.php"><i class="bi bi-boxes"></i> StockPro</a>
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
  <a class="nav-link <?= ($active ?? '') === $key ? 'active' : '' ?>" href="<?= $b ?><?= $file ?>"><i class="bi <?= $icon ?>"></i> <?= $label ?></a>
  <?php endforeach; ?>
  <div class="mt-auto small text-white-50"><?= e(current_user()['name'] ?? '') ?><br><?= e(str_replace('_',' ', current_user()['role'] ?? '')) ?></div>
  <a class="nav-link" href="<?= $b ?>auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</nav>
