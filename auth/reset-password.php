<?php require_once __DIR__ . '/../includes/auth.php'; $error=''; $token=$_GET['token']??($_POST['token']??'');
if (is_post()) { verify_csrf(); $pdo=db();
  $s=$pdo->prepare('SELECT * FROM password_resets WHERE token=? AND expires_at>NOW() ORDER BY id DESC LIMIT 1'); $s->execute([hash('sha256',$token)]); $r=$s->fetch();
  if (!$r) $error='Invalid or expired token.'; elseif (strlen($_POST['password']??'')<8) $error='Password must be at least 8 characters.';
  else { $pdo->prepare('UPDATE users SET password_hash=? WHERE email=?')->execute([password_hash($_POST['password'],PASSWORD_DEFAULT),$r['email']]); $pdo->prepare('DELETE FROM password_resets WHERE email=?')->execute([$r['email']]); redirect('login.php'); }
}
$t='Reset password'; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($t ?? 'Auth') ?> | StockPro SaaS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/css/style.css" rel="stylesheet"></head>
<body class="d-flex align-items-center justify-content-center min-vh-100"><div class="card shadow p-4" style="max-width:460px;width:100%">

<h3 class="mb-3">Reset password</h3><?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
<div class="mb-3"><label class="form-label">New password</label><input class="form-control" type="password" name="password" required></div>
<button class="btn btn-primary w-100">Reset password</button></form></div></body></html>
