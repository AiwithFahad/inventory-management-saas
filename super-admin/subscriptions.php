<?php require_once __DIR__ . '/../includes/auth.php'; $user=require_role(['super_admin'], '../auth/login.php'); $pdo=db(); $base='../'; 
$active='Subscriptions'; $pageTitle='Subscriptions';
if(is_post()){ verify_csrf(); $pdo->prepare('UPDATE subscriptions SET status=? WHERE id=?')->execute([$_POST['status'],(int)$_POST['id']]); flash('success','Subscription updated.'); redirect('subscriptions.php'); }
$rows=$pdo->query('SELECT s.*,t.name tenant_name,p.name plan_name FROM subscriptions s JOIN tenants t ON t.id=s.tenant_id JOIN plans p ON p.id=s.plan_id ORDER BY s.id DESC')->fetchAll();
$payments=$pdo->query('SELECT pay.*,t.name tenant_name FROM payments pay JOIN tenants t ON t.id=pay.tenant_id ORDER BY pay.id DESC LIMIT 20')->fetchAll();
include __DIR__ . '/header.php'; ?>
<div class="card p-0 overflow-auto mb-3"><table class="table mb-0"><tr><th>Tenant</th><th>Plan</th><th>Status</th><th>Cycle</th><th>Trial ends</th><th></th></tr>
<?php foreach($rows as $r): ?><tr><td><?= e($r['tenant_name']) ?></td><td><?= e($r['plan_name']) ?></td><td><?= e($r['status']) ?></td><td><?= e($r['billing_cycle']) ?></td><td><?= e($r['trial_ends_at']??'') ?></td>
<td><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><select class="form-select form-select-sm" name="status"><?php foreach(['trial','active','past_due','cancelled','expired'] as $st): ?><option <?= $r['status']===$st?'selected':'' ?>><?= $st ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary">Save</button></form></td></tr><?php endforeach; ?></table></div>
<div class="card p-3"><h6>Recent payments</h6><table class="table mb-0"><tr><th>Tenant</th><th>Amount</th><th>Status</th><th>Method</th><th>Date</th></tr><?php foreach($payments as $p): ?><tr><td><?= e($p['tenant_name']) ?></td><td><?= money($p['amount']) ?></td><td><?= e($p['status']) ?></td><td><?= e($p['method']??'') ?></td><td><?= e($p['created_at']) ?></td></tr><?php endforeach; ?></table></div>
<?php include __DIR__ . '/footer.php'; ?>
