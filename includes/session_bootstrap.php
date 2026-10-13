<?php
/**
 * Fullbright College - Student Management System
 * Session bootstrap (used as PHP's auto_prepend_file in containers).
 *
 * When SESSION_DRIVER=database, sessions are stored in MySQL so they survive
 * across instances and redeploys. If the database is unreachable, or the
 * driver is not set, the default file-based sessions are used unchanged.
 *
 * This file runs before every request, so it must never emit output.
 */

if (PHP_SAPI === 'cli') {
    return;
}

if (strtolower((string) getenv('SESSION_DRIVER')) !== 'database') {
    return;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session_handler.php';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    session_set_save_handler(new DatabaseSessionHandler($pdo), true);
} catch (Throwable $e) {
    return; // Database unavailable: keep default file-based sessions.
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $https,
]);
