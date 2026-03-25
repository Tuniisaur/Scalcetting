<?php
// Shared Session Configuration

$sessionDir = __DIR__ . '/sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}
session_save_path($sessionDir);

$lifetime = 2592000;
ini_set('session.gc_maxlifetime', $lifetime);
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), $_COOKIE[session_name()], time() + $lifetime, "/", "", false, true);
}
?>
