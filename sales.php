<?php require_once __DIR__ . '/includes/auth.php'; require_once __DIR__ . '/includes/roles.php';
$user = require_role(ROLE_SALES); $pdo = db(); check_tenant_active($pdo); $tid = require_tenant(); $base = ''; $active = 'sales'; $pageTitle = 'Sales Orders & Invoices';
// Invoice view
if (!empty($_GET['invoice'])) {
    $so = qa($pdo, 'SELECT s.*, c.name customer, w.name wname FROM sales s LEFT JOIN customers c ON c.id=s.customer_id JOIN warehouses w ON w.id=s.warehouse_id WHERE s.id=? AND s.tenant_id=?', [(int)$_GET['invoice'], $tid])[0] ?? null;
    if (!$so) exit('Invoice not found.');
    $items = qa($pdo, 'SELECT si.*, p.name pname, p.sku FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id=?', [$so['id']]);
    $paid = (float)(q1($pdo, 'SELECT COALESCE(SUM(amount),0) FROM sale_payments WHERE sale_id=?', [$so['id']]) ?: 0);
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Invoice INV-<?= $so['id'] ?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
    <body class="p-4"><div class="container" style="max-width:760px"><h3>StockPro — Invoice INV-<?= str_pad((string)$so['id'], 5, '0', STR_PAD_LEFT) ?></h3>
    <p class="text-muted">Customer: <?= e($so['customer'] ?? 'Walk-in') ?> · Warehouse: <?= e($so['wname']) ?> · Status: <?= e($so['status']) ?> · Payment: <?= e($so['payment_status']) ?> · <?= e($so['created_at']) ?></p>
    <table class="table"><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Price</th><th>Line</th></tr><?php foreach ($items as $it): ?><tr><td><?= e($it['pname']) ?></td><td><?= e($it['sku']) ?></td><td><?= $it['quantity'] ?></td><td><?= money($it['unit_price']) ?></td><td><?= money((float)$it['quantity'] * (float)$it['unit_price']) ?></td></tr><?php endforeach; ?></table>
    <p class="text-end">Subtotal <?= money($so['subtotal']) ?> · Tax <?= money($so['tax']) ?> · Discount −<?= money($so['discount']) ?><br><strong>Total <?= money($so['total']) ?></strong> · Paid <?= money($paid) ?> · Due <?= money((float)$so['total'] - $paid) ?></p>
    <button class="btn btn-primary" onclick="window.print()">Print invoice</button> <a class="btn btn-outline-secondary" href="sales.php">Back</a></div></body></html><?php exit;
}
if (is_post()) { verify_csrf(); $act = $_POST['action'] ?? '';
  try {
    if ($act === 'create') {
        $cust = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null; $wid = (int)$_POST['warehouse_id'];
        $taxRate = (float)($_POST['tax_rate'] ?? 0); $discount = (float)($_POST['discount'] ?? 0);
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO sales(tenant_id,customer_id,warehouse_id,status,payment_status,subtotal,tax,discount,total,created_at) VALUES(?,?,?,?, "draft","unpaid",0,0,0,0,NOW())')->execute([$tid, $cust, $wid]);
        $so = (int)$pdo->lastInsertId(); $sub = 0;
        foreach (($_POST['product_id'] ?? []) as $i => $pidRaw) { $pid = (int)$pidRaw; $qty = (float)($_POST['qty'][$i] ?? 0); $price = (float)($_POST['price'][$i] ?? 0); if (!$pid || $qty <= 0) continue;
            $pdo->prepare('INSERT INTO sale_items(tenant_id,sale_id,product_id,quantity,unit_price) VALUES(?,?,?,?,?)')->execute([$tid, $so, $pid, $qty, $price]); $sub += $qty * $price; }
        if ($sub <= 0) throw new RuntimeException('Add at least one item.');
        $tax = $sub * $taxRate / 100; $total = $sub + $tax - $discount;
        $pdo->prepare('UPDATE sales SET subtotal=?,tax=?,discount=?,total=? WHERE id=?')->execute([$sub, $tax, $discount, $total, $so]);
        $pdo->commit(); audit_log($pdo, 'sale_create', 'sales', $so); flash('success', "Sales order #$so created as draft. Confirm it to take stock out.");
    } elseif ($act === 'confirm') {
        $so = (int)$_POST['id'];
        $head = qa($pdo, 'SELECT * FROM sales WHERE id=? AND tenant_id=?', [$so, $tid])[0] ?? null;
        if (!$head || $head['status'] !== 'draft') throw new RuntimeException('Only draft orders can be confirmed.');
        $items = qa($pdo, 'SELECT * FROM sale_items WHERE sale_id=? AND tenant_id=?', [$so, $tid]);
        $pdo->beginTransaction();
        foreach ($items as $it) apply_movement($pdo, $tid, (int)$head['warehouse_id'], (int)$it['product_id'], 'out', -(float)$it['quantity'], 'SO #' . $so, 'Sale confirmed (stock-out)');
        $pdo->prepare('UPDATE sales SET status="confirmed" WHERE id=?')->execute([$so]);
        $pdo->commit(); audit_log($pdo, 'sale_confirm', 'sales', $so); log_activity($pdo, "Sale #$so confirmed — stock out"); flash('success', "Sale #$so confirmed — stock taken out in one transaction.");
    } elseif ($act === 'cancel') {
        $so = (int)$_POST['id']; $head = qa($pdo, 'SELECT * FROM sales WHERE id=? AND tenant_id=?', [$so, $tid])[0] ?? null;
        $pdo->beginTransaction();
        if ($head && $head['status'] === 'confirmed') { foreach (qa($pdo, 'SELECT * FROM sale_items WHERE sale_id=? AND tenant_id=?', [$so, $tid]) as $it) apply_movement($pdo, $tid, (int)$head['warehouse_id'], (int)$it['product_id'], 'in', (float)$it['quantity'], 'SO-Cancel #' . $so, 'Sale cancelled — stock restored'); }
        $pdo->prepare('UPDATE sales SET status="cancelled" WHERE id=? AND tenant_id=?')->execute([$so, $tid]); $pdo->commit(); flash('success','Sales order cancelled (stock restored if it was confirmed).');
    } elseif ($act === 'payment') {
        $so = (int)$_POST['sale_id']; $amt = (float)$_POST['amount'];
        $pdo->prepare('INSERT INTO sale_payments(tenant_id,sale_id,customer_id,amount,method,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$tid, $so, !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null, $amt, $_POST['method'] ?? 'cash']);
        $total = (float)(q1($pdo, 'SELECT total FROM sales WHERE id=? AND tenant_id=?', [$so, $tid]) ?: 0);
        $paid = (float)(q1($pdo, 'SELECT COALESCE(SUM(amount),0) FROM sale_payments WHERE sale_id=?', [$so]) ?: 0);
        $pdo->prepare('UPDATE sales SET payment_status=? WHERE id=?')->execute([$paid >= $total ? 'paid' : 'partial', $so]);
        flash('success','Payment recorded.');
    } elseif ($act === 'return') {
        $so = (int)$_POST['sale_id']; $pid = (int)$_POST['product_id']; $qty = abs((float)$_POST['quantity']);
        $head = qa($pdo, 'SELECT * FROM sales WHERE id=? AND tenant_id=?', [$so, $tid])[0] ?? null; if (!$head) throw new RuntimeException('Sale not found.');
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO sales_returns(tenant_id,sale_id,product_id,quantity,reason,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$tid, $so, $pid, $qty, trim($_POST['reason'] ?? 'Customer return')]);
        if ($head['status'] === 'confirmed') apply_movement($pdo, $tid, (int)$head['warehouse_id'], $pid, 'in', $qty, 'SO-Return #' . $so, trim($_POST['reason'] ?? 'Sales return'));
        $pdo->commit(); flash('success','Sales return recorded (stock restored if the sale was confirmed).');
    }
  } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); flash('error', 'Action failed: ' . $ex->getMessage()); }
  redirect('sales.php');
}
$custs = qa($pdo, 'SELECT * FROM customers WHERE tenant_id=? ORDER BY name', [$tid]);
$whs = qa($pdo, 'SELECT * FROM warehouses WHERE tenant_id=? ORDER BY is_main DESC, name', [$tid]);
$prods = qa($pdo, 'SELECT * FROM products WHERE tenant_id=? AND status="active" ORDER BY name', [$tid]);
$sales = qa($pdo, 'SELECT s.*, c.name customer, w.name wname, (SELECT COALESCE(SUM(amount),0) FROM sale_payments sp WHERE sp.sale_id=s.id) paid FROM sales s LEFT JOIN customers c ON c.id=s.customer_id JOIN warehouses w ON w.id=s.warehouse_id WHERE s.tenant_id=? ORDER BY s.id DESC LIMIT 25', [$tid]);
foreach ($sales as &$so) $so['items'] = qa($pdo, 'SELECT si.*, p.name pname FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id=?', [$so['id']]);
unset($so);
$returns = qa($pdo, 'SELECT sr.*, p.name pname FROM sales_returns sr JOIN products p ON p.id=sr.product_id WHERE sr.tenant_id=? ORDER BY sr.id DESC LIMIT 10', [$tid]);
include __DIR__ . '/includes/header.php'; ?>
<div class="card p-3 mb-3"><h6>Create sales order</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
<div class="row g-2 mb-2"><div class="col-md-3"><label class="form-label small">Customer</label><select class="form-select" name="customer_id"><option value="">Walk-in</option><?php foreach ($custs as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label small">Ship from warehouse</label><select class="form-select" name="warehouse_id" required><?php foreach ($whs as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><label class="form-label small">Tax %</label><input class="form-control" type="number" step="0.01" name="tax_rate" value="0"></div>
<div class="col-md-2"><label class="form-label small">Discount $</label><input class="form-control" type="number" step="0.01" name="discount" value="0"></div>
<div class="col-md-2 d-flex align-items-end"><button type="button" class="btn btn-outline-secondary" onclick="addItemRow('soItems')">+ Item row</button></div></div>
<table class="table table-sm" id="soItems"><tr><th>Product</th><th>Qty</th><th>Unit price</th></tr>
<tr class="item-row"><td><select class="form-select form-select-sm" name="product_id[]"><?php foreach ($prods as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>) — stock <?= $p['stock_qty'] ?></option><?php endforeach; ?></select></td>
<td><input class="form-control form-control-sm" type="number" step="0.01" name="qty[]" required></td><td><input class="form-control form-control-sm" type="number" step="0.01" name="price[]" required></td></tr></table>
<button class="btn btn-primary">Create draft order</button> <span class="small text-muted">Confirming an order takes stock out transactionally and blocks negative stock when enabled.</span></form></div>
<?php foreach ($sales as $so): ?><div class="card p-3 mb-2"><div class="d-flex justify-content-between"><strong>SO #<?= $so['id'] ?> — <?= e($so['customer'] ?? 'Walk-in') ?></strong><span><span class="badge bg-<?= $so['status'] === 'confirmed' ? 'success' : ($so['status'] === 'cancelled' ? 'danger' : 'warning') ?>"><?= e($so['status']) ?></span> <span class="badge bg-secondary"><?= e($so['payment_status']) ?></span> <?= money($so['total']) ?> (paid <?= money($so['paid']) ?>)</span></div>
<div class="small text-muted"><?= e($so['wname']) ?> · <?= e($so['created_at']) ?> · Items: <?php foreach ($so['items'] as $it) echo e($it['pname']) . ' × ' . $it['quantity'] . ' · '; ?></div>
<div class="d-flex flex-wrap gap-2 mt-2">
<a class="btn btn-sm btn-outline-dark" href="?invoice=<?= $so['id'] ?>">Invoice</a>
<?php if ($so['status'] === 'draft'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="confirm"><input type="hidden" name="id" value="<?= $so['id'] ?>"><button class="btn btn-sm btn-success">Confirm (−stock)</button></form><?php endif; ?>
<?php if ($so['status'] !== 'cancelled'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $so['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Cancel this order?">Cancel</button></form>
<form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="payment"><input type="hidden" name="sale_id" value="<?= $so['id'] ?>"><input type="hidden" name="customer_id" value="<?= e((string)($so['customer_id'] ?? '')) ?>"><input class="form-control form-control-sm" style="max-width:110px" type="number" step="0.01" name="amount" placeholder="Amount" required><select class="form-select form-select-sm" style="max-width:100px" name="method"><option>cash</option><option>card</option><option>bank</option></select><button class="btn btn-sm btn-primary">Record payment</button></form><?php endif; ?>
</div></div><?php endforeach; if (!$sales) echo '<div class="card p-3 text-muted">No sales orders yet.</div>'; ?>
<div class="card p-3"><h6>Sales returns</h6><form class="row g-2 mb-2" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="return">
<div class="col-md-2"><input class="form-control" type="number" name="sale_id" placeholder="SO #" required></div>
<div class="col-md-4"><select class="form-select" name="product_id"><?php foreach ($prods as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><input class="form-control" type="number" step="0.01" name="quantity" placeholder="Qty" required></div>
<div class="col-md-3"><input class="form-control" name="reason" placeholder="Reason"></div><div class="col-md-1"><button class="btn btn-outline-danger w-100">Return</button></div></form>
<?php foreach ($returns as $r): ?><div class="small border-bottom py-1">SO #<?= $r['sale_id'] ?> · <?= e($r['pname']) ?> · <?= $r['quantity'] ?> · <?= e($r['reason'] ?? '') ?> · <?= e($r['created_at']) ?></div><?php endforeach; ?></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
