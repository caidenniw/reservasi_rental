<?php
/**
 * api/asisten.php — endpoint asisten internal dashboard reservasi (JSON).
 * HANYA MEMBACA data. Tidak ada aksi tulis di endpoint ini.
 */
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
if ($token === '' || !hash_equals(asistenToken(), $token)) {
    jawab(['ok' => false, 'error' => 'Token keamanan tidak valid. Muat ulang halaman.'], 419);
}

$tanya = trim((string) ($_POST['tanya'] ?? ''));
if ($tanya === '') {
    jawab(['ok' => false, 'error' => 'Pertanyaan masih kosong.'], 422);
}
if (mb_strlen($tanya) > 600) {
    jawab(['ok' => false, 'error' => 'Pertanyaan terlalu panjang (maksimal 600 karakter).'], 422);
}

/* Pembatas sederhana: maksimal 40 pertanyaan per 10 menit per sesi. */
$kini = time();
$_SESSION['asisten_hits'] = array_values(array_filter(
    $_SESSION['asisten_hits'] ?? [],
    fn($t) => $kini - (int) $t < 600
));
if (count($_SESSION['asisten_hits']) >= 40) {
    jawab(['ok' => false, 'error' => 'Batas 40 pertanyaan per 10 menit tercapai. Coba lagi beberapa saat lagi.'], 429);
}
$_SESSION['asisten_hits'][] = $kini;

if (!asistenSiap()) {
    jawab(['ok' => false, 'error' => 'Asisten belum aktif: kunci API Gemini belum diisi di config/asisten.local.php.'], 503);
}

$riwayat = [];
if (!empty($_POST['riwayat'])) {
    $r = json_decode((string) $_POST['riwayat'], true);
    if (is_array($r)) {
        foreach (array_slice($r, -6) as $turn) {
            $riwayat[] = [
                'role' => (($turn['role'] ?? 'user') === 'asisten' ? 'asisten' : 'user'),
                'text' => (string) ($turn['text'] ?? ''),
            ];
        }
    }
}

$mulai = microtime(true);
$hasil = asistenTanya($tanya, $riwayat);
$detik = round(microtime(true) - $mulai, 1);

if (empty($hasil['ok'])) {
    jawab(['ok' => false, 'error' => (string) ($hasil['error'] ?? 'Gagal meminta jawaban.')], 502);
}

jawab([
    'ok' => true,
    'jawaban' => $hasil['jawaban'],
    'model' => $hasil['model'] ?? '',
    'sumber' => $hasil['konteks_label'] ?? '',
    'detik' => $detik,
]);
