<?php require_once __DIR__ . '/../includes/auth.php'; $user=require_role(['super_admin'], '../auth/login.php'); $pdo=db(); $base='../'; 
$active='Platform'; $pageTitle='Platform Dashboard';
$stats=['Tenants'=>$pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn(),'Users'=>$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
'Active subs'=>$pdo->query('SELECT COUNT(*) FROM subscriptions WHERE status IN ("active","trial")')->fetchColumn(),
'Revenue'=>'$'.number_format((float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM payments WHERE status="paid"')->fetchColumn(),2),
'Open tickets'=>$pdo->query('SELECT COUNT(*) FROM support_tickets WHERE status="open"')->fetchColumn()];
include __DIR__ . '/header.php'; ?>
<div class="row g-3"><?php foreach($stats as $l=>$v): ?><div class="col-md-4 col-xl-2"><div class="card stat-card p-3"><div class="fs-4 fw-bold"><?= e((string)$v) ?></div><div class="text-muted small"><?= e($l) ?></div></div></div><?php endforeach; ?></div>
<div class="card p-3 mt-3"><h6>Latest tenants</h6><table class="table mb-0"><tr><th>Tenant</th><th>Status</th><th>Created</th></tr>
<?php foreach($pdo->query('SELECT * FROM tenants ORDER BY id DESC LIMIT 8')->fetchAll() as $t): ?><tr><td><?= e($t['name']) ?></td><td><?= e($t['status']) ?></td><td><?= e($t['created_at']) ?></td></tr><?php endforeach; ?></table></div>
<?php include __DIR__ . '/footer.php'; ?>
