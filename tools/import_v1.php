<?php
/**
 * tools/import_v1.php — importer CLI "setia sheet" untuk file Orderan Juli 2026.
 *
 * Tujuan: hasil impor sama persis dengan sheet Orderan (hari pakai "Total Hari",
 * total tagihan = kolom S, pengeluaran = kolom Z, laba/insentif/laba bersih diambil
 * dari kolom AA/AB/AC dengan insentif 2,75%).
 *
 * Jalankan:  php tools/import_v1.php "D:/maganghub/New folder/Rental Bulan Juli 2026_v1.xlsx"
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "Jalankan lewat CLI saja.\n"); exit(1); }

require_once __DIR__ . '/../includes/functions.php';
if (file_exists(__DIR__ . '/../vendor/autoload.php')) require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = $argv[1] ?? 'D:/maganghub/New folder/Rental Bulan Juli 2026_v1.xlsx';
if (!is_file($path)) { fwrite(STDERR, "File tidak ada: $path\n"); exit(1); }

$db = getDB();

/* ===== helper lokal (sama dengan import_xlsx.php) ===== */
function xl($v): string { return trim((string) ($v ?? '')); }
function xln($v): int {
    if ($v === null || $v === '') return 0;
    if (is_numeric($v)) return (int) round((float) $v);
    $s = (string) $v;
    if (trim($s) === '-' || trim($s) === '') return 0;
    $s = preg_replace('/[^0-9]/', '', $s);
    return (int) ($s === '' ? 0 : $s);
}
function xldate($val, int $defaultYear = 2026): ?string {
    if ($val === null || $val === '') return null;
    if ($val instanceof DateTime) return $val->format('Y-m-d');
    if (is_numeric($val) && (float) $val > 30000 && (float) $val < 60000) {
        $base = new DateTime('1899-12-30');
        $base->modify('+' . (int) $val . ' days');
        return $base->format('Y-m-d');
    }
    $s = trim((string) $val);
    if ($s === '' || $s === '-') return null;
    $s = preg_replace('/\s+/', ' ', $s);
    $mapBulan = ['jan'=>1,'januari'=>1,'feb'=>2,'februari'=>2,'mar'=>3,'maret'=>3,'apr'=>4,'april'=>4,'mei'=>5,'jun'=>6,'juni'=>6,'jul'=>7,'juli'=>7,'agu'=>8,'ags'=>8,'agustus'=>8,'sep'=>9,'sept'=>9,'september'=>9,'okt'=>10,'oktober'=>10,'nov'=>11,'november'=>11,'des'=>12,'desember'=>12];
    if (preg_match('/^(\d{1,2})\s+([A-Za-z]+)$/u', $s, $m)) {
        $b = $mapBulan[strtolower($m[2])] ?? null;
        if ($b) return sprintf('%04d-%02d-%02d', $defaultYear, $b, (int) $m[1]);
    }
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})$/', $s, $m)) {
        $y = (int) $m[3]; if ($y < 100) $y += 2000;
        return sprintf('%04d-%02d-%02d', $y, (int) $m[2], (int) $m[1]);
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    $ts = strtotime($s);
    return $ts ? date('Y-m-d', $ts) : null;
}

$ss = IOFactory::load($path);
$ws = $ss->getSheetByName('Orderan') ?: $ss->getActiveSheet();
$maxRow = min($ws->getHighestDataRow(), 950);

$masuk = 0; $lewati = 0; $gagal = 0; $log = [];

for ($r = 4; $r <= $maxRow; $r++) {
    $keterangan = xl($ws->getCell('A' . $r)->getCalculatedValue());
    $handleBy   = xl($ws->getCell('B' . $r)->getCalculatedValue());
    $asalRaw    = mb_substr(preg_replace('/\s+/', ' ', xl($ws->getCell('C' . $r)->getCalculatedValue())), 0, 30);
    $unitNama   = xl($ws->getCell('D' . $r)->getCalculatedValue());
    $nopolRaw   = strtoupper(xl($ws->getCell('E' . $r)->getCalculatedValue()));
    $asalUnit   = normalisasiNama(xl($ws->getCell('F' . $r)->getCalculatedValue()));
    $driverNama = xl($ws->getCell('G' . $r)->getCalculatedValue());
    $rute       = xl($ws->getCell('H' . $r)->getCalculatedValue());
    $upgrade    = xl($ws->getCell('I' . $r)->getCalculatedValue());
    if ($upgrade === '-') $upgrade = '';
    $includeRaw = xl($ws->getCell('J' . $r)->getCalculatedValue());
    $pemesan    = xl($ws->getCell('K' . $r)->getCalculatedValue());
    $tamu       = xl($ws->getCell('L' . $r)->getCalculatedValue());
    $mulaiRaw   = $ws->getCell('M' . $r)->getCalculatedValue();
    $finishRaw  = $ws->getCell('N' . $r)->getCalculatedValue();
    $hariExcel  = xln($ws->getCell('O' . $r)->getCalculatedValue());
    $panjar     = xln($ws->getCell('P' . $r)->getCalculatedValue());
    $qRaw       = xln($ws->getCell('Q' . $r)->getCalculatedValue());
    $rRaw       = xl($ws->getCell('R' . $r)->getCalculatedValue());
    $totalRp    = xln($ws->getCell('S' . $r)->getCalculatedValue());
    $modalUnit  = xln($ws->getCell('T' . $r)->getCalculatedValue());
    $gaji       = xln($ws->getCell('U' . $r)->getCalculatedValue());
    $bbm        = xln($ws->getCell('V' . $r)->getCalculatedValue());
    $toll       = xln($ws->getCell('W' . $r)->getCalculatedValue());
    $rpLain     = xln($ws->getCell('X' . $r)->getCalculatedValue());
    $ketBiaya   = xl($ws->getCell('Y' . $r)->getCalculatedValue());
    $pengeluaran = xln($ws->getCell('Z' . $r)->getCalculatedValue());
    $labaExcel   = xln($ws->getCell('AA' . $r)->getCalculatedValue());
    $insentifExcel = xln($ws->getCell('AB' . $r)->getCalculatedValue());
    $labaBersihExcel = xln($ws->getCell('AC' . $r)->getCalculatedValue());
    $statusBayar = xl($ws->getCell('AD' . $r)->getCalculatedValue());
    $statusUnit  = xl($ws->getCell('AE' . $r)->getCalculatedValue());
    $refArsip    = xl($ws->getCell('AF' . $r)->getCalculatedValue());

    if ($pemesan === '' && $unitNama === '' && $nopolRaw === '') continue;
    if ($pemesan === '') { $lewati++; continue; }

    $tglMulai  = xldate($mulaiRaw);
    $tglFinish = xldate($finishRaw);
    if (!$tglMulai || !$tglFinish) { $gagal++; $log[] = "Baris $r: tanggal tidak valid ($pemesan)"; continue; }
    if (!$unitNama) { $gagal++; $log[] = "Baris $r: unit kosong ($pemesan)"; continue; }

    $nopolRaw = preg_replace('/\s+/', '', $nopolRaw);
    if ($nopolRaw === '') $nopolRaw = '-';

    /* dedup: pemesan + nopol + tgl_mulai */
    $d = $db->prepare('SELECT o.id FROM orders o JOIN order_items i ON i.order_id=o.id
                       WHERE o.nama_pesanan=? AND o.tgl_mulai=? AND i.nopol=? AND o.deleted_at IS NULL LIMIT 1');
    $d->bind_param('sss', $pemesan, $tglMulai, $nopolRaw);
    $d->execute();
    if ($d->get_result()->fetch_assoc()) { $lewati++; continue; }

    /* hari = Total Hari (kolom O), fallback hitung inklusif */
    $hari = $hariExcel > 0 ? $hariExcel : hitungHari($tglMulai, $tglFinish);
    if ($hari < 1) $hari = 1;

    /* uang: total jual = S (fallback Q), total modal = Z (fallback jumlah komponen) */
    $totalJual = $totalRp > 0 ? $totalRp : $qRaw;
    $totalModal = $pengeluaran > 0 ? $pengeluaran : ($modalUnit + $gaji + $bbm + $toll + $rpLain);

    $hargaJualPerHari  = $hari > 0 ? (int) round($totalJual / $hari) : $totalJual;
    $hargaModalPerHari = $hari > 0 ? (int) round($totalModal / $hari) : $totalModal;

    /* laba / insentif / laba bersih: pakai angka Excel, fallback hitung 2,75% */
    $laba = $labaExcel > 0 ? $labaExcel : max(0, $totalJual - $totalModal);
    $insentif = $insentifExcel > 0 ? $insentifExcel : (int) round($laba * 0.0275);
    $labaBersih = $labaBersihExcel > 0 ? $labaBersihExcel : ($laba - $insentif);

    $rincian = [];
    if ($modalUnit > 0) $rincian['modal_unit'] = $modalUnit;
    if ($gaji > 0)      $rincian['gaji_driver'] = $gaji;
    if ($bbm > 0)       $rincian['bahan_bakar'] = $bbm;
    if ($toll > 0)      $rincian['toll_parkir'] = $toll;
    if ($rpLain > 0)    $rincian['lainnya'] = $rpLain;
    if ($ketBiaya !== '' && $ketBiaya !== '-') $rincian['ket'] = $ketBiaya;
    $rincianJson = $rincian ? json_encode($rincian, JSON_UNESCAPED_UNICODE) : null;

    /* mapping asal user */
    $map = mapAsalUser($asalRaw);
    $tipePelanggan = $map['tipe'];
    $sumber = $map['sumber'];

    /* kota + wilayah dari rute */
    $kota = $rute;
    if (strpos($rute, ' - ') !== false) $kota = trim(explode(' - ', $rute)[0]);
    elseif (strpos($rute, '-') !== false) $kota = trim(explode('-', $rute)[0]);
    $kota = mb_substr($kota, 0, 100);
    if ($kota === '') $kota = '-';
    $wilayah = 'dalam_kota';
    $rl = strtolower($rute);
    if (str_contains($rl, 'sumatera') || str_contains($rl, 'palembang') || str_contains($rl, 'tapanuli')
        || str_contains($rl, 'brastagi') || str_contains($rl, 'sibolangit') || str_contains($rl, 'siantar')) {
        $wilayah = 'luar_kota';
    }

    /* partner / support by */
    $partnerId = null;
    $asu = strtolower(preg_replace('/\s+/', '', $asalUnit));
    if ($asalUnit !== '' && $asu !== '1000rent' && $asu !== '1000rentcar') {
        $ps = $db->prepare('SELECT id FROM partners WHERE LOWER(nama)=LOWER(?) AND deleted_at IS NULL LIMIT 1');
        $ps->bind_param('s', $asalUnit);
        $ps->execute();
        $prow = $ps->get_result()->fetch_assoc();
        if ($prow) $partnerId = (int) $prow['id'];
        else {
            $pi = $db->prepare('INSERT INTO partners (nama) VALUES (?)');
            $pi->bind_param('s', $asalUnit);
            $pi->execute();
            $partnerId = (int) $db->insert_id;
        }
    }

    /* unit lookup/create (nopol '-' = tidak ada nopol di Excel, lewati master) */
    $unitId = null;
    if ($nopolRaw !== '-') {
        $us = $db->prepare('SELECT id FROM units WHERE nopol=? AND deleted_at IS NULL LIMIT 1');
        $us->bind_param('s', $nopolRaw);
        $us->execute();
        $urow = $us->get_result()->fetch_assoc();
        if ($urow) $unitId = (int) $urow['id'];
        else {
            $kodeUnit = 'IMP-' . preg_replace('/[^A-Z0-9]/', '', $nopolRaw);
            $jenis = 'MPV'; $statusUnitDb = 'ready';
            $milikSendiri = ($asu === '' || $asu === '1000rent' || $asu === '1000rentcar');
            $pemilik = $milikSendiri ? 'sendiri' : 'partner';
            $partnerUnitBind = $milikSendiri ? null : $partnerId;
            $ui = $db->prepare('INSERT INTO units (kode_unit,nama_unit,nopol,jenis,pemilik,partner_id,harga_modal_default,harga_jual_default,status) VALUES (?,?,?,?,?,?,?,?,?)');
            $ui->bind_param('sssssiiss', $kodeUnit, $unitNama, $nopolRaw, $jenis, $pemilik, $partnerUnitBind, $hargaModalPerHari, $hargaJualPerHari, $statusUnitDb);
            $ui->execute();
            $unitId = (int) $db->insert_id;
        }
    }

    /* driver lookup/create */
    $driverId = null;
    if ($driverNama !== '' && $driverNama !== '-') {
        $ds = $db->prepare('SELECT id FROM drivers WHERE LOWER(nama)=LOWER(?) AND deleted_at IS NULL LIMIT 1');
        $ds->bind_param('s', $driverNama);
        $ds->execute();
        $drow = $ds->get_result()->fetch_assoc();
        if ($drow) $driverId = (int) $drow['id'];
        else {
            $di = $db->prepare('INSERT INTO drivers (nama,status) VALUES (?,?)');
            $sd = 'aktif';
            $di->bind_param('ss', $driverNama, $sd);
            $di->execute();
            $driverId = (int) $db->insert_id;
        }
    }

    /* customer lookup/create */
    $cs = $db->prepare('SELECT id FROM customers WHERE LOWER(nama_pesanan)=LOWER(?) AND deleted_at IS NULL LIMIT 1');
    $cs->bind_param('s', $pemesan);
    $cs->execute();
    $crow = $cs->get_result()->fetch_assoc();
    if ($crow) $customerId = (int) $crow['id'];
    else {
        $tipeCust = preg_match('/\b(PT|CV|UD|Dinas|Kantor|Badan|Otoritas|Bank|Universitas|Sekolah|Prov|Kab)\b/i', $pemesan) ? 'instansi' : 'perorangan';
        $ci = $db->prepare('INSERT INTO customers (tipe,nama_pesanan,nama_pic,hp_pic,sumber,status) VALUES (?,?,?,?,?, "baru")');
        $np = ''; $hp = '';
        $ci->bind_param('sssss', $tipeCust, $pemesan, $np, $hp, $sumber);
        $ci->execute();
        $customerId = (int) $db->insert_id;
    }

    /* status mapping */
    $su = strtolower(trim($statusUnit)); $sb = strtolower(trim($statusBayar));
    if ($su === 'cancel' || $su === 'batal') $statusOrder = 'cancelled';
    elseif ($sb === 'lunas') $statusOrder = 'paid';
    else $statusOrder = 'completed';

    $nomor = nomorDokumen('order');
    $createdBy = idUser();
    $tujuan = $rute;
    $catatan = '';
    if ($ketBiaya !== '' && $ketBiaya !== '-') $catatan = $ketBiaya;
    if ($rRaw !== '' && $rRaw !== '-') {
        $catatan = trim($catatan . ' | Tambahan: ' . $rRaw, ' |');
    }

    $db->begin_transaction();
    try {
        $fields = [
            ['nomor_order', $nomor, 's'], ['customer_id', $customerId, 'i'],
            ['tipe_pelanggan', $tipePelanggan, 's'], ['wilayah_pelayanan', $wilayah, 's'],
            ['kota', $kota, 's'], ['tgl_mulai', $tglMulai, 's'], ['tgl_finish', $tglFinish, 's'],
            ['jumlah_hari', $hari, 'i'], ['jam', '', 's'], ['jam_koordinasi', 0, 'i'],
            ['standby_point', '', 's'], ['flight', '', 's'], ['tujuan', $tujuan, 's'],
            ['nama_pesanan', $pemesan, 's'], ['nama_pic', '', 's'], ['hp_pic', '', 's'],
            ['data_tamu', $tamu, 's'], ['sumber', $sumber, 's'], ['asal_user_raw', $asalRaw, 's'],
            ['handle_by', $handleBy, 's'], ['partner_id', null, 'i'], ['panjar', $panjar, 'i'],
            ['keterangan', $keterangan, 's'], ['status', $statusOrder, 's'], ['catatan', $catatan, 's'],
            ['created_by', $createdBy, 'i'],
            ['laba', $laba, 'i'], ['insentif', $insentif, 'i'], ['laba_bersih', $labaBersih, 'i'],
            ['rincian_biaya', $rincianJson, 's'], ['ref_arsip', ($refArsip !== '' ? $refArsip : null), 's'],
        ];
        $kol = []; $types = ''; $vals = [];
        foreach ($fields as $fl) { $kol[] = '`' . $fl[0] . '`'; $types .= $fl[2]; $vals[] = $fl[1]; }
        $st = $db->prepare('INSERT INTO orders (' . implode(',', $kol) . ') VALUES (' . implode(',', array_fill(0, count($kol), '?')) . ')');
        $st->bind_param($types, ...$vals);
        $st->execute();
        $orderId = (int) $db->insert_id;

        /* item (subtotal = nilai persis Excel: total jual & total pengeluaran) */
        $driverIdBind = $driverId;
        $partnerIdBind = $partnerId;
        $hpKosong = '';
        $subModal = $totalModal;
        $subJual = $totalJual;
        $catUnit = '';
        $insItem = $db->prepare('INSERT INTO order_items (order_id,unit_id,driver_id,partner_id,nama_unit,nopol,upgrade,nama_driver,hp_driver,harga_modal_per_hari,harga_jual_per_hari,jumlah_hari,subtotal_modal,subtotal_jual,catatan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $insItem->bind_param('iiiisssssiiiiis', $orderId, $unitId, $driverIdBind, $partnerIdBind, $unitNama, $nopolRaw, $upgrade, $driverNama, $hpKosong, $hargaModalPerHari, $hargaJualPerHari, $hari, $subModal, $subJual, $catUnit);
        $insItem->execute();

        /* include (biaya 0, tidak mengubah total) */
        if ($includeRaw !== '' && $includeRaw !== '-') {
            $parts = preg_split('/[+,;]/', $includeRaw);
            foreach ($parts as $pinc) {
                $pinc = trim($pinc);
                if ($pinc === '') continue;
                $like = '%' . $pinc . '%';
                $st2 = $db->prepare('SELECT id,nama FROM includes WHERE nama LIKE ? LIMIT 1');
                $st2->bind_param('s', $like);
                $st2->execute();
                $incRow = $st2->get_result()->fetch_assoc();
                if ($incRow) {
                    $iid = (int) $incRow['id']; $nm = $incRow['nama']; $bi = 0;
                    $insInc = $db->prepare('INSERT INTO order_includes (order_id,include_id,nama,biaya) VALUES (?,?,?,?)');
                    $insInc->bind_param('iisi', $orderId, $iid, $nm, $bi);
                    $insInc->execute();
                } else {
                    $iidNull = null; $bi = 0;
                    $insInc = $db->prepare('INSERT INTO order_includes (order_id,include_id,nama,biaya) VALUES (?,?,?,?)');
                    $insInc->bind_param('iisi', $orderId, $iidNull, $pinc, $bi);
                    $insInc->execute();
                }
            }
        }

        $db->commit();
        hitungOrder($orderId);
        catatStatus($orderId, null, $statusOrder, 'Import v1 baris ' . $r . ' (' . $pemesan . ')');
        $masuk++;
    } catch (Throwable $e) {
        $db->rollback();
        $gagal++; $log[] = "Baris $r gagal: " . $e->getMessage();
    }
}

echo "SELESAI\n";
echo "Masuk: $masuk\nLewati (duplikat): $lewati\nGagal: $gagal\n";
if ($log) { echo "--- log gagal ---\n"; foreach ($log as $l) echo $l . "\n"; }
