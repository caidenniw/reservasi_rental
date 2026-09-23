# Dashboard Reservasi - 1000 Nusantara Rental

Sistem input pesanan, data pesanan, dan cetak invoice untuk rental mobil + driver.
PHP native (tanpa framework) + MySQL + Bootstrap 5. Dijalankan lokal di Laragon.

---

## 1. Cara menjalankan

1. Nyalakan **Laragon** (Apache + MySQL).
2. Buka browser: **http://localhost/rentalnusantara/**
   (kalau sudah me-restart Laragon, bisa juga **http://rentalnusantara.test/**)
3. Login: **admin** / **admin123**  <- ganti password setelah dipakai.

Database: `rentalnusantara` (MySQL). Kalau perlu pasang ulang dari nol:

```
mysql -uroot < database/schema.sql         # struktur + master (include & pengaturan)
mysql -uroot < database/seed_contoh.sql    # contoh unit/driver/partner/customer (boleh dilewati)
```

Akun admin dibuat lewat PHP (password di-hash, tidak ditulis di SQL):

```
php -r 'require "config/database.php"; $db=getDB();
$p=password_hash("admin123", PASSWORD_DEFAULT);
$s=$db->prepare("INSERT INTO users (username,nama,password) VALUES (?,?,?)");
$u="admin"; $n="Admin Reservasi"; $s->bind_param("sss",$u,$n,$p); $s->execute();'
```

---

## 2. Tiga menu

| Menu | Isi |
|---|---|
| **Beranda** | kartu angka (pesanan berjalan, pesanan bulan ini, unit keluar, invoice belum lunas), nilai pesanan + margin bulan ini, papan status, grafik 6 bulan (CSS, tanpa library), 6 pesanan terbaru |
| **Input Pesanan** | form 3 bagian sesuai template WA: Pelayanan, Customer & PIC, Unit/Driver/Harga. Hitung hari & ringkasan biaya otomatis. Ada **Simpan Draft** |
| **Data Pesanan & Invoice** | tabel + cari (no. order / pesanan / PIC / nopol / kota) + filter status & bulan + export CSV. Klik **Detail**: lihat semua data, ubah, salin teks WA, terbitkan/cetak/revisi invoice, catat pembayaran, riwayat status |

Master data (Unit, Driver, Customer, Partner, Include), **Import CSV**, dan **Pengaturan** ada di menu
**Master Data** pada kanan atas - sengaja tidak masuk sidebar agar sidebar tetap 3 menu.

---

## 3. Struktur folder

```
rentalnusantara/
├── config/database.php      koneksi, BASE_URL otomatis, zona waktu Asia/Jakarta, session
├── includes/
│   ├── functions.php        auth, CSRF, format rupiah/tanggal, nomor dokumen, hitung order, teks WA
│   ├── master_crud.php      CRUD generik halaman master
│   ├── master_tampilan.php  tampilan standar (form + tabel)
│   ├── header.php footer.php
├── auth/                    login, proses_login, logout
├── pages/
│   ├── beranda.php          menu 1
│   ├── pesanan_form.php     menu 2 (tambah/ubah)
│   ├── pesanan_proses.php   simpan pesanan (validate -> snapshot -> hitung total)
│   ├── pesanan_list.php     menu 3
│   ├── pesanan_detail.php   detail + aksi
│   ├── pesanan_aksi.php     ubah status, terbitkan/revisi invoice, catat pembayaran, batal/hapus
│   ├── pesanan_wa.php       teks konfirmasi WA (siap salin)
│   ├── invoice_cetak.php    halaman cetak (versi customer & lembar internal)
│   ├── import.php           import CSV + template
│   └── master/              unit, driver, customer, partner, include, pengaturan
├── api/autocomplete.php     endpoint JSON (dipakai untuk pencarian unit/customer)
├── database/                schema.sql, seed_contoh.sql
└── assets/                  css, js, bootstrap lokal, uploads (bukti transfer)
```

---

## 4. Aturan yang dipegang sistem

- **Satu pesanan = satu record.** Menu Input dan menu Data memakai tabel `orders` yang sama; tidak ada input dua kali.
- **Jumlah hari** = tanggal selesai - tanggal mulai + 1 (inklusif). 27-30 Sep = 4 hari.
- **Dua tingkat harga**: `harga_modal` (internal) dan `harga_jual` (customer).
  Invoice customer **tidak pernah** memuat modal, margin, maupun nama partner.
  Lembar internal dicetak terpisah (`invoice_cetak.php?mode=internal`).
- **Snapshot**: nama unit, nopol, driver, harga, dan data customer disalin ke pesanan/invoice saat disimpan.
  Master data berubah kemudian tidak mengubah dokumen lama.
- **Nomor dokumen** dibuat dengan transaksi + row lock: order `RN-YYMM-0001`, invoice `INV/YYYY/MM/0001`.
- **Kunci dokumen**: draft bebas diubah; setelah invoice terbit nomornya terkunci. Salah? pakai **Revisi** -
  nomor lama ditandai `batal`, nomor baru terbit, dan **pembayaran yang sudah masuk ikut pindah** ke nomor baru.
- **Validasi bentrok unit**: unit yang sama tidak bisa dipakai pada rentang tanggal yang bertumpuk
  (status draft/batal/ditutup dikecualikan).
- **Pembayaran**: menyimpan nominal + bukti transfer opsional; status invoice otomatis
  `terbit -> sebagian -> lunas`, dan pesanan otomatis jadi `Lunas` saat sisa 0.
- **Cetak** memakai halaman HTML + CSS `@media print` (Ctrl+P -> Save as PDF). Tidak ada file PDF permanen.

---

## 5. Status verifikasi (23-09-2026)

Semua alur sudah diuji end-to-end lewat HTTP (login, POST, baca database), bukan hanya dibaca kodenya:

| Uji | Hasil |
|---|---|
| Login + session + CSRF | berhasil (302 ke beranda, halaman 200) |
| Input pesanan (kasus OJK / Innova Reborn / Ade, 27-30 Sep) | tersimpan: 4 hari, modal 3.600.000, jual 4.800.000, margin 1.200.000 |
| Customer dibuat otomatis dari form pesanan | berhasil |
| Validasi bentrok unit | pesanan kedua untuk unit sama tanggal bertumpuk DITOLAK |
| Terbit invoice | `INV/2026/09/0001` + rincian item, jatuh tempo 7 hari |
| DP 1.000.000 | invoice `sebagian`, sisa 3.800.000 |
| Revisi invoice | nomor lama `batal`, nomor baru `INV/2026/09/0002`, pembayaran pindah |
| Pelunasan | invoice `lunas`, pesanan jadi `Lunas` |
| Cetak invoice customer | tidak memuat kata Margin/Modal (dicek langsung di HTML) |
| Lembar internal | memuat modal, margin, partner |
| Teks WA | sesuai template lama, tanpa baris modal |
| Master data (tambah/ubah/nonaktif) | berhasil |
| Import CSV | 1 masuk, 1 duplikat dilewati, laporan per baris |
| Export CSV + semua halaman | 200 OK |

Contoh pesanan `RN-2609-0001` (OJK Prov. Sumut) sengaja dibiarkan di database sebagai contoh -
boleh dihapus dari halaman detail kapan saja.

---

## 6. Catatan penting

- `php.ini` Laragon masih `date.timezone = UTC`; sudah diatasi dengan
  `date_default_timezone_set('Asia/Jakarta')` di `config/database.php`. Jangan dihapus, kalau tidak
  invoice yang dicetak malam bisa bertanggal beda sehari.
- **Type string `bind_param` jangan ditulis manual.** Satu karakter salah (misal `i` untuk kolom teks)
  membuat ENUM/teks terisi 0 dan error "Data truncated" hanya muncul saat dijalankan, tidak saat `php -l`.
  Di proyek ini type string dibangun otomatis dari daftar field.
- Kalau nanti dipakai lebih dari satu orang atau diserahkan ke perusahaan, tambahkan kolom `role`
  pada tabel `users` + filter di query **sebelum** dibagikan (sekarang semua yang login melihat semua data).
- Deploy belum dilakukan (sesuai keputusan: cukup laptop). Struktur dibuat portable:
  `BASE_URL` dihitung otomatis, tidak ada CDN, tidak ada library eksternal.


---

## 7. Pembaruan (restyle + audit, 23-09-2026)

Tampilan disamakan dengan referensi korporat mentor (gambar di `D:\maganghub\refrensiui`):

- Warna utama hijau -> **merah #e62e2e** + sidebar gelap gradasi (#1a1c21 -> merah) + slogan
  "Satu Sistem Seribu Perjalanan" di sidebar.
- Font **Inter** (file woff2 diunduh lokal di `assets/fonts/`, tanpa CDN).
- Badge status jadi **pill pastel** (Lunas hijau, DP/terbit biru, menunggu DP kuning, batal merah).
- Kartu rounded + shadow halus, tabel tanpa garis vertikal, topbar berisi tanggal hari ini + chip user.

Perbaikan audit (hasil cross-check ulang):

1. **Nopol unik vs soft delete** - unit yang dinonaktifkan otomatis diberi suffix nopol (`#del<id>`),
   jadi nopol yang sama bisa dipakai unit baru tanpa bentrok constraint database.
2. **Panel pratinjau invoice** di halaman detail (iframe kecil) + tombol cetak penuh.
3. **Throttle login** - setelah 5x password salah, login dikunci 60 detik (cukup untuk pemakaian lokal).

Seluruh alur diuji ulang end-to-end setelah perubahan (lihat tabel bagian 5) - semua lulus.


---

## 8. Invoice (mengikuti template referensi)

Tampilan cetak invoice disamakan dengan template perusahaan (`D:\maganghub\invoice\New folder`):

- **Header**: logo kiri (`assets/img/logo.png`), judul "INVOICE" + nama PT + website/email kanan.
- **Kotak info**: "DITAGIH KEPADA" (instansi + PIC) dan No. Faktur / Tanggal / Jatuh Tempo.
- **Tabel** header kuning `#FFC000`: No | Keterangan | Driver | Tanggal Pemakaian | Rute | Harga/Hari | Total Hari | Total Harga.
- **Footer total**: Total, Down Payment, Total Yang Harus Di Bayar.
- **Terbilang** (angka -> huruf, otomatis).
- **Kotak CATATAN** = info rekening (SMBC / BNI), bisa diubah di Pengaturan.
- **Tanda tangan** (`assets/img/ttd.png`) + nama penandatangan (`Yuswanto SH`), bisa diubah di Pengaturan.

Lembar internal (`?mode=internal`) menambah kolom Modal/Hari + kotak ringkasan (total modal, margin,
partner, handle by). Font Aptos Narrow/Segoe UI, cetak A4 portait, warna kuning dipaksa ikut tercetak
(`print-color-adjust: exact`).

Nomor faktur masih memakai format sistem `INV/YYYY/MM/0001` (bisa diubah lewat setting `prefix_invoice`).


---

## 9. Penanganan Kelemahan & Audit QA (23-09-2026)

Semua kelemahan hasil simulasi admin reservasi telah diselesaikan dan terverifikasi:

1. **Sinkronisasi Order vs Invoice**: Muncul peringatan otomatis di form & detail jika data pesanan diedit setelah invoice terbit, mengarahkan admin untuk tombol *Revisi Invoice*.
2. **Validasi Overpayment**: Pembayaran yang melebihi sisa tagihan otomatis ditolak sistem.
3. **Penyelarasan Tipe Pelanggan**: Label diseragamkan menjadi `Retail (Perorangan)`, `Perusahaan / Instansi`, dan `Repeat Order`.
4. **Keamanan Bukti Transfer**: Akses langsung URL ke folder uploads diblokir (`.htaccess` 403 Forbidden). Pengunduhan/preview file dialihkan melalui `pages/bukti.php` yang wajib session login aktif.
5. **Normalisasi HP Driver**: Input manual nomor HP driver (misal `08xx`) otomatis dikonversi ke format standar `+62xx`.
6. **Fitur Ubah Password**: Halaman `pages/ubah_password.php` disediakan di menu dropdown kanan atas untuk mengganti password admin secara aman.


---

## 10. Navigasi Sidebar Modern & Desain SaaS (23-09-2026)

Menu navigasi diperbarui menyeluruh mengadopsi standar UI/UX aplikasi modern (SaaS):

1. **Sidebar Terkelompok (Categorized Navigation)**:
   - **MENU UTAMA**: Dashboard, Input Pesanan, Data Pesanan & Faktur.
   - **DATA MASTER**: Armada Mobil, Data Driver, Data Pelanggan, Partner (Support By), Item Include.
   - **SISTEM & TOOLS**: Import CSV, Pengaturan Faktur, Ubah Password.
2. **Ikon SVG Vektor Ringan**: Setiap menu memiliki ikon SVG presisi dan tajam tanpa dependensi CDN eksternal.
3. **Profil Admin & Slogan**: Bagian bawah sidebar menampilkan avatar admin, status "Admin Reservasi", dan tombol keluar langsung.
4. **Mobile Drawer Offcanvas**: Di ponsel/tablet (`<= 991px`), sidebar bertransformasi menjadi *sliding drawer* dengan efek latar buram (*backdrop blur*) yang dibuka lewat tombol hamburger di topbar.
