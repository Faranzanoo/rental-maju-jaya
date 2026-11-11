<?php
require_once __DIR__ . '/koneksi.php';

// Hapus semua data sesi dan cookie-nya
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $c = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $c['path'],
        'domain'   => $c['domain'],
        'secure'   => $c['secure'],
        'httponly' => $c['httponly'],
        'samesite' => $c['samesite'] ?: 'Lax',
    ]);
}
session_destroy();

header("Location: index.php");
exit;
