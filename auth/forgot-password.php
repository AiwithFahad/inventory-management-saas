<?php require_once __DIR__ . '/../includes/auth.php'; $msg='';
if (is_post()) { verify_csrf(); $pdo=db(); $email=trim($_POST['email']??'');
  $s=$pdo->prepare('SELECT id FROM users WHERE email=?'); $s->execute([$email]); $uid=$s->fetchColumn();
  if ($uid) { $tok=bin2hex(random_bytes(32)); $pdo->prepare('INSERT INTO password_resets(email,token,expires_at,created_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR),NOW())')->execute([$email,hash('sha256',$tok)]); 
    // In production this link is emailed; shown here only in local/demo mode.
    $msg = APP_ENV==='production' ? 'If that email exists, a reset link has been sent.' : 'Reset link (demo): reset-password.php?token='.$tok; }
  else $msg='If that email exists, a reset link has been sent.';
}
$t='Forgot password'; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($t ?? 'Auth') ?> | StockPro SaaS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/css/style.css" rel="stylesheet"></head>
<body class="d-flex align-items-center justify-content-center min-vh-100"><div class="card shadow p-4" style="max-width:460px;width:100%">

<h3 class="mb-3">Forgot password</h3><?php if($msg): ?><div class="alert alert-info small"><?= e($msg) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?><div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
<button class="btn btn-primary w-100">Send reset link</button></form><p class="small mt-3 mb-0"><a href="login.php">Back to login</a></p></div></body></html>
