<?php require_once __DIR__ . '/includes/auth.php'; require_once __DIR__ . '/includes/roles.php';
$user = require_role(ROLE_SALES); $pdo = db(); check_tenant_active($pdo); $tid = require_tenant(); $base = ''; $active = 'customers'; $pageTitle = 'Customers';
if (is_post()) { verify_csrf(); $act = $_POST['action'] ?? '';
  try {
    if ($act === 'delete') $pdo->prepare('DELETE FROM customers WHERE id=? AND tenant_id=?')->execute([(int)$_POST['id'], $tid]);
    else {
        $d = [trim($_POST['name']), trim($_POST['email'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['address'] ?? ''), (float)($_POST['credit_limit'] ?? 0)];
        if (!empty($_POST['id'])) $pdo->prepare('UPDATE customers SET name=?,email=?,phone=?,address=?,credit_limit=? WHERE id=? AND tenant_id=?')->execute([...$d, (int)$_POST['id'], $tid]);
        else $pdo->prepare('INSERT INTO customers(tenant_id,name,email,phone,address,credit_limit,created_at) VALUES(?,?,?,?,?,?,NOW())')->execute([$tid, ...$d]);
    }
    flash('success','Customer saved.');
  } catch (Throwable $ex) { flash('error', 'Action failed: ' . $ex->getMessage()); }
  redirect('customers.php');
}
$rows = qa($pdo, 'SELECT c.*, (SELECT COALESCE(SUM(total),0) FROM sales s WHERE s.customer_id=c.id AND s.status!="cancelled") bought, (SELECT COALESCE(SUM(amount),0) FROM sale_payments sp WHERE sp.customer_id=c.id) paid, (SELECT COUNT(*) FROM sales s WHERE s.customer_id=c.id) orders FROM customers c WHERE c.tenant_id=? ORDER BY c.name', [$tid]);
$edit = null; if (!empty($_GET['edit'])) $edit = qa($pdo, 'SELECT * FROM customers WHERE id=? AND tenant_id=?', [(int)$_GET['edit'], $tid])[0] ?? null;
include __DIR__ . '/includes/header.php'; ?>
<div class="row g-3"><div class="col-lg-8"><div class="card p-3"><table class="table table-sm mb-0"><tr><th>Customer</th><th>Contact</th><th>Orders</th><th>Bought</th><th>Paid</th><th>Balance due</th><th>Credit limit</th><th></th></tr>
<?php foreach ($rows as $r): $bal = (float)$r['bought'] - (float)$r['paid']; ?><tr><td><strong><?= e($r['name']) ?></strong></td><td class="small"><?= e($r['phone'] ?? '') ?><br><?= e($r['email'] ?? '') ?></td><td><?= $r['orders'] ?></td><td><?= money($r['bought']) ?></td><td><?= money($r['paid']) ?></td><td class="<?= $bal > 0 ? 'text-danger fw-bold' : '' ?>"><?= money($bal) ?></td><td><?= money($r['credit_limit']) ?></td>
<td><a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>">Edit</a> <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Delete customer?">Del</button></form></td></tr><?php endforeach; if (!$rows) echo '<tr><td colspan="8" class="text-muted">No customers yet.</td></tr>'; ?></table></div></div>
<div class="col-lg-4"><div class="card p-3"><h6><?= $edit ? 'Edit' : 'Add' ?> customer</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($edit['id'] ?? '')) ?>">
<?php foreach ([['name','Name','text'],['email','Email','text'],['phone','Phone','text'],['address','Address','text'],['credit_limit','Credit limit','number']] as [$f,$l,$t]): ?><div class="mb-2"><label class="form-label small"><?= $l ?></label><input class="form-control" type="<?= $t ?>" <?= $t === 'number' ? 'step="0.01"' : '' ?> name="<?= $f ?>" value="<?= e((string)($edit[$f] ?? '')) ?>" <?= $f === 'name' ? 'required' : '' ?>></div><?php endforeach; ?>
<button class="btn btn-primary w-100">Save customer</button></form></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
