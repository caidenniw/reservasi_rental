-- =====================================================================
-- 1000 NUSANTARA RENTAL - Dashboard Reservasi
-- Skema database v1.0 (23-09-2026)
-- MySQL 8 / MariaDB - InnoDB / utf8mb4
-- Cara pakai:  mysql -uroot < database/schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS rentalnusantara
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rentalnusantara;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS status_logs, payments, invoice_items, invoices,
                     order_biaya, order_includes, order_items, orders,
                     doc_counters, settings, includes, partners,
                     drivers, units, customers, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- PENGGUNA (login wajib, tanpa peran / satu tingkat akses)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  username    VARCHAR(50)  NOT NULL UNIQUE,
  nama        VARCHAR(100) NOT NULL,
  password    VARCHAR(255) NOT NULL COMMENT 'hash bcrypt',
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  last_login  DATETIME     NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- MASTER
-- ---------------------------------------------------------------------
CREATE TABLE customers (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  tipe          ENUM('perorangan','perusahaan','instansi','RO') NOT NULL DEFAULT 'perorangan',
  nama_pesanan  VARCHAR(150) NOT NULL COMMENT 'nama instansi / perusahaan / orang',
  nama_pic      VARCHAR(100) NULL,
  hp_pic        VARCHAR(30)  NULL,
  email         VARCHAR(100) NULL,
  alamat        VARCHAR(255) NULL,
  sumber        ENUM('wa','telepon','instagram','tiktok','facebook','website','referral','lainnya') NOT NULL DEFAULT 'wa',
  status        ENUM('baru','tetap','RO') NOT NULL DEFAULT 'baru',
  catatan       TEXT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at    DATETIME NULL,
  KEY idx_nama_pesanan (nama_pesanan)
) ENGINE=InnoDB;

CREATE TABLE units (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  kode_unit           VARCHAR(30)  NULL,
  nama_unit           VARCHAR(100) NOT NULL,
  nopol               VARCHAR(20)  NOT NULL,
  merek               VARCHAR(50)  NULL,
  model               VARCHAR(50)  NULL,
  tahun               SMALLINT     NULL,
  jenis               ENUM('MPV','SUV','Hiace','Bus','Sedan','Pickup','Lain') NOT NULL DEFAULT 'MPV',
  transmisi           ENUM('manual','matic') NULL,
  kapasitas           TINYINT      NULL,
  pemilik             ENUM('sendiri','partner') NOT NULL DEFAULT 'sendiri',
  partner_id          INT          NULL,
  harga_modal_default BIGINT       NOT NULL DEFAULT 0,
  harga_jual_default  BIGINT       NOT NULL DEFAULT 0,
  status              ENUM('ready','maintenance','keluar','nonaktif') NOT NULL DEFAULT 'ready',
  catatan             TEXT NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME NULL,
  UNIQUE KEY uq_nopol (nopol),
  KEY idx_unit_nama (nama_unit)
) ENGINE=InnoDB;

CREATE TABLE drivers (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nama        VARCHAR(100) NOT NULL,
  hp          VARCHAR(30)  NULL,
  wilayah     VARCHAR(100) NULL,
  nomor_sim   VARCHAR(50)  NULL,
  bank        VARCHAR(50)  NULL,
  no_rekening VARCHAR(50)  NULL,
  status      ENUM('aktif','izin','sakit','nonaktif') NOT NULL DEFAULT 'aktif',
  catatan     TEXT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at  DATETIME NULL,
  KEY idx_driver_nama (nama)
) ENGINE=InnoDB;

CREATE TABLE partners (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nama        VARCHAR(100) NOT NULL COMMENT 'Support By',
  tipe        ENUM('vendor','perantara','owner_unit') NOT NULL DEFAULT 'vendor',
  hp          VARCHAR(30)  NULL,
  alamat      VARCHAR(255) NULL,
  bank        VARCHAR(50)  NULL,
  no_rekening VARCHAR(50)  NULL,
  catatan     TEXT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at  DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE includes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nama       VARCHAR(100) NOT NULL,
  urutan     TINYINT NOT NULL DEFAULT 0,
  is_default TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE settings (
  `key`   VARCHAR(50) PRIMARY KEY,
  `value` TEXT NULL,
  label   VARCHAR(100) NULL,
  grup    VARCHAR(30) NOT NULL DEFAULT 'umum',
  urutan  TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TRANSAKSI
-- ---------------------------------------------------------------------
CREATE TABLE orders (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  nomor_order        VARCHAR(30) NOT NULL UNIQUE,
  customer_id        INT NULL,
  tipe_pelanggan     ENUM('retail','corporate','RO','RTR') NOT NULL DEFAULT 'retail', -- retail=perorangan, corporate=CO/Corp perusahaan end-user, RO=Repeat Order, RTR=Rent to Rent (biro lain sewa unit kita)
  wilayah_pelayanan  ENUM('dalam_kota','luar_kota') NOT NULL DEFAULT 'dalam_kota',
  kota               VARCHAR(100) NOT NULL,
  tgl_mulai          DATE NOT NULL,
  tgl_finish         DATE NOT NULL,
  jumlah_hari        SMALLINT NOT NULL DEFAULT 1,
  jam                VARCHAR(50)  NULL,
  jam_koordinasi     TINYINT(1) NOT NULL DEFAULT 0,
  standby_point      VARCHAR(150) NULL,
  flight             VARCHAR(50)  NULL,
  tujuan             VARCHAR(200) NULL,
  nama_pesanan       VARCHAR(150) NOT NULL,
  nama_pic           VARCHAR(100) NULL,
  hp_pic             VARCHAR(30)  NULL,
  sumber             ENUM('wa','telepon','instagram','tiktok','facebook','website','referral','lainnya') NULL,
  handle_by          VARCHAR(100) NULL,
  partner_id         INT NULL COMMENT 'Support By',
  status             ENUM('draft','inquiry','quoted','waiting_dp','booked','in_trip','completed','invoiced','paid','reported','cancelled','closed') NOT NULL DEFAULT 'draft',
  total_modal        BIGINT NOT NULL DEFAULT 0,
  total_jual         BIGINT NOT NULL DEFAULT 0,
  total_tambahan     BIGINT NOT NULL DEFAULT 0,
  grand_total        BIGINT NOT NULL DEFAULT 0,
  margin             BIGINT NOT NULL DEFAULT 0,
  catatan            TEXT NULL,
  created_by         INT NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME NULL,
  KEY idx_status (status),
  KEY idx_tgl (tgl_mulai, tgl_finish),
  KEY idx_customer (customer_id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  order_id             INT NOT NULL,
  unit_id              INT NULL,
  driver_id            INT NULL,
  partner_id           INT NULL,
  nama_unit            VARCHAR(100) NOT NULL COMMENT 'snapshot',
  nopol                VARCHAR(20)  NOT NULL COMMENT 'snapshot',
  nama_driver          VARCHAR(100) NULL COMMENT 'snapshot',
  hp_driver            VARCHAR(30)  NULL COMMENT 'snapshot',
  harga_modal_per_hari BIGINT NOT NULL DEFAULT 0,
  harga_jual_per_hari  BIGINT NOT NULL DEFAULT 0,
  jumlah_hari          SMALLINT NOT NULL DEFAULT 1,
  subtotal_modal       BIGINT NOT NULL DEFAULT 0,
  subtotal_jual        BIGINT NOT NULL DEFAULT 0,
  catatan              VARCHAR(255) NULL,
  KEY idx_order (order_id),
  KEY idx_unit (unit_id),
  CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_includes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  order_id   INT NOT NULL,
  include_id INT NULL,
  nama       VARCHAR(100) NOT NULL,
  biaya      BIGINT NOT NULL DEFAULT 0,
  KEY idx_order (order_id),
  CONSTRAINT fk_inc_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_biaya (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  nama     VARCHAR(100) NOT NULL,
  nominal  BIGINT NOT NULL DEFAULT 0,
  catatan  VARCHAR(255) NULL,
  KEY idx_order (order_id),
  CONSTRAINT fk_biaya_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE invoices (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  order_id          INT NOT NULL,
  nomor_invoice     VARCHAR(30) NOT NULL UNIQUE,
  nomor_revisi_ke   TINYINT NOT NULL DEFAULT 0,
  tanggal_invoice   DATE NOT NULL,
  jatuh_tempo       DATE NULL,
  total             BIGINT NOT NULL DEFAULT 0,
  dp                BIGINT NOT NULL DEFAULT 0,
  sisa              BIGINT NOT NULL DEFAULT 0,
  status            ENUM('draft','terbit','sebagian','lunas','batal') NOT NULL DEFAULT 'terbit',
  customer_snapshot TEXT NULL COMMENT 'JSON data customer saat terbit',
  order_snapshot    TEXT NULL COMMENT 'JSON data order saat terbit',
  catatan           VARCHAR(255) NULL,
  issued_at         DATETIME NULL,
  issued_by         INT NULL,
  replaced_by       VARCHAR(30) NULL COMMENT 'nomor invoice pengganti bila direvisi',
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_order (order_id),
  KEY idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE invoice_items (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id    INT NOT NULL,
  no            TINYINT NOT NULL DEFAULT 0,
  keterangan    VARCHAR(255) NOT NULL,
  driver        VARCHAR(100) NULL,
  tanggal_pakai VARCHAR(60)  NULL,
  rute          VARCHAR(255) NULL,
  harga_hari    BIGINT NOT NULL DEFAULT 0,
  total_hari    SMALLINT NOT NULL DEFAULT 1,
  total_harga   BIGINT NOT NULL DEFAULT 0,
  KEY idx_invoice (invoice_id),
  CONSTRAINT fk_invitem FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id   INT NOT NULL,
  tanggal_bayar DATE NOT NULL,
  tipe         ENUM('dp','pelunasan','lain') NOT NULL DEFAULT 'pelunasan',
  nominal      BIGINT NOT NULL DEFAULT 0,
  metode       ENUM('transfer','cash','qris','lain') NOT NULL DEFAULT 'transfer',
  bank         VARCHAR(50) NULL,
  bukti_path   VARCHAR(255) NULL,
  catatan      VARCHAR(255) NULL,
  created_by   INT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_invoice (invoice_id),
  CONSTRAINT fk_pay_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE status_logs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  order_id    INT NOT NULL,
  status_lama VARCHAR(30) NULL,
  status_baru VARCHAR(30) NOT NULL,
  catatan     VARCHAR(255) NULL,
  oleh        VARCHAR(100) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_order (order_id)
) ENGINE=InnoDB;

-- penomoran dokumen anti-dobel
CREATE TABLE doc_counters (
  jenis   VARCHAR(20) NOT NULL,
  periode VARCHAR(10) NOT NULL,
  urut    INT NOT NULL DEFAULT 0,
  PRIMARY KEY (jenis, periode)
) ENGINE=InnoDB;

-- =====================================================================
-- SEED DATA
-- =====================================================================
INSERT INTO includes (nama, urutan, is_default) VALUES
  ('Mobil', 1, 1),
  ('Driver', 2, 1),
  ('BBM', 3, 1),
  ('Parkir', 4, 1),
  ('Tol', 5, 0),
  ('Makan Driver', 6, 0),
  ('Menginap Driver', 7, 0),
  ('Asuransi', 8, 0),
  ('Antar Jemput Bandara', 9, 0);

INSERT INTO settings (`key`, `value`, label, grup, urutan) VALUES
  ('nama_pt',          'PT. Seribu Nusantara Rental',  'Nama perusahaan', 'kop', 1),
  ('brand',            '1000 RENT CAR',                'Nama brand', 'kop', 2),
  ('tagline',          '"1000 RENT CAR - SOLUSI PERJALANAN MERANGKAI NUSANTARA"', 'Tagline', 'kop', 3),
  ('alamat_pt',        '',                             'Alamat kantor', 'kop', 4),
  ('telepon_pt',       '',                             'Telepon kantor', 'kop', 5),
  ('email_pt',         'bisnis@1000nusantara.id',      'Email', 'kop', 6),
  ('website_pt',       'www.1000nusantara.id',         'Website', 'kop', 7),
  ('instagram_pt',     'www.instagram.com/1000nusantara.id', 'Instagram/TikTok', 'kop', 8),
  ('npwp',             '',                             'NPWP', 'kop', 9),
  ('bank_nama',        'BCA',                          'Nama bank', 'bayar', 1),
  ('bank_rekening',    '',                             'Nomor rekening', 'bayar', 2),
  ('bank_atas_nama',   'PT. Seribu Nusantara Rental',  'Atas nama', 'bayar', 3),
  ('dp_persen_default','30',                           'DP default (%)', 'bayar', 4),
  ('invoice_jatuh_tempo_hari', '7',                    'Jatuh tempo invoice (hari)', 'bayar', 5),
  ('prefix_order',     'RN',                           'Awalan nomor order', 'nomor', 1),
  ('prefix_invoice',   '1000-INV',                     'Awalan nomor faktur', 'nomor', 2),
  ('kode_cabang',      'MDN',                          'Kode cabang penerbit faktur', 'nomor', 3),
  ('footer_invoice',   'Terimakasih atas Pilihan Perjalanan Anda Bersama Kami. Anda Dapat Memesan Rental Mobil SE INDONESIA Karena Kami Hadir Di 38 PROVINSI.', 'Catatan kaki invoice', 'kop', 10),
  ('ttd_nama',         '',                             'Nama penandatangan', 'kop', 11),
  ('ttd_jabatan',      'Admin Reservasi',              'Jabatan penandatangan', 'kop', 12),
  ('penandatangan',    'Yuswanto SH',                  'Nama penandatangan invoice', 'kop', 13),
  ('email_pt2',        '1000rentcarmedan@gmail.com',   'Email kedua (header invoice)', 'kop', 14),
  ('logo',             'assets/img/logo.png',          'Path logo (header invoice)', 'kop', 15),
  ('ttd',              'assets/img/ttd.png',           'Path gambar tanda tangan', 'kop', 16),
  ('catatan_bank',     'A/C : 002-6366-1000 (SMBC)\nA/C : 30523-1000-1 (BNI)\nA/N : PT. SERIBU NUSANTARA RENTAL', 'Catatan / info bank (kotak CATATAN invoice)', 'bayar', 6);
