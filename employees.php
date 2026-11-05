<?php require_once __DIR__ . '/includes/auth.php'; require_once __DIR__ . '/includes/roles.php';
$user = require_role(ROLE_OWNER); $pdo = db(); check_tenant_active($pdo); $tid = require_tenant(); $base = ''; $active = 'employees'; $pageTitle = 'Employees & Roles';
$roleNames = ['warehouse_manager','stock_manager','sales_manager','purchase_manager','accountant','employee'];
if (is_post()) { verify_csrf(); $act = $_POST['action'] ?? '';
  try {
    if ($act === 'toggle') $pdo->prepare('UPDATE users SET status=IF(status="active","suspended","active") WHERE id=? AND tenant_id=?')->execute([(int)$_POST['id'], $tid]);
    elseif ($act === 'role') { if (!in_array($_POST['role'], $roleNames, true)) throw new RuntimeException('Invalid role.'); $pdo->prepare('UPDATE users SET role=? WHERE id=? AND tenant_id=? AND role!="owner"')->execute([$_POST['role'], (int)$_POST['id'], $tid]); }
    else {
        $lim = subscription_limits($pdo); $cnt = (int)q1($pdo, 'SELECT COUNT(*) FROM users WHERE tenant_id=?', [$tid]);
        if ($cnt >= (int)($lim['max_users'] ?? 3)) throw new RuntimeException('Plan employee limit reached (' . ($lim['max_users'] ?? 3) . '). Upgrade your plan.');
        if (strlen($_POST['password'] ?? '') < 8) throw new RuntimeException('Password must be at least 8 characters.');
        $pdo->prepare('INSERT INTO users(tenant_id,name,email,password_hash,role,status,email_verified_at,created_at) VALUES(?,?,?,?,?,"active",NOW(),NOW())')
            ->execute([$tid, trim($_POST['name']), trim($_POST['email']), password_hash($_POST['password'], PASSWORD_DEFAULT), in_array($_POST['role'], $roleNames, true) ? $_POST['role'] : 'employee']);
    }
    flash('success','Employee saved.');
  } catch (Throwable $ex) { flash('error', 'Action failed: ' . $ex->getMessage()); }
  redirect('employees.php');
}
$rows = qa($pdo, 'SELECT * FROM users WHERE tenant_id=? ORDER BY role, name', [$tid]);
include __DIR__ . '/includes/header.php'; ?>
<div class="row g-3"><div class="col-lg-8"><div class="card p-3"><table class="table table-sm mb-0"><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td><td>
<?php if ($r['role'] === 'owner'): ?><span class="badge bg-primary">owner</span><?php else: ?><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= $r['id'] ?>"><select class="form-select form-select-sm" name="role"><?php foreach ($roleNames as $rn): ?><option value="<?= $rn ?>" <?= $r['role'] === $rn ? 'selected' : '' ?>><?= str_replace('_',' ', $rn) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-primary">Save</button></form><?php endif; ?></td>
<td><span class="badge bg-<?= $r['status'] === 'active' ? 'success' : 'danger' ?>"><?= e($r['status']) ?></span></td>
<td><?php if ((int)$r['id'] !== (int)$user['id']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-secondary"><?= $r['status'] === 'active' ? 'Suspend' : 'Activate' ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></table></div></div>
<div class="col-lg-4"><div class="card p-3"><h6>Add employee</h6><form method="post"><?= csrf_field() ?>
<div class="mb-2"><input class="form-control" name="name" placeholder="Full name" required></div>
<div class="mb-2"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
<div class="mb-2"><input class="form-control" type="password" name="password" placeholder="Password (min 8)" required></div>
<div class="mb-2"><select class="form-select" name="role"><?php foreach ($roleNames as $rn): ?><option value="<?= $rn ?>"><?= str_replace('_',' ', $rn) ?></option><?php endforeach; ?></select></div>
<button class="btn btn-primary w-100">Add employee (plan limit enforced)</button></form>
<p class="small text-muted mt-2 mb-0">Roles gate every module with require_role(): stock pages (warehouse/stock managers), sales (sales manager/accountant), purchases (purchase manager), finance (owner/accountant), settings &amp; employees (owner).</p></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
