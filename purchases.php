<?php require_once __DIR__ . '/includes/auth.php'; require_once __DIR__ . '/includes/roles.php';
$user = require_role(ROLE_PURCHASE); $pdo = db(); check_tenant_active($pdo); $tid = require_tenant(); $base = ''; $active = 'purchases'; $pageTitle = 'Purchase Orders';
if (is_post()) { verify_csrf(); $act = $_POST['action'] ?? '';
  try {
    if ($act === 'create') {
        $sup = (int)$_POST['supplier_id']; $wid = (int)$_POST['warehouse_id'];
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO purchases(tenant_id,supplier_id,warehouse_id,status,total,created_at) VALUES(?,?,?,"ordered",0,NOW())')->execute([$tid, $sup, $wid]);
        $po = (int)$pdo->lastInsertId(); $total = 0;
        foreach (($_POST['product_id'] ?? []) as $i => $pidRaw) { $pid = (int)$pidRaw; $qty = (float)($_POST['qty'][$i] ?? 0); $cost = (float)($_POST['cost'][$i] ?? 0); if (!$pid || $qty <= 0) continue;
            $pdo->prepare('INSERT INTO purchase_items(tenant_id,purchase_id,product_id,quantity,received_qty,unit_cost) VALUES(?,?,?,?,0,?)')->execute([$tid, $po, $pid, $qty, $cost]); $total += $qty * $cost; }
        if ($total <= 0) throw new RuntimeException('Add at least one item.');
        $pdo->prepare('UPDATE purchases SET total=? WHERE id=?')->execute([$total, $po]);
        $pdo->commit(); audit_log($pdo, 'po_create', 'purchases', $po); flash('success', "Purchase order #$po created.");
    } elseif ($act === 'approve') {
        $pdo->prepare('UPDATE purchases SET status="approved" WHERE id=? AND tenant_id=? AND status IN ("draft","ordered")')->execute([(int)$_POST['id'], $tid]); flash('success','Purchase order approved.');
    } elseif ($act === 'receive') {
        $po = (int)$_POST['id'];
        $head = qa($pdo, 'SELECT * FROM purchases WHERE id=? AND tenant_id=?', [$po, $tid])[0] ?? null; if (!$head) throw new RuntimeException('PO not found.');
        $items = qa($pdo, 'SELECT * FROM purchase_items WHERE purchase_id=? AND tenant_id=?', [$po, $tid]);
        $pdo->beginTransaction(); $any = false; $all = true;
        foreach ($items as $it) { $recv = (float)($_POST['recv_' . $it['id']] ?? 0); $remaining = (float)$it['quantity'] - (float)$it['received_qty'];
            if ($recv > $remaining) $recv = $remaining; if ($recv <= 0) { if ($remaining > 0) $all = false; continue; }
            apply_movement($pdo, $tid, (int)$head['warehouse_id'], (int)$it['product_id'], 'in', $recv, 'PO #' . $po, 'Goods received', trim($_POST['batch_' . $it['id']] ?? '') ?: null, ($_POST['expiry_' . $it['id']] ?? '') ?: null);
            $pdo->prepare('UPDATE purchase_items SET received_qty=received_qty+? WHERE id=?')->execute([$recv, $it['id']]); $any = true;
            if ($recv < $remaining) $all = false; }
        if (!$any) throw new RuntimeException('Nothing received — enter a quantity.');
        $pdo->prepare('UPDATE purchases SET status=?, received_at=NOW() WHERE id=?')->execute([$all ? 'received' : 'partial', $po]);
        $pdo->commit(); audit_log($pdo, 'po_receive', 'purchases', $po); log_activity($pdo, "Goods received for PO #$po"); flash('success', 'Goods received — stock updated in transaction.');
    } elseif ($act === 'return') {
        $po = (int)$_POST['purchase_id']; $pid = (int)$_POST['product_id']; $qty = abs((float)$_POST['quantity']);
        $head = qa($pdo, 'SELECT * FROM purchases WHERE id=? AND tenant_id=?', [$po, $tid])[0] ?? null; if (!$head) throw new RuntimeException('PO not found.');
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO purchase_returns(tenant_id,purchase_id,product_id,quantity,reason,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$tid, $po, $pid, $qty, trim($_POST['reason'] ?? 'Return to supplier')]);
        if ($head['status'] === 'received' || $head['status'] === 'partial') apply_movement($pdo, $tid, (int)$head['warehouse_id'], $pid, 'out', -$qty, 'PO-Return #' . $po, trim($_POST['reason'] ?? 'Purchase return'));
        $pdo->commit(); flash('success','Purchase return recorded (stock removed if it had been received).');
    } elseif ($act === 'payment') {
        $pdo->prepare('INSERT INTO supplier_payments(tenant_id,supplier_id,purchase_id,amount,method,note,created_at) VALUES(?,?,?,?,?,?,NOW())')->execute([$tid,(int)$_POST['supplier_id'],(int)$_POST['purchase_id'] ?: null,(float)$_POST['amount'],$_POST['method'] ?? 'cash',trim($_POST['note'] ?? '')]); flash('success','Supplier payment recorded.');
    }
  } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); flash('error', 'Action failed: ' . $ex->getMessage()); }
  redirect('purchases.php');
}
$sups = qa($pdo, 'SELECT * FROM suppliers WHERE tenant_id=? ORDER BY name', [$tid]);
$whs = qa($pdo, 'SELECT * FROM warehouses WHERE tenant_id=? ORDER BY is_main DESC, name', [$tid]);
$prods = qa($pdo, 'SELECT * FROM products WHERE tenant_id=? AND status="active" ORDER BY name', [$tid]);
$pos = qa($pdo, 'SELECT p.*, s.name supplier FROM purchases p JOIN suppliers s ON s.id=p.supplier_id WHERE p.tenant_id=? ORDER BY p.id DESC LIMIT 25', [$tid]);
foreach ($pos as &$po) $po['items'] = qa($pdo, 'SELECT pi.*, pr.name pname FROM purchase_items pi JOIN products pr ON pr.id=pi.product_id WHERE pi.purchase_id=?', [$po['id']]);
unset($po);
$returns = qa($pdo, 'SELECT pr.*, p.name pname FROM purchase_returns pr JOIN products p ON p.id=pr.product_id WHERE pr.tenant_id=? ORDER BY pr.id DESC LIMIT 10', [$tid]);
include __DIR__ . '/includes/header.php'; ?>
<div class="card p-3 mb-3"><h6>Create purchase order</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
<div class="row g-2 mb-2"><div class="col-md-4"><label class="form-label small">Supplier</label><select class="form-select" name="supplier_id" required><?php foreach ($sups as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label small">Deliver to warehouse</label><select class="form-select" name="warehouse_id" required><?php foreach ($whs as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4 d-flex align-items-end"><button type="button" class="btn btn-outline-secondary" onclick="addItemRow('poItems')">+ Add item row</button></div></div>
<table class="table table-sm" id="poItems"><tr><th>Product</th><th>Qty</th><th>Unit cost</th></tr>
<tr class="item-row"><td><select class="form-select form-select-sm" name="product_id[]"><?php foreach ($prods as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option><?php endforeach; ?></select></td>
<td><input class="form-control form-control-sm" type="number" step="0.01" name="qty[]" required></td><td><input class="form-control form-control-sm" type="number" step="0.01" name="cost[]" required></td></tr></table>
<button class="btn btn-primary">Create PO</button></form></div>
<?php foreach ($pos as $po): ?><div class="card p-3 mb-2"><div class="d-flex justify-content-between"><strong>PO #<?= $po['id'] ?> — <?= e($po['supplier']) ?></strong><span><span class="badge bg-<?= $po['status'] === 'received' ? 'success' : ($po['status'] === 'partial' ? 'info' : 'warning') ?>"><?= e($po['status']) ?></span> <?= money($po['total']) ?></span></div>
<table class="table table-sm mt-2"><tr><th>Product</th><th>Ordered</th><th>Received</th><th>Cost</th></tr><?php foreach ($po['items'] as $it): ?><tr><td><?= e($it['pname']) ?></td><td><?= $it['quantity'] ?></td><td><?= $it['received_qty'] ?></td><td><?= money($it['unit_cost']) ?></td></tr><?php endforeach; ?></table>
<div class="d-flex flex-wrap gap-2">
<?php if ($po['status'] === 'ordered'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $po['id'] ?>"><button class="btn btn-sm btn-outline-primary">Approve</button></form><?php endif; ?>
<?php if (in_array($po['status'], ['ordered','approved','partial'], true)): ?><form method="post" class="border rounded p-2"><?= csrf_field() ?><input type="hidden" name="action" value="receive"><input type="hidden" name="id" value="<?= $po['id'] ?>">
<span class="small fw-bold">Receive goods (partial supported):</span>
<?php foreach ($po['items'] as $it): $rem = (float)$it['quantity'] - (float)$it['received_qty']; if ($rem <= 0) continue; ?><div class="d-flex gap-1 mt-1"><span class="small align-self-center"><?= e($it['pname']) ?></span><input class="form-control form-control-sm" style="max-width:90px" type="number" step="0.01" name="recv_<?= $it['id'] ?>" value="<?= $rem ?>"><input class="form-control form-control-sm" style="max-width:110px" name="batch_<?= $it['id'] ?>" placeholder="Batch"><input class="form-control form-control-sm" style="max-width:140px" type="date" name="expiry_<?= $it['id'] ?>"></div><?php endforeach; ?>
<button class="btn btn-sm btn-success mt-1">Receive (+stock)</button></form>
<form method="post" class="border rounded p-2"><?= csrf_field() ?><input type="hidden" name="action" value="payment"><input type="hidden" name="supplier_id" value="<?= $po['supplier_id'] ?>"><input type="hidden" name="purchase_id" value="<?= $po['id'] ?>">
<span class="small fw-bold">Pay supplier:</span><div class="d-flex gap-1 mt-1"><input class="form-control form-control-sm" style="max-width:110px" type="number" step="0.01" name="amount" required><select class="form-select form-select-sm" name="method"><option>cash</option><option>card</option><option>bank</option></select><button class="btn btn-sm btn-primary">Pay</button></div></form><?php endif; ?>
</div></div><?php endforeach; if (!$pos) echo '<div class="card p-3 text-muted">No purchase orders yet.</div>'; ?>
<div class="card p-3"><h6>Purchase returns</h6><form class="row g-2 mb-2" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="return">
<div class="col-md-2"><input class="form-control" type="number" name="purchase_id" placeholder="PO #" required></div>
<div class="col-md-4"><select class="form-select" name="product_id"><?php foreach ($prods as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><input class="form-control" type="number" step="0.01" name="quantity" placeholder="Qty" required></div>
<div class="col-md-3"><input class="form-control" name="reason" placeholder="Reason"></div><div class="col-md-1"><button class="btn btn-outline-danger w-100">Return</button></div></form>
<?php foreach ($returns as $r): ?><div class="small border-bottom py-1">PO #<?= $r['purchase_id'] ?> · <?= e($r['pname']) ?> · <?= $r['quantity'] ?> · <?= e($r['reason'] ?? '') ?> · <?= e($r['created_at']) ?></div><?php endforeach; ?></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
