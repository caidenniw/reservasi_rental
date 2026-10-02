<?php
/**
 * tests/uji_4_parse_teks.php — uji unit parser teks pesanan.
 * Jalankan: php tests/uji_4_parse_teks.php
 */
require_once __DIR__ . '/../includes/parse_teks_lib.php';

$lulus = 0; $gagal = 0;
function cek(string $label, $dapat, $harap): void
{
    global $lulus, $gagal;
    $ok = $dapat === $harap;
    if ($ok) { $lulus++; echo "  [OK]   $label\n"; }
    else { $gagal++; echo "  [GAGAL] $label -> dapat: " . var_export($dapat, true) . " | harap: " . var_export($harap, true) . "\n"; }
}

/* ---------- KASUS 1: contoh teks Deni (1 unit) ---------- */
$teks1 = <<<TXT
PT. Seribu Nusantara Rental

Pelayanan: Dalam Kota Medan
Tanggal: 03-10-2026 s/d 05-10-2026 (3 Day)
___________________________
Nama Driver : Budi Santoso
Hp/Wa : 0812-1000-0012
Unit : Daihatsu Xenia
No. Plat : BK1006RN
___________________________
Stanby: -
Flight: -
Jam: -

Pesanan : Ibu Sari
Pic : Sari
Hp/Wa : 0811-2000-0004

Include : -

Harga Daihatsu Xenia : Rp 300.000/hari
Total : Rp 900.000 (3 hari)

Terimakasih atas Pilihan Perjalanan Anda Bersama Kami. Anda Dapat Memesan Rental Mobil SE INDONESIA Karena Kami Hadir Di 38 PROVINSI.

"1000 RENT CAR - SOLUSI PERJALANAN MERANGKAI NUSANTARA"

Instagram/TikTok : www.instagram.com/1000nusantara.id
Website : www.1000nusantara.id
Email : bisnis@1000nusantara.id
TXT;

echo "KASUS 1 — contoh Deni (1 unit)\n";
$r = parseTeksPesanan($teks1);
cek('wilayah', $r['data']['wilayah_pelayanan'], 'dalam_kota');
cek('kota', $r['data']['kota'], 'Medan');
cek('tgl_mulai', $r['data']['tgl_mulai'], '2026-10-03');
cek('tgl_finish', $r['data']['tgl_finish'], '2026-10-05');
cek('jumlah_hari', $r['data']['jumlah_hari'], 3);
cek('jumlah item', count($r['items']), 1);
cek('nama_unit', $r['items'][0]['nama_unit'], 'Daihatsu Xenia');
cek('nopol', $r['items'][0]['nopol'], 'BK1006RN');
cek('nama_driver', $r['items'][0]['nama_driver'], 'Budi Santoso');
cek('hp_driver', $r['items'][0]['hp_driver'], '0812-1000-0012');
cek('harga_jual_per_hari', $r['items'][0]['harga_jual_per_hari'], 300000);
cek('nama_pesanan', $r['data']['nama_pesanan'], 'Ibu Sari');
cek('nama_pic', $r['data']['nama_pic'], 'Sari');
cek('hp_pic', $r['data']['hp_pic'], '0811-2000-0004');
cek('standby kosong', $r['data']['standby_point'], '');
cek('flight kosong', $r['data']['flight'], '');
cek('jam kosong', $r['data']['jam'], '');
cek('include kosong', $r['includes'], []);
cek('total_teks', $r['total_teks'], 900000);
cek('tanpa baris tak dikenali', $r['tidak_dikenali'], []);
cek('tanpa catatan', $r['catatan'], []);
cek('yakin 100', $r['yakin'], 100);

/* ---------- KASUS 2: dua armada + jam koordinasi + include + biaya ---------- */
$teks2 = <<<TXT
PT. Seribu Nusantara Rental

Pelayanan: Luar Kota Gunung Sitoli
Tanggal: 10-11-2026 s/d 11-11-2026 (2 Day)
___________________________
Nama Driver : Andi
Hp/Wa : 0813-1
Unit : Toyota Avanza
No. Plat : BK1234AA
- - - - - - - - - - - - - -
Nama Driver : -
Hp/Wa : -
Unit : Hiace Commuter
No. Plat : BK9999ZZ
___________________________
Stanby: Bandara Binaka
Flight: GA 123
Jam: Kordinasi dengan user

Pesanan : PT. Apkasi
Pic : Rina
Hp/Wa : 0811-9

Include : Tol+BBM

Harga Toyota Avanza : Rp 450.000/hari
Harga Hiace Commuter : Rp 1.200.000/hari
Overtime : Rp 150.000
Total : Rp 3.450.000 (2 hari)
TXT;

echo "\nKASUS 2 — 2 armada, luar kota, jam koordinasi, include, biaya\n";
$r2 = parseTeksPesanan($teks2);
cek('wilayah', $r2['data']['wilayah_pelayanan'], 'luar_kota');
cek('kota', $r2['data']['kota'], 'Gunung Sitoli');
cek('jumlah item', count($r2['items']), 2);
cek('item1 nopol', $r2['items'][0]['nopol'], 'BK1234AA');
cek('item1 harga', $r2['items'][0]['harga_jual_per_hari'], 450000);
cek('item2 unit', $r2['items'][1]['nama_unit'], 'Hiace Commuter');
cek('item2 driver kosong', $r2['items'][1]['nama_driver'], '');
cek('item2 harga', $r2['items'][1]['harga_jual_per_hari'], 1200000);
cek('standby', $r2['data']['standby_point'], 'Bandara Binaka');
cek('flight', $r2['data']['flight'], 'GA 123');
cek('jam koordinasi', $r2['data']['jam_koordinasi'], 1);
cek('jam kosong', $r2['data']['jam'], '');
cek('nama_pesanan', $r2['data']['nama_pesanan'], 'PT. Apkasi');
cek('include', $r2['includes'], ['Tol', 'BBM']);
cek('biaya overtime', $r2['biaya'][0]['nama'] ?? '', 'Overtime');
cek('biaya nominal', $r2['biaya'][0]['nominal'] ?? 0, 150000);
cek('total_teks', $r2['total_teks'], 3450000);

/* ---------- KASUS 3: driver kosong + format tahun dulu (Y-m-d) ---------- */
$teks3 = <<<TXT
Pelayanan: Dalam Kota Medan
Tanggal: 2026-12-01 s/d 2026-12-01 (1 Day)
___________________________
Nama Driver : -
Hp/Wa : -
Unit : Daihatsu Xenia
No. Plat : BK1006RN
___________________________
Stanby: -
Flight: -
Jam: -

Pesanan : Ibu Sari
Pic : Sari
Hp/Wa : 0811-2000-0004

Include : -

Harga Daihatsu Xenia : Rp 300.000/hari
Total : Rp 300.000 (1 hari)
TXT;

echo "\nKASUS 3 — driver kosong, tanggal format Y-m-d, 1 hari\n";
$r3 = parseTeksPesanan($teks3);
cek('tgl format Y-m-d', $r3['data']['tgl_mulai'], '2026-12-01');
cek('jumlah hari 1', $r3['data']['jumlah_hari'], 1);
cek('item tetap terbaca', count($r3['items']), 1);
cek('driver kosong', $r3['items'][0]['nama_driver'], '');
cek('nopol', $r3['items'][0]['nopol'], 'BK1006RN');

/* ---------- KASUS 4: total sengaja beda -> harus muncul peringatan ---------- */
$teks4 = str_replace('Total : Rp 900.000 (3 hari)', 'Total : Rp 800.000 (3 hari)', $teks1);
echo "\nKASUS 4 — total tidak cocok -> peringatan\n";
$r4 = parseTeksPesanan($teks4);
$ada = false;
foreach ($r4['catatan'] as $c) { if (stripos($c, 'tidak sama') !== false) $ada = true; }
cek('peringatan total muncul', $ada, true);

/* ---------- ringkasan ---------- */
echo "\n==============================\n";
echo "LULUS: $lulus   GAGAL: $gagal\n";
exit($gagal > 0 ? 1 : 0);
