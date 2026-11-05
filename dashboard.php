<?php require_once __DIR__ . '/includes/auth.php'; require_once __DIR__ . '/includes/roles.php';
$user = require_role(ROLE_ALL); $pdo = db(); check_tenant_active($pdo); $tid = require_tenant(); $base = ''; $active = 'dashboard'; $pageTitle = 'Inventory Dashboard';
$totalProducts = (int)q1($pdo, 'SELECT COUNT(*) FROM products WHERE tenant_id=? AND status="active"', [$tid]);
$totalStock = (float)(q1($pdo, 'SELECT COALESCE(SUM(quantity),0) FROM product_warehouse WHERE tenant_id=?', [$tid]) ?: 0);
$stockValue = (float)(q1($pdo, 'SELECT COALESCE(SUM(pw.quantity*p.cost_price),0) FROM product_warehouse pw JOIN products p ON p.id=pw.product_id WHERE pw.tenant_id=?', [$tid]) ?: 0);
$low = qa($pdo, 'SELECT * FROM products WHERE tenant_id=? AND status="active" AND stock_qty>0 AND stock_qty<=min_stock ORDER BY stock_qty', [$tid]);
$out = qa($pdo, 'SELECT * FROM products WHERE tenant_id=? AND status="active" AND stock_qty<=0 ORDER BY name', [$tid]);
$suppliers = (int)q1($pdo, 'SELECT COUNT(*) FROM suppliers WHERE tenant_id=?', [$tid]);
$openPO = (int)q1($pdo, 'SELECT COUNT(*) FROM purchases WHERE tenant_id=? AND status IN ("draft","ordered","approved","partial")', [$tid]);
$openSO = (int)q1($pdo, 'SELECT COUNT(*) FROM sales WHERE tenant_id=? AND status IN ("draft","confirmed")', [$tid]);
$revRows = qa($pdo, 'SELECT DATE_FORMAT(created_at,"%b") m, COALESCE(SUM(total),0) v FROM sales WHERE tenant_id=? AND status!="cancelled" GROUP BY MONTH(created_at) ORDER BY MONTH(created_at)', [$tid]);
$moves = qa($pdo, 'SELECT sm.*, p.name pname, w.name wname FROM stock_movements sm JOIN products p ON p.id=sm.product_id JOIN warehouses w ON w.id=sm.warehouse_id WHERE sm.tenant_id=? ORDER BY sm.id DESC LIMIT 8', [$tid]);
$alertsCount = count($low) + count($out);
include __DIR__ . '/includes/header.php'; ?>
<?php if ($alertsCount): ?><div class="alert alert-warning"><strong><?= $alertsCount ?> stock alerts:</strong> <?= count($low) ?> low-stock, <?= count($out) ?> out-of-stock. <a href="alerts.php">View alerts &amp; reorder suggestions</a></div><?php endif; ?>
<div class="row g-3">
<?php foreach ([['Total products',$totalProducts,'bi-box-seam'],['Total stock qty',$totalStock,'bi-boxes'],['Stock value',money($stockValue),'bi-cash-stack'],['Low stock',count($low),'bi-exclamation-triangle'],['Out of stock',count($out),'bi-x-circle'],['Suppliers',$suppliers,'bi-truck'],['Open purchase orders',$openPO,'bi-cart-plus'],['Open sales orders',$openSO,'bi-receipt']] as [$l,$v,$i]): ?>
<div class="col-md-3 col-xl-3"><div class="card stat-card p-3"><i class="bi <?= $i ?> fs-4 text-primary"></i><div class="fs-4 fw-bold"><?= e((string)$v) ?></div><div class="text-muted small"><?= e($l) ?></div></div></div><?php endforeach; ?>
</div>
<div class="row g-3 mt-1"><div class="col-lg-8"><div class="card p-3"><h6>Monthly sales revenue</h6><canvas id="revChart" height="120"></canvas></div>
<div class="card p-3 mt-3"><h6>Recent stock movements</h6><table class="table table-sm mb-0"><tr><th>Product</th><th>Warehouse</th><th>Type</th><th>Qty</th><th>Ref</th><th>When</th></tr>
<?php foreach ($moves as $m): ?><tr><td><?= e($m['pname']) ?></td><td><?= e($m['wname']) ?></td><td><span class="badge bg-secondary"><?= e($m['type']) ?></span></td><td><?= $m['quantity'] ?></td><td><?= e($m['reference'] ?? '') ?></td><td class="small"><?= e($m['created_at']) ?></td></tr><?php endforeach; if (!$moves) echo '<tr><td colspan="6" class="text-muted">No movements yet.</td></tr>'; ?></table></div></div>
<div class="col-lg-4"><div class="card p-3"><h6>Low / out of stock</h6><ul class="list-unstyled small mb-0">
<?php foreach (array_merge($out, $low) as $p): ?><li class="border-bottom py-2"><strong><?= e($p['name']) ?></strong> (<?= e($p['sku']) ?>) — <?= $p['stock_qty'] ?> left, min <?= $p['min_stock'] ?></li><?php endforeach; if (!$alertsCount) echo '<li class="py-2 text-muted">All stock levels healthy.</li>'; ?></ul></div></div></div>
<?php $extraJs = '<script>makeChart("revChart","bar",' . json_encode(array_column($revRows,'m')) . ',' . json_encode(array_map('floatval', array_column($revRows,'v'))) . ',"Revenue");</script>'; include __DIR__ . '/includes/footer.php'; ?>
