<?php
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/fungsi.php';

// Wajib login
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit; }

const HALAMAN_VALID = ['riwayat', 'barang', 'member'];
const BARIS_RIWAYAT = 200;   // jumlah transaksi yang ditampilkan sekaligus

$hal = $_GET['hal'] ?? 'riwayat';
if (!is_string($hal) || !in_array($hal, HALAMAN_VALID, true)) {
    $hal = 'riwayat';
}

/** Apakah sebuah baris dengan id tersebut ada? (nama tabel dibatasi daftar tetap) */
function baris_ada(mysqli $conn, string $tabel, int $id): bool
{
    if (!in_array($tabel, ['barang', 'member', 'transaksi'], true)) {
        return false;
    }
    $stmt = $conn->prepare("SELECT 1 FROM `$tabel` WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_row();
}

/**
 * Proses satu aksi admin. Semua input divalidasi di server.
 * @return array{ok: bool, pesan: string}
 */
function proses_admin(mysqli $conn, array $post): array
{
    $aksi = $post['aksi'] ?? '';
    $id   = angka_bulat($post['id'] ?? 0, 0, PHP_INT_MAX) ?? 0;

    try {
        switch ($aksi) {
            case 'hapus_transaksi':
            case 'hapus_barang':
            case 'hapus_member':
                $tabel = ['hapus_transaksi' => 'transaksi', 'hapus_barang' => 'barang', 'hapus_member' => 'member'][$aksi];
                if ($id < 1 || !baris_ada($conn, $tabel, $id)) {
                    return ['ok' => false, 'pesan' => 'Data tidak ditemukan.'];
                }
                $stmt = $conn->prepare("DELETE FROM `$tabel` WHERE id=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                return ['ok' => true, 'pesan' => ['transaksi' => 'Transaksi', 'barang' => 'Barang', 'member' => 'Member'][$tabel] . ' berhasil dihapus.'];

            case 'tambah_barang':
            case 'edit_barang':
                $nama  = teks_pendek($post['nama'] ?? null, 100);
                $harga = angka_bulat($post['harga'] ?? null, 0, MAKS_HARGA);
                if ($nama === null) {
                    return ['ok' => false, 'pesan' => 'Nama barang harus diisi (maksimal 100 karakter).'];
                }
                if ($harga === null) {
                    return ['ok' => false, 'pesan' => 'Harga harus berupa bilangan bulat 0 sampai ' . number_format(MAKS_HARGA, 0, ',', '.') . '.'];
                }
                if ($aksi === 'tambah_barang') {
                    $stmt = $conn->prepare("INSERT INTO barang (nama, harga) VALUES (?,?)");
                    $stmt->bind_param("si", $nama, $harga);
                    $stmt->execute();
                    return ['ok' => true, 'pesan' => 'Barang berhasil ditambahkan.'];
                }
                if ($id < 1 || !baris_ada($conn, 'barang', $id)) {
                    return ['ok' => false, 'pesan' => 'Data tidak ditemukan.'];
                }
                $stmt = $conn->prepare("UPDATE barang SET nama=?, harga=? WHERE id=?");
                $stmt->bind_param("sii", $nama, $harga, $id);
                $stmt->execute();
                return ['ok' => true, 'pesan' => 'Barang berhasil diubah.'];

            case 'tambah_member':
            case 'edit_member':
                $nama   = teks_pendek($post['nama'] ?? null, 50);
                $diskon = persen_valid($post['diskon'] ?? null);
                if ($nama === null) {
                    return ['ok' => false, 'pesan' => 'Nama member harus diisi (maksimal 50 karakter).'];
                }
                if ($diskon === null) {
                    return ['ok' => false, 'pesan' => 'Diskon harus 0 sampai 100 (maksimal 2 desimal).'];
                }
                if ($aksi === 'tambah_member') {
                    $stmt = $conn->prepare("INSERT INTO member (nama, diskon) VALUES (?,?)");
                    $stmt->bind_param("sd", $nama, $diskon);
                    $stmt->execute();
                    return ['ok' => true, 'pesan' => 'Member berhasil ditambahkan.'];
                }
                if ($id < 1 || !baris_ada($conn, 'member', $id)) {
                    return ['ok' => false, 'pesan' => 'Data tidak ditemukan.'];
                }
                $stmt = $conn->prepare("UPDATE member SET nama=?, diskon=? WHERE id=?");
                $stmt->bind_param("sdi", $nama, $diskon, $id);
                $stmt->execute();
                return ['ok' => true, 'pesan' => 'Member berhasil diubah.'];

            default:
                return ['ok' => false, 'pesan' => 'Aksi tidak dikenal.'];
        }
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1062) {
            return ['ok' => false, 'pesan' => 'Nama tersebut sudah dipakai. Gunakan nama lain.'];
        }
        error_log('[Rental Maju Jaya] proses_admin: ' . $e->getMessage());
        return ['ok' => false, 'pesan' => 'Terjadi kesalahan saat menyimpan data.'];
    }
}

/* ================= PROSES AKSI (tambah / edit / hapus) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? '')) {
        $_SESSION['galat'] = 'Sesi form kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {
        $hasil = proses_admin($conn, $_POST);
        $_SESSION[$hasil['ok'] ? 'pesan' : 'galat'] = $hasil['pesan'];
    }
    header("Location: admin.php?hal=" . urlencode($hal));
    exit;
}

$pesan = $_SESSION['pesan'] ?? '';
$galat = $_SESSION['galat'] ?? '';
unset($_SESSION['pesan'], $_SESSION['galat']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Admin</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container lebar">
    <div class="header">
        <h1>⚙️ DASHBOARD ADMIN</h1>
        <p>Login sebagai: <?= e($_SESSION['admin']) ?></p>
    </div>

    <div class="content">
        <div class="menu">
            <a href="admin.php?hal=riwayat" class="<?= $hal == 'riwayat' ? 'aktif' : '' ?>">📋 Riwayat Transaksi</a>
            <a href="admin.php?hal=barang"  class="<?= $hal == 'barang'  ? 'aktif' : '' ?>">🚗 Kelola Barang</a>
            <a href="admin.php?hal=member"  class="<?= $hal == 'member'  ? 'aktif' : '' ?>">👤 Kelola Member</a>
            <a href="index.php">🧾 Kasir</a>
            <a href="logout.php">🚪 Logout</a>
        </div>

        <?php if ($pesan): ?><div class="sukses"><?= e($pesan) ?></div><?php endif; ?>
        <?php if ($galat): ?><div class="error"><?= e($galat) ?></div><?php endif; ?>

<?php /* ================= RIWAYAT TRANSAKSI ================= */ if ($hal == 'riwayat'):
    $q      = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
    $dari   = tanggal_valid($_GET['dari'] ?? null) ? $_GET['dari'] : '';
    $sampai = tanggal_valid($_GET['sampai'] ?? null) ? $_GET['sampai'] : '';

    $where = []; $tipe = ''; $param = [];
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = "(nama_pembeli LIKE ? OR no_transaksi LIKE ?)";
        $param[] = $like; $param[] = $like; $tipe .= 'ss';
    }
    if ($dari !== '')   { $where[] = "tanggal >= ?"; $param[] = $dari;   $tipe .= 's'; }
    if ($sampai !== '') { $where[] = "tanggal <= ?"; $param[] = $sampai; $tipe .= 's'; }
    $klausa = $where ? " WHERE " . implode(" AND ", $where) : "";

    // Jumlah dan total pendapatan dihitung dari SEMUA transaksi yang cocok, bukan hanya yang tampil
    $stmt = $conn->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(total_bayar), 0) AS total FROM transaksi" . $klausa);
    if ($param) $stmt->bind_param($tipe, ...$param);
    $stmt->execute();
    $ringkas    = $stmt->get_result()->fetch_assoc();
    $jumlahAll  = (int)$ringkas['n'];
    $pendapatan = (int)$ringkas['total'];

    $stmt = $conn->prepare("SELECT * FROM transaksi" . $klausa . " ORDER BY id DESC LIMIT " . BARIS_RIWAYAT);
    if ($param) $stmt->bind_param($tipe, ...$param);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
        <h2>📋 Riwayat Transaksi</h2>

        <form method="get" class="filter">
            <input type="hidden" name="hal" value="riwayat">
            <input type="text" name="q" placeholder="Cari nama / no transaksi" value="<?= e($q) ?>">
            <input type="date" name="dari" value="<?= e($dari) ?>" title="Dari tanggal">
            <input type="date" name="sampai" value="<?= e($sampai) ?>" title="Sampai tanggal">
            <button type="submit">🔍 Cari</button>
            <a href="admin.php?hal=riwayat" class="btn btn-abu">Reset</a>
        </form>

        <div class="scroll">
        <table class="tabel">
            <tr><th>No. Transaksi</th><th>Pembeli</th><th>Member</th><th>Tanggal</th><th>Durasi</th><th>Total Bayar</th><th>Aksi</th></tr>
            <?php foreach ($data as $d): ?>
            <tr>
                <td><?= e($d['no_transaksi']) ?></td>
                <td><?= e($d['nama_pembeli']) ?></td>
                <td><?= e($d['member']) ?></td>
                <td><?= e(date('d-m-Y', strtotime($d['tanggal']))) ?></td>
                <td><?= (int)$d['durasi'] ?> hari</td>
                <td class="right"><?= rp($d['total_bayar']) ?></td>
                <td style="white-space:nowrap">
                    <a class="btn btn-kecil" href="admin.php?hal=riwayat&lihat=<?= (int)$d['id'] ?>">Lihat Nota</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('Hapus transaksi ini?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="hapus_transaksi">
                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                        <button class="btn-merah btn-kecil">Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$data): ?><tr><td colspan="7" style="text-align:center">Belum ada transaksi.</td></tr><?php endif; ?>
        </table>
        </div>
        <p><b><?= $jumlahAll ?></b> transaksi | Total pendapatan: <b><?= rp($pendapatan) ?></b>
        <?php if ($jumlahAll > BARIS_RIWAYAT): ?><br><small>Menampilkan <?= BARIS_RIWAYAT ?> transaksi terbaru. Persempit dengan pencarian atau rentang tanggal.</small><?php endif; ?></p>

        <?php
        if (isset($_GET['lihat'])) {
            $idLihat = angka_bulat($_GET['lihat'], 1, PHP_INT_MAX);
            $nota = $idLihat ? ambil_nota($conn, $idLihat) : null;
            if ($nota) tampil_modal_nota($nota);
        }
        ?>

<?php /* ================= KELOLA BARANG ================= */ elseif ($hal == 'barang'):
    $data = $conn->query("SELECT * FROM barang ORDER BY id")->fetch_all(MYSQLI_ASSOC);
?>
        <h2>🚗 Kelola Barang / Layanan</h2>

        <form method="post" class="tambah">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah_barang">
            <input type="text" name="nama" placeholder="Nama barang / layanan" maxlength="100" required>
            <input type="number" name="harga" placeholder="Harga per hari" min="0" max="<?= MAKS_HARGA ?>" required>
            <button type="submit">+ Tambah</button>
        </form>

        <div class="scroll">
        <table class="tabel">
            <tr><th>Nama Barang / Layanan</th><th>Harga per Hari (Rp)</th><th style="width:170px">Aksi</th></tr>
            <?php foreach ($data as $d): ?>
            <tr>
                <td>
                    <form id="f<?= (int)$d['id'] ?>" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"></form>
                    <input form="f<?= (int)$d['id'] ?>" type="text" name="nama" value="<?= e($d['nama']) ?>" maxlength="100" required>
                </td>
                <td><input form="f<?= (int)$d['id'] ?>" type="number" name="harga" value="<?= (int)$d['harga'] ?>" min="0" max="<?= MAKS_HARGA ?>" required></td>
                <td style="white-space:nowrap">
                    <button form="f<?= (int)$d['id'] ?>" name="aksi" value="edit_barang" class="btn-kecil">Simpan</button>
                    <button form="f<?= (int)$d['id'] ?>" name="aksi" value="hapus_barang" class="btn-merah btn-kecil" onclick="return confirm('Hapus barang ini?')">Hapus</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>

<?php /* ================= KELOLA MEMBER ================= */ elseif ($hal == 'member'):
    $data = $conn->query("SELECT * FROM member ORDER BY id")->fetch_all(MYSQLI_ASSOC);
?>
        <h2>👤 Kelola Jenis Member</h2>

        <form method="post" class="tambah">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah_member">
            <input type="text" name="nama" placeholder="Nama member" maxlength="50" required>
            <input type="number" name="diskon" placeholder="Diskon (%)" min="0" max="100" step="0.01" required>
            <button type="submit">+ Tambah</button>
        </form>

        <div class="scroll">
        <table class="tabel">
            <tr><th>Nama Member</th><th>Diskon (%)</th><th style="width:170px">Aksi</th></tr>
            <?php foreach ($data as $d): ?>
            <tr>
                <td>
                    <form id="f<?= (int)$d['id'] ?>" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"></form>
                    <input form="f<?= (int)$d['id'] ?>" type="text" name="nama" value="<?= e($d['nama']) ?>" maxlength="50" required>
                </td>
                <td><input form="f<?= (int)$d['id'] ?>" type="number" name="diskon" value="<?= e($d['diskon']) ?>" min="0" max="100" step="0.01" required></td>
                <td style="white-space:nowrap">
                    <button form="f<?= (int)$d['id'] ?>" name="aksi" value="edit_member" class="btn-kecil">Simpan</button>
                    <button form="f<?= (int)$d['id'] ?>" name="aksi" value="hapus_member" class="btn-merah btn-kecil" onclick="return confirm('Hapus member ini?')">Hapus</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
<?php endif; ?>

    </div>
</div>
</body>
</html>
