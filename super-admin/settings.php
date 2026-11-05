<?php require_once __DIR__ . '/../includes/auth.php'; $user=require_role(['super_admin'], '../auth/login.php'); $pdo=db(); $base='../'; 
$active='Settings'; $pageTitle='Platform Settings';
if(is_post()){ verify_csrf(); foreach(['platform_name','support_email','default_currency','maintenance_mode'] as $k){ $pdo->prepare('INSERT INTO settings(tenant_id,setting_key,setting_value) VALUES(0,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$k,$_POST[$k]??'']); } flash('success','Settings saved.'); redirect('settings.php'); }
$cfg=[]; foreach($pdo->query('SELECT setting_key,setting_value FROM settings WHERE tenant_id=0')->fetchAll() as $r) $cfg[$r['setting_key']]=$r['setting_value'];
$logs=$pdo->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 20')->fetchAll();
include __DIR__ . '/header.php'; ?>
<div class="card p-3 mb-3"><form method="post"><?= csrf_field() ?>
<?php foreach([['platform_name','Platform name'],['support_email','Support email'],['default_currency','Default currency']] as [$k,$l]): ?><div class="mb-2"><label class="form-label"><?= $l ?></label><input class="form-control" name="<?= $k ?>" value="<?= e($cfg[$k]??'') ?>"></div><?php endforeach; ?>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="maintenance_mode" value="1" id="m" <?= ($cfg['maintenance_mode']??'')==='1'?'checked':'' ?>><label class="form-check-label" for="m">Maintenance mode</label></div>
<button class="btn btn-primary">Save settings</button></form></div>
<div class="card p-3"><h6>Audit log (latest)</h6><table class="table mb-0"><tr><th>Action</th><th>Entity</th><th>User</th><th>Date</th></tr><?php foreach($logs as $l): ?><tr><td><?= e($l['action']) ?></td><td><?= e($l['entity']) ?> #<?= e((string)($l['entity_id']??'')) ?></td><td><?= e((string)($l['user_id']??'')) ?></td><td><?= e($l['created_at']) ?></td></tr><?php endforeach; ?></table></div>
<?php include __DIR__ . '/footer.php'; ?>
