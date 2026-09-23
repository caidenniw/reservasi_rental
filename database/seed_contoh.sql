-- Data contoh untuk uji coba (boleh dihapus/diubah dari dashboard)
USE rentalnusantara;

INSERT INTO partners (nama, tipe, hp, catatan) VALUES
  ('Om Karius', 'vendor', '', 'Support unit dari pesan WA contoh');

SET @partner := (SELECT id FROM partners WHERE nama = 'Om Karius' LIMIT 1);

INSERT INTO units (kode_unit, nama_unit, nopol, jenis, tahun, transmisi, kapasitas, pemilik, partner_id, harga_modal_default, harga_jual_default, status) VALUES
  ('INV-01', 'Innova Reborn', 'BK 1507 LIN', 'MPV', 2022, 'matic', 7, 'sendiri', NULL, 900000, 1200000, 'ready'),
  ('AVZ-01', 'Avanza',        'BK 1234 XY',  'MPV', 2021, 'manual', 7, 'sendiri', NULL, 600000, 850000, 'ready'),
  ('HIA-01', 'Hiace Commuter','BK 9001 ZZ',  'Hiace', 2020, 'manual', 14, 'partner', @partner, 1100000, 1500000, 'ready');

INSERT INTO drivers (nama, hp, wilayah, bank, status) VALUES
  ('Ade', '+6285362973299', 'Gunung Sitoli', 'BRI', 'aktif'),
  ('Budi Santoso', '+628123456789', 'Medan', 'BCA', 'aktif');

INSERT INTO customers (tipe, nama_pesanan, nama_pic, hp_pic, sumber, status) VALUES
  ('instansi', 'Otoritas Jasa Keuangan Prov. Sumut', 'Bp. Feri Yuanda', '+6285296592610', 'wa', 'baru'),
  ('perorangan', 'Andi', 'Andi', '+628111111111', 'wa', 'baru'),
  ('perorangan', 'Budi', 'Budi', '+628222222222', 'wa', 'baru');
