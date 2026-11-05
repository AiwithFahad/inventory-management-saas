<?php require_once __DIR__ . '/includes/auth.php'; require_once __DIR__ . '/includes/roles.php';
$user = require_role(ROLE_ALL); $pdo = db(); check_tenant_active($pdo); $tid = require_tenant(); $base = ''; $active = 'alerts'; $pageTitle = 'Alerts & Reorder Suggestions';
$low = qa($pdo, 'SELECT * FROM products WHERE tenant_id=? AND status="active" AND stock_qty>0 AND stock_qty<=min_stock ORDER BY stock_qty', [$tid]);
$out = qa($pdo, 'SELECT * FROM products WHERE tenant_id=? AND status="active" AND stock_qty<=0 ORDER BY name', [$tid]);
$expiring = qa($pdo, 'SELECT b.*, p.name pname, w.name wname FROM batches b JOIN products p ON p.id=b.product_id JOIN warehouses w ON w.id=b.warehouse_id WHERE b.tenant_id=? AND b.quantity>0 AND b.expiry_date IS NOT NULL AND b.expiry_date<=DATE_ADD(CURDATE(),INTERVAL 30 DAY) ORDER BY b.expiry_date', [$tid]);
// Reorder suggestion: avg daily out-usage (last 30 days) * 14 days cover, at least up to min_stock*2.
$suggest = qa($pdo, 'SELECT p.*, COALESCE((SELECT SUM(ABS(sm.quantity))/30 FROM stock_movements sm WHERE sm.product_id=p.id AND sm.tenant_id=p.tenant_id AND sm.quantity<0 AND sm.type IN ("out","sale") AND sm.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)),0) avg_daily FROM products p WHERE p.tenant_id=? AND p.status="active" AND p.stock_qty<=p.min_stock ORDER BY p.stock_qty', [$tid]);
foreach ($suggest as &$s) { $cover = ceil((float)$s['avg_daily'] * 14); $s['suggested'] = max((float)$s['min_stock'] * 2 - (float)$s['stock_qty'], $cover, (float)$s['min_stock']); }
unset($s);
$notifs = qa($pdo, 'SELECT * FROM notifications WHERE tenant_id=? ORDER BY id DESC LIMIT 10', [$tid]);
include __DIR__ . '/includes/header.php'; ?>
<div class="row g-3">
<div class="col-lg-6"><div class="card p-3 border-danger"><h6>Out of stock (<?= count($out) ?>)</h6><?php foreach ($out as $p): ?><div class="border-bottom py-2"><strong><?= e($p['name']) ?></strong> <span class="text-muted small"><?= e($p['sku']) ?></span> — reorder now</div><?php endforeach; if (!$out) echo '<p class="text-muted small mb-0">None.</p>'; ?></div>
<div class="card p-3 mt-3 border-warning"><h6>Low stock (<?= count($low) ?>)</h6><?php foreach ($low as $p): ?><div class="border-bottom py-2"><strong><?= e($p['name']) ?></strong> — <?= $p['stock_qty'] ?> left (min <?= $p['min_stock'] ?>)</div><?php endforeach; if (!$low) echo '<p class="text-muted small mb-0">None.</p>'; ?></div>
<div class="card p-3 mt-3"><h6>Notifications</h6><?php foreach ($notifs as $n): ?><div class="border-bottom py-2 small"><strong><?= e($n['title']) ?></strong> <?= e($n['body'] ?? '') ?> <span class="text-muted">· <?= e($n['created_at']) ?></span></div><?php endforeach; if (!$notifs) echo '<p class="text-muted small mb-0">No notifications.</p>'; ?></div></div>
<div class="col-lg-6"><div class="card p-3"><h6>Expiring within 30 days (<?= count($expiring) ?>)</h6><table class="table table-sm mb-0"><tr><th>Product</th><th>Batch</th><th>Warehouse</th><th>Qty</th><th>Expiry</th></tr>
<?php foreach ($expiring as $b): ?><tr class="table-warning"><td><?= e($b['pname']) ?></td><td><?= e($b['batch_no']) ?></td><td><?= e($b['wname']) ?></td><td><?= $b['quantity'] ?></td><td><?= e($b['expiry_date']) ?></td></tr><?php endforeach; if (!$expiring) echo '<tr><td colspan="5" class="text-muted">No batches expiring soon.</td></tr>'; ?></table></div>
<div class="card p-3 mt-3"><h6>Reorder suggestions</h6><p class="small text-muted">Rule: bring stock back to at least 2 × minimum level, or 14 days of average usage (last 30 days of stock-out movements), whichever is larger.</p>
<table class="table table-sm mb-0"><tr><th>Product</th><th>Current</th><th>Min</th><th>Avg daily use</th><th>Suggested order</th></tr>
<?php foreach ($suggest as $s): ?><tr><td><?= e($s['name']) ?></td><td><?= $s['stock_qty'] ?></td><td><?= $s['min_stock'] ?></td><td><?= number_format((float)$s['avg_daily'], 2) ?></td><td class="fw-bold"><?= $s['suggested'] ?> <?= e($s['unit']) ?></td></tr><?php endforeach; if (!$suggest) echo '<tr><td colspan="5" class="text-muted">Nothing to reorder.</td></tr>'; ?></table>
<a class="btn btn-sm btn-primary mt-2" href="purchases.php">Create a purchase order</a></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
