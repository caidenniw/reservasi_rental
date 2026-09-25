# Panduan Flow RentalNusantara — Best Practice (v2 — RTR jadi tipe sendiri)

> Untuk admin reservasi. Baca 5 menit, langsung bisa pakai. Disusun 25 Sept 2026 — Caai untuk Deni Arya.

## 0. Inti dalam 30 Detik

Sebelum: WA → catat 31 kolom di Excel → hitung manual → ketik invoice Word.
Sekarang: **WA → Input Pesanan (3 langkah, 2 menit) → Simpan → Terbitkan Invoice (otomatis bernomor, panjar jadi DP) → Pelunasan.**

Prinsip:
- **Input sekali, pakai berkali.** Panjar cukup isi di form, tidak isi lagi di invoice.
- **Data Tamu internal saja** — tidak ikut cetak invoice/WA pelanggan (sesuai permintaan).
- **Asal User mentah tetap disimpan** (`RTR`/`Corp`/`RO`/lainnya) jadi audit tidak hilang, tapi sistem otomatis mapping ke tipe benar.
- **RTR = Rent to Rent** (biro lain sewa unit kita). Beda dengan `Corporate` (perusahaan pakai sendiri) dan `RO` (Repeat Order/pelanggan lama).

---

## 1. Cheat Sheet 1 Halaman (tempel di meja admin)

```
WA MASUK ──> BUKA Input Pesanan
              │
              ├─ Langkah 1 Pelayanan: Luar/Dalam Kota, Kota, Tgl Mulai–Selesai (otomatis +1 hari), Jam, Standby, Flight
              ├─ Langkah 2 Customer: Nama Pesanan (=customer), PIC, HP, Data Tamu (internal), Asal User (RTR/Corp/RO/IG/Web/Bu Tika), Keterangan, Handle By
              └─ Langkah 3 Unit: Unit+Nopol, Driver (wajib), Upgrade, Support By/Partner per unit, Harga Modal/Jual per hari, Biaya tambahan (Overtime/BBM/Toll)
                     ↕ live ringkas: Grand = Jual×hari + tambahan | Sisa = Grand − Panjar | Margin = Grand − Modal×hari
              │
              ▼
        SIMPAN → nomor RN-2609-xxxx (counter otomatis per bulan)
              │
              ▼
        DETAIL — cek Panjar & Sisa. Belum ada invoice.
              │
              ▼
        TERBITKAN INVOICE → nomor INV/2026/09/xxxx terkunci + panjar otomatis jadi 1 baris DP (tidak input lagi)
              │
              ├─ Jika ada yang salah harga/tanggal ──> REVISI INVOICE (nomor lama Batal, nomor baru terbit, uang yang sudah masuk IKUT PINDAH)
              └─ Jika lunas ──> CATAT PEMBAYARAN (pelunasan) → status jadi LUNAS → CETAK
```

**Aturan emas Panjar:**
- Ada panjar? Isi di form (mis 1.000.000). Simpan. Lupakan. Nanti saat Terbitkan Invoice, DP 1jt muncul sendiri.
- Tidak ada panjar? Kosongkan (0). Invoice terbit sisa = Grand penuh.
- Panjar = Grand? Isi panjar = Grand. Invoice langsung LUNAS saat terbit (untuk retail bayar di muka).
- Jangan pernah input panjar lagi di halaman Catat Pembayaran — sudah otomatis.

---

## 2. Flow Lengkap — Dari WA Sampai Uang Masuk (contoh nyata)

### Contoh A — RTR + Panjar (yang paling sering, Boavista Rent Car)
1. Boavista WA: _"Butuh Zenix 3 hari 25–27 Sept, tamu Imigrasi, panjar 1jt"_
2. Admin Input Pesanan:
   - Pelayanan: Luar Kota, Kota `Deli Serdang`, 25-09 s/d 27-09 → sistem hitung **3 hari inklusif** (25,26,27). Tujuan `Medan - L. Pakam`, Standby `Bandara Kualanamu`, Flight `GA-123`, Jam `08:00`.
   - Customer: Nama Pesanan `Boavista Rent Car`, PIC `Budi`, HP `0812...`, **Data Tamu** `Imigrasi (Tamu Dinas X)` — internal, tidak cetak. **Asal User** ketik `RTR` → sistem otomatis set **Tipe = RTR (Rent to Rent)** & Sumber = WhatsApp. Handle `Admin`.
   - Unit: `Zenix G Hybrid BK 1048 AFO`, Driver `Ade`, **Upgrade** `Up Reborn`, Modal 900rb/hari, Jual 1,2jt/hari, hari 3 → subtotal jual 3,6jt. Tambah **Biaya** `Overtime 2 jam 200rb`. **Panjar** `1.000.000`. Live: Grand 3,8jt, Sisa 2,8jt, Margin 1,1jt.
3. Simpan → `RN-2609-xxxx` status `booked`. Cek Detail: badge `RTR`, Data Tamu terlihat, Upgrade+Support terlihat, ringkas Panjar & Sisa benar.
4. Klik **Terbitkan Invoice** → `INV/2026/09/xxxx` terkunci. Otomatis ada baris **DP Rp 1.000.000 — Panjar awal dari form pesanan**. Invoice status `sebagian`, sisa 2,8jt. Flash: _"Panjar otomatis tercatat sebagai DP"._
5. Boavista transfer 2,8jt → **Catat Pembayaran** tipe `pelunasan` 2,8jt → Invoice `lunas`, Order `paid`. Selesai. Tombol **Cetak Invoice** (untuk customer, tanpa modal) & **Cetak Lembar Internal** (dengan modal/margin, untuk arsip).

### Contoh B — Corporate tanpa panjar (PT Guthrie)
Sama, tapi Asal User `CO` → **Corporate**, Panjar 0. Invoice terbit `terbit` sisa = Grand. Pelunasan manual penuh.

### Contoh C — Retail IG bayar lunas di muka
Asal User `IG` → **Retail / Instagram**, 1 hari 850rb, Panjar = 850rb (=Grand). Invoice langsung `lunas` saat terbit, Order langsung `paid` — tidak perlu catat lagi.

---

## 3. Penjelasan Best Practice (kenapa begini, bukan begitu)

| Keputusan | Kenapa ini yang terbaik | Alternatif (dan trade-off) |
|---|---|---|
| **Panjar di form, bukan di invoice** | Input sekali → DP otomatis. Mencegah lupa/double-input. Sisa live terlihat sebelum simpan. Historis Excel juga panjar di awal. | Alternatif: input DP di invoice saja. Trade-off: harus ingat dua tempat, rawan salah Sisa. Sistem kita dukung keduanya, tapi best practice = form. |
| **RTR jadi tipe sendiri** | Excel 2026: 37 baris RTR semua unit 1000 Rent margin tipis 2jt (10,5−8,5jt) — pola bisnis beda dengan Corporate (end-user). Dipisah, laporan RTR vs Corporate jadi akurat. | Alternatif: gabung RTR ke Corporate. Trade-off: tidak bisa bedakan revenue sewa ke biro vs ke perusahaan langsung. Dulu RTR fallback ke corporate/lainnya sebelum klarifikasi. |
| **CO/Corp/Corporation → Corporate** | Excel campur tulis `Corp` dan `Co` — dinormalisasi di `mapAsalUser()` biar tidak bikin kategori baru. | Alternatif: bikin tipe `CO` terpisah. Trade-off: fragmentasi, filter jadi ribet. |
| **RO tetap tipe sendiri** | RO = loyalitas, bukan jenis customer. Perlu untuk promo/analisis repeat. | Alternatif: flag terpisah. Trade-off: tambah kompleks, untuk mini dashboard cukup tipe. |
| **Data Tamu internal** | Pemesan `Boavista Rent Car` ≠ Tamu `Imigrasi`. Invoice ke Boavista tidak perlu sebut tamu (privasi + rapi). Simpan internal untuk handle komplain/lacak. | Alternatif: cetak di invoice. Trade-off: invoice jadi bocorkan data tamu ke pemesan, tidak diminta. |
| **Harga disimpan per-hari, bukan total** | Excel kadang tulis total, kadang per-hari, bahkan `1.150.000` bisa per-hari (row DISBUDPORAPAR). Simpan per-hari → hitung inklusif konsisten, revisi hari otomatis. | Alternatif: simpan total. Trade-off: ganti tanggal tidak auto-koreksi. Importer sudah ada heuristic 15% untuk deteksi. |
| **Support By per unit, bukan per order** | Satu pesanan bisa 3 mobil: 2 dari 1000 Rent, 1 dari Aksa. Partner beda per unit, margin beda. | Alternatif: global per order. Trade-off: tidak akurat jika mix armada. |
| **Invoice bernomor & terkunci** | Nomor `INV/YYYY/MM/xxxx` counter per bulan, anti duplikat, audit keuangan rapi. Revisi = nomor baru + lama Batal + pembayaran pindah otomatis (tidak hilang uang). | Alternatif: edit invoice langsung. Trade-off: nomor bisa inkonsisten, jejak audit hilang. |
| **Import Excel pratinjau dulu, baru import** | Excel 918 baris, header merge, tanggal `01 Ags` tanpa tahun, harga campur — rawan salah. Pratinjau 25 baris + ringkas total/siap/error → admin cek dulu. | Alternatif: langsung import semua. Trade-off: kalau 30 baris error, cleanup manual capek. Best practice: import 25–50 baris per batch, cek Data Pesanan, baru lanjut. |

---

## 4. Semua Alternatif & Kemungkinan (jangan kaget kalau ini terjadi)

**A. Tanpa panjar** → Panjar 0 → Invoice `terbit` sisa = Grand → Catat pelunasan manual penuh.

**B. Panjar = Grand (bayar di muka)** → Invoice `lunas` langsung, tidak perlu pelunasan lagi.

**C. Panjar melebihi Grand** → Sistem `min(panjar, grand)` — otomatis cap ke Grand, sisa 0.

**D. Multi-unit 1 tagihan** → Tambah blok unit. Tiap unit beda driver/upgrade/support/harga/hari. Grand menjumlah semua. Contoh: DISBUDPORAPAR 3 unit × 1,15jt/hari × 11 hari + overtime beda-beda.

**E. Mix armada partner** → Per unit pilih Support By (1000 Rent = kosong, Aksa/Kak Maria = pilih). Margin = Grand − ΣModal×hari (modal partner tetap dicatat).

**F. Salah harga/tanggal setelah invoice terbit** → Jangan edit form langsung. Klik **Revisi Invoice** → nomor baru, lama jadi `batal`, semua pembayaran **ikut pindah** ke nomor baru. Nomor terkunci tetap terjaga.

**G. Customer batal** → **Batalkan Pesanan** (status `cancelled`) atau **Hapus** (soft-delete, data tetap di DB untuk audit).

**H. Unit bentrok jadwal** → Sistem cek `cekBentrokUnit()` — tolak simpan kalau nopol sama dipakai range tanggal tumpang tindih (kecuali status `draft`). Pesan error sebut nomor order yang bentrok. Draft boleh bentrok (belum pasti).

**I. Import histori Excel** → Upload `Rental Bulan Juli 2026.xlsx` sheet **Orderan** di **SISTEM & TOOLS → Import Excel (Orderan)**. Pratinjau 25 baris: cek Panjar, Asal mapping, tanggal, harga per-hari. Duplikat (`pemesan+nopol+tgl_mulai`) **dilewati otomatis**. Status `Lunas`→`paid`, `Finish`→`completed`, `Cancel`→`cancelled`. Harga total vs per-hari dideteksi toleransi 15%. Panjar histori tersimpan tapi tidak auto-buat invoice — terbitkan manual biar nomor rapi. Tips: jangan langsung 900 baris kalau error >5, perbaiki Excel dulu.

**J. Hari inklusif beda 1 hari vs Excel** → Sistem `hitungHari = floor((finish−mulai)/86400)+1` (27–30 = 4 hari). Excel kadang 3 hari (tidak inklusif). Uji 2 menemukan 4 baris BEDA (row 4–7, 12). Best practice: patokan sistem (inklusif) karena sesuai template WA `25-09 s/d 27-09 (3 Day)`; jika Excel lama beda 1 hari, koreksi saat input.

**K. Tanggal Excel `01 Ags` / `28 Juni` tanpa tahun** → Importer anggap 2026 (defaultYear). Serial Excel (angka 30000–60000) juga didukung. Format `d/m/Y` dan `Y-m-d` juga bisa.

**L. Harga `1.150.000` ambigu** → Di Excel, `1.150.000` di row DISBUDPORAPAR itu per-hari (bukan total), karena Total 13,7jt / 11 hari ≈ 1,24jt. Importer cek `Q×hari+tambahan ≈ Total` (±15%) → putuskan per-hari. Jika masih ambigu (mis Total kosong), fallback `Total/hari`.

**M. Overtime teks bebas** → Kolom R Excel sering `26 Jun Ovt 1 Jam 02 Juli Ovt 4 Jam Rp. 100rb/jam` — importer ekstrak angka `rb`/`ribu` terbesar sebagai nominal biaya tambahan. Jika tidak ada nominal, disimpan sebagai catatan saja.

**N. Ubah panjar setelah invoice terbit** → Panjar di order tidak mengubah invoice yang sudah terkunci. Harus **Revisi Invoice** (panjar snapshot saat terbit). Untuk belum terbit, edit form → panjar berubah → terbitkan nanti ikut.

**O. Cetak** → Invoice customer = tanpa modal/margin. Lembar internal = dengan modal & margin (untuk bos/arsip).

---

## 5. SOP Harian Admin (3 menit)

1. Pagi cek **Data Pesanan** — filter `booked`/`invoiced`.
2. WA baru → **Input Pesanan** (jangan lupa Asal User & Panjar).
3. Sore cek **Detail** → **Terbitkan Invoice** untuk yang sudah pasti (panjar otomatis).
4. Transfer masuk → **Catat Pembayaran** (jangan melebihi sisa — sistem tolak).
5. Mingguan: **Import Excel** histori kalau perlu (batch 25 baris).

## 6. Mapping Asal User (hafalan cepat)

| Tulis di Asal User | Jadi Tipe | Sumber | Contoh |
|---|---|---|---|
| `RTR`, `Rent to Rent` | **RTR (Rent to Rent)** | WhatsApp | Boavista Rent Car |
| `Corp`, `CO`, `Corporation`, `Apkasi` | **Perusahaan / Instansi** | WhatsApp | PT Guthrie, TAPEM, DISBUDPORAPAR |
| `RO` | **Repeat Order** | WhatsApp | PT. Prima Medica (order ke-2) |
| `IG` | Retail | Instagram | Customer dari IG |
| `Web`/`Website` | Retail | Website | Customer dari web |
| `Bu Tika`/`butika` | Retail | Referral | Referral Bu Tika |
| (kosong) | Retail | WhatsApp | Default |
| Lainnya | Retail | Lainnya | Tercatat mentah, tidak hilang |

> Ketik bebas: `rtr`, `RTR`, `  RTR  `, `co`, `CO` semua jadi benar (case & spasi diabaikan).

## 7. Upgrade (hafalan cepat)

`Up Reborn`, `Reborn`, `Up Avanza`, `Avanza`, `Zenix G`, `Premio Std`, `Up Zenix`, `Up Hiace` — ketik bebas, atau pilih datalist.

## 8. FAQ

**Q: Panjar sudah transfer tapi saya lupa isi di form, sudah terlanjur simpan?**
A: Edit Pesanan (sebelum terbitkan invoice) → isi Panjar → Simpan. Baru Terbitkan Invoice — DP akan ikut.

**Q: Invoice sudah terbit, mau tambah overtime?**
A: Edit Pesanan → tambah Biaya → Simpan → **Revisi Invoice**. Biaya baru ikut ke invoice baru, DP/pelunasan lama ikut pindah.

**Q: Excel lama 918 baris mau diimport semua langsung?**
A: Jangan. Pratinjau dulu. Kalau `error >5`, perbaiki Excel. Import 50 baris per batch, cek Data Pesanan tiap batch.

**Q: Hari di Excel 30, di sistem 31?**
A: Sistem inklusif (3 Juli s/d 2 Agust = 31 hari). Excel lama ada yang tidak inklusif. Ikuti sistem.

---

## 9. Kontak & Versi

- Project: `C:\laragon\www\rentalnusantara` — `http://192.168.0.15:8081` / `http://127.0.0.1:8081`
- Login: `admin / admin123`
- Menu Import: **SISTEM & TOOLS → Import Excel (Orderan)**
- DB: `rentalnusantara` — enum `tipe_pelanggan` = `retail, corporate, RO, RTR`
- Tag backup: `backup-pre-panjar-...` & `backup-post-import-...` — `git log --oneline`
- Dokumen ini + `DESAIN-DASHBOARD-RESERVASI.md` + catatan Obsidian `2026-09-25_...md`
- Uji: `tests/uji_1_map_hitung.php` (22/22 PASS), `uji_2_import_10baris.php` (10 baris mapping benar), `uji_3_e2e.php` (3 skenario PASS + revisi + cleanup)

> Jika RTR nanti punya sub-tipe (RTR Langganan vs Insidental), tidak perlu ubah DB — cukup tambah kolom atau pakai Keterangan. Struktur sekarang sudah siap.

— Caai • 25 Sept 2026
