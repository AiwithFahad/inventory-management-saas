<?php require_once __DIR__ . '/../includes/auth.php'; $user=require_role(['super_admin'], '../auth/login.php'); $pdo=db(); $base='../'; 
$active='Coupons'; $pageTitle='Coupons';
if(is_post()){ verify_csrf();
 if(($_POST['action']??'')==='delete') $pdo->prepare('UPDATE coupons SET is_active=0 WHERE id=?')->execute([(int)$_POST['id']]);
 else $pdo->prepare('INSERT INTO coupons(code,discount_percent,max_uses,expires_at,is_active,created_at) VALUES(?,?,?,?,1,NOW())')->execute([strtoupper(trim($_POST['code']??'')),(int)($_POST['discount_percent']??0),(int)($_POST['max_uses']??100),$_POST['expires_at']?:null]);
 flash('success','Coupon saved.'); redirect('coupons.php'); }
$rows=$pdo->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll();
include __DIR__ . '/header.php'; ?>
<div class="card p-3 mb-3"><form class="row g-2" method="post"><?= csrf_field() ?><div class="col-md-3"><input class="form-control" name="code" placeholder="CODE" required></div><div class="col-md-2"><input class="form-control" type="number" name="discount_percent" placeholder="% off" required></div><div class="col-md-2"><input class="form-control" type="number" name="max_uses" placeholder="Max uses" value="100"></div><div class="col-md-3"><input class="form-control" type="date" name="expires_at"></div><div class="col-md-2"><button class="btn btn-primary w-100">Add coupon</button></div></form></div>
<table class="table card"><tr><th>Code</th><th>Discount</th><th>Used / Max</th><th>Expires</th><th>Active</th><th></th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['code']) ?></td><td><?= $r['discount_percent'] ?>%</td><td><?= $r['used_count'] ?> / <?= $r['max_uses'] ?></td><td><?= e($r['expires_at']??'') ?></td><td><?= $r['is_active']?'Yes':'No' ?></td><td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Deactivate</button></form></td></tr><?php endforeach; ?></table>
<?php include __DIR__ . '/footer.php'; ?>
