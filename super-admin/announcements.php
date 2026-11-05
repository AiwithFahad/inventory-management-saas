<?php require_once __DIR__ . '/../includes/auth.php'; $user=require_role(['super_admin'], '../auth/login.php'); $pdo=db(); $base='../'; 
$active='Announcements'; $pageTitle='Announcements';
if(is_post()){ verify_csrf();
 if(($_POST['action']??'')==='delete') $pdo->prepare('DELETE FROM announcements WHERE id=?')->execute([(int)$_POST['id']]);
 else $pdo->prepare('INSERT INTO announcements(title,body,is_active,created_at) VALUES(?,?,1,NOW())')->execute([trim($_POST['title']??''),trim($_POST['body']??'')]);
 flash('success','Announcement saved.'); redirect('announcements.php'); }
$rows=$pdo->query('SELECT * FROM announcements ORDER BY id DESC')->fetchAll();
include __DIR__ . '/header.php'; ?>
<div class="card p-3 mb-3"><form method="post"><?= csrf_field() ?><input class="form-control mb-2" name="title" placeholder="Title" required><textarea class="form-control mb-2" name="body" placeholder="Message" required></textarea><button class="btn btn-primary">Publish</button></form></div>
<?php foreach($rows as $r): ?><div class="card p-3 mb-2"><strong><?= e($r['title']) ?></strong><p class="mb-1"><?= e($r['body']) ?></p><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></div><?php endforeach; ?>
<?php include __DIR__ . '/footer.php'; ?>
