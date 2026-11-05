<?php require_once __DIR__ . '/../includes/auth.php';
$user = require_login('../auth/login.php'); $pdo = db(); $tid = require_tenant();
header('Content-Type: application/json');
$q = '%' . trim($_GET['q'] ?? '') . '%';
echo json_encode(qa($pdo, 'SELECT id,name,sku,barcode,stock_qty,sale_price FROM products WHERE tenant_id=? AND (name LIKE ? OR sku LIKE ? OR barcode LIKE ?) ORDER BY name LIMIT 10', [$tid, $q, $q, $q]));
