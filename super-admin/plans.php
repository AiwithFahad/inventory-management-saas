<?php require_once __DIR__ . '/../includes/auth.php'; $user=require_role(['super_admin'], '../auth/login.php'); $pdo=db(); $base='../'; 
$active='Plans'; $pageTitle='Subscription Plans';
if(is_post()){ verify_csrf();
 if(($_POST['action']??'')==='delete') $pdo->prepare('UPDATE plans SET is_active=0 WHERE id=?')->execute([(int)$_POST['id']]);
 else { $d=[trim($_POST['name']??''),strtolower(preg_replace('/[^a-z0-9]+/','-',$_POST['name']??'')), (float)($_POST['price_monthly']??0),(float)($_POST['price_yearly']??0),(int)($_POST['max_users']??1),(int)($_POST['max_products']??10),(int)($_POST['max_warehouses']??10)];
   if(!empty($_POST['id'])) $pdo->prepare('UPDATE plans SET name=?,slug=?,price_monthly=?,price_yearly=?,max_users=?,max_products=?,max_warehouses=? WHERE id=?')->execute([...$d,(int)$_POST['id']]);
   else $pdo->prepare('INSERT INTO plans(name,slug,price_monthly,price_yearly,max_users,max_products,max_warehouses,is_active,created_at) VALUES(?,?,?,?,?,?,?,1,NOW())')->execute($d);
 } flash('success','Plan saved.'); redirect('plans.php'); }
$rows=$pdo->query('SELECT * FROM plans ORDER BY price_monthly')->fetchAll(); $edit=null; if(!empty($_GET['edit'])){$s=$pdo->prepare('SELECT * FROM plans WHERE id=?');$s->execute([(int)$_GET['edit']]);$edit=$s->fetch();}
include __DIR__ . '/header.php'; ?>
<div class="card p-3 mb-3"><form class="row g-2" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($edit['id']??'') ?>">
<?php foreach([['name','Name','text'],['price_monthly','Monthly $','number'],['price_yearly','Yearly $','number'],['max_users','Max users','number'],['max_products','Max products','number'],['max_warehouses','Max warehouses','number']] as [$f,$l,$t]): ?><div class="col-md-2"><input class="form-control" type="<?= $t ?>" step="any" name="<?= $f ?>" placeholder="<?= $l ?>" value="<?= e($edit[$f]??'') ?>" required></div><?php endforeach; ?>
<div class="col-md-12"><button class="btn btn-primary"><?= $edit?'Update':'Add' ?> plan</button></div></form></div>
<table class="table card"><tr><th>Plan</th><th>Monthly</th><th>Yearly</th><th>Limits (users/products/warehouses)</th><th>Active</th><th></th></tr>
<?php foreach($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= money($r['price_monthly']) ?></td><td><?= money($r['price_yearly']) ?></td><td><?= $r['max_users'] ?>/<?= $r['max_products'] ?>/<?= $r['max_warehouses'] ?></td><td><?= $r['is_active']?'Yes':'No' ?></td>
<td><a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>">Edit</a> <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Deactivate</button></form></td></tr><?php endforeach; ?></table>
<?php include __DIR__ . '/footer.php'; ?>
