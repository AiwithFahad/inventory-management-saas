<?php require_once __DIR__ . '/includes/auth.php'; require_once __DIR__ . '/includes/roles.php'; $user=require_role(['owner']); $pdo=db(); check_tenant_active($pdo); $tid=require_tenant(); $base=''; 
$active='subscription'; $pageTitle='Subscription & Billing';
if (is_post()) { verify_csrf(); $planId=(int)$_POST['plan_id']; $cycle=$_POST['cycle']==='yearly'?'yearly':'monthly';
  $s=$pdo->prepare('SELECT * FROM plans WHERE id=? AND is_active=1'); $s->execute([$planId]); $plan=$s->fetch(); if(!$plan) redirect('subscription.php');
  $amount=$cycle==='yearly'?(float)$plan['price_yearly']:(float)$plan['price_monthly'];
  $couponCode=strtoupper(trim($_POST['coupon']??'')); $coupon=null;
  if($couponCode!==''){ $cs=$pdo->prepare('SELECT * FROM coupons WHERE code=? AND is_active=1 AND (expires_at IS NULL OR expires_at>NOW()) AND used_count<max_uses'); $cs->execute([$couponCode]); $coupon=$cs->fetch(); if($coupon) $amount=round($amount*(1-(int)$coupon['discount_percent']/100),2); }
  try{ $pdo->beginTransaction();
    $pdo->prepare('UPDATE subscriptions SET status="cancelled" WHERE tenant_id=? AND status IN ("active","trial")')->execute([$tid]);
    $pdo->prepare('INSERT INTO subscriptions(tenant_id,plan_id,status,billing_cycle,starts_at,ends_at,created_at) VALUES(?,?,"active",?,NOW(),DATE_ADD(NOW(),INTERVAL 1 '.($cycle==='yearly'?'YEAR':'MONTH').'),NOW())')->execute([$tid,$planId,$cycle]);
    $subId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO payments(tenant_id,subscription_id,amount,status,method,paid_at,created_at) VALUES(?,?,?,"paid","card",NOW(),NOW())')->execute([$tid,$subId,$amount]); $payId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO invoices(tenant_id,payment_id,invoice_number,amount,status,issued_at) VALUES(?,?,?,?,"paid",NOW())')->execute([$tid,$payId,'INV-'.date('Y').'-'.str_pad((string)$payId,5,'0',STR_PAD_LEFT),$amount]);
    if ($coupon) $pdo->prepare('UPDATE coupons SET used_count=used_count+1 WHERE id=?')->execute([$coupon['id']]);
    $pdo->commit(); audit_log($pdo,'subscription_change','subscriptions',$subId); flash('success','Plan changed to '.$plan['name'].' ('.$cycle.'). Invoice generated.');
  }catch(Throwable $ex){$pdo->rollBack(); flash('error','Plan change failed.');}
  redirect('subscription.php'); }
$s=$pdo->prepare('SELECT s.*,p.name plan_name,p.price_monthly,p.price_yearly FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.tenant_id=? ORDER BY s.id DESC LIMIT 1'); $s->execute([$tid]); $current=$s->fetch();
$plans=$pdo->query('SELECT * FROM plans WHERE is_active=1 ORDER BY price_monthly')->fetchAll();
$s=$pdo->prepare('SELECT i.*,p.method FROM invoices i LEFT JOIN payments p ON p.id=i.payment_id WHERE i.tenant_id=? ORDER BY i.id DESC'); $s->execute([$tid]); $invoices=$s->fetchAll();
// coupon check helper (used via ?coupon=CODE on page)
$couponMsg=''; if(!empty($_GET['coupon'])){ $s=$pdo->prepare('SELECT * FROM coupons WHERE code=? AND is_active=1 AND (expires_at IS NULL OR expires_at>NOW())'); $s->execute([strtoupper($_GET['coupon'])]); $c=$s->fetch(); $couponMsg=$c?('Coupon valid: '.$c['discount_percent'].'% off'): 'Coupon not valid.'; }
include __DIR__ . '/includes/header.php'; ?>
<?php if($current): ?><div class="alert alert-primary">Current plan: <strong><?= e($current['plan_name']) ?></strong> · Status: <?= e($current['status']) ?> · Cycle: <?= e($current['billing_cycle']) ?><?php if($current['trial_ends_at']): ?> · Trial ends <?= e($current['trial_ends_at']) ?><?php endif; ?></div><?php endif; ?>
<?php if($couponMsg): ?><div class="alert alert-info"><?= e($couponMsg) ?></div><?php endif; ?>
<form class="row g-2 mb-3" method="get"><div class="col-md-4"><input class="form-control" name="coupon" placeholder="Coupon code e.g. WELCOME10"></div><div class="col-md-2"><button class="btn btn-outline-primary w-100">Check coupon</button></div></form>
<div class="row g-3"><?php foreach($plans as $p): ?><div class="col-md-3"><div class="card p-3 h-100 <?= ($current['plan_id']??0)==$p['id']?'border-primary border-2':'' ?>"><h5><?= e($p['name']) ?></h5><div class="fs-4 fw-bold"><?= money($p['price_monthly']) ?><small class="fs-6 text-muted">/mo</small></div><div class="text-muted small"><?= money($p['price_yearly']) ?>/year</div>
<ul class="small mt-2"><li><?= $p['max_users'] ?> employees</li><li><?= $p['max_products'] ?> products</li><li><?= $p['max_warehouses'] ?> warehouses</li></ul>
<form method="post"><?= csrf_field() ?><input type="hidden" name="plan_id" value="<?= $p['id'] ?>"><input class="form-control form-control-sm mb-2" name="coupon" placeholder="Coupon (optional)"><select class="form-select form-select-sm mb-2" name="cycle"><option>monthly</option><option>yearly</option></select><button class="btn btn-primary w-100 btn-sm"><?= ($current['plan_id']??0)==$p['id']?'Renew / Change cycle':'Upgrade / Downgrade' ?></button></form></div></div><?php endforeach; ?></div>
<div class="card p-3 mt-3"><h6>Invoices</h6><table class="table mb-0"><tr><th>Invoice</th><th>Amount</th><th>Status</th><th>Method</th><th>Date</th></tr><?php foreach($invoices as $i): ?><tr><td><?= e($i['invoice_number']) ?></td><td><?= money($i['amount']) ?></td><td><?= e($i['status']) ?></td><td><?= e($i['method']??'') ?></td><td><?= e($i['issued_at']) ?></td></tr><?php endforeach; ?></table></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
