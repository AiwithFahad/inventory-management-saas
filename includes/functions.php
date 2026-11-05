<?php
declare(strict_types=1);
function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): void { header('Location: ' . $url); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) { http_response_code(419); exit('CSRF token mismatch.'); }
}
function flash(string $key, ?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'][$key] = $msg; return $msg; }
    $v = $_SESSION['flash'][$key] ?? null; unset($_SESSION['flash'][$key]); return $v;
}
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function tenant_id(): ?int { $u = current_user(); return isset($u['tenant_id']) && $u['tenant_id'] !== null ? (int)$u['tenant_id'] : null; }
function qa(PDO $pdo, string $sql, array $a = []): array { $s = $pdo->prepare($sql); $s->execute($a); return $s->fetchAll(); }
function q1(PDO $pdo, string $sql, array $a = []) { $s = $pdo->prepare($sql); $s->execute($a); return $s->fetchColumn(); }
function audit_log(PDO $pdo, string $action, string $entity = '', ?int $entityId = null): void {
    try {
        $u = current_user();
        $s = $pdo->prepare('INSERT INTO audit_logs(tenant_id,user_id,action,entity,entity_id,ip_address,created_at) VALUES(?,?,?,?,?,?,NOW())');
        $s->execute([$u['tenant_id'] ?? null, $u['id'] ?? null, $action, $entity, $entityId, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $ignored) {}
}
function log_activity(PDO $pdo, string $text): void {
    try { $s = $pdo->prepare('INSERT INTO activities(tenant_id,user_id,description,created_at) VALUES(?,?,?,NOW())'); $s->execute([tenant_id(), current_user()['id'] ?? null, $text]); } catch (Throwable $ignored) {}
}
function money($n): string { return '$' . number_format((float)$n, 2); }
function export_csv(array $rows, string $filename): void {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    foreach ($rows as $i => $r) { if ($i === 0) fputcsv($out, array_keys($r)); fputcsv($out, $r); }
    fclose($out); exit;
}
function subscription_limits(PDO $pdo): array {
    $s = $pdo->prepare('SELECT p.* FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.tenant_id=? AND s.status IN ("trial","active") ORDER BY s.id DESC LIMIT 1');
    $s->execute([tenant_id()]); return $s->fetch() ?: ['name'=>'Free Trial','max_products'=>50,'max_warehouses'=>1,'max_users'=>3];
}
function tenant_settings(PDO $pdo, int $tid): array {
    $cfg = [];
    foreach (qa($pdo, 'SELECT setting_key,setting_value FROM settings WHERE tenant_id=?', [$tid]) as $r) $cfg[$r['setting_key']] = $r['setting_value'];
    return $cfg;
}
function tenant_setting(PDO $pdo, int $tid, string $key, string $default = ''): string {
    $v = q1($pdo, 'SELECT setting_value FROM settings WHERE tenant_id=? AND setting_key=?', [$tid, $key]);
    return $v === false || $v === null ? $default : (string)$v;
}
function prevent_negative(PDO $pdo, int $tid): bool { return tenant_setting($pdo, $tid, 'prevent_negative_stock', '1') === '1'; }
/** Default (main) warehouse for a tenant. */
function main_warehouse(PDO $pdo, int $tid): ?int {
    $id = q1($pdo, 'SELECT id FROM warehouses WHERE tenant_id=? ORDER BY is_main DESC, id LIMIT 1', [$tid]);
    return $id === false ? null : (int)$id;
}
function warehouse_name(PDO $pdo, int $tid, int $wid): string {
    return (string)(q1($pdo, 'SELECT name FROM warehouses WHERE id=? AND tenant_id=?', [$wid, $tid]) ?: '');
}
/**
 * Core stock engine. MUST be called inside a DB transaction.
 * Writes one stock_movements row (signed $delta) and updates the cached
 * product_warehouse quantity + products.stock_qty total.
 */
function apply_movement(PDO $pdo, int $tid, int $wid, int $pid, string $type, float $delta, string $reference = '', string $note = '', ?string $batchNo = null, ?string $expiry = null): void {
    $exists = q1($pdo, 'SELECT id FROM products WHERE id=? AND tenant_id=?', [$pid, $tid]);
    if (!$exists) throw new RuntimeException('Product not found for this tenant.');
    $wExists = q1($pdo, 'SELECT id FROM warehouses WHERE id=? AND tenant_id=?', [$wid, $tid]);
    if (!$wExists) throw new RuntimeException('Warehouse not found for this tenant.');
    $cur = (float)(q1($pdo, 'SELECT quantity FROM product_warehouse WHERE tenant_id=? AND product_id=? AND warehouse_id=?', [$tid, $pid, $wid]) ?: 0);
    if ($delta < 0 && prevent_negative($pdo, $tid) && ($cur + $delta) < 0) {
        throw new RuntimeException('Negative stock prevented: only ' . $cur . ' available in this warehouse.');
    }
    if ($batchNo) {
        $pdo->prepare('INSERT INTO batches(tenant_id,product_id,warehouse_id,batch_no,expiry_date,quantity,created_at) VALUES(?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)')
            ->execute([$tid, $pid, $wid, $batchNo, $expiry ?: null, $delta]);
    }
    $pdo->prepare('INSERT INTO stock_movements(tenant_id,warehouse_id,product_id,type,quantity,reference,note,batch_no,expiry_date,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([$tid, $wid, $pid, $type, $delta, $reference, $note, $batchNo, $expiry ?: null, current_user()['id'] ?? null]);
    $pdo->prepare('INSERT INTO product_warehouse(tenant_id,product_id,warehouse_id,quantity) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)')
        ->execute([$tid, $pid, $wid, $delta]);
    $pdo->prepare('UPDATE products SET stock_qty=(SELECT COALESCE(SUM(quantity),0) FROM product_warehouse WHERE tenant_id=? AND product_id=?) WHERE id=? AND tenant_id=?')
        ->execute([$tid, $tid, $pid, $tid]);
}
function gen_barcode(PDO $pdo, int $tid): string {
    do { $code = '89' . str_pad((string)random_int(0, 9999999999), 10, '0', STR_PAD_LEFT); }
    while (q1($pdo, 'SELECT COUNT(*) FROM products WHERE tenant_id=? AND barcode=?', [$tid, $code]));
    return $code;
}
function notify(PDO $pdo, int $tid, string $title, string $body = ''): void {
    try { $pdo->prepare('INSERT INTO notifications(tenant_id,user_id,title,body,created_at) VALUES(?,?,?,?,NOW())')->execute([$tid, current_user()['id'] ?? null, $title, $body]); } catch (Throwable $ignored) {}
}
