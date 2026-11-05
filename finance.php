<?php require_once __DIR__ . '/includes/auth.php'; require_once __DIR__ . '/includes/roles.php';
$user = require_role(ROLE_MONEY); $pdo = db(); check_tenant_active($pdo); $tid = require_tenant(); $base = ''; $active = 'finance'; $pageTitle = 'Finance & Valuation';
$salesIncome = (float)(q1($pdo, 'SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND status!="cancelled"', [$tid]) ?: 0);
$salesCollected = (float)(q1($pdo, 'SELECT COALESCE(SUM(amount),0) FROM sale_payments WHERE tenant_id=?', [$tid]) ?: 0);
$purchaseExpense = (float)(q1($pdo, 'SELECT COALESCE(SUM(total),0) FROM purchases WHERE tenant_id=? AND status!="cancelled"', [$tid]) ?: 0);
$purchasePaid = (float)(q1($pdo, 'SELECT COALESCE(SUM(amount),0) FROM supplier_payments WHERE tenant_id=?', [$tid]) ?: 0);
$cogs = (float)(q1($pdo, 'SELECT COALESCE(SUM(ABS(sm.quantity)*p.cost_price),0) FROM stock_movements sm JOIN products p ON p.id=sm.product_id WHERE sm.tenant_id=? AND sm.type="out" AND sm.reference LIKE "SO #%"', [$tid]) ?: 0);
$grossProfit = $salesIncome - $cogs;
$valuation = qa($pdo, 'SELECT w.name, COUNT(DISTINCT pw.product_id) skus, COALESCE(SUM(pw.quantity),0) qty, COALESCE(SUM(pw.quantity*p.cost_price),0) value FROM warehouses w LEFT JOIN product_warehouse pw ON pw.warehouse_id=w.id LEFT JOIN products p ON p.id=pw.product_id WHERE w.tenant_id=? GROUP BY w.id ORDER BY w.name', [$tid]);
$supBal = qa($pdo, 'SELECT s.name, (SELECT COALESCE(SUM(total),0) FROM purchases p WHERE p.supplier_id=s.id AND p.status!="cancelled") - (SELECT COALESCE(SUM(amount),0) FROM supplier_payments sp WHERE sp.supplier_id=s.id) balance FROM suppliers s WHERE s.tenant_id=? HAVING balance!=0 ORDER BY balance DESC', [$tid]);
$custBal = qa($pdo, 'SELECT c.name, (SELECT COALESCE(SUM(total),0) FROM sales sl WHERE sl.customer_id=c.id AND sl.status!="cancelled") - (SELECT COALESCE(SUM(amount),0) FROM sale_payments sp WHERE sp.customer_id=c.id) balance FROM customers c WHERE c.tenant_id=? HAVING balance!=0 ORDER BY balance DESC', [$tid]);
include __DIR__ . '/includes/header.php'; ?>
<div class="row g-3">
<?php foreach ([['Sales income (invoiced)',$salesIncome],['Sales collected',$salesCollected],['Receivables (due)',$salesIncome-$salesCollected],['Purchase expense (ordered)',$purchaseExpense],['Purchases paid',$purchasePaid],['Payables (owed)',$purchaseExpense-$purchasePaid],['Cost of goods sold (sales stock-out)', $cogs],['Gross profit (sales − COGS)',$grossProfit]] as [$l,$v]): ?>
<div class="col-md-3"><div class="card stat-card p-3"><div class="fs-4 fw-bold"><?= money($v) ?></div><div class="text-muted small"><?= e($l) ?></div></div></div><?php endforeach; ?>
</div>
<div class="row g-3 mt-1"><div class="col-lg-6"><div class="card p-3"><h6>Inventory valuation by warehouse</h6><table class="table table-sm mb-0"><tr><th>Warehouse</th><th>SKUs</th><th>Qty</th><th>Value at cost</th></tr>
<?php $tot = 0; foreach ($valuation as $v): $tot += (float)$v['value']; ?><tr><td><?= e($v['name']) ?></td><td><?= $v['skus'] ?></td><td><?= $v['qty'] ?></td><td><?= money($v['value']) ?></td></tr><?php endforeach; ?><tr class="fw-bold"><td>Total</td><td></td><td></td><td><?= money($tot) ?></td></tr></table></div></div>
<div class="col-lg-3"><div class="card p-3"><h6>Owed to suppliers</h6><?php foreach ($supBal as $r): ?><div class="d-flex justify-content-between border-bottom py-1"><span><?= e($r['name']) ?></span><strong><?= money($r['balance']) ?></strong></div><?php endforeach; if (!$supBal) echo '<p class="text-muted small mb-0">No outstanding supplier balances.</p>'; ?></div></div>
<div class="col-lg-3"><div class="card p-3"><h6>Customers owe</h6><?php foreach ($custBal as $r): ?><div class="d-flex justify-content-between border-bottom py-1"><span><?= e($r['name']) ?></span><strong><?= money($r['balance']) ?></strong></div><?php endforeach; if (!$custBal) echo '<p class="text-muted small mb-0">No outstanding customer balances.</p>'; ?></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
