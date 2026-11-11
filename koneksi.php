<?php
/**
 * Bootstrap: dimuat paling awal oleh setiap halaman.
 * Memuat konfigurasi, mengatur zona waktu dan tampilan error, memulai sesi
 * dengan aman, lalu membuka koneksi database ($conn).
 */
if (!is_file(__DIR__ . '/config.php')) {
    http_response_code(500);
    exit('Konfigurasi belum ada. Salin config.example.php menjadi config.php lalu sesuaikan.');
}
require_once __DIR__ . '/config.php';

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

// Sesi: cookie HttpOnly + SameSite, dan ID sesi yang tidak diterima dari luar
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    session_name('RENTALSID');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Error database dijadikan exception (dan dicatat), bukan peringatan yang terlewat
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('[Rental Maju Jaya] koneksi database: ' . $e->getMessage());
    http_response_code(500);
    exit(APP_DEBUG
        ? 'Koneksi database gagal: ' . $e->getMessage()
        : 'Koneksi database gagal. Periksa config.php.');
}
