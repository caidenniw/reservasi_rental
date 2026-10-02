<?php
/**
 * api/parse_pesanan.php — endpoint "Bedah teks pesanan" (JSON).
 *
 * Alur:
 *   1. Parser deterministik (includes/parse_teks_lib.php) membaca teks template.
 *   2. Kalau hasilnya lemah (keyakinan < 60 atau tanggal/nama pesanan kosong),
 *      barulah dicoba fallback AI untuk MENAMBAL field yang belum terbaca.
 *   3. Hasil dilengkapi pencocokan ke data master (unit/driver/customer/include):
 *      harga modal & jual diambil dari master unit bila ada.
 *
 * Endpoint ini TIDAK menyimpan apa pun. Penyimpanan tetap lewat form pesanan
 * yang direview pengguna.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/parse_teks_lib.php';
require_once __DIR__ . '/../includes/asisten_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function jawab(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) {
    jawab(['ok' => false, 'error' => 'Sesi berakhir. Silakan masuk ulang.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jawab(['ok' => false, 'error' => 'Metode tidak diizinkan.'], 405);
}

$token = (string) ($_POST['token'] ?? '');
if ($token === '' || !hash_equals(parseToken(), $token)) {
    jawab(['ok' => false, 'error' => 'Token keamanan tidak valid. Muat ulang halaman.'], 419);
}

$teks = trim((string) ($_POST['teks'] ?? ''));
if ($teks === '') {
    jawab(['ok' => false, 'error' => 'Teks pesanan masih kosong.'], 422);
}
if (mb_strlen($teks) > 8000) {
    jawab(['ok' => false, 'error' => 'Teks terlalu panjang (maksimal 8.000 karakter).'], 422);
}

/* Pembatas sederhana: maksimal 30 bedah per 10 menit per sesi. */
$kini = time();
$_SESSION['parse_hits'] = array_values(array_filter($_SESSION['parse_hits'] ?? [], fn($t) => $kini - (int) $t < 600));
if (count($_SESSION['parse_hits']) >= 30) {
    jawab(['ok' => false, 'error' => 'Batas 30 bedah per 10 menit tercapai. Coba lagi beberapa saat lagi.'], 429);
}
$_SESSION['parse_hits'][] = $kini;

/* ===================== 1. PARSER DETERMINISTIK ===================== */
$hasil = parseTeksPesanan($teks);
$sumber = 'parser';

/* ===================== 2. FALLBACK AI (hanya bila perlu) ===================== */
$lemah = $hasil['yakin'] < 60
      || $hasil['data']['tgl_mulai'] === ''
      || $hasil['data']['nama_pesanan'] === '';

if ($lemah && asistenSiap()) {
    $ai = asistenParseJson($teks);
    if (!empty($ai['ok']) && is_array($ai['json'])) {
        $j = $ai['json'];
        $sumber = 'parser+ai';
        foreach (['wilayah_pelayanan', 'kota', 'jam', 'standby_point', 'flight', 'nama_pesanan', 'nama_pic', 'hp_pic'] as $k) {
            if (($hasil['data'][$k] ?? '') === '' && !empty($j[$k])) $hasil['data'][$k] = (string) $j[$k];
        }
        foreach (['tgl_mulai', 'tgl_finish'] as $k) {
            if ($hasil['data'][$k] === '' && !empty($j[$k])) {
                $t = pn_tanggal((string) $j[$k]);
                if ($t !== '') $hasil['data'][$k] = $t;
            }
        }
        if ($hasil['data']['wilayah_pelayanan'] === 'dalam_kota' && ($j['wilayah_pelayanan'] ?? '') === 'luar_kota') {
            $hasil['data']['wilayah_pelayanan'] = 'luar_kota';
        }
        if ($hasil['data']['jumlah_hari'] <= 0 && !empty($j['jumlah_hari'])) $hasil['data']['jumlah_hari'] = (int) $j['jumlah_hari'];
        if ((int) $hasil['data']['jam_koordinasi'] === 0 && !empty($j['jam_koordinasi'])) $hasil['data']['jam_koordinasi'] = 1;
        if (!$hasil['items'] && !empty($j['items']) && is_array($j['items'])) {
            foreach ($j['items'] as $it) {
                $hasil['items'][] = [
                    'nama_driver'         => (string) ($it['nama_driver'] ?? ''),
                    'hp_driver'           => (string) ($it['hp_driver'] ?? ''),
                    'nama_unit'           => (string) ($it['nama_unit'] ?? ''),
                    'nopol'               => strtoupper((string) ($it['nopol'] ?? '')),
                    'harga_jual_per_hari' => (int) preg_replace('/[^0-9]/', '', (string) ($it['harga_jual_per_hari'] ?? '0')),
                ];
            }
        }
        if (!$hasil['includes'] && !empty($j['includes']) && is_array($j['includes'])) {
            $hasil['includes'] = array_values(array_filter(array_map('trim', $j['includes'])));
        }
        if (!$hasil['biaya'] && !empty($j['biaya']) && is_array($j['biaya'])) {
            foreach ($j['biaya'] as $b) {
                $hasil['biaya'][] = ['nama' => (string) ($b['nama'] ?? ''), 'nominal' => (int) preg_replace('/[^0-9]/', '', (string) ($b['nominal'] ?? '0'))];
            }
        }
        if ($hasil['total_teks'] === 0 && !empty($j['total_teks'])) {
            $hasil['total_teks'] = (int) preg_replace('/[^0-9]/', '', (string) $j['total_teks']);
        }
        $hasil['catatan'][] = 'Beberapa bagian dibaca dengan bantuan AI — mohon diperiksa.';
    } elseif (asistenSiap()) {
        $hasil['catatan'][] = 'AI pembaca teks sedang tidak bisa dipakai; hasil sepenuhnya dari pembaca pola.';
    }
}

/* jumlah hari susulan bila baru terisi dari AI */
if ($hasil['data']['jumlah_hari'] <= 0 && $hasil['data']['tgl_mulai'] !== '' && $hasil['data']['tgl_finish'] !== '') {
    $hasil['data']['jumlah_hari'] = max(1, (int) round((strtotime($hasil['data']['tgl_finish']) - strtotime($hasil['data']['tgl_mulai'])) / 86400 + 1));
}

/* ===================== 3. PELENGKAP DATA MASTER ===================== */
$db = getDB();
$hari = max(1, (int) $hasil['data']['jumlah_hari']);

foreach ($hasil['items'] as &$it) {
    $it['unit_id'] = 0;
    $it['driver_id'] = 0;
    $it['harga_modal_per_hari'] = 0;
    $it['dari_master'] = ['unit' => false, 'driver' => false];
    $it['jumlah_hari'] = $hari;

    /* unit: cocokkan nopol (abaikan spasi & besar-kecil huruf) */
    $nopol = preg_replace('/\s+/', '', (string) $it['nopol']);
    if ($nopol !== '') {
        $st = $db->prepare("SELECT id, nama_unit, nopol, harga_modal_default, harga_jual_default
                            FROM units WHERE deleted_at IS NULL
                              AND REPLACE(UPPER(nopol), ' ', '') = UPPER(?) LIMIT 1");
        $st->bind_param('s', $nopol);
        $st->execute();
        if ($u = $st->get_result()->fetch_assoc()) {
            $it['unit_id'] = (int) $u['id'];
            $it['dari_master']['unit'] = true;
            if ($it['nama_unit'] === '') $it['nama_unit'] = $u['nama_unit'];
            $it['harga_modal_per_hari'] = (int) $u['harga_modal_default'];
            if ((int) $it['harga_jual_per_hari'] <= 0) $it['harga_jual_per_hari'] = (int) $u['harga_jual_default'];
        }
    }

    /* driver: cocokkan nama (abaikan besar-kecil huruf) */
    $nama = trim((string) $it['nama_driver']);
    if ($nama !== '') {
        $st = $db->prepare("SELECT id, nama, hp FROM drivers WHERE deleted_at IS NULL AND LOWER(nama) = LOWER(?) LIMIT 1");
        $st->bind_param('s', $nama);
        $st->execute();
        if ($d = $st->get_result()->fetch_assoc()) {
            $it['driver_id'] = (int) $d['id'];
            $it['dari_master']['driver'] = true;
            $it['nama_driver'] = $d['nama'];
            if ($it['hp_driver'] === '') $it['hp_driver'] = (string) ($d['hp'] ?? '');
        }
    }
}
unset($it);

/* customer: kalau nama pesanan sudah ada di master, ambil tipe & sumber-nya */
$tipe = 'retail';
$sumOrder = 'wa';
$customerAda = false;
if ($hasil['data']['nama_pesanan'] !== '' && $hasil['data']['nama_pesanan'] !== '-') {
    $st = $db->prepare("SELECT id, tipe, sumber FROM customers WHERE deleted_at IS NULL AND LOWER(nama_pesanan) = LOWER(?) LIMIT 1");
    $st->bind_param('s', $hasil['data']['nama_pesanan']);
    $st->execute();
    if ($c = $st->get_result()->fetch_assoc()) {
        $customerAda = true;
        $tipe = in_array($c['tipe'], ['perorangan', 'perusahaan', 'instansi', 'RO'], true) ? $c['tipe'] : 'retail';
        $sumOrder = $c['sumber'] ?: 'wa';
    }
}

/* include: cocokkan nama ke master */
$incMaster = [];
$res = $db->query("SELECT id, nama FROM includes ORDER BY urutan, nama");
while ($r = $res->fetch_assoc()) $incMaster[] = $r;
$includesOut = [];
$incTakKenal = [];
foreach ($hasil['includes'] as $namaInc) {
    $cocok = null;
    foreach ($incMaster as $m) {
        if (mb_strtolower($m['nama']) === mb_strtolower($namaInc)) { $cocok = $m; break; }
    }
    if ($cocok) $includesOut[] = ['id' => (int) $cocok['id'], 'nama' => $cocok['nama']];
    else $incTakKenal[] = $namaInc;
}
if ($incTakKenal) {
    $hasil['catatan'][] = 'Include tidak ada di master: ' . implode(', ', $incTakKenal) . ' (bisa dicatat di kolom catatan).';
}

/* ===================== 4. BALASAN ===================== */
jawab([
    'ok'         => true,
    'sumber'     => $sumber,
    'yakin'      => (int) $hasil['yakin'],
    'data'       => $hasil['data'],
    'items'      => $hasil['items'],
    'includes'   => $includesOut,
    'biaya'      => $hasil['biaya'],
    'total_teks' => (int) $hasil['total_teks'],
    'tidak_dikenali' => $hasil['tidak_dikenali'],
    'catatan'    => $hasil['catatan'],
    /* saran nilai "tidak ada di teks" */
    'saran'      => [
        'tipe_pelanggan' => $tipe,
        'sumber'         => $sumOrder,
        'customer_baru'  => !$customerAda,
    ],
]);
