<?php
require_once __DIR__ . '/../config/database.php';

/* ============================== DASAR ============================== */

function redirect(string $path): void { header('Location: ' . $path); exit; }
function setFlash(string $tipe, string $pesan): void { $_SESSION['flash'][] = ['tipe' => $tipe, 'pesan' => $pesan]; }
function getFlash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function sanitize($s): string { return htmlspecialchars(trim((string) $s), ENT_QUOTES, 'UTF-8'); }
function e($s): string { return sanitize($s); }
function potong($s, int $n): string { $s = (string) $s; return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s; }

/* ============================== CSRF ============================== */

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrfField(): string { return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">'; }
function csrfUrl(): string { return 'csrf_token=' . generateCsrfToken(); }

function verifyCsrfToken(): void
{
    $token = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');
    if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        setFlash('danger', 'Token keamanan tidak valid. Silakan ulangi dari halaman sebelumnya.');
        redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/index.php');
    }
    unset($_SESSION['csrf_token']);
}

/* ============================== AUTH ============================== */

function isLoggedIn(): bool { return !empty($_SESSION['user_id']); }
function namaUser(): string { return $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'admin'); }
function idUser(): int { return (int) ($_SESSION['user_id'] ?? 0); }

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/auth/login.php');
    }
}

/* ============================== FORMAT ============================== */

function rupiah($n, bool $prefix = true): string
{
    $s = number_format((float) $n, 0, ',', '.');
    return $prefix ? 'Rp ' . $s : $s;
}
function angka($s): int
{
    $s = preg_replace('/[^0-9]/', '', (string) $s);
    return (int) ($s === '' ? 0 : $s);
}
function normalisasiHp(?string $hp): string
{
    $d = preg_replace('/[^0-9]/', '', (string) $hp);
    if ($d === '') return '';
    if (str_starts_with($d, '0'))  $d = '62' . substr($d, 1);
    elseif (str_starts_with($d, '8')) $d = '62' . $d;
    return '+' . $d;
}
function tglId(?string $tgl, bool $tahun = true): string
{
    if (!$tgl) return '-';
    $t = strtotime($tgl);
    if (!$t) return '-';
    return date('d', $t) . ' ' . bulanSingkat((int) date('n', $t)) . ($tahun ? ' ' . date('Y', $t) : '');
}
function tglAngka(?string $tgl): string
{
    if (!$tgl) return '-';
    $t = strtotime($tgl);
    return $t ? date('d-m-Y', $t) : '-';
}
function bulanSingkat(int $b): string
{
    return ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][$b] ?? '';
}
function hariPanjang(?int $ts = null): string
{
    $ts = $ts ?: time();
    $h = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    return $h[(int) date('w', $ts)] . ', ' . date('j', $ts) . ' ' . bulanPanjang((int) date('n', $ts)) . ' ' . date('Y', $ts);
}
function bulanPanjang(int $b): string
{
    return ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][$b] ?? '';
}
function terbilang(int $n): string
{
    if ($n < 0) return 'Minus ' . terbilang(-$n);
    $angka = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
    if ($n < 12) return $angka[$n];
    if ($n < 20) return terbilang($n - 10) . ' Belas';
    if ($n < 100) return terbilang(intdiv($n, 10)) . ' Puluh' . ($n % 10 ? ' ' . terbilang($n % 10) : '');
    if ($n < 200) return 'Seratus' . ($n - 100 ? ' ' . terbilang($n - 100) : '');
    if ($n < 1000) return terbilang(intdiv($n, 100)) . ' Ratus' . ($n % 100 ? ' ' . terbilang($n % 100) : '');
    if ($n < 2000) return 'Seribu' . ($n - 1000 ? ' ' . terbilang($n - 1000) : '');
    if ($n < 1000000) return terbilang(intdiv($n, 1000)) . ' Ribu' . ($n % 1000 ? ' ' . terbilang($n % 1000) : '');
    if ($n < 1000000000) return terbilang(intdiv($n, 1000000)) . ' Juta' . ($n % 1000000 ? ' ' . terbilang($n % 1000000) : '');
    if ($n < 1000000000000) return terbilang(intdiv($n, 1000000000)) . ' Miliar' . ($n % 1000000000 ? ' ' . terbilang($n % 1000000000) : '');
    return terbilang(intdiv($n, 1000000000000)) . ' Triliun' . ($n % 1000000000000 ? ' ' . terbilang($n % 1000000000000) : '');
}

function formatRentang(string $mulai, string $finish): string
{
    $a = strtotime($mulai); $b = strtotime($finish);
    if (!$a || !$b) return '-';
    if (date('m', $a) === date('m', $b) && date('Y', $a) === date('Y', $b)) {
        return date('d', $a) . '-' . date('d', $b) . ' ' . bulanSingkat((int) date('n', $a)) . ' ' . date('y', $a);
    }
    return date('d', $a) . ' ' . bulanSingkat((int) date('n', $a)) . ' - ' . date('d', $b) . ' ' . bulanSingkat((int) date('n', $b)) . ' ' . date('y', $b);
}

function hitungHari(?string $mulai, ?string $finish): int
{
    if (!$mulai || !$finish) return 0;
    $a = strtotime($mulai); $b = strtotime($finish);
    if (!$a || !$b || $b < $a) return 0;
    return (int) floor(($b - $a) / 86400) + 1; // inklusif: 27-30 Sept = 4 hari
}

/* ============================== LABEL ============================== */

function daftarStatus(): array
{
    return ['draft', 'inquiry', 'quoted', 'waiting_dp', 'booked', 'in_trip', 'completed', 'invoiced', 'paid', 'reported', 'cancelled', 'closed'];
}
function statusLabel(string $s): string
{
    $m = [
        'draft' => 'Draft', 'inquiry' => 'Inquiry', 'quoted' => 'Penawaran', 'waiting_dp' => 'Menunggu DP',
        'booked' => 'Booked', 'in_trip' => 'Sedang Trip', 'completed' => 'Selesai Trip',
        'invoiced' => 'Invoice Terbit', 'paid' => 'Lunas', 'reported' => 'Masuk Laporan',
        'cancelled' => 'Batal', 'closed' => 'Ditutup',
        'terbit' => 'Invoice Terbit', 'sebagian' => 'Dibayar Sebagian', 'lunas' => 'Lunas', 'batal' => 'Batal',
    ];
    return $m[$s] ?? ucfirst(str_replace('_', ' ', $s));
}
function statusBadge(string $s): string
{
    $m = [
        'draft' => 'slate', 'inquiry' => 'slate', 'quoted' => 'blue', 'waiting_dp' => 'amber',
        'booked' => 'blue', 'in_trip' => 'cyan', 'completed' => 'green', 'invoiced' => 'blue',
        'paid' => 'green', 'reported' => 'slate', 'cancelled' => 'red', 'closed' => 'slate',
        'terbit' => 'blue', 'sebagian' => 'amber', 'lunas' => 'green', 'batal' => 'red',
    ];
    $c = $m[$s] ?? 'slate';
    return '<span class="badge-pill pill-' . $c . '">' . e(statusLabel($s)) . '</span>';
}
function labelWilayah(string $w): string { return $w === 'luar_kota' ? 'Luar Kota' : 'Dalam Kota'; }
function labelTipePelanggan(string $t): string
{
    return ['retail' => 'Retail (Perorangan)', 'corporate' => 'Perusahaan / Instansi', 'RO' => 'Repeat Order'][$t] ?? $t;
}
function labelSumber(string $s): string
{
    return ['wa' => 'WhatsApp', 'telepon' => 'Telepon', 'instagram' => 'Instagram', 'tiktok' => 'TikTok',
        'facebook' => 'Facebook', 'website' => 'Website', 'referral' => 'Referral', 'lainnya' => 'Lainnya'][$s] ?? $s;
}

/* ============================== PENGATURAN ============================== */

function semuaSetting(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $res = getDB()->query('SELECT `key`, `value` FROM settings');
        while ($r = $res->fetch_assoc()) $cache[$r['key']] = $r['value'];
    }
    return $cache;
}
function getSetting(string $key, string $default = ''): string
{
    $s = semuaSetting();
    return isset($s[$key]) && $s[$key] !== '' ? (string) $s[$key] : $default;
}
function simpanSetting(string $key, string $value): void
{
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
}

/* ============================== PENOMORAN DOKUMEN ============================== */

function nomorDokumen(string $jenis): string
{
    $db = getDB();
    $prefix  = $jenis === 'invoice' ? getSetting('prefix_invoice', 'INV') : getSetting('prefix_order', 'RN');
    $periode = date('Y-m');
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT urut FROM doc_counters WHERE jenis = ? AND periode = ? FOR UPDATE');
        $stmt->bind_param('ss', $jenis, $periode);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $urut = $row ? ((int) $row['urut'] + 1) : 1;
        if ($row) {
            $upd = $db->prepare('UPDATE doc_counters SET urut = ? WHERE jenis = ? AND periode = ?');
            $upd->bind_param('iss', $urut, $jenis, $periode);
            $upd->execute();
        } else {
            $ins = $db->prepare('INSERT INTO doc_counters (jenis, periode, urut) VALUES (?, ?, ?)');
            $ins->bind_param('ssi', $jenis, $periode, $urut);
            $ins->execute();
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    $pad = str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    return $jenis === 'invoice'
        ? $prefix . '/' . date('Y/m') . '/' . $pad
        : $prefix . '-' . date('ym') . '-' . $pad;
}

/* ============================== ORDER ============================== */

/** Hitung ulang total order dari item + biaya tambahan, simpan, lalu kembalikan nilainya. */
function hitungOrder(int $orderId): array
{
    $db = getDB();
    $tot = ['total_modal' => 0, 'total_jual' => 0, 'total_tambahan' => 0, 'grand_total' => 0, 'margin' => 0];

    $s = $db->prepare('SELECT COALESCE(SUM(subtotal_modal),0) m, COALESCE(SUM(subtotal_jual),0) j FROM order_items WHERE order_id = ?');
    $s->bind_param('i', $orderId);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $tot['total_modal'] = (int) $r['m'];
    $tot['total_jual']  = (int) $r['j'];

    $s = $db->prepare('SELECT COALESCE(SUM(nominal),0) t FROM order_biaya WHERE order_id = ?');
    $s->bind_param('i', $orderId);
    $s->execute();
    $biaya = (int) $s->get_result()->fetch_assoc()['t'];

    $s = $db->prepare('SELECT COALESCE(SUM(biaya),0) t FROM order_includes WHERE order_id = ?');
    $s->bind_param('i', $orderId);
    $s->execute();
    $incBiaya = (int) $s->get_result()->fetch_assoc()['t'];

    $tot['total_tambahan'] = $biaya + $incBiaya;
    $tot['grand_total']    = $tot['total_jual'] + $tot['total_tambahan'];
    $tot['margin']         = $tot['grand_total'] - $tot['total_modal'];

    $u = $db->prepare('UPDATE orders SET total_modal = ?, total_jual = ?, total_tambahan = ?, grand_total = ?, margin = ? WHERE id = ?');
    $u->bind_param('iiiiii', $tot['total_modal'], $tot['total_jual'], $tot['total_tambahan'], $tot['grand_total'], $tot['margin'], $orderId);
    $u->execute();

    return $tot;
}

/** Ambil order lengkap (order + customer + items + include + biaya). */
function ambilOrder(int $id): ?array
{
    $db = getDB();
    $s = $db->prepare('SELECT o.*, c.tipe AS customer_tipe, c.alamat AS customer_alamat, c.email AS customer_email,
                              c.status AS customer_status, p.nama AS partner_nama
                       FROM orders o
                       LEFT JOIN customers c ON c.id = o.customer_id
                       LEFT JOIN partners  p ON p.id = o.partner_id
                       WHERE o.id = ? AND o.deleted_at IS NULL');
    $s->bind_param('i', $id);
    $s->execute();
    $o = $s->get_result()->fetch_assoc();
    if (!$o) return null;

    $s = $db->prepare('SELECT oi.*, p.nama AS partner_nama FROM order_items oi
                       LEFT JOIN partners p ON p.id = oi.partner_id
                       WHERE oi.order_id = ? ORDER BY oi.id');
    $s->bind_param('i', $id);
    $s->execute();
    $o['items'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);

    $s = $db->prepare('SELECT * FROM order_includes WHERE order_id = ? ORDER BY id');
    $s->bind_param('i', $id);
    $s->execute();
    $o['includes'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);

    $s = $db->prepare('SELECT * FROM order_biaya WHERE order_id = ? ORDER BY id');
    $s->bind_param('i', $id);
    $s->execute();
    $o['biaya'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);

    $s = $db->prepare('SELECT * FROM invoices WHERE order_id = ? AND status <> "batal" ORDER BY id DESC');
    $s->bind_param('i', $id);
    $s->execute();
    $o['invoices'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);

    $s = $db->prepare('SELECT * FROM status_logs WHERE order_id = ? ORDER BY id DESC LIMIT 30');
    $s->bind_param('i', $id);
    $s->execute();
    $o['logs'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);

    return $o;
}

function catatStatus(int $orderId, ?string $lama, string $baru, string $catatan = ''): void
{
    $db = getDB();
    $oleh = namaUser();
    $s = $db->prepare('INSERT INTO status_logs (order_id, status_lama, status_baru, catatan, oleh) VALUES (?, ?, ?, ?, ?)');
    $s->bind_param('issss', $orderId, $lama, $baru, $catatan, $oleh);
    $s->execute();
}

/** Cek bentrok jadwal unit. Kembalikan daftar order yang bertumpuk. */
function cekBentrokUnit(int $unitId, string $mulai, string $finish, int $kecualiOrder = 0): array
{
    $db = getDB();
    $s = $db->prepare('SELECT o.id, o.nomor_order, o.tgl_mulai, o.tgl_finish, o.nama_pesanan
                       FROM order_items i JOIN orders o ON o.id = i.order_id
                       WHERE i.unit_id = ? AND o.id <> ? AND o.deleted_at IS NULL
                         AND o.status NOT IN ("cancelled","closed","draft")
                         AND NOT (o.tgl_finish < ? OR o.tgl_mulai > ?)
                       ORDER BY o.tgl_mulai');
    $s->bind_param('iiss', $unitId, $kecualiOrder, $mulai, $finish);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

/** Nama partner (Support By) per unit, unik, dipisah koma. */
function partnerList(array $order): string
{
    $names = [];
    foreach (($order['items'] ?? []) as $it) {
        if (!empty($it['partner_nama'])) $names[$it['partner_nama']] = true;
    }
    return implode(', ', array_keys($names));
}

/* ============================== TEKS WHATSAPP ============================== */

function teksWaOrder(array $o, bool $denganHarga = true): string
{
    $garis = '___________________________';
    $hari  = (int) $o['jumlah_hari'];
    $baris = [];
    $baris[] = getSetting('nama_pt', 'PT. SERIBU NUSANTARA RENTAL');
    $baris[] = '';
    $baris[] = 'Pelayanan: ' . labelWilayah($o['wilayah_pelayanan']) . ' ' . $o['kota'];
    $baris[] = 'Tanggal: ' . date('d-m-Y', strtotime($o['tgl_mulai'])) . ' s/d ' . date('d-m-Y', strtotime($o['tgl_finish'])) . ' (' . $hari . ' Day)';
    $baris[] = $garis;

    foreach ($o['items'] as $i => $it) {
        if ($i > 0) $baris[] = '- - - - - - - - - - - - - -';
        $baris[] = 'Nama Driver : ' . ($it['nama_driver'] ?: '-');
        $baris[] = 'Hp/Wa : ' . ($it['hp_driver'] ?: '-');
        $baris[] = 'Unit : ' . $it['nama_unit'];
        $baris[] = 'No. Plat : ' . $it['nopol'];
    }
    $baris[] = $garis;
    $baris[] = 'Stanby: ' . ($o['standby_point'] ?: '-');
    $baris[] = 'Flight: ' . ($o['flight'] ?: '-');
    $baris[] = 'Jam: ' . ($o['jam_koordinasi'] ? 'Kordinasi dengan user' : ($o['jam'] ?: '-'));
    $baris[] = '';
    $baris[] = 'Pesanan : ' . $o['nama_pesanan'];
    $baris[] = 'Pic : ' . ($o['nama_pic'] ?: '-');
    $baris[] = 'Hp/Wa : ' . ($o['hp_pic'] ?: '-');
    $baris[] = '';
    $inc = array_map(fn($x) => $x['nama'], $o['includes']);
    $baris[] = 'Include : ' . ($inc ? implode('+', $inc) : '-');

    if ($denganHarga) {
        $baris[] = '';
        foreach ($o['items'] as $it) {
            $baris[] = 'Harga ' . $it['nama_unit'] . ' : ' . rupiah($it['harga_jual_per_hari']) . '/hari';
        }
        foreach ($o['biaya'] as $b) {
            $baris[] = $b['nama'] . ' : ' . rupiah($b['nominal']);
        }
        $baris[] = 'Total : ' . rupiah($o['grand_total']) . ' (' . $hari . ' hari)';
    }

    $baris[] = '';
    $baris[] = getSetting('footer_invoice', 'Terimakasih atas Pilihan Perjalanan Anda Bersama Kami.');
    $baris[] = '';
    $baris[] = getSetting('tagline', '');
    $baris[] = '';
    $baris[] = 'Instagram/TikTok : ' . getSetting('instagram_pt', '');
    $baris[] = 'Website : ' . getSetting('website_pt', '');
    $baris[] = 'Email : ' . getSetting('email_pt', '');

    return implode("\n", $baris);
}

/* ============================== UPLOAD ============================== */

function uploadBukti(array $file): array
{
    if (($file['error'] ?? 1) === UPLOAD_ERR_NO_FILE) return ['ok' => false, 'path' => '', 'msg' => ''];
    if ($file['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'path' => '', 'msg' => 'Upload gagal (kode ' . $file['error'] . ').'];

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $izin = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    if (!in_array($ext, $izin, true)) return ['ok' => false, 'path' => '', 'msg' => 'Bukti harus JPG/PNG/WEBP/PDF.'];
    if ($file['size'] > 5 * 1024 * 1024) return ['ok' => false, 'path' => '', 'msg' => 'Ukuran bukti maksimal 5 MB.'];

    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0777, true);
    $nama = 'bukti_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $nama)) {
        return ['ok' => false, 'path' => '', 'msg' => 'Gagal menyimpan file bukti.'];
    }
    return ['ok' => true, 'path' => 'assets/uploads/' . $nama, 'msg' => 'Bukti terunggah.'];
}

/* ============================== PAGINASI ============================== */

function paginasi(int $total, int $perPage, int $halaman): array
{
    $jumlahHalaman = max(1, (int) ceil($total / $perPage));
    $halaman = max(1, min($halaman, $jumlahHalaman));
    return ['total' => $total, 'per_page' => $perPage, 'halaman' => $halaman, 'jumlah_halaman' => $jumlahHalaman, 'offset' => ($halaman - 1) * $perPage];
}
