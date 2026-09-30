# Panduan Penggunaan — Dashboard Reservasi 1000 Nusantara Rental

Versi: September 2026. Dokumen ini menjelaskan cara mengoperasikan sistem reservasi
mobil + driver dari nol, ditujukan untuk admin yang baru pertama kali memakai sistem.
Baca berurutan, terutama bagian "Alur Kerja Utama" dan "Status Pesanan".

---

## 1. Cara Masuk

1. Buka browser, akses salah satu alamat berikut (mesin Laragon harus menyala):
   - http://localhost/rentalnusantara/
   - http://127.0.0.1:8081/
2. Muncul halaman "Masuk". Isi:
   - Username: admin
   - Password: admin123
   - **Catatan penting:** ini akun bawaan. Segera ganti password lewat menu
     **Ubah Password** di grup SISTEM & TOOLS begitu sistem mulai dipakai rutin.
3. Klik **Masuk**. Kamu akan mendarat di Beranda.

Untuk keluar, klik **Keluar Sistem** di bagian bawah sidebar kiri.

## 2. Peta Menu (Sidebar Kiri)

| Grup | Menu | Fungsi |
|---|---|---|
| MENU UTAMA | Dashboard | Beranda, ringkasan & grafik |
| MENU UTAMA | Input Pesanan | Membuat pesanan baru |
| MENU UTAMA | Data Pesanan & Faktur | Daftar semua pesanan, cari, filter, export |
| DATA MASTER | Armada Mobil | Data unit/mobil |
| DATA MASTER | Data Driver | Data sopir |
| DATA MASTER | Data Pelanggan | Data customer |
| DATA MASTER | Partner (Support By) | Data partner/vendor |
| DATA MASTER | Item Include | Daftar item include (contoh: sopir, BBM) |
| SISTEM & TOOLS | Asisten Data | Chat bot tanya data (baca saja) |
| SISTEM & TOOLS | Import Excel (Orderan) | Impor data pesanan lama dari file Excel |
| SISTEM & TOOLS | Import CSV | Impor pesanan dari CSV |
| SISTEM & TOOLS | Pengaturan Faktur | Identitas PT, bank, awalan nomor dokumen |
| SISTEM & TOOLS | Ubah Password | Ganti kata sandi |

Di pojok kanan atas ada tombol **Pesanan Baru** (jalan pintas ke Input Pesanan)
dan chip nama admin.

---

## 3. Alur Kerja Utama (dari terima order sampai lunas)

Ringkasnya begini:

    Input Pesanan  ->  Simpan (status Booked)
          ->  Terbitkan Invoice  (nomor faktur dibuat, panjar otomatis jadi DP)
          ->  Catat Pembayaran   (DP / pelunasan, sampai sisa = 0)
          ->  Status jadi Lunas  ->  Cetak Invoice / Lembar Internal

Detail tiap langkah di bawah.

### 3.1 Membuat Pesanan (menu Input Pesanan)

Form terdiri dari 3 bagian. Tanda `*` artinya wajib diisi.

**Bagian 1 — Pelayanan**
- **Tipe Pelanggan**: Retail (perorangan), Perusahaan/Instansi, Repeat Order (RO),
  atau RTR (Rent to Rent = biro lain menyewa unit kita).
- **Wilayah**: Dalam Kota / Luar Kota.
- **Kota / Lokasi**: wajib. Contoh: "Medan", "Gunung Sitoli (Nias)".
- **Tujuan / Rute**: opsional, tampil di kolom Rute invoice.
- **Tanggal Mulai & Selesai**: wajib. **Jumlah Hari dihitung otomatis dan inklusif**
  (tanggal selesai - tanggal mulai + 1). Contoh: 27 s/d 30 Sept = 4 hari.
- **Jam / Koordinasi dengan user**: isi jam, atau centang "Koordinasi dengan user".
- **Standby Point** (mis. Bandara Binaka) dan **Flight** (opsional).

**Bagian 2 — Customer & PIC**
- **Nama Pesanan / Instansi**: wajib. Kalau nama belum ada di master, sistem otomatis
  membuatkan data pelanggan baru saat disimpan.
- **Sumber Order**: WhatsApp / Telepon / Instagram / TikTok / Facebook / Website /
  Referral / Lainnya.
- **Nama PIC**, **HP/WA PIC**: penanggung jawab yang dihubungi.
- **Data Tamu**: internal saja (tidak dicetak di invoice) — mis. "Imigrasi / Lapas".
- **Keterangan** dan **Asal User (arsip Excel)**: isi "RTR / Corp / RO / Apkasi / IG /
  Web" kalau ingin menyamakan dengan catatan lama; Tipe & Sumber ikut menyesuaikan.
- **Handle By**: nama yang menangani. **Catatan Internal**: bebas.

**Bagian 3 — Unit, Driver & Harga**
- Satu baris = satu mobil. Pilih **Driver** (wajib) dan **Unit**; nama unit, nopol,
  dan harga otomatis terisi dari master, tapi masih bisa dikoreksi manual.
- Isi **Harga Modal / Hari** (biaya internal) dan **Harga Jual / Hari** (harga ke
  customer). **Hari (per unit)** mengikuti jumlah hari pesanan, boleh disesuaikan.
- **Support By**: partner yang mendukung unit itu (opsional).
- **+ Tambah Mobil Lain (Rombongan)**: bila satu pesanan pakai lebih dari satu mobil.
- **Biaya Tambahan**: biaya lain di luar sewa (mis. biaya tol, uang makan sopir).
- **Include**: item yang disertakan (mis. Sopir, BBM); isi biaya bila dibebankan.
- **Panjar / DP Awal**: isi bila customer sudah transfer sebelum invoice terbit.
  Otomatis jadi pembayaran DP saat invoice diterbitkan — tidak perlu diinput dua kali.
- Ringkasan di bawah menampilkan: Subtotal modal, Subtotal jual, Biaya tambahan,
  **Total Tagihan**, Panjar, Sisa Tagihan, dan Margin (internal).

**Tombol bawah:**
- **Simpan Pesanan** — simpan dengan status default **Booked** (atau status yang dipilih).
- **Simpan Draft** — simpan sebagai Draft (belum final, tidak memblokir jadwal unit).
- **Batal** — kembali tanpa menyimpan.

Yang divalidasi sistem: nama pesanan, kota, tanggal wajib; tanggal selesai tidak boleh
sebelum tanggal mulai; minimal 1 unit (nama + nopol); setiap unit harus punya driver;
**jadwal unit dicek supaya tidak bentrok** dengan pesanan lain; **panjar tidak boleh
melebihi total tagihan**.

### 3.2 Melihat & Mengelola Pesanan (menu Data Pesanan & Faktur)

- Daftar semua pesanan, bisa dicari (nomor order/invoice, nama, PIC, nopol, kota),
  difilter (status, tipe, bulan, rentang tanggal), diurutkan, dan **Export CSV**.
- Klik **Detail** pada sebuah baris untuk membuka halaman detail pesanan.

### 3.3 Halaman Detail Pesanan

Berisi: data pelayanan, customer & PIC, unit & driver, ringkasan biaya, kartu invoice,
pengubah status, dan riwayat status. Tombol penting:
- **Ubah Data** — kembali ke form untuk mengubah pesanan.
- **Salin Teks WA** — teks pesanan siap tempel untuk dikirim ke driver/pelanggan.
- **Terbitkan Invoice** — membuat faktur (lihat 3.4).
- **Cetak Invoice / Cetak Lembar Internal** — lihat 3.6.
- **Perbarui Invoice** — menyesuaikan isi invoice dengan data terbaru (nomor tetap).
- **Batalkan Pesanan** — membatalkan (invoice ikut ditandai batal).
- **Hapus** — menyembunyikan pesanan dari daftar (data tetap tersimpan di database).

### 3.4 Invoice

**Terbitkan Invoice** akan:
1. Membuat nomor faktur otomatis dengan format `1000-INV/{Romawi}/{Cabang}-{Urut}`,
   contoh `1000-INV/IX/MDN-24631`.
2. Menetapkan **Jatuh Tempo** (default +7 hari, bisa diubah di Pengaturan Faktur).
3. Menjadikan **Panjar** otomatis sebagai pembayaran DP.
4. Mengubah status pesanan menjadi **Invoice Terbit**.

**Catat Pembayaran** (di kartu invoice): isi tanggal bayar, tipe (DP/Pelunasan/Lainnya),
nominal, metode (Transfer/Cash/QRIS/Lainnya), bank, bukti (foto/PDF), dan catatan.
- Nominal **tidak boleh melebihi sisa tagihan**.
- Saat total yang dibayar = total tagihan, invoice jadi **Lunas** dan status pesanan
  otomatis menjadi **Lunas**.

**Hapus catatan pembayaran** (ikon x di daftar pembayaran): pembayaran dihapus dan
invoice kembali menjadi Terbit / Dibayar Sebagian; status pesanan ikut turun ke
Invoice Terbit.

### 3.5 Mengubah Invoice (nomor tetap)

Kalau isi pesanan berubah SETELAH invoice terbit (mis. harga atau hari dikoreksi),
jangan buat invoice baru. Gunakan **Perbarui Invoice**: isi dan total invoice
disesuaikan dengan data terbaru, **nomor faktur tetap sama**, dan pembayaran yang
sudah masuk tetap menempel. Ini keputusan internal perusahaan.

### 3.6 Cetak Dokumen

- **Cetak Invoice** = dokumen untuk customer (tanpa harga modal & margin).
- **Cetak Lembar Internal** = dokumen internal (menampilkan modal, margin, partner).
- Keduanya siap cetak A4; di halaman detail ada pratinjau langsung.

---

## 4. Status Pesanan (pahami ini supaya tidak salah alur)

Ada 12 status. Dibagi jadi dua kelompok:

**Status operasional (diubah lewat form / tombol Ubah Status):**

| Status | Arti |
|---|---|
| Draft | Masih rancangan, belum final |
| Inquiry | Baru bertanya, belum ada penawaran |
| Penawaran (quoted) | Sudah diberi harga |
| Menunggu DP | Menunggu uang muka |
| Booked | Sudah dipesan / dijadwalkan |
| Sedang Trip | Mobil sedang dipakai |
| Selesai Trip | Trip selesai, belum ditagih |

**Status dokumen (berubah OTOMATIS lewat aksi invoice/pembatalan, bukan dari form):**

| Status | Kapan terjadi |
|---|---|
| Invoice Terbit | Setelah "Terbitkan Invoice" |
| Lunas | Setelah pembayaran memenuhi total tagihan |
| Masuk Laporan | Ditandai admin (lewat Ubah Status) |
| Batal | Setelah "Batalkan Pesanan" |
| Ditutup | Ditandai admin (lewat Ubah Status) |

Aturan penting:
- Status dokumen **tidak bisa diturunkan lewat form** — sistem sengaja menguncinya
  supaya pesanan yang sudah ber-invoice/lunas tidak berubah jadi Draft.
- Status **Lunas** hanya naik saat invoice benar-benar lunas; kalau pembayaran dihapus,
  status turun kembali ke **Invoice Terbit**.
- **Batalkan Pesanan** ikut menandai invoice aktif sebagai Batal (sisa jadi 0), tapi
  catatan pembayaran tetap tersimpan sebagai bukti uang masuk.

---

## 5. Beranda & Cara Baca Angkanya

Beranda terbagi menjadi beberapa bagian:

**Ringkasan periode** — angka yang mengikuti pilihan tanggal (Hari ini / 7 hari /
Bulan ini / 3 bulan / Tahun ini / Rentang sendiri):
- Pesanan dalam periode, unit bertugas, **nilai jual**, dan **margin (internal)**.
- Tabel "Pesanan dalam periode (terbaru 12)" + tombol buka di Data Pesanan.
- Catatan: pesanan historis impor (Lunas tanpa invoice) TIDAK dihitung sebagai
  pendapatan.

**Kondisi saat ini** — angka real-time, tidak terpengaruh pilihan periode:
- Pesanan berjalan hari ini, unit keluar hari ini, invoice belum lunas (dengan total
  sisa tagihan), dan pesanan yang datanya belum lengkap.

**Papan status** — jumlah dan nilai per status; klik sebuah kotak untuk membuka daftar
pesanan dengan status itu.

**Tren 6 bulan** — grafik (Chart.js). Bisa ganti ukuran (**Nilai jual / Margin /
Jumlah pesanan**) dan bentuk (**Batang / Garis**). Arahkan kursor untuk rincian,
klik batang/titik untuk membuka daftar pesanan bulan itu.

**Pesanan terbaru diinput** — 6 pesanan terakhir yang dimasukkan.

**Ketersediaan unit** — kisi unit x tanggal (7/14/30 hari ke depan). Kotak merah =
unit terpakai (klik untuk buka detail pesanan), kotak abu = bebas. Ada pencarian unit
dan opsi "Tampilkan semua unit". Papan ini akan tampak kosong bila belum ada reservasi
ke depan — itu normal, akan terisi begitu pesanan baru masuk.

---

## 6. Asisten Data (Chat Bot, baca saja)

- Buka dari menu **Asisten Data**, atau klik tombol **Asisten** mengambang di kanan
  bawah halaman mana pun.
- Ketik pertanyaan bahasa biasa, contoh:
  - "Pesanan apa saja yang berjalan hari ini?"
  - "Invoice mana yang belum lunas dan berapa sisanya?"
  - "Unit BK 1261 OOO kosong tanggal 5-7 November 2026?"
  - "Rekap nilai pesanan 6 bulan terakhir"
- Asisten **hanya membaca data** — tidak bisa membuat, mengubah, atau menghapus apa pun.
  Kalau datanya tidak ada, ia bilang tidak ada dan menunjukkan menu yang tepat.
- Batasan: maksimal 40 pertanyaan per 10 menit, tiap pertanyaan maksimal 600 karakter.

---

## 7. Data Master

Data master dipakai supaya saat input pesanan, banyak isian terisi otomatis.

- **Armada Mobil**: kode, nama unit, nopol, merek, harga modal/jual default, partner,
  dan status. Unit yang nonaktif tidak muncul di pilihan.
- **Data Driver**: nama, HP, wilayah, SIM, bank/rekening, status (aktif/izin/sakit/
  nonaktif). Hanya driver "aktif" yang muncul di pilihan.
- **Data Pelanggan**: nama pesanan/instansi, tipe, PIC, HP, email, alamat, sumber.
- **Partner (Support By)**: nama, tipe (vendor/perantara/owner unit), HP, alamat, bank.
- **Item Include**: nama item include dan urutannya; item default otomatis tercentang
  saat input pesanan baru.

Hati-hati saat menghapus data master: sistem memakai hapus permanen. Nama unit/nopol/
driver yang sudah terlanjur dipakai di pesanan lama tetap tersimpan di riwayat pesanan
itu, jadi riwayat tidak hilang — tetapi dropdown dan laporan master tidak akan
menampilkan data yang sudah dihapus.

## 8. Import Data Lama

- **Import Excel (Orderan)**: untuk memindahkan data pesanan lama (arsip Excel).
  Unggah file `.xlsx` (mis. "Rental Bulan Juli 2026.xlsx"), sistem menampilkan pratinjau,
  memetakan kolom ke form kita (termasuk Data Tamu, Upgrade, Asal User, Panjar), dan
  melewati baris duplikat (kriteria: pemesan + nopol + tanggal mulai sama).
  Data historis yang diimpor otomatis bertanda Lunas/Selesai dan diberi label "data
  historis" (tidak dihitung sebagai pendapatan beranda).
- **Import CSV**: impor sederhana untuk data pesanan berbentuk CSV.

## 9. Pengaturan Faktur

Tempat mengisi identitas yang tercetak di invoice: nama PT, brand, tagline, alamat,
telepon, email, website, Instagram, NPWP, catatan kaki invoice, nama & jabatan
penandatangan, logo, serta **awalan nomor order (RN)**, **awalan nomor faktur (1000-INV)**,
**kode cabang (MDN)**, informasi bank, dan **jatuh tempo invoice (hari)**.

---

## 10. Troubleshooting Ringkas

| Gejala | Yang perlu dicek |
|---|---|
| Tidak bisa masuk | Mesin Laragon menyala? Username/password benar? Ada pesan "terlalu banyak percobaan" (tunggu 60 detik) |
| Angka beranda aneh | Cek pilihan periode; ingat angka "Ringkasan periode" mengikuti tanggal, "Kondisi saat ini" tidak |
| Pesanan ditolak saat simpan | Baca pesan merah: biasanya unit bentrok jadwal, driver belum dipilih, atau panjar melebihi total |
| Panjar melebihi total tagihan | Kecilkan nominal panjar (sistem menolak, tidak memotong diam-diam) |
| Invoice tidak bisa dibuat | Pesanan ini sudah punya invoice aktif — pakai "Perbarui Invoice" |
| Nomor faktur "lompat" | Nomor yang terpakai tidak pernah dipakai ulang (termasuk yang dibatalkan saat uji) — itu wajar |
| Asisten tidak muncul / error | Kunci API belum diisi (config/asisten.local.php) atau kuota habis; hubungi admin sistem |
| Grafik tidak muncul | Cek berkas `assets/vendor/chartjs/chart.umd.min.js` masih ada |

---

## 11. Catatan untuk Admin Sistem

- Data saat ini mayoritas arsip Juli 2026 (hasil impor). Sebagian besar berstatus
  Selesai Trip / Lunas.
- Ada 114 pesanan historis bertanda Lunas tanpa invoice (Rp 722.299.905). Sudah
  dilabeli dan dikeluarkan dari angka pendapatan. Perlakuan finalnya menunggu
  keputusan manajemen.
- Beberapa keputusan yang masih menunggu: tanggal 12 baris data susulan, verifikasi
  1 pesanan bernilai janggal, dan aturan perhitungan laba bersih 5% bila dibutuhkan.
- Seluruh fitur inti (perbaikan alur status, asisten AI, beranda interaktif) sudah
  terverifikasi dan berjalan. Cadangan kerja tersimpan di git (commit berkala).
