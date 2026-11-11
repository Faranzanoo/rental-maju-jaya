<?php
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/fungsi.php';

$barang  = $conn->query("SELECT * FROM barang ORDER BY nama")->fetch_all(MYSQLI_ASSOC);
$members = $conn->query("SELECT * FROM member ORDER BY diskon")->fetch_all(MYSQLI_ASSOC);

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hasil = proses_kasir($conn, $_POST);
    if ($hasil['error'] === null) {
        // Simpan id nota, lalu muat ulang halaman supaya tidak terkirim dobel saat refresh
        $_SESSION['nota_id'] = $hasil['trx_id'];
        header("Location: index.php");
        exit;
    }
    $error = $hasil['error'];
}

// Ada nota yang baru diproses? tampilkan sebagai pop up
$nota = null;
if (isset($_SESSION['nota_id'])) {
    $nota = ambil_nota($conn, (int)$_SESSION['nota_id']);
    unset($_SESSION['nota_id']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rental Mobil & Motor Maju Jaya</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🚘 RENTAL MOBIL & MOTOR MAJU JAYA</h1>
        <p>Jl. Anggrek No. 45, Bandung | Telp: (022) 555-1212</p>
        <?php if (isset($_SESSION['admin'])): ?>
            <a class="pill" href="admin.php">⚙️ Dashboard Admin</a>
        <?php else: ?>
            <a class="pill" href="login.php">🔐 Login Admin</a>
        <?php endif; ?>
    </div>

    <div class="content">
        <h2>🧾 Nota Pembelian</h2>

        <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <table class="form-table">
                <tr>
                    <td>No. Transaksi</td>
                    <td><input type="text" value="Dibuat otomatis saat nota diproses" readonly></td>
                </tr>
                <tr>
                    <td>Nama Pembeli</td>
                    <td><input type="text" name="nama_pembeli" maxlength="100" value="<?= e($_POST['nama_pembeli'] ?? '') ?>" required></td>
                </tr>
                <tr>
                    <td>Jenis Member</td>
                    <td>
                        <select name="member" id="member" onchange="hitung()">
                            <?php foreach ($members as $m): ?>
                                <option value="<?= e($m['nama']) ?>" data-diskon="<?= e($m['diskon']) ?>"
                                    <?= (($_POST['member'] ?? '') === $m['nama']) ? 'selected' : '' ?>><?= e($m['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Tanggal</td>
                    <td><input type="date" name="tanggal" value="<?= e(is_string($_POST['tanggal'] ?? null) ? $_POST['tanggal'] : date('Y-m-d')) ?>" required></td>
                </tr>
                <tr>
                    <td>Durasi Sewa (hari)</td>
                    <td><input type="number" name="durasi" id="durasi" value="<?= (int)($_POST['durasi'] ?? 1) ?>" min="1" max="<?= MAKS_DURASI ?>" oninput="hitung()"></td>
                </tr>
            </table>

            <h3>Daftar Barang / Layanan</h3>
            <table class="item-table">
                <thead><tr><th>Barang / Layanan</th><th style="width:25%">Jumlah</th></tr></thead>
                <tbody id="baris">
                <?php for ($i = 0; $i < 5; $i++): ?>
                <tr>
                    <td>
                        <select name="barang[]" onchange="hitung()">
                            <option value="">-- Pilih Barang / Layanan --</option>
                            <?php foreach ($barang as $b): ?>
                                <option value="<?= (int)$b['id'] ?>" data-harga="<?= (int)$b['harga'] ?>"><?= e($b['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" name="jumlah[]" value="0" min="0" max="<?= MAKS_JUMLAH ?>" oninput="hitung()"></td>
                </tr>
                <?php endfor; ?>
                </tbody>
            </table>
            <div style="margin-top:10px"><button type="button" class="btn-abu btn-kecil" onclick="tambahBaris()">+ Tambah Barang</button></div>

            <!-- Total otomatis (hanya tampilan; angka yang disimpan dihitung ulang di server) -->
            <div class="live">
                <div><span>Subtotal</span><span id="l-sub">Rp 0</span></div>
                <div><span>Diskon</span><span id="l-diskon">Rp 0</span></div>
                <div><span>PPN (11%)</span><span id="l-ppn">Rp 0</span></div>
                <div class="tot"><span>Total Bayar</span><span id="l-total">Rp 0</span></div>
            </div>

            <div class="btn-wrap"><button type="submit">💰 Proses Nota</button></div>
        </form>
    </div>
</div>

<?php if ($nota) tampil_modal_nota($nota); ?>

<script>
const MAKS_BARIS = <?= (int)MAKS_BARIS ?>;

function rp(n) { return 'Rp ' + n.toLocaleString('id-ID'); }

function hitung() {
    let sub = 0;
    document.querySelectorAll('#baris tr').forEach(tr => {
        const harga  = +tr.querySelector('select').selectedOptions[0].dataset.harga || 0;
        const jumlah = +tr.querySelector('input').value || 0;
        sub += harga * jumlah;
    });
    sub *= Math.max(1, +document.getElementById('durasi').value || 1);

    const persen = +document.getElementById('member').selectedOptions[0].dataset.diskon || 0;
    const diskon = Math.round(sub * persen / 100);
    const ppn    = Math.round((sub - diskon) * 0.11);

    document.getElementById('l-sub').textContent    = rp(sub);
    document.getElementById('l-diskon').textContent = rp(diskon);
    document.getElementById('l-ppn').textContent    = rp(ppn);
    document.getElementById('l-total').textContent  = rp(sub - diskon + ppn);
}

function tambahBaris() {
    const tbody = document.getElementById('baris');
    if (tbody.rows.length >= MAKS_BARIS) { return; }
    const baris = tbody.rows[0].cloneNode(true);
    baris.querySelector('select').value = '';
    baris.querySelector('input').value = 0;
    tbody.appendChild(baris);
}
hitung();
</script>
</body>
</html>
