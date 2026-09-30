-- Migrasi 30 Sep 2026: kolom laba/insentif/laba bersih + rincian biaya + ref arsip
-- Ditambahkan supaya hasil import setia-sheet (Rental Bulan Juli 2026_v1.xlsx) bisa
-- menyimpan angka LABA / INSENTIF (2,75%) / LABA BERSIH / rincian pengeluaran.

ALTER TABLE orders
  ADD COLUMN laba BIGINT NOT NULL DEFAULT 0 AFTER margin,
  ADD COLUMN insentif BIGINT NOT NULL DEFAULT 0 AFTER laba,
  ADD COLUMN laba_bersih BIGINT NOT NULL DEFAULT 0 AFTER insentif,
  ADD COLUMN rincian_biaya TEXT NULL AFTER laba_bersih,
  ADD COLUMN ref_arsip VARCHAR(50) NULL AFTER rincian_biaya;
