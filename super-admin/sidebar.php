<nav class="sidebar d-flex flex-column p-3">
<a class="brand text-white text-decoration-none fs-5 fw-bold mb-4" href="index.php"><i class="bi bi-shield-lock"></i> Super Admin</a>
<?php foreach([['index.php','Platform','bi-speedometer2'],['tenants.php','Tenants','bi-buildings'],['plans.php','Plans','bi-layers'],['subscriptions.php','Subscriptions','bi-credit-card'],['coupons.php','Coupons','bi-ticket-percent'],['tickets.php','Support Tickets','bi-life-preserver'],['announcements.php','Announcements','bi-megaphone'],['settings.php','Settings','bi-gear']] as [$f,$l,$i]): ?>
<a class="nav-link <?= ($active??'')===$l?'active':'' ?>" href="<?= $f ?>"><i class="bi <?= $i ?>"></i> <?= $l ?></a><?php endforeach; ?>
<div class="mt-auto small text-white-50"><?= e(current_user()['name'] ?? '') ?></div>
<a class="nav-link" href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></nav>
