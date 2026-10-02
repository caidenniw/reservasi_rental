<?php
/**
 * includes/parse_teks_lib.php
 * ---------------------------------------------------------------
 * Mesin pembaca teks konfirmasi reservasi (format template teksWaOrder)
 * menjadi data pesanan terstruktur.
 *
 * Sifat:
 * - MURNI teks: tidak menyentuh database (pencocokan ke master dilakukan
 *   di api/parse_pesanan.php).
 * - Deterministik: mengikuti label baris template kita sendiri.
 * - Jujur: baris yang tidak dikenali TIDAK ditebak, tapi dilaporkan lewat
 *   kunci 'tidak_dikenali' supaya bisa diisi manual.
 *
 * Catatan penting soal "Hp/Wa" yang muncul DUA kali (driver & PIC):
 * urutan baris dipakai untuk membedakan, bukan sekadar cari label pertama.
 */

/* ============================== TOKEN ENDPOINT ============================== */

/**
 * Token khusus endpoint parse. Berbeda dengan token CSRF form, token ini
 * TIDAK dihapus sekali pakai supaya tombol "Bedah & Isi Otomatis" boleh
 * diklik berkali-kali tanpa memuat ulang halaman.
 */
function parseToken(): string
{
    if (empty($_SESSION['parse_token'])) {
        $_SESSION['parse_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['parse_token'];
}

/* ============================== UTILITAS KECIL ============================== */

/** Nilai setelah titik dua; "-" atau kosong dianggap tidak ada. */
function pn_nilai(string $v): string
{
    $v = trim($v);
    if ($v === '' || $v === '-' || $v === '–') return '';
    return $v;
}

/** Ambil angka dari teks rupiah/angka lain. "Rp 300.000" -> 300000. */
function pn_angka(string $s): int
{
    $s = preg_replace('/[^0-9]/', '', $s);
    return $s === '' ? 0 : (int) $s;
}

/** Normalisasi tanggal apa pun (d-m-Y / Y-m-d / d/m/Y) -> Y-m-d, atau '' bila gagal. */
function pn_tanggal(string $t): string
{
    $t = trim($t);
    if (!preg_match('/^(\d{1,4})[-\/.](\d{1,2})[-\/.](\d{1,4})$/', $t, $m)) return '';
    $a = (int) $m[1];
    $b = (int) $m[2];
    $c = (int) $m[3];
    if ($a > 31 || strlen($m[1]) === 4) { // sudah Y-m-d
        $y = $a; $mo = $b; $d = $c;
    } else {                              // d-m-Y
        $d = $a; $mo = $b; $y = $c;
    }
    if ($y < 100) $y += 2000;
    if ($mo < 1 || $mo > 12 || $d < 1 || $d > 31) return '';
    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

/** Baris pemisah antar armada, mis. "- - - - - - -". */
function pn_separator(string $ln): bool
{
    return $ln !== '' && strpos($ln, '_') === false
        && preg_match('/^[-\s]+$/', $ln) === 1
        && substr_count($ln, '-') >= 3;
}

/** "Dalam Kota Medan" / "Luar Kota Gunung Sitoli" -> [wilayah, kota]. */
function pn_pelayanan(string $v): array
{
    $v = trim($v);
    $low = mb_strtolower($v);
    $wilayah = 'dalam_kota';
    if (preg_match('/\bluar\b|\bluarkota\b/i', $low)) $wilayah = 'luar_kota';
    $kota = preg_replace('/^\s*(dalam|luar)\s*kota\b/i', '', $v);
    $kota = trim((string) $kota, " \t:-|");
    return [$wilayah, $kota];
}

/** "A+B", "A, B", "A dan B" -> daftar nama include. */
function pn_includes(string $v): array
{
    $v = pn_nilai($v);
    if ($v === '') return [];
    $bagian = preg_split('/\s*[+,]\s*|\s+dan\s+/i', $v);
    $out = [];
    foreach ($bagian as $b) {
        $b = trim($b);
        if ($b !== '' && $b !== '-') $out[] = $b;
    }
    return $out;
}

/* ============================== PARSER UTAMA ============================== */

/**
 * @return array{
 *   data: array<string,mixed>,
 *   items: array<int,array<string,mixed>>,
 *   includes: array<int,string>,
 *   biaya: array<int,array{nama:string,nominal:int}>,
 *   total_teks: int,
 *   tidak_dikenali: array<int,string>,
 *   catatan: array<int,string>,
 *   yakin: int
 * }
 */
function parseTeksPesanan(string $teks): array
{
    $hasil = [
        'data' => [
            'wilayah_pelayanan' => 'dalam_kota',
            'kota'              => '',
            'tgl_mulai'         => '',
            'tgl_finish'        => '',
            'jumlah_hari'       => 0,
            'jam'               => '',
            'jam_koordinasi'    => 0,
            'standby_point'     => '',
            'flight'            => '',
            'nama_pesanan'      => '',
            'nama_pic'          => '',
            'hp_pic'            => '',
        ],
        'items'          => [],
        'includes'       => [],
        'biaya'          => [],
        'total_teks'     => 0,
        'tidak_dikenali' => [],
        'catatan'        => [],
        'yakin'          => 0,
    ];

    /* --- normalisasi baris --- */
    $raw = str_replace(["\r\n", "\r", "\xC2\xA0", "\t"], ["\n", "\n", ' ', ' '], $teks);
    $lines = array_map(static function ($l) {
        return trim((string) preg_replace('/ {2,}/', ' ', $l));
    }, explode("\n", $raw));

    /* --- 1. BLOK ARMADA (unit/driver), berbasis urutan baris --- */
    $current = null;
    $tutup = function () use (&$current, &$hasil) {
        if ($current !== null && ($current['nama_unit'] !== '' || $current['nopol'] !== '' || $current['nama_driver'] !== '')) {
            $hasil['items'][] = $current;
        }
        $current = null;
    };
    foreach ($lines as $ln) {
        if ($ln === '') continue;
        if (pn_separator($ln)) { $tutup(); continue; }

        if (preg_match('/^nama\s*driver\s*:\s*(.*)$/i', $ln, $m)) {
            $tutup(); // armada sebelumnya selesai
            $current = ['nama_driver' => pn_nilai($m[1]), 'hp_driver' => '', 'nama_unit' => '', 'nopol' => ''];
            continue;
        }
        if ($current !== null) {
            if (preg_match('/^hp\s*\/?\s*wa\s*:\s*(.*)$/i', $ln, $m)) { $current['hp_driver'] = pn_nilai($m[1]); continue; }
            if (preg_match('/^unit\s*:\s*(.*)$/i', $ln, $m))           { $current['nama_unit'] = pn_nilai($m[1]); continue; }
            if (preg_match('/^no\.?\s*plat\s*:\s*(.*)$/i', $ln, $m))   { $current['nopol'] = strtoupper(pn_nilai($m[1])); continue; }
            $tutup(); // baris lain menandakan blok armada sudah lewat
        }
    }
    $tutup();

    /* --- 2. BARIS LAINNYA (pelayanan, tanggal, meta, customer, harga) --- */
    $sawPic = false;
    $hargaMap = [];

    foreach ($lines as $ln) {
        if ($ln === '') continue;

        // item & separator sudah ditangani di atas -> jangan diproses lagi
        if (preg_match('/^nama\s*driver\s*:/i', $ln) || preg_match('/^unit\s*:/i', $ln)
            || preg_match('/^no\.?\s*plat\s*:/i', $ln) || pn_separator($ln)) {
            continue;
        }

        // footer & identitas perusahaan: dilewati, tidak dianggap "tidak dikenali"
        if (preg_match('/^(instagram|website|email|terimakasih|1000\s*rent\s*car|pt\.?\s)/i', $ln)
            || stripos($ln, 'www.') !== false || strpos($ln, '@') !== false || strpos($ln, ':') === false) {
            continue;
        }

        if (preg_match('/^pelayanan\s*:\s*(.*)$/i', $ln, $m)) {
            [$wil, $kota] = pn_pelayanan($m[1]);
            $hasil['data']['wilayah_pelayanan'] = $wil;
            $hasil['data']['kota'] = $kota;
            continue;
        }

        if (preg_match('/^tanggal\s*:\s*(.*)$/i', $ln, $m)) {
            $val = $m[1];
            if (preg_match('/(\d{1,4}[-\/.]\d{1,2}[-\/.]\d{1,4})\s*(?:s\s*\/\s*d|sd|s\.d\.|sampai|hingga|to)\s*(\d{1,4}[-\/.]\d{1,2}[-\/.]\d{1,4})/i', $val, $mm)) {
                $hasil['data']['tgl_mulai']  = pn_tanggal($mm[1]);
                $hasil['data']['tgl_finish'] = pn_tanggal($mm[2]);
            }
            if (preg_match('/\((\d{1,3})\s*(?:day|hari)\)/i', $val, $mm)) {
                $hasil['data']['jumlah_hari'] = (int) $mm[1];
            }
            continue;
        }

        if (preg_match('/^stanby\s*:\s*(.*)$/i', $ln, $m)) { $hasil['data']['standby_point'] = pn_nilai($m[1]); continue; }
        if (preg_match('/^flight\s*:\s*(.*)$/i', $ln, $m)) { $hasil['data']['flight'] = pn_nilai($m[1]); continue; }

        if (preg_match('/^jam\s*:\s*(.*)$/i', $ln, $m)) {
            $j = trim($m[1]);
            if (stripos($j, 'kordinasi') !== false || stripos($j, 'koordinasi') !== false) {
                $hasil['data']['jam_koordinasi'] = 1;
            } else {
                $hasil['data']['jam'] = pn_nilai($j);
            }
            continue;
        }

        if (preg_match('/^pesanan\s*:\s*(.*)$/i', $ln, $m)) { $hasil['data']['nama_pesanan'] = trim($m[1]); $sawPic = false; continue; }
        if (preg_match('/^pic\s*:\s*(.*)$/i', $ln, $m))     { $hasil['data']['nama_pic'] = pn_nilai($m[1]); $sawPic = true; continue; }
        if (preg_match('/^hp\s*\/?\s*wa\s*:\s*(.*)$/i', $ln, $m)) {
            // hanya diterima sebagai HP PIC bila muncul SETELAH baris "Pic :"
            if ($sawPic && $hasil['data']['hp_pic'] === '') $hasil['data']['hp_pic'] = pn_nilai($m[1]);
            continue;
        }

        if (preg_match('/^include\s*:\s*(.*)$/i', $ln, $m)) { $hasil['includes'] = pn_includes($m[1]); continue; }

        if (preg_match('/^harga\s+(.+?)\s*:\s*(.+)$/i', $ln, $m)) {
            $hargaMap[mb_strtolower(trim($m[1]))] = pn_angka($m[2]);
            continue;
        }

        if (preg_match('/^total\s*:\s*(.+)$/i', $ln, $m)) {
            // ambil hanya angka rupiah pertama; "(3 hari)" di belakang jangan ikut terbaca
            $hasil['total_teks'] = preg_match('/([\d][\d.,]*)/', $m[1], $mm) ? pn_angka($mm[1]) : 0;
            continue;
        }

        if (preg_match('/^(.+?)\s*:\s*(?:rp\s*)?[\d.,]+\s*$/i', $ln, $m)) {
            $hasil['biaya'][] = ['nama' => trim($m[1]), 'nominal' => pn_angka($m[2] ?? $ln)];
            continue;
        }

        $hasil['tidak_dikenali'][] = $ln;
    }

    /* --- 3. tempelkan harga jual dari blok "Harga <unit>" ke item terkait --- */
    if ($hargaMap) {
        foreach ($hasil['items'] as &$it) {
            $nama = mb_strtolower((string) $it['nama_unit']);
            $harga = null;
            if ($nama !== '' && isset($hargaMap[$nama])) {
                $harga = $hargaMap[$nama];
            } else {
                foreach ($hargaMap as $k => $v) {
                    if ($nama !== '' && (mb_strpos($k, $nama) !== false || mb_strpos($nama, $k) !== false)) { $harga = $v; break; }
                }
            }
            $it['harga_jual_per_hari'] = $harga ?? 0;
        }
        unset($it);
    }
    // pastikan semua item punya kunci harga walau teks tanpa blok harga
    foreach ($hasil['items'] as &$it) {
        if (!array_key_exists('harga_jual_per_hari', $it)) $it['harga_jual_per_hari'] = 0;
    }
    unset($it);

    /* --- 4. hitung jumlah hari bila tidak tertulis --- */
    if ($hasil['data']['jumlah_hari'] <= 0 && $hasil['data']['tgl_mulai'] !== '' && $hasil['data']['tgl_finish'] !== '') {
        $selisih = (strtotime($hasil['data']['tgl_finish']) - strtotime($hasil['data']['tgl_mulai'])) / 86400 + 1;
        $hasil['data']['jumlah_hari'] = max(1, (int) round($selisih));
    }

    /* --- 5. keyakinan & catatan --- */
    $skor = 0;
    if ($hasil['data']['kota'] !== '')          $skor += 10;
    if ($hasil['data']['tgl_mulai'] !== '')     $skor += 25;
    if ($hasil['data']['tgl_finish'] !== '')    $skor += 10;
    if ($hasil['data']['nama_pesanan'] !== '')  $skor += 25;
    if ($hasil['items'])                        $skor += 30;
    $skor -= min(45, count($hasil['tidak_dikenali']) * 15);
    $hasil['yakin'] = max(0, min(100, $skor));

    if ($hasil['tidak_dikenali']) {
        $hasil['catatan'][] = count($hasil['tidak_dikenali']) . ' baris tidak dikenali (perlu diperiksa manual).';
    }
    if ($hasil['total_teks'] > 0 && $hasil['items']) {
        $jual = 0;
        foreach ($hasil['items'] as $it) $jual += (int) ($it['harga_jual_per_hari'] ?? 0) * (int) $hasil['data']['jumlah_hari'];
        foreach ($hasil['biaya'] as $b) $jual += (int) $b['nominal'];
        if ($jual > 0 && $jual !== $hasil['total_teks']) {
            $hasil['catatan'][] = 'Total di teks (Rp ' . number_format($hasil['total_teks'], 0, ',', '.')
                . ') tidak sama dengan hitungan item + biaya (Rp ' . number_format($jual, 0, ',', '.') . '). Periksa manual.';
        }
    }

    return $hasil;
}
