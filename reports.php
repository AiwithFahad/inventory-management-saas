<?php require_once __DIR__ . '/includes/auth.php'; require_once __DIR__ . '/includes/roles.php';
$user = require_role(ROLE_ALL); $pdo = db(); check_tenant_active($pdo); $tid = require_tenant(); $base = ''; $active = 'reports'; $pageTitle = 'Reports';
$type = $_GET['type'] ?? 'stock'; $from = $_GET['from'] ?? date('Y-m-01'); $to = $_GET['to'] ?? date('Y-m-d');
$queries = [
 'stock' => ['Stock report', 'SELECT p.sku,p.name,p.unit,p.stock_qty,p.min_stock,p.cost_price,(p.stock_qty*p.cost_price) value FROM products p WHERE p.tenant_id=? ORDER BY p.name', [$tid]],
 'movements' => ['Stock movement report', 'SELECT sm.created_at,p.name product,w.name warehouse,sm.type,sm.quantity,sm.reference,sm.note FROM stock_movements sm JOIN products p ON p.id=sm.product_id JOIN warehouses w ON w.id=sm.warehouse_id WHERE sm.tenant_id=? AND DATE(sm.created_at) BETWEEN ? AND ? ORDER BY sm.id DESC', [$tid, $from, $to]],
 'purchases' => ['Purchase report', 'SELECT pu.id,s.name supplier,pu.status,pu.total,pu.created_at FROM purchases pu JOIN suppliers s ON s.id=pu.supplier_id WHERE pu.tenant_id=? AND DATE(pu.created_at) BETWEEN ? AND ? ORDER BY pu.id DESC', [$tid, $from, $to]],
 'sales' => ['Sales report', 'SELECT sl.id,c.name customer,sl.status,sl.payment_status,sl.total,sl.created_at FROM sales sl LEFT JOIN customers c ON c.id=sl.customer_id WHERE sl.tenant_id=? AND DATE(sl.created_at) BETWEEN ? AND ? ORDER BY sl.id DESC', [$tid, $from, $to]],
 'expiry' => ['Expiry report', 'SELECT p.name product,b.batch_no,b.quantity,b.expiry_date FROM batches b JOIN products p ON p.id=b.product_id WHERE b.tenant_id=? AND b.quantity>0 ORDER BY b.expiry_date', [$tid]],
 'profit' => ['Profit report (per product sold)', 'SELECT p.sku,p.name,SUM(ABS(sm.quantity)) units_sold,SUM(ABS(sm.quantity)*p.sale_price) revenue,SUM(ABS(sm.quantity)*p.cost_price) cost,SUM(ABS(sm.quantity)*(p.sale_price-p.cost_price)) gross_profit FROM stock_movements sm JOIN products p ON p.id=sm.product_id WHERE sm.tenant_id=? AND sm.type="out" AND sm.reference LIKE "SO #%" GROUP BY p.id ORDER BY gross_profit DESC', [$tid]],
];
[$title, $sql, $args] = $queries[$type] ?? $queries['stock'];
$rows = qa($pdo, $sql, $args);
if (($_GET['export'] ?? '') === '1') export_csv($rows ?: [['empty' => 'no data']], $type . '-report.csv');
include __DIR__ . '/includes/header.php'; ?>
<form class="row g-2 mb-3" method="get"><div class="col-md-3"><select class="form-select" name="type"><?php foreach ($queries as $k => $v): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($v[0]) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div><div class="col-md-2"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
<div class="col-md-2"><button class="btn btn-primary w-100">Run report</button></div>
<div class="col-md-2"><a class="btn btn-outline-success w-100" href="?type=<?= e($type) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&export=1">CSV export</a></div></form>
<div class="card p-3"><h6><?= e($title) ?> — <?= count($rows) ?> rows</h6>
<?php if ($rows): ?><div class="table-responsive"><table class="table table-sm mb-0"><tr><?php foreach (array_keys($rows[0]) as $h): ?><th><?= e(str_replace('_',' ', $h)) ?></th><?php endforeach; ?></tr>
<?php foreach ($rows as $r): ?><tr><?php foreach ($r as $v): ?><td><?= e((string)($v ?? '')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></table></div>
<?php else: ?><p class="text-muted mb-0">No data for this report / period.</p><?php endif; ?></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
