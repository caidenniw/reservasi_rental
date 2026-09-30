<?php
/**
 * includes/asisten_lib.php
 * Mesin asisten internal dashboard reservasi: mengumpulkan data dari database
 * (HANYA BACA) lalu meminta jawaban ke Google Gemini.
 *
 * Aturan rancangan:
 * - Tidak ada satu pun query yang mengubah data (SELECT saja).
 * - Asisten hanya menjawab dari konteks yang dikirim; kalau data tidak ada,
 *   model diinstruksikan menjawab jujur.
 * - Kunci API disimpan di config/asisten.local.php (tidak ikut git).
 */
require_once __DIR__ . '/functions.php';

function asistenKredensial(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $path = dirname(__DIR__) . '/config/asisten.local.php';
        $isi = is_file($path) ? require $path : [];
        $cfg = is_array($isi) ? $isi : [];
    }
    return $cfg;
}

function asistenSiap(): bool
{
    $c = asistenKredensial();
    return !empty($c['api_key']);
}

/**
 * Token khusus asisten. Berbeda dengan token CSRF form, token ini TIDAK
 * dihapus sekali pakai supaya widget bisa mengirim beberapa pertanyaan
 * tanpa memuat ulang halaman. Aman karena endpoint hanya membaca data.
 */
function asistenToken(): string
{
    if (empty($_SESSION['asisten_token'])) {
        $_SESSION['asisten_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['asisten_token'];
}

/* ============================ PENGUMPULAN DATA (BACA SAJA) ============================ */

function asistenRingkasan(): string
{
    $db = getDB();
    $hariIni = date('Y-m-d');

    $q = function (string $sql) use ($db): int {
        return (int) $db->query($sql)->fetch_assoc()['c'];
    };

    $berjalan = $q("SELECT COUNT(*) c FROM orders WHERE deleted_at IS NULL AND status NOT IN ('cancelled','closed')
                    AND tgl_mulai <= '$hariIni' AND tgl_finish >= '$hariIni'");
    $bulanIni = $q("SELECT COUNT(*) c FROM orders WHERE deleted_at IS NULL
                    AND tgl_mulai BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-t') . "'");
    $unitKeluar = $q("SELECT COUNT(DISTINCT i.unit_id) c FROM order_items i JOIN orders o ON o.id = i.order_id
                      WHERE o.deleted_at IS NULL AND i.unit_id IS NOT NULL AND o.status NOT IN ('cancelled','closed')
                      AND o.tgl_mulai <= '$hariIni' AND o.tgl_finish >= '$hariIni'");
    $papan = [];
    $res = $db->query("SELECT status, COUNT(*) c FROM orders WHERE deleted_at IS NULL GROUP BY status ORDER BY c DESC");
    while ($r = $res->fetch_assoc()) $papan[] = statusLabel($r['status']) . '=' . $r['c'];

    $inv = $db->query("SELECT COUNT(*) c, COALESCE(SUM(i.sisa),0) s FROM invoices i JOIN orders o ON o.id = i.order_id
                       WHERE i.status IN ('terbit','sebagian') AND o.deleted_at IS NULL
                         AND o.status NOT IN ('cancelled','closed')")->fetch_assoc();

    $jualBulan = (int) $db->query("SELECT COALESCE(SUM(grand_total),0) t FROM orders
        WHERE deleted_at IS NULL AND status NOT IN ('cancelled','closed')
          AND tgl_mulai BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-t') . "'")->fetch_assoc()['t'];

    $tot = $db->query("SELECT COUNT(*) o, (SELECT COUNT(*) FROM units WHERE deleted_at IS NULL) u,
                       (SELECT COUNT(*) FROM drivers WHERE deleted_at IS NULL) d,
                       (SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL) c FROM orders WHERE deleted_at IS NULL")->fetch_assoc();

    $out  = "RINGKASAN SISTEM (tanggal hari ini " . date('d-m-Y') . ")\n";
    $out .= "- Pesanan berjalan hari ini: $berjalan\n";
    $out .= "- Unit keluar hari ini: $unitKeluar\n";
    $out .= "- Pesanan bulan ini (" . bulanPanjang((int) date('n')) . ' ' . date('Y') . "): $bulanIni, nilai Rp " . number_format($jualBulan, 0, ',', '.') . "\n";
    $out .= "- Invoice belum lunas: " . (int) $inv['c'] . " (sisa total Rp " . number_format((float) $inv['s'], 0, ',', '.') . ")\n";
    $out .= "- Jumlah data: " . (int) $tot['o'] . " pesanan, " . (int) $tot['u'] . " unit, " . (int) $tot['d'] . " driver, " . (int) $tot['c'] . " pelanggan\n";
    $out .= "- Sebaran status pesanan: " . implode(', ', $papan) . "\n";
    $out .= "- Catatan: pesanan historis impor berstatus Lunas tanpa invoice tidak dihitung sebagai pendapatan.\n";
    return $out;
}

function asistenPesananHariIni(): string
{
    $db = getDB();
    $hariIni = date('Y-m-d');
    $st = $db->prepare("SELECT o.nomor_order, o.nama_pesanan, o.kota, o.tgl_mulai, o.tgl_finish, o.jumlah_hari,
                               o.status, o.grand_total, o.nama_pic, o.hp_pic, o.standby_point,
                               (SELECT GROUP_CONCAT(DISTINCT i.nopol SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) nopol,
                               (SELECT GROUP_CONCAT(DISTINCT i.nama_driver SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) driver
                        FROM orders o
                        WHERE o.deleted_at IS NULL AND o.status NOT IN ('cancelled','closed')
                          AND (o.tgl_mulai <= ? AND o.tgl_finish >= ? OR o.tgl_mulai = ?)
                        ORDER BY o.tgl_mulai LIMIT 25");
    $st->bind_param('sss', $hariIni, $hariIni, $hariIni);
    $st->execute();
    return asistenFormatPesanan('PESANAN HARI INI / SEDANG BERJALAN', $st->get_result()->fetch_all(MYSQLI_ASSOC));
}

function asistenFormatPesanan(string $judul, array $rows): string
{
    if (!$rows) return $judul . ": tidak ada data yang cocok.\n";
    $out = $judul . " (" . count($rows) . " baris)\n";
    foreach ($rows as $i => $r) {
        $out .= ($i + 1) . '. ' . $r['nomor_order'] . ' | ' . tglAngka($r['tgl_mulai']) . ' s/d ' . tglAngka($r['tgl_finish'])
              . ' (' . (int) $r['jumlah_hari'] . ' hari) | ' . $r['nama_pesanan']
              . ' | status: ' . statusLabel($r['status'])
              . ' | total: Rp ' . number_format((float) $r['grand_total'], 0, ',', '.')
              . ' | nopol: ' . ($r['nopol'] ?: '-')
              . ' | driver: ' . ($r['driver'] ?: '-')
              . ' | PIC: ' . ($r['nama_pic'] ?: '-') . ($r['hp_pic'] ? ' (' . $r['hp_pic'] . ')' : '')
              . ' | kota: ' . $r['kota'] . "\n";
    }
    return $out;
}

function asistenCariPesanan(string $kata): string
{
    $db = getDB();
    $like = '%' . $kata . '%';
    $st = $db->prepare("SELECT o.nomor_order, o.nama_pesanan, o.kota, o.tgl_mulai, o.tgl_finish, o.jumlah_hari,
                               o.status, o.grand_total, o.nama_pic, o.hp_pic,
                               (SELECT GROUP_CONCAT(DISTINCT i.nopol SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) nopol,
                               (SELECT GROUP_CONCAT(DISTINCT i.nama_driver SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) driver
                        FROM orders o
                        WHERE o.deleted_at IS NULL
                          AND (o.nomor_order LIKE ? OR o.nama_pesanan LIKE ? OR o.nama_pic LIKE ? OR o.kota LIKE ?
                               OR EXISTS (SELECT 1 FROM order_items x WHERE x.order_id = o.id AND x.nopol LIKE ?)
                               OR EXISTS (SELECT 1 FROM invoices v WHERE v.order_id = o.id AND v.nomor_invoice LIKE ?))
                        ORDER BY o.tgl_mulai DESC LIMIT 15");
    $st->bind_param('ssssss', $like, $like, $like, $like, $like, $like);
    $st->execute();
    return asistenFormatPesanan('HASIL PENCARIAN "' . $kata . '"', $st->get_result()->fetch_all(MYSQLI_ASSOC));
}

function asistenInvoiceBelumLunas(): string
{
    $db = getDB();
    $rows = $db->query("SELECT i.nomor_invoice, i.tanggal_invoice, i.jatuh_tempo, i.total, i.dp, i.sisa, i.status,
                               o.nomor_order, o.nama_pesanan, o.nama_pic, o.hp_pic
                        FROM invoices i JOIN orders o ON o.id = i.order_id
                        WHERE i.status IN ('terbit','sebagian') AND o.deleted_at IS NULL
                          AND o.status NOT IN ('cancelled','closed')
                        ORDER BY i.jatuh_tempo")->fetch_all(MYSQLI_ASSOC);
    if (!$rows) return "INVOICE BELUM LUNAS: tidak ada. Semua invoice sudah lunas atau belum terbit.\n";
    $out = "INVOICE BELUM LUNAS (" . count($rows) . " invoice)\n";
    $tot = 0.0;
    foreach ($rows as $i => $r) {
        $tot += (float) $r['sisa'];
        $out .= ($i + 1) . '. ' . $r['nomor_invoice'] . ' | pesanan ' . $r['nomor_order'] . ' - ' . $r['nama_pesanan']
              . ' | terbit ' . tglAngka($r['tanggal_invoice']) . ' | jatuh tempo ' . tglAngka($r['jatuh_tempo'])
              . ' | total Rp ' . number_format((float) $r['total'], 0, ',', '.')
              . ' | dibayar Rp ' . number_format((float) $r['dp'], 0, ',', '.')
              . ' | SISA Rp ' . number_format((float) $r['sisa'], 0, ',', '.')
              . ' | status ' . statusLabel($r['status'])
              . ' | PIC ' . ($r['nama_pic'] ?: '-') . ($r['hp_pic'] ? ' (' . $r['hp_pic'] . ')' : '') . "\n";
    }
    $out .= "TOTAL SISA TAGIHAN: Rp " . number_format($tot, 0, ',', '.') . "\n";
    return $out;
}

function asistenRekapBulanan(int $jumlahBulan = 6): string
{
    $db = getDB();
    $out = "REKAP PER BULAN (berdasarkan tanggal mulai sewa; pesanan batal/tertutup & historis tanpa invoice dikecualikan)\n";
    for ($i = $jumlahBulan - 1; $i >= 0; $i--) {
        $awal  = date('Y-m-01', strtotime("-$i month"));
        $akhir = date('Y-m-t', strtotime("-$i month"));
        $st = $db->prepare("SELECT COUNT(*) c, COALESCE(SUM(o.grand_total),0) j, COALESCE(SUM(o.margin),0) m
                            FROM orders o
                            WHERE o.deleted_at IS NULL AND o.status NOT IN ('cancelled','closed')
                              AND NOT (o.status = 'paid' AND NOT EXISTS (SELECT 1 FROM invoices iv WHERE iv.order_id = o.id AND iv.status <> 'batal'))
                              AND o.tgl_mulai BETWEEN ? AND ?");
        $st->bind_param('ss', $awal, $akhir);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $out .= '- ' . bulanPanjang((int) date('n', strtotime($awal))) . ' ' . date('Y', strtotime($awal))
              . ': ' . (int) $r['c'] . ' pesanan, nilai Rp ' . number_format((float) $r['j'], 0, ',', '.')
              . ', margin internal Rp ' . number_format((float) $r['m'], 0, ',', '.') . "\n";
    }
    return $out;
}

function asistenCekKetersediaan(string $kode, string $mulai, string $sampai): string
{
    $db = getDB();
    $like = '%' . $kode . '%';
    /* Nopol sering diketik berspasi ("BK 1261 OOO") padahal di master tersimpan
       tanpa spasi ("BK1261OOO") - cocokkan dua-duanya. */
    $padat = preg_replace('/\s+/', '', $kode);
    $likePadat = '%' . $padat . '%';
    $st = $db->prepare("SELECT u.id, u.nama_unit, u.nopol, u.status,
                               (SELECT COUNT(*) FROM order_items i WHERE i.unit_id = u.id) dipakai
                        FROM units u
                        WHERE u.deleted_at IS NULL
                          AND (u.nopol LIKE ? OR REPLACE(u.nopol, ' ', '') LIKE ? OR u.nama_unit LIKE ?)
                        LIMIT 5");
    $st->bind_param('sss', $like, $likePadat, $like);
    $st->execute();
    $units = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    if (!$units) {
        return "CEK KETERSEDIAAN \"$kode\" ($mulai s/d $sampai): unit dengan nopol/nama itu tidak ada di data master.\n"
             . "PENTING: kalau pengguna hanya menyebut nama unit tanpa nopol yang pasti, katakan datanya tidak ditemukan dan minta nopolnya.\n";
    }
    $out = "CEK KETERSEDIAAN UNIT \"$kode\" ($mulai s/d $sampai)\n";
    foreach ($units as $u) {
        $st2 = $db->prepare("SELECT o.nomor_order, o.nama_pesanan, o.tgl_mulai, o.tgl_finish, o.status
                             FROM order_items i JOIN orders o ON o.id = i.order_id
                             WHERE i.unit_id = ? AND o.deleted_at IS NULL
                               AND o.status NOT IN ('cancelled','closed','draft')
                               AND NOT (o.tgl_finish < ? OR o.tgl_mulai > ?)
                             ORDER BY o.tgl_mulai");
        $st2->bind_param('iss', $u['id'], $mulai, $sampai);
        $st2->execute();
        $pakai = $st2->get_result()->fetch_all(MYSQLI_ASSOC);
        $out .= '- ' . $u['nama_unit'] . ' (' . $u['nopol'] . '), status master: ' . $u['status']
              . ', total pernah dipakai ' . (int) $u['dipakai'] . ' pesanan. ';
        if (!$pakai) {
            $out .= "BEBAS pada rentang tanggal itu (tidak ada pesanan bertumpuk).\n";
        } else {
            $out .= "TIDAK BEBAS, bertumpuk dengan: ";
            $pakaiTeks = [];
            foreach ($pakai as $p) {
                $pakaiTeks[] = $p['nomor_order'] . ' (' . tglAngka($p['tgl_mulai']) . '-' . tglAngka($p['tgl_finish']) . ', ' . $p['nama_pesanan'] . ')';
            }
            $out .= implode('; ', $pakaiTeks) . "\n";
        }
    }
    return $out;
}

function asistenMaster(string $kata): string
{
    $db = getDB();
    $like = '%' . $kata . '%';
    $out = "DATA MASTER COCOK \"$kata\"\n";

    $st = $db->prepare("SELECT nama_unit, nopol, status, harga_jual_default FROM units
                        WHERE deleted_at IS NULL AND (nama_unit LIKE ? OR nopol LIKE ?) LIMIT 10");
    $st->bind_param('ss', $like, $like);
    $st->execute();
    $u = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $out .= "- Unit:\n";
    foreach ($u as $r) {
        $out .= '  ' . $r['nama_unit'] . ' (' . $r['nopol'] . ') status ' . $r['status']
              . ', harga jual default Rp ' . number_format((float) $r['harga_jual_default'], 0, ',', '.') . "\n";
    }
    if (!$u) $out .= "  (tidak ada)\n";

    $st = $db->prepare("SELECT nama, hp, wilayah, status FROM drivers
                        WHERE deleted_at IS NULL AND nama LIKE ? LIMIT 10");
    $st->bind_param('s', $like);
    $st->execute();
    $d = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $out .= "- Driver:\n";
    foreach ($d as $r) {
        $out .= '  ' . $r['nama'] . ' | HP ' . ($r['hp'] ?: '-') . ' | wilayah ' . ($r['wilayah'] ?: '-') . ' | status ' . $r['status'] . "\n";
    }
    if (!$d) $out .= "  (tidak ada)\n";
    return $out;
}

/** Deteksi maksud pertanyaan + susun konteks data. */
function asistenKonteks(string $tanya, array &$label = []): string
{
    $t = ' ' . mb_strtolower($tanya) . ' ';
    $bagian = [];
    $label  = [];

    /* bulan yang disebutkan (januari..desember) */
    $bulanId = ['januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6,
                'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12];

    /* 1. ketersediaan unit + tanggal */
    if (preg_match('/\b(kosong|tersedia|bebas|bentrok|bisa dipakai|dipakai|terpakai|availab)/i', $tanya) && preg_match('/\b([A-Z]{1,2}\s?\d{3,4}\s?[A-Z]{1,3})\b/i', $tanya, $mNopol)) {
        $mulai = null; $sampai = null;
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $tanya, $mTgl)) { $mulai = $sampai = $mTgl[1]; }
        elseif (preg_match('/(\d{1,2})\s*[-–s\/]+\s*(\d{1,2})\s+([A-Za-z]+)\s*(\d{4})?/i', $tanya, $mR)) {
            $bln = $bulanId[mb_strtolower($mR[3])] ?? 0;
            $thn = $mR[4] ?: date('Y');
            if ($bln) {
                $mulai  = sprintf('%04d-%02d-%02d', $thn, $bln, (int) $mR[1]);
                $sampai = sprintf('%04d-%02d-%02d', $thn, $bln, (int) $mR[2]);
            }
        } elseif (preg_match('/(\d{1,2})\s+([A-Za-z]+)\s*(\d{4})?/', $tanya, $mS)) {
            $bln = $bulanId[mb_strtolower($mS[2])] ?? 0;
            $thn = $mS[3] ?: date('Y');
            if ($bln) { $mulai = $sampai = sprintf('%04d-%02d-%02d', $thn, $bln, (int) $mS[1]); }
        }
        if ($mulai && $sampai && $sampai < $mulai) { $tmp = $mulai; $mulai = $sampai; $sampai = $tmp; }
        if (!$mulai) { $mulai = $sampai = date('Y-m-d'); }
        $bagian[] = asistenCekKetersediaan($mNopol[1], $mulai, $sampai);
        $label[]  = 'ketersediaan unit';
    }

    /* 2. invoice / tagihan */
    if (preg_match('/\b(invoice|tagihan|belum lunas|belum bayar|piutang|sisa bayar|bayar)/i', $tanya)) {
        $bagian[] = asistenInvoiceBelumLunas();
        $label[]  = 'invoice belum lunas';
    }

    /* 3. hari ini / sedang berjalan */
    if (preg_match('/\b(hari ini|sekarang|sedang|sedang trip|unit keluar|berjalan|hari ini apa)\b/i', $t)) {
        $bagian[] = asistenPesananHariIni();
        $label[]  = 'pesanan hari ini';
    }

    /* 4. rekap / pendapatan / total bulan */
    if (preg_match('/\b(rekap|omzet|omset|pendapatan|margin|laba|total (bulan|per bulan)|bulan ini|tahun ini)/i', $tanya)
        || preg_match('/\b(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)\b/i', $tanya)) {
        $bagian[] = asistenRekapBulanan(6);
        $label[]  = 'rekap bulanan';
    }

    /* 5. pencarian pesanan: nomor order / nama / nopol / pic / kota */
    $kataKunci = [];
    if (preg_match('/\b(RN-\d{4}-\d{4})\b/i', $tanya, $mNo)) $kataKunci[] = $mNo[1];
    if (preg_match('/\b([A-Z]{1,2}\s?\d{3,4}\s?[A-Z]{1,3})\b/', $tanya, $mPola)) $kataKunci[] = preg_replace('/\s+/', '', $mPola[1]);
    if (preg_match('/\b(?:pt\.?|cv|ud|kabupaten|kantor|dinas|bank|polres|universitas|sekolah)\s+([A-Za-z\.\' ]{3,40})/i', $tanya, $mPt)) $kataKunci[] = trim($mPt[1]);
    if (preg_match('/(?:pesanan|order|atas nama|pelanggan|pic|untuk)\s+([A-Za-z\.\' ]{3,40})/i', $tanya, $mPs)) $kataKunci[] = trim($mPs[1]);

    foreach (array_unique($kataKunci) as $kk) {
        $kk = trim($kk);
        if (mb_strlen($kk) < 3) continue;
        $bagian[] = asistenCariPesanan($kk);
        $label[]  = 'pencarian: ' . $kk;
    }

    /* 6. data master unit/driver kalau tidak ada hasil lain */
    if (!$bagian && preg_match('/\b(unit|driver|mobil|armada|master)\b/i', $tanya)) {
        $kata = preg_replace('/\b(unit|driver|mobil|armada|master|ada|berapa|apa|siapa|data|yang|di|ini)\b/i', ' ', $tanya);
        $kata = trim(preg_replace('/\s+/', ' ', $kata));
        if (mb_strlen($kata) >= 3) { $bagian[] = asistenMaster($kata); $label[] = 'data master'; }
    }

    /* selalu sertakan ringkasan supaya model punya konteks umum */
    array_unshift($bagian, asistenRingkasan());
    return implode("\n", $bagian);
}

/* ============================ PEMANGGILAN MODEL ============================ */

function asistenTanya(string $tanya, array $riwayat = []): array
{
    $cfg = asistenKredensial();
    if (empty($cfg['api_key'])) {
        return ['ok' => false, 'error' => 'Kunci API belum diisi di config/asisten.local.php'];
    }

    $label  = [];
    $konteks = asistenKonteks($tanya, $label);

    $system = "Kamu adalah asisten internal dashboard reservasi 1000 Nusantara Rental.\n\n"
        . "ATURAN WAJIB:\n"
        . "1. Jawab HANYA dari KONTEKS DATA di bawah. Jangan memakai pengetahuan di luar itu.\n"
        . "2. Kalau data yang ditanya tidak ada di konteks, bilang jujur bahwa datanya tidak ada di sistem dan sebutkan menu mana yang bisa dipakai untuk mencarinya.\n"
        . "3. Jangan mengarang nomor order, nomor invoice, harga, nama unit, atau tanggal.\n"
        . "4. Kamu hanya bisa MEMBACA data. Kalau diminta mengubah/menghapus/membuat data, tolak dengan sopan dan arahkan ke menu yang sesuai.\n"
        . "5. Uang ditulis format Rp 1.234.567. Tanggal ditulis 30-09-2026.\n"
        . "6. Bahasa Indonesia, ringkas dan langsung ke inti, tanpa emoji, tanpa basa-basi berlebihan.\n"
        . "7. Kalau jawabannya berupa daftar, pakai daftar bernomor singkat; sebutkan angka kunci (jumlah, sisa tagihan).\n"
        . "8. FORMAT JAWABAN (wajib, supaya rapi di layar sempit):\n"
        . "   - Mulai dengan 1 kalimat inti yang memuat angka kunci. Contoh: \"Ada 1 pesanan berjalan hari ini.\"\n"
        . "   - Kalau ada rincian, tulis SETIAP data pada satu baris sendiri, diawali \"- \", dengan urutan tetap:\n"
        . "     NOMOR ORDER · TANGGAL · NAMA PESANAN · STATUS · Rp NILAI\n"
        . "     Setiap bagian dipisahkan \" · \" (spasi, titik tengah, spasi). Maksimal 6 baris rincian.\n"
        . "     Kalau datanya lebih dari 6, tambahkan baris terakhir: \"dan N data lain - buka menu Data Pesanan & Faktur\".\n"
        . "   - Untuk invoice pakai urutan: NOMOR INVOICE · PESANAN · JATUH TEMPO · SISA Rp NILAI\n"
        . "   - Untuk ketersediaan unit: baris pertama langsung jawab BEBAS / TIDAK BEBAS, lalu baris rincian bila ada.\n"
        . "   - JANGAN memakai tanda bintang, tanda pagar, tabel markdown, atau emoji.\n"
        . "   - Jangan mengulang-ulang judul konteks; langsung ke isi. Hindari kalimat pembuka seperti \"Berikut adalah\".\n"
        . "   - Kalau menolak permintaan atau data tidak ada, cukup 1-2 kalimat.\n\n"
        . "KONTEKS DATA:\n" . $konteks;

    $models = array_values(array_filter(array_merge(
        [(string) ($cfg['model'] ?? 'gemini-flash-lite-latest')],
        (array) ($cfg['model_cadangan'] ?? [])
    )));
    $base = rtrim((string) ($cfg['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');

    $contents = [];
    foreach (array_slice($riwayat, -6) as $turn) {
        $teks = trim((string) ($turn['text'] ?? ''));
        if ($teks === '') continue;
        $contents[] = ['role' => (($turn['role'] ?? 'user') === 'asisten' ? 'model' : 'user'), 'parts' => [['text' => $teks]]];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $tanya]]];

    $body = json_encode([
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents' => $contents,
        'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 900],
    ], JSON_UNESCAPED_UNICODE);

    $error = 'tidak ada model yang dicoba';
    foreach ($models as $model) {
        $url = $base . '/models/' . $model . ':generateContent';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $cfg['api_key']],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($resp === false) { $error = 'Koneksi ke layanan jawaban gagal: ' . $curlErr; continue; }
        $json = json_decode((string) $resp, true);
        if ($code !== 200) {
            $error = 'HTTP ' . $code . ' dari model ' . $model . ': ' . mb_substr((string) ($json['error']['message'] ?? $resp), 0, 200);
            continue;
        }
        $teks = '';
        foreach (($json['candidates'][0]['content']['parts'] ?? []) as $part) {
            $teks .= (string) ($part['text'] ?? '');
        }
        $teks = trim($teks);
        if ($teks === '') { $error = 'Model ' . $model . ' mengembalikan jawaban kosong.'; continue; }

        return ['ok' => true, 'jawaban' => $teks, 'model' => $model, 'konteks_label' => implode(', ', array_unique($label))];
    }

    return ['ok' => false, 'error' => $error];
}
