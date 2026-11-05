<?php
declare(strict_types=1);
define('APP_NAME', 'StockPro SaaS');
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'stock_saas');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');
define('TRIAL_DAYS', 14);
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>isset($_SERVER['HTTPS'])]);
    session_start();
}
date_default_timezone_set('Asia/Karachi');
