<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
function require_login(string $loginUrl = null): array {
    $u = current_user();
    if (!$u) {
        if (!empty($_COOKIE['remember'])) {
            [$id, $token] = array_pad(explode(':', $_COOKIE['remember'], 2), 2, '');
            if (ctype_digit($id) && $token) {
                $s = db()->prepare('SELECT * FROM users WHERE id=? AND status="active"'); $s->execute([(int)$id]);
                $row = $s->fetch();
                if ($row && $row['remember_token'] && hash_equals($row['remember_token'], hash('sha256', $token))) {
                    $_SESSION['user'] = ['id'=>$row['id'],'tenant_id'=>$row['tenant_id'],'name'=>$row['name'],'email'=>$row['email'],'role'=>$row['role']];
                    return $_SESSION['user'];
                }
            }
        }
        redirect($loginUrl ?? '/auth/login.php');
    }
    return $u;
}
function require_role(array $roles, string $loginUrl = null): array {
    $u = require_login($loginUrl);
    if (!in_array($u['role'], $roles, true)) { http_response_code(403); exit('Access denied for your role.'); }
    return $u;
}
function require_tenant(): int {
    $t = tenant_id();
    if ($t === null) { http_response_code(403); exit('Tenant context required.'); }
    return $t;
}
function check_tenant_active(PDO $pdo): void {
    $t = tenant_id(); if ($t === null) return;
    $s = $pdo->prepare('SELECT status FROM tenants WHERE id=?'); $s->execute([$t]);
    if ($s->fetchColumn() === 'suspended') { session_destroy(); redirect('/auth/login.php?error=suspended'); }
}
