<?php
/**
 * Konfigurasi aplikasi.
 *
 * Salin file ini menjadi config.php lalu sesuaikan nilainya.
 * config.php sengaja TIDAK ikut di-commit (lihat .gitignore),
 * jadi password database tidak pernah masuk ke repositori.
 */

// --- Database ---------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'rental_majujaya');
define('DB_USER', 'root');   // bawaan Laragon / XAMPP
define('DB_PASS', '');       // bawaan Laragon / XAMPP (kosong). WAJIB diisi di server publik.

// --- Aplikasi ---------------------------------------------------------
define('APP_TIMEZONE', 'Asia/Jakarta');

// true = tampilkan detail error di layar. HANYA untuk pengembangan lokal.
define('APP_DEBUG', false);
