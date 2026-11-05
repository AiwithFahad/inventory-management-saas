<?php require_once __DIR__ . '/../includes/auth.php';
if (current_user()) redirect(current_user()['role']==='super_admin' ? '../super-admin/index.php' : '../dashboard.php');
$error = $_GET['error'] ?? '';
if (is_post()) {
  verify_csrf(); $pdo=db();
  $s=$pdo->prepare('SELECT u.*,t.status tenant_status FROM users u LEFT JOIN tenants t ON t.id=u.tenant_id WHERE u.email=?'); $s->execute([trim($_POST['email']??'')]); $u=$s->fetch();
  if (!$u || !password_verify($_POST['password']??'', $u['password_hash'])) $error='Invalid email or password.';
  elseif ($u['status']!=='active') $error='Account is suspended.';
  elseif (($u['tenant_status']??'active')==='suspended') $error='Tenant account is suspended.';
  else {
    session_regenerate_id(true);
    $_SESSION['user']=['id'=>$u['id'],'tenant_id'=>$u['tenant_id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']];
    if (!empty($_POST['remember'])) { $tok=bin2hex(random_bytes(32)); $pdo->prepare('UPDATE users SET remember_token=? WHERE id=?')->execute([hash('sha256',$tok),$u['id']]); setcookie('remember',$u['id'].':'.$tok,time()+86400*30,'/','',false,true); }
    audit_log($pdo,'login','users',(int)$u['id']);
    redirect($u['role']==='super_admin' ? '../super-admin/index.php' : '../dashboard.php');
  }
}
$t='Login'; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($t ?? 'Auth') ?> | StockPro SaaS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/css/style.css" rel="stylesheet"></head>
<body class="d-flex align-items-center justify-content-center min-vh-100"><div class="card shadow p-4" style="max-width:460px;width:100%">

<h3 class="mb-3">Welcome back</h3>
<?php if($error): ?><div class="alert alert-danger"><?= e($error==='suspended'?'Account suspended.':$error) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?><div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
<div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember" id="r"><label class="form-check-label" for="r">Remember me</label></div>
<button class="btn btn-primary w-100">Login</button></form>
<div class="d-flex justify-content-between mt-3 small"><a href="forgot-password.php">Forgot password?</a><a href="register.php">Create account</a></div>
<div class="alert alert-light mt-3 small mb-0">Demo: super@stockpro.test / password<br>Demo business: admin@demo.com / password</div>
</div></body></html>
