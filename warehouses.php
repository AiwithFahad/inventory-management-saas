<?php require_once __DIR__ . '/includes/auth.php'; require_once __DIR__ . '/includes/roles.php';
$user = require_role(ROLE_MANAGE); $pdo = db(); check_tenant_active($pdo); $tid = require_tenant(); $base = ''; $active = 'warehouses'; $pageTitle = 'Warehouses';
if (is_post()) { verify_csrf(); $act = $_POST['action'] ?? '';
  try {
    if ($act === 'transfer') {
        $from = (int)$_POST['from_warehouse']; $to = (int)$_POST['to_warehouse']; $pid = (int)$_POST['product_id']; $qty = abs((float)$_POST['quantity']);
        if ($from === $to) throw new RuntimeException('Source and destination warehouses must differ.');
        $ref = 'TRF-' . date('YmdHis');
        $pdo->beginTransaction();
        apply_movement($pdo, $tid, $from, $pid, 'transfer', -$qty, $ref, 'Transfer to ' . warehouse_name($pdo, $tid, $to));
        apply_movement($pdo, $tid, $to, $pid, 'transfer', $qty, $ref, 'Transfer from ' . warehouse_name($pdo, $tid, $from));
        $pdo->commit(); audit_log($pdo, 'stock_transfer', 'products', $pid); flash('success', "Transferred $qty units ($ref).");
    } elseif ($act === 'delete') {
        $pdo->prepare('DELETE FROM warehouses WHERE id=? AND tenant_id=? AND is_main=0')->execute([(int)$_POST['id'], $tid]); flash('success','Warehouse deleted (main warehouse cannot be deleted).');
    } else {
        $d = [$tid, trim($_POST['name']), trim($_POST['location'] ?? ''), trim($_POST['address'] ?? ''), trim($_POST['racks'] ?? ''), trim($_POST['shelves'] ?? ''), trim($_POST['bins'] ?? ''), !empty($_POST['is_main']) ? 1 : 0];
        if (!empty($_POST['id'])) { $pdo->prepare('UPDATE warehouses SET name=?,location=?,address=?,racks=?,shelves=?,bins=?,is_main=? WHERE id=? AND tenant_id=?')->execute([trim($_POST['name']),trim($_POST['location'] ?? ''),trim($_POST['address'] ?? ''),trim($_POST['racks'] ?? ''),trim($_POST['shelves'] ?? ''),trim($_POST['bins'] ?? ''),!empty($_POST['is_main'])?1:0,(int)$_POST['id'],$tid]); }
        else {
            $lim = subscription_limits($pdo); $cnt = (int)q1($pdo, 'SELECT COUNT(*) FROM warehouses WHERE tenant_id=?', [$tid]);
            if ($cnt >= (int)($lim['max_warehouses'] ?? 1)) throw new RuntimeException('Plan warehouse limit reached (' . ($lim['max_warehouses'] ?? 1) . '). Upgrade your plan.');
            if (!empty($_POST['is_main'])) $pdo->prepare('UPDATE warehouses SET is_main=0 WHERE tenant_id=?')->execute([$tid]);
            $pdo->prepare('INSERT INTO warehouses(tenant_id,name,location,address,racks,shelves,bins,is_main,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())')->execute($d);
        }
        flash('success','Warehouse saved.');
    }
  } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); flash('error', 'Action failed: ' . $ex->getMessage()); }
  redirect('warehouses.php');
}
$whs = qa($pdo, 'SELECT w.*, (SELECT COUNT(DISTINCT product_id) FROM product_warehouse pw WHERE pw.warehouse_id=w.id AND pw.quantity>0) skus, (SELECT COALESCE(SUM(pw.quantity*p.cost_price),0) FROM product_warehouse pw JOIN products p ON p.id=pw.product_id WHERE pw.warehouse_id=w.id) value FROM warehouses w WHERE w.tenant_id=? ORDER BY w.is_main DESC, w.name', [$tid]);
$prods = qa($pdo, 'SELECT * FROM products WHERE tenant_id=? AND status="active" ORDER BY name', [$tid]);
$viewId = (int)($_GET['view'] ?? ($whs[0]['id'] ?? 0));
$inv = $viewId ? qa($pdo, 'SELECT pw.quantity, p.name, p.sku, p.unit, p.cost_price FROM product_warehouse pw JOIN products p ON p.id=pw.product_id WHERE pw.tenant_id=? AND pw.warehouse_id=? ORDER BY p.name', [$tid, $viewId]) : [];
$edit = null; if (!empty($_GET['edit'])) $edit = qa($pdo, 'SELECT * FROM warehouses WHERE id=? AND tenant_id=?', [(int)$_GET['edit'], $tid])[0] ?? null;
include __DIR__ . '/includes/header.php'; ?>
<div class="row g-3"><div class="col-lg-7">
<?php foreach ($whs as $w): ?><div class="card p-3 mb-2"><div class="d-flex justify-content-between"><div><strong><?= e($w['name']) ?></strong> <?= $w['is_main'] ? '<span class="badge bg-primary">main</span>' : '' ?><div class="small text-muted"><?= e($w['location'] ?? '') ?> · <?= e($w['address'] ?? '') ?><br>Racks: <?= e($w['racks'] ?? '—') ?> · Shelves: <?= e($w['shelves'] ?? '—') ?> · Bins: <?= e($w['bins'] ?? '—') ?></div></div>
<div class="text-end"><div class="fw-bold"><?= money($w['value']) ?></div><div class="small text-muted"><?= $w['skus'] ?> SKUs in stock</div>
<a class="btn btn-sm btn-outline-primary mt-1" href="?view=<?= $w['id'] ?>">View inventory</a> <a class="btn btn-sm btn-outline-secondary mt-1" href="?edit=<?= $w['id'] ?>">Edit</a>
<form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $w['id'] ?>"><button class="btn btn-sm btn-outline-danger mt-1" data-confirm="Delete warehouse?">Delete</button></form></div></div></div><?php endforeach; ?>
<div class="card p-3"><h6>Inventory — <?= e(warehouse_name($pdo, $tid, $viewId)) ?></h6><table class="table table-sm mb-0"><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Value</th></tr>
<?php foreach ($inv as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['sku']) ?></td><td><?= $r['quantity'] ?> <?= e($r['unit']) ?></td><td><?= money((float)$r['quantity'] * (float)$r['cost_price']) ?></td></tr><?php endforeach; if (!$inv) echo '<tr><td colspan="4" class="text-muted">No stock in this warehouse.</td></tr>'; ?></table></div></div>
<div class="col-lg-5"><div class="card p-3"><h6><?= $edit ? 'Edit' : 'Add' ?> warehouse</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($edit['id'] ?? '')) ?>">
<?php foreach ([['name','Name', $edit['name'] ?? ''],['location','Location / city', $edit['location'] ?? ''],['address','Address', $edit['address'] ?? ''],['racks','Racks (e.g. A1–A6)', $edit['racks'] ?? ''],['shelves','Shelves', $edit['shelves'] ?? ''],['bins','Bins / zones', $edit['bins'] ?? '']] as [$f,$l,$v]): ?><div class="mb-2"><label class="form-label small"><?= $l ?></label><input class="form-control" name="<?= $f ?>" value="<?= e((string)$v) ?>" <?= $f === 'name' ? 'required' : '' ?>></div><?php endforeach; ?>
<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_main" value="1" id="mn" <?= !empty($edit['is_main']) ? 'checked' : '' ?>><label class="form-check-label" for="mn">Main warehouse</label></div>
<button class="btn btn-primary w-100">Save warehouse</button></form></div>
<div class="card p-3 mt-3"><h6>Transfer stock between warehouses</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="transfer">
<div class="mb-2"><label class="form-label small">Product</label><select class="form-select" name="product_id" required><?php foreach ($prods as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option><?php endforeach; ?></select></div>
<div class="row g-2"><div class="col-6"><label class="form-label small">From</label><select class="form-select" name="from_warehouse" required><?php foreach ($whs as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-6"><label class="form-label small">To</label><select class="form-select" name="to_warehouse" required><?php foreach ($whs as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label small">Quantity</label><input class="form-control" type="number" step="0.01" name="quantity" required></div></div>
<button class="btn btn-primary w-100 mt-3">Transfer (transactional, linked movements)</button></form></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
