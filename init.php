<?php
$cfg = require __DIR__ . '/config.php';

date_default_timezone_set($cfg['app']['timezone'] ?? 'UTC');
ini_set('display_errors','0');
error_reporting(E_ALL);
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_name($cfg['security']['session_name']);
session_set_cookie_params([
    'lifetime' => $cfg['security']['session_cookie_lifetime'],
    'path'     => $cfg['app']['base_url'] ?: '/',
    'httponly' => true,
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();
// if (empty($_SESSION['csrf_token'])) {
//     $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// }

require __DIR__ . '/functions.php';

// Гарантируем каталог data/
if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0775, true);
}

// Подключаем SQLite
$pdo = new PDO('sqlite:' . $cfg['sqlite']['path'], null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

ensure_sqlite_schema($pdo);