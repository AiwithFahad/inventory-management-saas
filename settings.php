<?php require_once __DIR__ . '/includes/auth.php'; require_once __DIR__ . '/includes/roles.php';
$user = require_role(ROLE_OWNER); $pdo = db(); check_tenant_active($pdo); $tid = require_tenant(); $base = ''; $active = 'settings'; $pageTitle = 'Settings';
if (is_post()) { verify_csrf(); $act = $_POST['action'] ?? '';
  if ($act === 'profile') { $pdo->prepare('UPDATE users SET name=? WHERE id=?')->execute([trim($_POST['name'] ?? ''), $user['id']]); $_SESSION['user']['name'] = trim($_POST['name'] ?? ''); flash('success','Profile updated.'); }
  elseif ($act === 'password') {
      if (!password_verify($_POST['current'] ?? '', (string)q1($pdo, 'SELECT password_hash FROM users WHERE id=?', [$user['id']]))) flash('error','Current password is wrong.');
      elseif (strlen($_POST['new'] ?? '') < 8) flash('error','New password must be at least 8 characters.');
      else { $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($_POST['new'], PASSWORD_DEFAULT), $user['id']]); flash('success','Password changed.'); }
  } elseif ($act === 'ticket') {
      $pdo->prepare('INSERT INTO support_tickets(tenant_id,user_id,subject,message,status,priority,created_at) VALUES(?,?,?,?, "open",?,NOW())')->execute([$tid, $user['id'], trim($_POST['subject'] ?? ''), trim($_POST['message'] ?? ''), $_POST['priority'] ?? 'medium']); flash('success','Support ticket created.');
  } else {
      foreach (['company_name','currency','timezone','low_stock_threshold'] as $k) $pdo->prepare('INSERT INTO settings(tenant_id,setting_key,setting_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$tid, $k, trim($_POST[$k] ?? '')]);
      $pdo->prepare('INSERT INTO settings(tenant_id,setting_key,setting_value) VALUES(?, "prevent_negative_stock", ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$tid, !empty($_POST['prevent_negative_stock']) ? '1' : '0']);
      flash('success','Settings saved.');
  }
  redirect('settings.php');
}
$cfg = tenant_settings($pdo, $tid);
$tickets = qa($pdo, 'SELECT * FROM support_tickets WHERE tenant_id=? ORDER BY id DESC LIMIT 10', [$tid]);
$ann = qa($pdo, 'SELECT * FROM announcements WHERE is_active=1 ORDER BY id DESC LIMIT 3');
include __DIR__ . '/includes/header.php'; ?>
<?php foreach ($ann as $a): ?><div class="alert alert-info"><strong><?= e($a['title']) ?></strong> — <?= e($a['body']) ?></div><?php endforeach; ?>
<div class="row g-3"><div class="col-lg-6"><div class="card p-3"><h6>Business &amp; inventory settings</h6><form method="post"><?= csrf_field() ?>
<div class="mb-2"><label class="form-label">Business name</label><input class="form-control" name="company_name" value="<?= e($cfg['company_name'] ?? '') ?>"></div>
<div class="mb-2"><label class="form-label">Currency</label><input class="form-control" name="currency" value="<?= e($cfg['currency'] ?? 'USD') ?>"></div>
<div class="mb-2"><label class="form-label">Timezone</label><input class="form-control" name="timezone" value="<?= e($cfg['timezone'] ?? 'Asia/Karachi') ?>"></div>
<div class="mb-2"><label class="form-label">Low-stock threshold override (0 = use product min levels)</label><input class="form-control" type="number" name="low_stock_threshold" value="<?= e($cfg['low_stock_threshold'] ?? '0') ?>"></div>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="prevent_negative_stock" value="1" id="pns" <?= ($cfg['prevent_negative_stock'] ?? '1') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="pns"><strong>Prevent negative stock</strong> — block stock-out / sale confirm / transfer when a warehouse does not have enough quantity</label></div>
<button class="btn btn-primary">Save</button></form></div>
<div class="card p-3 mt-3"><h6>Change password</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password"><input class="form-control mb-2" type="password" name="current" placeholder="Current password" required><input class="form-control mb-2" type="password" name="new" placeholder="New password (min 8)" required><button class="btn btn-outline-primary">Change password</button></form></div></div>
<div class="col-lg-6"><div class="card p-3"><h6>Profile</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="profile"><input class="form-control mb-2" name="name" value="<?= e($user['name']) ?>"><div class="text-muted small mb-2"><?= e($user['email']) ?> · <?= e($user['role']) ?></div><button class="btn btn-primary">Update profile</button></form></div>
<div class="card p-3 mt-3"><h6>Support tickets</h6><form class="row g-2 mb-2" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="ticket"><div class="col-6"><input class="form-control" name="subject" placeholder="Subject" required></div><div class="col-6"><input class="form-control" name="message" placeholder="Message" required></div><div class="col-12"><button class="btn btn-sm btn-outline-primary">Open ticket</button></div></form>
<?php foreach ($tickets as $t): ?><div class="border-bottom py-2 small"><strong><?= e($t['subject']) ?></strong> <span class="badge bg-secondary"><?= e($t['status']) ?></span> — <?= e($t['message']) ?></div><?php endforeach; if (!$tickets) echo '<p class="text-muted small mb-0">No tickets yet.</p>'; ?></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
