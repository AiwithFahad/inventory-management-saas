<?php require_once __DIR__ . '/../includes/auth.php'; $error='';
if (is_post()) { verify_csrf(); $pdo=db();
  $company=trim($_POST['company']??''); $name=trim($_POST['name']??''); $email=trim($_POST['email']??''); $pass=$_POST['password']??'';
  if (strlen($pass)<8) $error='Password must be at least 8 characters.';
  else { try { $pdo->beginTransaction();
    $slug=strtolower(preg_replace('/[^a-z0-9]+/','-', $company)); 
    $pdo->prepare('INSERT INTO tenants(name,slug,status,created_at) VALUES(?,?,"active",NOW())')->execute([$company,$slug.'-'.substr(bin2hex(random_bytes(3)),0,6)]);
    $tid=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO users(tenant_id,name,email,password_hash,role,status,email_verified_at,created_at) VALUES(?,?,?,?, "owner","active",NOW(),NOW())')->execute([$tid,$name,$email,password_hash($pass,PASSWORD_DEFAULT)]);
    $uid=(int)$pdo->lastInsertId();
    $plan=$pdo->query('SELECT id FROM plans WHERE slug="trial" LIMIT 1')->fetchColumn();
    $pdo->prepare('INSERT INTO subscriptions(tenant_id,plan_id,status,billing_cycle,starts_at,trial_ends_at,created_at) VALUES(?,?,"trial","monthly",NOW(),DATE_ADD(NOW(),INTERVAL 14 DAY),NOW())')->execute([$tid,$plan]);
    // default Main Warehouse + settings + starter taxonomy
    $pdo->prepare('INSERT INTO warehouses(tenant_id,name,location,is_main,created_at) VALUES(?, "Main Warehouse", "Head office", 1, NOW())')->execute([$tid]);
    foreach ([['prevent_negative_stock','1'],['company_name',$company],['currency','USD'],['timezone','Asia/Karachi']] as [$k,$v]) $pdo->prepare('INSERT INTO settings(tenant_id,setting_key,setting_value) VALUES(?,?,?)')->execute([$tid,$k,$v]);
    foreach (['General','Electronics','Accessories'] as $c) $pdo->prepare('INSERT INTO categories(tenant_id,name) VALUES(?,?)')->execute([$tid,$c]);
    $pdo->commit(); $_SESSION['user']=['id'=>$uid,'tenant_id'=>$tid,'name'=>$name,'email'=>$email,'role'=>'owner']; redirect('../dashboard.php');
  } catch (Throwable $ex) { $pdo->rollBack(); $error='Registration failed — email may already exist.'; } }
}
$t='Register'; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($t ?? 'Auth') ?> | StockPro SaaS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/css/style.css" rel="stylesheet"></head>
<body class="d-flex align-items-center justify-content-center min-vh-100"><div class="card shadow p-4" style="max-width:460px;width:100%">

<h3 class="mb-3">Start your free trial</h3><?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?>
<div class="mb-3"><label class="form-label">Company</label><input class="form-control" name="company" required></div>
<div class="mb-3"><label class="form-label">Your name</label><input class="form-control" name="name" required></div>
<div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
<div class="mb-3"><label class="form-label">Password (min 8)</label><input class="form-control" type="password" name="password" required></div>
<button class="btn btn-primary w-100">Create tenant account</button></form>
<p class="small mt-3 mb-0">Already registered? <a href="login.php">Login</a></p></div></body></html>
