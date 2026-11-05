<?php require_once __DIR__ . '/includes/auth.php'; require_once __DIR__ . '/includes/roles.php';
$user = require_role(ROLE_PURCHASE); $pdo = db(); check_tenant_active($pdo); $tid = require_tenant(); $base = ''; $active = 'suppliers'; $pageTitle = 'Suppliers';
if (is_post()) { verify_csrf(); $act = $_POST['action'] ?? '';
  try {
    if ($act === 'delete') $pdo->prepare('DELETE FROM suppliers WHERE id=? AND tenant_id=?')->execute([(int)$_POST['id'], $tid]);
    elseif ($act === 'payment') { $pdo->prepare('INSERT INTO supplier_payments(tenant_id,supplier_id,purchase_id,amount,method,note,created_at) VALUES(?,?,?,?,?,?,NOW())')->execute([$tid,(int)$_POST['supplier_id'],!empty($_POST['purchase_id'])?(int)$_POST['purchase_id']:null,(float)$_POST['amount'],$_POST['method'] ?? 'cash',trim($_POST['note'] ?? '')]); flash('success','Supplier payment recorded.'); redirect('suppliers.php'); }
    else {
        $d = [trim($_POST['name']), trim($_POST['contact'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['category'] ?? ''), trim($_POST['address'] ?? '')];
        if (!empty($_POST['id'])) $pdo->prepare('UPDATE suppliers SET name=?,contact=?,email=?,phone=?,category=?,address=? WHERE id=? AND tenant_id=?')->execute([...$d, (int)$_POST['id'], $tid]);
        else $pdo->prepare('INSERT INTO suppliers(tenant_id,name,contact,email,phone,category,address,created_at) VALUES(?,?,?,?,?,?,?,NOW())')->execute([$tid, ...$d]);
        flash('success','Supplier saved.');
    }
  } catch (Throwable $ex) { flash('error', 'Action failed: ' . $ex->getMessage()); }
  redirect('suppliers.php');
}
$rows = qa($pdo, 'SELECT s.*, (SELECT COALESCE(SUM(total),0) FROM purchases p WHERE p.supplier_id=s.id AND p.status!="cancelled") bought, (SELECT COALESCE(SUM(amount),0) FROM supplier_payments sp WHERE sp.supplier_id=s.id) paid, (SELECT COUNT(*) FROM purchases p WHERE p.supplier_id=s.id) po_count FROM suppliers s WHERE s.tenant_id=? ORDER BY s.name', [$tid]);
$edit = null; if (!empty($_GET['edit'])) $edit = qa($pdo, 'SELECT * FROM suppliers WHERE id=? AND tenant_id=?', [(int)$_GET['edit'], $tid])[0] ?? null;
$history = !empty($_GET['history']) ? qa($pdo, 'SELECT * FROM purchases WHERE tenant_id=? AND supplier_id=? ORDER BY id DESC', [$tid, (int)$_GET['history']]) : [];
include __DIR__ . '/includes/header.php'; ?>
<div class="row g-3"><div class="col-lg-8"><div class="card p-3"><table class="table table-sm mb-0"><tr><th>Supplier</th><th>Contact</th><th>Category</th><th>POs</th><th>Purchased</th><th>Paid</th><th>Balance owed</th><th></th></tr>
<?php foreach ($rows as $r): $bal = (float)$r['bought'] - (float)$r['paid']; ?><tr><td><strong><?= e($r['name']) ?></strong></td><td class="small"><?= e($r['contact'] ?? '') ?><br><?= e($r['phone'] ?? '') ?> <?= e($r['email'] ?? '') ?></td><td><?= e($r['category'] ?? '') ?></td><td><?= $r['po_count'] ?></td><td><?= money($r['bought']) ?></td><td><?= money($r['paid']) ?></td><td class="<?= $bal > 0 ? 'text-danger fw-bold' : '' ?>"><?= money($bal) ?></td>
<td><a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>">Edit</a> <a class="btn btn-sm btn-outline-secondary" href="?history=<?= $r['id'] ?>">History</a> <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Delete supplier?">Del</button></form></td></tr><?php endforeach; if (!$rows) echo '<tr><td colspan="8" class="text-muted">No suppliers yet.</td></tr>'; ?></table></div>
<?php if ($history): ?><div class="card p-3 mt-3"><h6>Purchase history</h6><table class="table table-sm mb-0"><tr><th>PO</th><th>Total</th><th>Status</th><th>Date</th></tr><?php foreach ($history as $h): ?><tr><td>#<?= $h['id'] ?></td><td><?= money($h['total']) ?></td><td><?= e($h['status']) ?></td><td><?= e($h['created_at']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<div class="card p-3 mt-3"><h6>Record supplier payment</h6><form class="row g-2" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="payment">
<div class="col-md-4"><select class="form-select" name="supplier_id" required><?php foreach ($rows as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><input class="form-control" type="number" step="0.01" name="amount" placeholder="Amount" required></div>
<div class="col-md-3"><select class="form-select" name="method"><option>cash</option><option>card</option><option>bank</option></select></div>
<div class="col-md-2"><button class="btn btn-primary w-100">Pay</button></div></form></div></div>
<div class="col-lg-4"><div class="card p-3"><h6><?= $edit ? 'Edit' : 'Add' ?> supplier</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($edit['id'] ?? '')) ?>">
<?php foreach ([['name','Supplier name'],['contact','Contact person'],['email','Email'],['phone','Phone'],['category','Category'],['address','Address']] as [$f,$l]): ?><div class="mb-2"><label class="form-label small"><?= $l ?></label><input class="form-control" name="<?= $f ?>" value="<?= e($edit[$f] ?? '') ?>" <?= $f === 'name' ? 'required' : '' ?>></div><?php endforeach; ?>
<button class="btn btn-primary w-100">Save supplier</button></form></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
