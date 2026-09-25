<?php
// Uji 1: mapAsalUser + helper + hitung + panjar - dijalankan via: php C:/laragon/www/rentalnusantara/tests/uji_1_map_hitung.php
require_once 'C:/laragon/www/rentalnusantara/includes/functions.php';

echo "=== UJI 1: mapAsalUser + label + hitungHari + rupiah + panjar ===\n\n";

// --- 1a. mapAsalUser ---
$cases = [
    'RTR' => ['tipe'=>'RTR','sumber'=>'wa'],
    'rtr' => ['tipe'=>'RTR','sumber'=>'wa'],
    'Rent to Rent' => ['tipe'=>'RTR','sumber'=>'wa'],
    'rent-to-rent' => ['tipe'=>'RTR','sumber'=>'wa'],
    'renttorent' => ['tipe'=>'RTR','sumber'=>'wa'],
    'Corp' => ['tipe'=>'corporate','sumber'=>'wa'],
    'CO' => ['tipe'=>'corporate','sumber'=>'wa'],
    'co' => ['tipe'=>'corporate','sumber'=>'wa'],
    'Corporation' => ['tipe'=>'corporate','sumber'=>'wa'],
    'corporation' => ['tipe'=>'corporate','sumber'=>'wa'],
    'RO' => ['tipe'=>'RO','sumber'=>'wa'],
    'ro' => ['tipe'=>'RO','sumber'=>'wa'],
    'Apkasi' => ['tipe'=>'corporate','sumber'=>'wa'],
    'IG' => ['tipe'=>'retail','sumber'=>'instagram'],
    'Web' => ['tipe'=>'retail','sumber'=>'website'],
    'website' => ['tipe'=>'retail','sumber'=>'website'],
    'Bu Tika' => ['tipe'=>'retail','sumber'=>'referral'],
    'butika' => ['tipe'=>'retail','sumber'=>'referral'],
    '' => ['tipe'=>'retail','sumber'=>'wa'],
    'Ngasal' => ['tipe'=>'retail','sumber'=>'lainnya'],
    '  RTR  ' => ['tipe'=>'RTR','sumber'=>'wa'],
    ' CO ' => ['tipe'=>'corporate','sumber'=>'wa'],
];
$pass = 0; $fail = 0;
foreach ($cases as $raw => $exp) {
    $m = mapAsalUser($raw);
    $ok = ($m['tipe'] === $exp['tipe'] && $m['sumber'] === $exp['sumber']);
    if ($ok) $pass++; else $fail++;
    $status = $ok ? 'PASS' : 'FAIL';
    echo sprintf("[%s] raw='%s' => tipe='%s' (%s) sumber='%s' (%s) raw_out='%s' | expect tipe='%s' sumber='%s'\n",
        $status, $raw, $m['tipe'], labelTipePelanggan($m['tipe']), $m['sumber'], labelSumber($m['sumber']), $m['raw'], $exp['tipe'], $exp['sumber']
    );
}
echo "\nmapAsalUser: $pass PASS, $fail FAIL dari ".count($cases)." kasus\n\n";

// --- 1b. labelTipePelanggan ---
echo "--- labelTipePelanggan ---\n";
foreach (['retail','corporate','RO','RTR','unknown'] as $t) {
    echo "  $t => ".labelTipePelanggan($t)."\n";
}
echo "\n";

// --- 1c. hitungHari (inklusif) ---
echo "--- hitungHari (inklusif: 27-30 = 4 hari) ---\n";
$hariCases = [
    ['2026-07-03','2026-08-02',31],
    ['2026-09-25','2026-09-27',3],
    ['2026-09-25','2026-09-25',1],
    ['2026-09-27','2026-09-25',0],
    ['','2026-09-25',0],
];
foreach ($hariCases as [$a,$b,$exp]) {
    $got = hitungHari($a,$b);
    $st = $got===$exp?'PASS':'FAIL';
    echo "  [$st] hitungHari('$a','$b') = $got (expect $exp)\n";
}
echo "\n";

// --- 1d. rupiah & angka ---
echo "--- rupiah & angka ---\n";
echo "  rupiah(3800000) = ".rupiah(3800000)."\n";
echo "  rupiah(10500000) = ".rupiah(10500000)."\n";
echo "  angka('Rp 1.200.000') = ".angka('Rp 1.200.000')." (expect 1200000)\n";
echo "  angka('1.0500000.0') = ".angka('10500000.0')."\n";
echo "  normalisasiHp('081234567890') = ".normalisasiHp('081234567890')."\n";
echo "  normalisasiHp('+62812') = ".normalisasiHp('+62812')."\n";
echo "\n";

// --- 1e. panjar logic (max 0) ---
echo "--- logika Panjar / Sisa (simulasi form) ---\n";
foreach ([[3800000,1000000],[3800000,0],[3800000,3800000],[3800000,5000000],[0,0]] as [$grand,$panjar]) {
    $sisa = max(0, $grand - $panjar);
    $panjarEfektif = min($panjar, $grand);
    echo "  Grand ".rupiah($grand)." - Panjar ".rupiah($panjar)." => Sisa ".rupiah($sisa)." (panjar efektif ".rupiah($panjarEfektif).")\n";
}
echo "\n";

// --- 1f. hitungOrder via DB (order id 10 yang ada) ---
echo "--- hitungOrder DB (pakai order id 10 yang sudah ada) ---\n";
try {
    $db = getDB();
    $r = $db->query("SELECT id, nomor_order, grand_total, panjar FROM orders WHERE id=10");
    if ($row = $r->fetch_assoc()) {
        echo "  Order 10: ".json_encode($row, JSON_UNESCAPED_UNICODE)."\n";
        $hit = hitungOrder(10);
        echo "  hitungOrder(10) = ".json_encode($hit, JSON_UNESCAPED_UNICODE)."\n";
        echo "  grand=".rupiah($hit['grand_total'])." margin=".rupiah($hit['margin'])."\n";
    } else {
        echo "  Order 10 tidak ada\n";
    }
} catch (Throwable $e) {
    echo "  ERROR hitungOrder: ".$e->getMessage()."\n";
}
echo "\n=== UJI 1 SELESAI ===\n";
