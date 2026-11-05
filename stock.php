<?php require_once __DIR__ . '/includes/auth.php'; require_once __DIR__ . '/includes/roles.php';
$user = require_role(ROLE_STOCK); $pdo = db(); check_tenant_active($pdo); $tid = require_tenant(); $base = ''; $active = 'stock'; $pageTitle = 'Stock Movements';
if (is_post()) { verify_csrf();
  try { $pdo->beginTransaction();
    $pid = (int)$_POST['product_id']; $wid = (int)$_POST['warehouse_id']; $qty = abs((float)$_POST['quantity']); $act = $_POST['action'] ?? 'in';
    $batch = trim($_POST['batch_no'] ?? '') ?: null; $expiry = $_POST['expiry_date'] ?? null; if ($expiry === '') $expiry = null;
    $note = trim($_POST['note'] ?? '');
    if ($act === 'in') apply_movement($pdo, $tid, $wid, $pid, 'in', $qty, 'manual-in', $note, $batch, $expiry);
    elseif ($act === 'out') apply_movement($pdo, $tid, $wid, $pid, 'out', -$qty, 'manual-out', $note);
    elseif ($act === 'damage') apply_movement($pdo, $tid, $wid, $pid, 'damage', -$qty, 'damage', $note ?: 'Damaged stock');
    elseif ($act === 'expiry') apply_movement($pdo, $tid, $wid, $pid, 'expiry', -$qty, 'expiry-writeoff', $note ?: 'Expired stock written off', $batch, $expiry);
    else { $delta = (float)$_POST['quantity']; apply_movement($pdo, $tid, $wid, $pid, 'adjustment', $delta, 'adjustment', $note ?: 'Stock count adjustment', $batch, $expiry); }
    $pdo->commit(); audit_log($pdo, 'stock_' . $act, 'products', $pid); log_activity($pdo, "Stock $act for product #$pid ({$_POST['quantity']})"); flash('success', 'Stock movement recorded.');
  } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); flash('error', $ex->getMessage()); }
  redirect('stock.php');
}
$prods = qa($pdo, 'SELECT * FROM products WHERE tenant_id=? AND status="active" ORDER BY name', [$tid]);
$whs = qa($pdo, 'SELECT * FROM warehouses WHERE tenant_id=? ORDER BY is_main DESC, name', [$tid]);
$current = qa($pdo, 'SELECT pw.quantity, p.name, p.sku, p.unit, p.min_stock, w.name wname FROM product_warehouse pw JOIN products p ON p.id=pw.product_id JOIN warehouses w ON w.id=pw.warehouse_id WHERE pw.tenant_id=? ORDER BY w.name, p.name', [$tid]);
$pidF = (int)($_GET['product_id'] ?? 0);
$histSql = 'SELECT sm.*, p.name pname, w.name wname FROM stock_movements sm JOIN products p ON p.id=sm.product_id JOIN warehouses w ON w.id=sm.warehouse_id WHERE sm.tenant_id=?';
$hArgs = [$tid]; if ($pidF) { $histSql .= ' AND sm.product_id=?'; $hArgs[] = $pidF; } $histSql .= ' ORDER BY sm.id DESC LIMIT 40';
$history = qa($pdo, $histSql, $hArgs);
$batches = qa($pdo, 'SELECT b.*, p.name pname FROM batches b JOIN products p ON p.id=b.product_id WHERE b.tenant_id=? AND b.quantity>0 ORDER BY b.expiry_date', [$tid]);
include __DIR__ . '/includes/header.php'; ?>
<div class="row g-3"><div class="col-lg-5"><div class="card p-3"><h6>Stock in / out / adjust / damage / expiry</h6>
<form method="post"><?= csrf_field() ?>
<div class="mb-2"><label class="form-label small">Product</label><select class="form-select" name="product_id" required><?php foreach ($prods as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>) — total <?= $p['stock_qty'] ?></option><?php endforeach; ?></select></div>
<div class="mb-2"><label class="form-label small">Warehouse</label><select class="form-select" name="warehouse_id" required><?php foreach ($whs as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
<div class="row g-2"><div class="col-6"><label class="form-label small">Quantity (for adjustment: signed +/−)</label><input class="form-control" type="number" step="0.01" name="quantity" required></div>
<div class="col-6"><label class="form-label small">Batch no (optional)</label><input class="form-control" name="batch_no"></div>
<div class="col-6"><label class="form-label small">Expiry date (optional)</label><input class="form-control" type="date" name="expiry_date"></div>
<div class="col-6"><label class="form-label small">Note</label><input class="form-control" name="note" placeholder="Reason"></div></div>
<div class="d-flex flex-wrap gap-2 mt-3">
<button class="btn btn-success btn-sm" name="action" value="in">Stock in</button>
<button class="btn btn-warning btn-sm" name="action" value="out">Stock out</button>
<button class="btn btn-primary btn-sm" name="action" value="adjustment">Adjustment</button>
<button class="btn btn-outline-danger btn-sm" name="action" value="damage">Record damaged</button>
<button class="btn btn-outline-dark btn-sm" name="action" value="expiry">Record expired</button></div>
<p class="small text-muted mt-2 mb-0">Every action writes a movement row and updates the warehouse cache in one transaction. Negative stock is <?= prevent_negative($pdo,$tid) ? '<strong>blocked</strong>' : 'allowed' ?> by your settings.</p></form></div>
<div class="card p-3 mt-3"><h6>Batches &amp; expiry</h6><table class="table table-sm mb-0"><tr><th>Product</th><th>Batch</th><th>Qty</th><th>Expiry</th></tr>
<?php foreach ($batches as $b): $soon = $b['expiry_date'] && $b['expiry_date'] <= date('Y-m-d', strtotime('+30 days')); ?><tr class="<?= $soon ? 'table-warning' : '' ?>"><td><?= e($b['pname']) ?></td><td><?= e($b['batch_no']) ?></td><td><?= $b['quantity'] ?></td><td><?= e($b['expiry_date'] ?? '') ?><?= $soon ? ' ⚠ ≤30d' : '' ?></td></tr><?php endforeach; if (!$batches) echo '<tr><td colspan="4" class="text-muted">No batches.</td></tr>'; ?></table></div></div>
<div class="col-lg-7"><div class="card p-3"><h6>Current stock by warehouse</h6><table class="table table-sm mb-0"><tr><th>Warehouse</th><th>Product</th><th>SKU</th><th>Qty</th><th>Min</th></tr>
<?php foreach ($current as $c): ?><tr class="<?= $c['quantity'] <= 0 ? 'table-danger' : ($c['quantity'] <= $c['min_stock'] ? 'table-warning' : '') ?>"><td><?= e($c['wname']) ?></td><td><?= e($c['name']) ?></td><td><?= e($c['sku']) ?></td><td><?= $c['quantity'] ?> <?= e($c['unit']) ?></td><td><?= $c['min_stock'] ?></td></tr><?php endforeach; if (!$current) echo '<tr><td colspan="5" class="text-muted">No stock yet.</td></tr>'; ?></table></div>
<div class="card p-3 mt-3"><h6>Movement history</h6><form class="row g-2 mb-2" method="get"><div class="col-md-8"><select class="form-select form-select-sm" name="product_id"><option value="">All products</option><?php foreach ($prods as $p): ?><option value="<?= $p['id'] ?>" <?= $pidF == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><button class="btn btn-sm btn-primary w-100">Filter history</button></div></form>
<table class="table table-sm mb-0"><tr><th>Product</th><th>Warehouse</th><th>Type</th><th>Qty</th><th>Batch</th><th>Ref / Note</th><th>When</th></tr>
<?php foreach ($history as $h): ?><tr><td><?= e($h['pname']) ?></td><td><?= e($h['wname']) ?></td><td><span class="badge bg-secondary"><?= e($h['type']) ?></span></td><td><?= $h['quantity'] ?></td><td><?= e($h['batch_no'] ?? '') ?></td><td class="small"><?= e($h['reference'] ?? '') ?> <?= e($h['note'] ?? '') ?></td><td class="small"><?= e($h['created_at']) ?></td></tr><?php endforeach; ?></table></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
