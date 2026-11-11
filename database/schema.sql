-- ======================================================================
--  Rental Mobil & Motor Maju Jaya: skema database + data DEMO
--
--  Semua data di bawah ini fiktif. Akun demo:
--      admin / Admin@12345
--  GANTI password ini kalau aplikasi dipasang di server yang bisa diakses orang lain.
--
--  Cara pakai: import file ini lewat HeidiSQL / phpMyAdmin, atau
--      mysql -u root < database/schema.sql
--  Kompatibel dengan MySQL 5.7+/8 dan MariaDB 10.4+.
-- ======================================================================

CREATE DATABASE IF NOT EXISTS rental_majujaya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rental_majujaya;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS detail_transaksi, transaksi, barang, member, admin, login_attempts;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admin (
  id       INT NOT NULL AUTO_INCREMENT,
  username VARCHAR(50)  NOT NULL,
  password VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE barang (
  id    INT NOT NULL AUTO_INCREMENT,
  nama  VARCHAR(100) NOT NULL,
  harga BIGINT UNSIGNED NOT NULL DEFAULT 0,          -- harga per hari
  PRIMARY KEY (id),
  UNIQUE KEY uq_barang_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE member (
  id     INT NOT NULL AUTO_INCREMENT,
  nama   VARCHAR(50) NOT NULL,
  diskon DECIMAL(5,2) NOT NULL DEFAULT 0,            -- persen
  PRIMARY KEY (id),
  UNIQUE KEY uq_member_nama (nama),
  CONSTRAINT chk_member_diskon CHECK (diskon >= 0 AND diskon <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transaksi (
  id           INT NOT NULL AUTO_INCREMENT,
  no_transaksi VARCHAR(30)  NOT NULL,
  nama_pembeli VARCHAR(100) NOT NULL,
  member       VARCHAR(50)  NOT NULL,                -- nama member saat transaksi (arsip)
  tanggal      DATE NOT NULL,
  durasi       INT UNSIGNED NOT NULL DEFAULT 1,      -- hari
  subtotal     BIGINT UNSIGNED NOT NULL,
  diskon       BIGINT UNSIGNED NOT NULL DEFAULT 0,
  ppn          BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_bayar  BIGINT UNSIGNED NOT NULL,
  dibuat_pada  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_transaksi_no (no_transaksi),
  KEY idx_transaksi_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE detail_transaksi (
  id           INT NOT NULL AUTO_INCREMENT,
  transaksi_id INT NOT NULL,
  nama_barang  VARCHAR(100) NOT NULL,                -- arsip: nama saat transaksi
  harga        BIGINT UNSIGNED NOT NULL,
  jumlah       INT UNSIGNED NOT NULL,
  total        BIGINT UNSIGNED NOT NULL,             -- harga x jumlah x durasi
  PRIMARY KEY (id),
  KEY idx_detail_transaksi (transaksi_id),
  CONSTRAINT fk_detail_transaksi FOREIGN KEY (transaksi_id) REFERENCES transaksi (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pembatas percobaan login (lihat login.php)
CREATE TABLE login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_address   VARCHAR(45) NOT NULL,
  attempted_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ip_waktu (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------- DATA DEMO -------------------------------

INSERT INTO admin (username, password) VALUES
 ('admin', '$2y$10$buYCWXj6yV1oKAuYgKoK5.sD3IIogWu0UBtObyk.Fgy.9xuQC12Oy');

INSERT INTO barang (nama, harga) VALUES
 ('Toyota Avanza', 350000),
 ('Honda Brio', 300000),
 ('Toyota Innova Reborn', 450000),
 ('Mitsubishi Xpander', 400000),
 ('Honda Vario 150', 100000),
 ('Yamaha NMAX', 150000),
 ('Jasa Sopir', 150000),
 ('Helm Tambahan', 15000),
 ('Jas Hujan', 10000);

INSERT INTO member (nama, diskon) VALUES
 ('Non-Member', 0),
 ('Silver', 5),
 ('Gold', 10),
 ('Platinum', 15);

INSERT INTO transaksi (id, no_transaksi, nama_pembeli, member, tanggal, durasi, subtotal, diskon, ppn, total_bayar, dibuat_pada) VALUES
 (1, 'TRX-261005-00001', 'Budi Santoso', 'Gold', '2026-10-05', 3, 1500000, 150000, 148500, 1498500, '2026-10-05 09:00:00'),
 (2, 'TRX-261008-00002', 'Siti Rahma', 'Silver', '2026-10-08', 2, 460000, 23000, 48070, 485070, '2026-10-08 09:00:00'),
 (3, 'TRX-261012-00003', 'Andi Pratama', 'Non-Member', '2026-10-12', 1, 160000, 0, 17600, 177600, '2026-10-12 09:00:00'),
 (4, 'TRX-261015-00004', 'Dewi Lestari', 'Platinum', '2026-10-15', 5, 3000000, 450000, 280500, 2830500, '2026-10-15 09:00:00'),
 (5, 'TRX-261020-00005', 'Rizky Maulana', 'Gold', '2026-10-20', 2, 800000, 80000, 79200, 799200, '2026-10-20 09:00:00'),
 (6, 'TRX-261024-00006', 'Putri Ayu', 'Non-Member', '2026-10-24', 4, 1260000, 0, 138600, 1398600, '2026-10-24 09:00:00');

INSERT INTO detail_transaksi (transaksi_id, nama_barang, harga, jumlah, total) VALUES
 (1, 'Toyota Avanza', 350000, 1, 1050000),
 (1, 'Jasa Sopir', 150000, 1, 450000),
 (2, 'Honda Vario 150', 100000, 2, 400000),
 (2, 'Helm Tambahan', 15000, 2, 60000),
 (3, 'Yamaha NMAX', 150000, 1, 150000),
 (3, 'Jas Hujan', 10000, 1, 10000),
 (4, 'Toyota Innova Reborn', 450000, 1, 2250000),
 (4, 'Jasa Sopir', 150000, 1, 750000),
 (5, 'Mitsubishi Xpander', 400000, 1, 800000),
 (6, 'Honda Brio', 300000, 1, 1200000),
 (6, 'Helm Tambahan', 15000, 1, 60000);
