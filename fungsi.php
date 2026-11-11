<?php
/**
 * Fungsi bantu: format, keamanan (CSRF), validasi, perhitungan nota,
 * proses kasir, dan tampilan nota.
 */

// Batas wajar supaya input tidak membuat angka meluap
const MAKS_DURASI = 365;        // hari
const MAKS_JUMLAH = 100;        // unit per baris
const MAKS_BARIS  = 30;         // baris barang per nota
const MAKS_HARGA  = 100000000;  // Rp per hari

function rp($n)
{
    return "Rp " . number_format((float)$n, 0, ',', '.');
}

/** Escape output HTML. Nilai yang bukan teks/angka (mis. array dari input yang dimanipulasi) menjadi string kosong. */
function e($s)
{
    if (is_array($s) || is_object($s)) {
        return '';
    }
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ------------------------------ CSRF ------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valid($token): bool
{
    return is_string($token)
        && $token !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/* ---------------------------- Validasi ---------------------------- */

/** Teks pendek yang dirapikan; null jika kosong, bukan teks, atau terlalu panjang. */
function teks_pendek($v, int $maks): ?string
{
    if (!is_string($v)) {
        return null;
    }
    $t = preg_replace('/\s+/u', ' ', $v);
    if ($t === null) {
        return null;           // bukan UTF-8 yang valid
    }
    $t = trim($t);
    return ($t !== '' && mb_strlen($t) <= $maks) ? $t : null;
}

/** Bilangan bulat dalam rentang; null jika tidak valid. */
function angka_bulat($v, int $min, int $maks): ?int
{
    $r = filter_var(is_string($v) ? trim($v) : $v, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $maks],
    ]);
    return $r === false ? null : $r;
}

/** Persentase 0-100 dengan maksimal 2 desimal; null jika tidak valid. */
function persen_valid($v): ?float
{
    if (!is_string($v) && !is_int($v) && !is_float($v)) {
        return null;
    }
    $t = trim((string)$v);
    if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $t)) {
        return null;
    }
    $f = (float)$t;
    return ($f >= 0 && $f <= 100) ? $f : null;
}

/** Tanggal yang benar-benar ada, format Y-m-d. */
function tanggal_valid($t): bool
{
    if (!is_string($t)) {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $t);
    return $d !== false && $d->format('Y-m-d') === $t;
}

/* ---------------------------- Perhitungan ---------------------------- */

/**
 * Hitung nota dari subtotal (harga x jumlah x lama sewa).
 * Diskon member dihitung dari subtotal, lalu PPN 11% dari (subtotal - diskon).
 * Rumus yang sama dipakai di JavaScript pada halaman kasir (hanya untuk tampilan);
 * angka yang disimpan selalu dihitung ulang di sini.
 */
function hitung_nota(int $subtotal, float $persenDiskon): array
{
    $diskon = (int) round($subtotal * $persenDiskon / 100);
    $ppn    = (int) round(($subtotal - $diskon) * 0.11);
    return [
        'subtotal'    => $subtotal,
        'diskon'      => $diskon,
        'ppn'         => $ppn,
        'total_bayar' => $subtotal - $diskon + $ppn,
    ];
}

/* ------------------------------ Nota ------------------------------ */

// Ambil 1 nota lengkap (beserta item-itemnya) dari database
function ambil_nota($conn, $id)
{
    $stmt = $conn->prepare("SELECT * FROM transaksi WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $n = $stmt->get_result()->fetch_assoc();
    if (!$n) return null;

    $stmt = $conn->prepare("SELECT * FROM detail_transaksi WHERE transaksi_id=? ORDER BY id");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $n['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return $n;
}

// Tampilkan nota dalam bentuk pop up (modal)
function tampil_modal_nota($n) { ?>
<div class="modal show" id="modalNota" onclick="if(event.target===this) tutupModal()">
  <div class="modal-box">
    <div class="nota" id="areaNota">
        <div class="judul">Nota Pembelian</div>
        <div class="sub">Rental Mobil & Motor Maju Jaya</div>

        <div class="info">
            <b>No:</b> <?= e($n['no_transaksi']) ?><br>
            <b>Pembeli:</b> <?= e($n['nama_pembeli']) ?><br>
            <b>Member:</b> <?= e($n['member']) ?><br>
            <b>Tanggal:</b> <?= e(date('d-m-Y', strtotime($n['tanggal']))) ?><br>
            <b>Durasi:</b> <?= (int)$n['durasi'] ?> hari
        </div>

        <table class="detail">
            <tr><th>No</th><th>Barang / Layanan</th><th>Harga</th><th>Jumlah</th><th>Total</th></tr>
            <?php foreach ($n['items'] as $i => $it): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($it['nama_barang']) ?></td>
                <td class="right"><?= rp($it['harga']) ?></td>
                <td class="right"><?= (int)$it['jumlah'] ?></td>
                <td class="right"><?= rp($it['total']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <table class="ringkasan">
            <tr><td>Subtotal</td><td><?= rp($n['subtotal']) ?></td></tr>
            <tr><td>Diskon</td><td><?= rp($n['diskon']) ?></td></tr>
            <tr><td>PPN (11%)</td><td><?= rp($n['ppn']) ?></td></tr>
            <tr><td>Total Bayar</td><td><?= rp($n['total_bayar']) ?></td></tr>
        </table>

        <div class="terima-kasih">
            Terima kasih telah menggunakan layanan kami 🚙<br>
            Semoga perjalanan Anda menyenangkan!
        </div>
    </div>
    <div class="modal-btn">
        <button onclick="window.print()">🖨️ Cetak</button>
        <button class="btn-abu" onclick="tutupModal()">✖ Tutup</button>
    </div>
  </div>
</div>
<script>
function tutupModal() { document.getElementById('modalNota').classList.remove('show'); }
</script>
<?php }

/* ------------------------- Proses nota kasir ------------------------- */

function _galat(string $pesan): array
{
    return ['error' => $pesan, 'trx_id' => null];
}

/**
 * Validasi isian kasir, hitung ulang semua angka di server, lalu simpan nota dan
 * itemnya dalam satu transaksi database (tersimpan utuh, atau tidak sama sekali).
 *
 * Browser hanya mengirim APA yang disewa (id barang dan jumlah), nama pembeli, member,
 * tanggal, dan durasi. Harga, subtotal, diskon, PPN, total, dan nomor transaksi tidak
 * pernah dipercaya dari browser.
 *
 * @return array{error: ?string, trx_id: ?int}
 */
function proses_kasir(mysqli $conn, array $post): array
{
    if (!csrf_valid($post['csrf_token'] ?? '')) {
        return _galat('Sesi form kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }

    $pembeli    = teks_pendek($post['nama_pembeli'] ?? null, 100);
    $namaMember = teks_pendek($post['member'] ?? null, 50);
    $tanggal    = $post['tanggal'] ?? '';
    $durasi     = angka_bulat($post['durasi'] ?? null, 1, MAKS_DURASI);
    $ids        = $post['barang'] ?? [];
    $jumlahPost = $post['jumlah'] ?? [];

    if ($pembeli === null) {
        return _galat('Nama pembeli harus diisi (maksimal 100 karakter)!');
    }
    if (!tanggal_valid($tanggal)) {
        return _galat('Tanggal tidak valid!');
    }
    if ($durasi === null) {
        return _galat('Durasi sewa harus 1 sampai ' . MAKS_DURASI . ' hari!');
    }
    if ($namaMember === null) {
        return _galat('Jenis member tidak valid!');
    }
    if (!is_array($ids) || !is_array($jumlahPost) || count($ids) > MAKS_BARIS) {
        return _galat('Data barang tidak valid!');
    }

    // Kumpulkan barang yang dipilih (barang yang sama digabung)
    $dipilih = [];
    foreach ($ids as $i => $idBarang) {
        if ($idBarang === '' || $idBarang === null) {
            continue;
        }
        if (!is_string($idBarang) || !ctype_digit($idBarang)) {
            return _galat('Ada barang yang tidak valid!');
        }
        $jumlah = angka_bulat($jumlahPost[$i] ?? 0, 0, MAKS_JUMLAH);
        if ($jumlah === null) {
            return _galat('Jumlah tiap barang harus antara 0 sampai ' . MAKS_JUMLAH . '!');
        }
        if ($jumlah === 0) {
            continue;
        }
        $id = (int)$idBarang;
        $dipilih[$id] = ($dipilih[$id] ?? 0) + $jumlah;
        if ($dipilih[$id] > MAKS_JUMLAH) {
            return _galat('Jumlah tiap barang maksimal ' . MAKS_JUMLAH . '!');
        }
    }
    if (count($dipilih) === 0) {
        return _galat('Pilih minimal satu barang / layanan dengan jumlah lebih dari 0!');
    }

    try {
        $stmt = $conn->prepare("SELECT nama, diskon FROM member WHERE nama=?");
        $stmt->bind_param("s", $namaMember);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
        if (!$member) {
            return _galat('Jenis member tidak ditemukan!');
        }

        // Harga SELALU diambil dari database
        $items = [];
        $subtotal = 0;
        $stmt = $conn->prepare("SELECT nama, harga FROM barang WHERE id=?");
        foreach ($dipilih as $id => $jumlah) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $b = $stmt->get_result()->fetch_assoc();
            if (!$b) {
                return _galat('Ada barang yang sudah tidak tersedia. Muat ulang halaman kasir.');
            }
            $total = (int)$b['harga'] * $jumlah * $durasi;     // harga x jumlah x lama sewa
            $items[] = ['nama' => $b['nama'], 'harga' => (int)$b['harga'], 'jumlah' => $jumlah, 'total' => $total];
            $subtotal += $total;
        }

        $hasil = hitung_nota($subtotal, (float)$member['diskon']);

        $conn->begin_transaction();

        // Nomor transaksi dibuat dari ID baris (unik, tanpa angka acak dan tanpa perulangan)
        $sementara = 'TMP-' . bin2hex(random_bytes(8));
        $stmt = $conn->prepare(
            "INSERT INTO transaksi (no_transaksi, nama_pembeli, member, tanggal, durasi, subtotal, diskon, ppn, total_bayar)
             VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param(
            "ssssiiiii",
            $sementara, $pembeli, $member['nama'], $tanggal, $durasi,
            $hasil['subtotal'], $hasil['diskon'], $hasil['ppn'], $hasil['total_bayar']
        );
        $stmt->execute();
        $trxId = (int)$conn->insert_id;

        $noTransaksi = 'TRX-' . date('ymd') . '-' . str_pad((string)$trxId, 5, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("UPDATE transaksi SET no_transaksi=? WHERE id=?");
        $stmt->bind_param("si", $noTransaksi, $trxId);
        $stmt->execute();

        $stmt = $conn->prepare(
            "INSERT INTO detail_transaksi (transaksi_id, nama_barang, harga, jumlah, total) VALUES (?,?,?,?,?)"
        );
        foreach ($items as $it) {
            $stmt->bind_param("isiii", $trxId, $it['nama'], $it['harga'], $it['jumlah'], $it['total']);
            $stmt->execute();
        }

        $conn->commit();
        return ['error' => null, 'trx_id' => $trxId];
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
            // tidak ada transaksi yang perlu dibatalkan
        }
        error_log('[Rental Maju Jaya] proses_kasir: ' . $e->getMessage());
        return _galat('Terjadi kesalahan saat menyimpan nota. Silakan coba lagi.');
    }
}
