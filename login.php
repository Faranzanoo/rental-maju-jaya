<?php
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/fungsi.php';

if (isset($_SESSION['admin'])) { header("Location: admin.php"); exit; }

// Pembatasan percobaan login: maks. LOGIN_MAX_GAGAL kali per alamat IP dalam LOGIN_WINDOW_MENIT menit
const LOGIN_MAX_GAGAL    = 5;
const LOGIN_WINDOW_MENIT = 15;
// Hash palsu: dipakai agar waktu respons sama baik user ada maupun tidak
const LOGIN_HASH_PALSU = '$2y$10$It65yEgwD94RgNcIkOJq0.oaBIZ0Px4Kz1g7ERE9Rsz7s.JGSSo1i';

function login_terlalu_banyak_gagal(mysqli $conn, string $ip): bool
{
    try {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS n FROM login_attempts
             WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL " . LOGIN_WINDOW_MENIT . " MINUTE)"
        );
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_assoc()['n'] >= LOGIN_MAX_GAGAL;
    } catch (mysqli_sql_exception $e) {
        error_log('[Rental Maju Jaya] login_attempts: ' . $e->getMessage());
        return false;   // tabel belum ada: jangan blokir login
    }
}

function login_catat_gagal(mysqli $conn, string $ip): void
{
    try {
        $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $conn->query("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
    } catch (mysqli_sql_exception $e) {
        error_log('[Rental Maju Jaya] login_attempts: ' . $e->getMessage());
    }
}

$ip    = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!csrf_valid($_POST['csrf_token'] ?? '')) {
        $error = "Sesi form kedaluwarsa. Muat ulang halaman lalu coba lagi.";
    } elseif ($username === '' || $password === '') {
        $error = "Username dan password tidak boleh kosong!";
    } elseif (login_terlalu_banyak_gagal($conn, $ip)) {
        $error = "Terlalu banyak percobaan login. Coba lagi dalam " . LOGIN_WINDOW_MENIT . " menit.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM admin WHERE username=?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $a = $stmt->get_result()->fetch_assoc();

        $hash = $a ? $a['password'] : LOGIN_HASH_PALSU;
        if (password_verify($password, $hash) && $a) {
            // Cegah session fixation: buat ID sesi baru setelah login
            session_regenerate_id(true);
            $_SESSION['admin'] = $a['username'];

            try {
                $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
                $stmt->bind_param("s", $ip);
                $stmt->execute();
            } catch (mysqli_sql_exception $e) {
                error_log('[Rental Maju Jaya] login_attempts: ' . $e->getMessage());
            }
            header("Location: admin.php");
            exit;
        }
        login_catat_gagal($conn, $ip);
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login Admin</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-box">
    <div class="header"><h1>🔐 Login Admin</h1><p>Rental Mobil & Motor Maju Jaya</p></div>
    <div class="content">
        <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label>Username</label>
            <input type="text" name="username" required autofocus>
            <label>Password</label>
            <input type="password" name="password" required>
            <div class="btn-wrap"><button type="submit">Masuk</button></div>
            <div class="btn-wrap" style="margin-top:10px"><a href="index.php" style="font-size:13px;color:#7c52c8">← Kembali ke kasir</a></div>
        </form>
    </div>
</div>
</body>
</html>
