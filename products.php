<?php require_once __DIR__ . '/includes/auth.php'; require_once __DIR__ . '/includes/roles.php';
$user = require_role(ROLE_ALL); $pdo = db(); check_tenant_active($pdo); $tid = require_tenant(); $base = ''; $active = 'products'; $pageTitle = 'Products';
// CSV export
if (($_GET['export'] ?? '') === '1') {
    $rows = qa($pdo, 'SELECT p.sku,p.barcode,p.name,c.name category,b.name brand,p.unit,p.cost_price,p.sale_price,p.stock_qty,p.min_stock,p.max_stock,p.status FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN brands b ON b.id=p.brand_id WHERE p.tenant_id=? ORDER BY p.name', [$tid]);
    export_csv($rows, 'products.csv');
}
if (is_post()) { verify_csrf(); $act = $_POST['action'] ?? '';
  try {
    if ($act === 'category') { $pdo->prepare('INSERT INTO categories(tenant_id,name) VALUES(?,?)')->execute([$tid, trim($_POST['name'])]); flash('success','Category added.'); }
    elseif ($act === 'brand') { $pdo->prepare('INSERT INTO brands(tenant_id,name) VALUES(?,?)')->execute([$tid, trim($_POST['name'])]); flash('success','Brand added.'); }
    elseif ($act === 'delete') { require_role(ROLE_MANAGE); $pdo->prepare('DELETE FROM products WHERE id=? AND tenant_id=?')->execute([(int)$_POST['id'], $tid]); flash('success','Product deleted.'); }
    elseif ($act === 'import' && !empty($_FILES['csv']['tmp_name'])) {
        require_role(ROLE_STOCK);
        $fh = fopen($_FILES['csv']['tmp_name'], 'r'); $head = fgetcsv($fh); $n = 0;
        while (($r = fgetcsv($fh)) !== false) { if (count($r) < 3) continue; $d = array_combine(array_map(fn($h)=>strtolower(trim($h)), $head), array_pad($r, count($head), ''));
            $sku = trim($d['sku'] ?? ''); if ($sku === '') continue;
            $pdo->prepare('INSERT INTO products(tenant_id,sku,barcode,name,unit,cost_price,sale_price,min_stock,max_stock,stock_qty,track_expiry,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,0,?,?,NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),sale_price=VALUES(sale_price),cost_price=VALUES(cost_price)')
                ->execute([$tid, $sku, trim($d['barcode'] ?? '') ?: gen_barcode($pdo,$tid), trim($d['name']), trim($d['unit'] ?? 'pcs') ?: 'pcs', (float)($d['cost_price'] ?? 0), (float)($d['sale_price'] ?? 0), (float)($d['min_stock'] ?? 5), (float)($d['max_stock'] ?? 100), !empty($d['track_expiry']) ? 1 : 0, 'active']); $n++; }
        fclose($fh); flash('success', "Imported $n products from CSV.");
    } else { // product save
        require_role(ROLE_STOCK);
        $barcode = trim($_POST['barcode'] ?? ''); if ($barcode === '') $barcode = gen_barcode($pdo, $tid);
        $d = [$tid, trim($_POST['sku']), $barcode, trim($_POST['name']), trim($_POST['description'] ?? ''), !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null, !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null, trim($_POST['unit'] ?? 'pcs') ?: 'pcs', (float)$_POST['cost_price'], (float)$_POST['sale_price'], (float)$_POST['min_stock'], (float)$_POST['max_stock'], !empty($_POST['track_expiry']) ? 1 : 0, $_POST['status'] ?? 'active'];
        if (!empty($_POST['id'])) { $pdo->prepare('UPDATE products SET sku=?,barcode=?,name=?,description=?,category_id=?,brand_id=?,unit=?,cost_price=?,sale_price=?,min_stock=?,max_stock=?,track_expiry=?,status=? WHERE id=? AND tenant_id=?')->execute([trim($_POST['sku']),$barcode,trim($_POST['name']),trim($_POST['description'] ?? ''),!empty($_POST['category_id'])?(int)$_POST['category_id']:null,!empty($_POST['brand_id'])?(int)$_POST['brand_id']:null,trim($_POST['unit'] ?? 'pcs') ?: 'pcs',(float)$_POST['cost_price'],(float)$_POST['sale_price'],(float)$_POST['min_stock'],(float)$_POST['max_stock'],!empty($_POST['track_expiry'])?1:0,$_POST['status'] ?? 'active',(int)$_POST['id'],$tid]); $pid = (int)$_POST['id']; }
        else {
            $lim = subscription_limits($pdo); $cnt = (int)q1($pdo, 'SELECT COUNT(*) FROM products WHERE tenant_id=?', [$tid]);
            if ($cnt >= (int)($lim['max_products'] ?? 50)) throw new RuntimeException('Plan product limit reached (' . ($lim['max_products'] ?? 50) . '). Upgrade your plan.');
            $pdo->prepare('INSERT INTO products(tenant_id,sku,barcode,name,description,category_id,brand_id,unit,cost_price,sale_price,min_stock,max_stock,track_expiry,status,stock_qty,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,NOW())')->execute($d); $pid = (int)$pdo->lastInsertId();
        }
        audit_log($pdo, 'product_save', 'products', $pid); log_activity($pdo, 'Product saved: ' . trim($_POST['name'])); flash('success','Product saved.');
    }
  } catch (Throwable $ex) { flash('error', 'Action failed: ' . $ex->getMessage()); }
  redirect('products.php');
}
$cats = qa($pdo, 'SELECT * FROM categories WHERE tenant_id=? ORDER BY name', [$tid]);
$brands = qa($pdo, 'SELECT * FROM brands WHERE tenant_id=? ORDER BY name', [$tid]);
$q = trim($_GET['q'] ?? ''); $catF = (int)($_GET['category_id'] ?? 0);
$sql = 'SELECT p.*, c.name cat, b.name brand FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN brands b ON b.id=p.brand_id WHERE p.tenant_id=?';
$args = [$tid];
if ($q !== '') { $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)'; $args = array_merge($args, ["%$q%","%$q%","%$q%"]); }
if ($catF) { $sql .= ' AND p.category_id=?'; $args[] = $catF; }
$sql .= ' ORDER BY p.name';
$rows = qa($pdo, $sql, $args);
$edit = null; if (!empty($_GET['edit'])) $edit = qa($pdo, 'SELECT * FROM products WHERE id=? AND tenant_id=?', [(int)$_GET['edit'], $tid])[0] ?? null;
include __DIR__ . '/includes/header.php'; ?>
<div class="row g-3"><div class="col-lg-8"><div class="card p-3">
<form class="row g-2 mb-3" method="get"><div class="col-md-5"><input class="form-control" name="q" placeholder="Search name / SKU / barcode" value="<?= e($q) ?>"></div>
<div class="col-md-4"><select class="form-select" name="category_id"><option value="">All categories</option><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $catF == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><button class="btn btn-primary">Filter</button> <a class="btn btn-outline-success" href="?export=1">CSV export</a></div></form>
<table class="table table-sm"><tr><th>SKU / Barcode</th><th>Product</th><th>Category</th><th>Cost / Sale</th><th>Stock</th><th>Min–Max</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr class="<?= $r['stock_qty'] <= 0 ? 'table-danger' : ($r['stock_qty'] <= $r['min_stock'] ? 'table-warning' : '') ?>"><td class="small"><?= e($r['sku']) ?><br><span class="text-muted"><?= e($r['barcode']) ?></span></td><td><?= e($r['name']) ?><?= $r['track_expiry'] ? ' <span class="badge bg-info">expiry</span>' : '' ?></td><td><?= e($r['cat'] ?? '') ?> / <?= e($r['brand'] ?? '') ?></td><td><?= money($r['cost_price']) ?> / <?= money($r['sale_price']) ?></td><td><?= $r['stock_qty'] ?> <?= e($r['unit']) ?></td><td><?= $r['min_stock'] ?>–<?= $r['max_stock'] ?></td><td><?= e($r['status']) ?></td>
<td><a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>">Edit</a> <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Delete product?">Del</button></form></td></tr><?php endforeach; if (!$rows) echo '<tr><td colspan="8" class="text-muted">No products found.</td></tr>'; ?></table></div>
<div class="card p-3 mt-3"><h6>Import products (CSV)</h6><p class="small text-muted mb-2">Header row: sku,barcode,name,unit,cost_price,sale_price,min_stock,max_stock,track_expiry — the file is parsed row-by-row and upserted by SKU.</p>
<form method="post" enctype="multipart/form-data" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="import"><input class="form-control" type="file" name="csv" accept=".csv" required><button class="btn btn-primary">Import</button></form></div></div>
<div class="col-lg-4"><div class="card p-3"><h6><?= $edit ? 'Edit' : 'Add' ?> product</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($edit['id'] ?? '')) ?>">
<div class="mb-2"><label class="form-label small">SKU (unique per business)</label><input class="form-control" name="sku" required value="<?= e($edit['sku'] ?? '') ?>"></div>
<div class="mb-2"><label class="form-label small">Barcode (blank = auto-generate)</label><input class="form-control" name="barcode" value="<?= e($edit['barcode'] ?? '') ?>"></div>
<div class="mb-2"><label class="form-label small">Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
<div class="mb-2"><label class="form-label small">Description</label><input class="form-control" name="description" value="<?= e($edit['description'] ?? '') ?>"></div>
<div class="row g-2"><div class="col-6"><label class="form-label small">Category</label><select class="form-select" name="category_id"><option value="">—</option><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= ($edit['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-6"><label class="form-label small">Brand</label><select class="form-select" name="brand_id"><option value="">—</option><?php foreach ($brands as $b): ?><option value="<?= $b['id'] ?>" <?= ($edit['brand_id'] ?? 0) == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-4"><label class="form-label small">Unit</label><input class="form-control" name="unit" value="<?= e($edit['unit'] ?? 'pcs') ?>"></div>
<div class="col-4"><label class="form-label small">Cost price</label><input class="form-control" type="number" step="0.01" name="cost_price" value="<?= e((string)($edit['cost_price'] ?? '0')) ?>"></div>
<div class="col-4"><label class="form-label small">Sale price</label><input class="form-control" type="number" step="0.01" name="sale_price" value="<?= e((string)($edit['sale_price'] ?? '0')) ?>"></div>
<div class="col-4"><label class="form-label small">Min stock</label><input class="form-control" type="number" step="0.01" name="min_stock" value="<?= e((string)($edit['min_stock'] ?? '5')) ?>"></div>
<div class="col-4"><label class="form-label small">Max stock</label><input class="form-control" type="number" step="0.01" name="max_stock" value="<?= e((string)($edit['max_stock'] ?? '100')) ?>"></div>
<div class="col-4"><label class="form-label small">Status</label><select class="form-select" name="status"><option value="active" <?= ($edit['status'] ?? '') === 'active' ? 'selected' : '' ?>>active</option><option value="inactive" <?= ($edit['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>inactive</option></select></div></div>
<div class="form-check my-2"><input class="form-check-input" type="checkbox" name="track_expiry" value="1" id="te" <?= !empty($edit['track_expiry']) ? 'checked' : '' ?>><label class="form-check-label" for="te">Track expiry / batches</label></div>
<button class="btn btn-primary w-100">Save product</button></form></div>
<div class="card p-3 mt-3"><h6>Categories &amp; brands</h6>
<form method="post" class="d-flex gap-2 mb-2"><?= csrf_field() ?><input type="hidden" name="action" value="category"><input class="form-control" name="name" placeholder="New category" required><button class="btn btn-outline-primary">Add</button></form>
<form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="brand"><input class="form-control" name="name" placeholder="New brand" required><button class="btn btn-outline-primary">Add</button></form>
<p class="small text-muted mt-2 mb-0"><?= count($cats) ?> categories · <?= count($brands) ?> brands</p></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
