<?php require_once __DIR__ . '/../includes/auth.php'; $user=require_role(['super_admin'], '../auth/login.php'); $pdo=db(); $base='../'; 
$active='Support Tickets'; $pageTitle='Support Tickets';
if(is_post()){ verify_csrf();
 if(($_POST['action']??'')==='create'){ $pdo->prepare('INSERT INTO support_tickets(tenant_id,user_id,subject,message,status,priority,created_at) VALUES(?,?,?,?, "open",?,NOW())')->execute([(int)$_POST['tenant_id']?:null,$user['id'],trim($_POST['subject']??''),trim($_POST['message']??''),$_POST['priority']??'medium']); }
 else $pdo->prepare('UPDATE support_tickets SET status=? WHERE id=?')->execute([$_POST['status'],(int)$_POST['id']]);
 flash('success','Ticket updated.'); redirect('tickets.php'); }
$rows=$pdo->query('SELECT tk.*,t.name tenant_name,u.name user_name FROM support_tickets tk LEFT JOIN tenants t ON t.id=tk.tenant_id LEFT JOIN users u ON u.id=tk.user_id ORDER BY tk.id DESC')->fetchAll();
$tenants=$pdo->query('SELECT id,name FROM tenants ORDER BY name')->fetchAll();
include __DIR__ . '/header.php'; ?>
<div class="card p-3 mb-3"><form class="row g-2" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
<div class="col-md-3"><select class="form-select" name="tenant_id"><option value="">Platform</option><?php foreach($tenants as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><input class="form-control" name="subject" placeholder="Subject" required></div><div class="col-md-4"><input class="form-control" name="message" placeholder="Message" required></div>
<div class="col-md-1"><select class="form-select" name="priority"><option>low</option><option selected>medium</option><option>high</option></select></div><div class="col-md-1"><button class="btn btn-primary w-100">Add</button></div></form></div>
<table class="table card"><tr><th>Subject</th><th>Tenant</th><th>By</th><th>Priority</th><th>Status</th><th></th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['subject']) ?><div class="small text-muted"><?= e($r['message']) ?></div></td><td><?= e($r['tenant_name']??'Platform') ?></td><td><?= e($r['user_name']??'') ?></td><td><?= e($r['priority']) ?></td><td><?= e($r['status']) ?></td>
<td><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><select class="form-select form-select-sm" name="status"><?php foreach(['open','in_progress','resolved','closed'] as $st): ?><option <?= $r['status']===$st?'selected':'' ?>><?= $st ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary">Save</button></form></td></tr><?php endforeach; ?></table>
<?php include __DIR__ . '/footer.php'; ?>
