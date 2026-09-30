<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();

$cari   = trim((string) ($_GET['cari'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$bulan  = trim((string) ($_GET['bulan'] ?? ''));
$dari   = trim((string) ($_GET['dari'] ?? ''));
$sampai = trim((string) ($_GET['sampai'] ?? ''));
$tipe   = trim((string) ($_GET['tipe'] ?? ''));
$urut   = trim((string) ($_GET['urut'] ?? 'sewa'));
$hal    = max(1, (int) ($_GET['hal'] ?? 1));
$perHal = 15;

$where  = ['o.deleted_at IS NULL'];
$params = [];
$types  = '';

if ($cari !== '') {
    $where[] = '(o.nomor_order LIKE ? OR o.nama_pesanan LIKE ? OR o.nama_pic LIKE ? OR o.kota LIKE ?
                 OR EXISTS (SELECT 1 FROM order_items x WHERE x.order_id = o.id AND x.nopol LIKE ?)
                 OR EXISTS (SELECT 1 FROM invoices v WHERE v.order_id = o.id AND v.nomor_invoice LIKE ? AND v.status <> \'batal\'))';
    for ($i = 0; $i < 6; $i++) { $params[] = '%' . $cari . '%'; $types .= 's'; }
}
if ($status !== '') { $where[] = 'o.status = ?'; $params[] = $status; $types .= 's'; }
if ($bulan !== '')  { $where[] = "DATE_FORMAT(o.tgl_mulai, '%Y-%m') = ?"; $params[] = $bulan; $types .= 's'; }
if ($dari !== '')   { $where[] = 'o.tgl_mulai >= ?'; $params[] = $dari; $types .= 's'; }
if ($sampai !== '') { $where[] = 'o.tgl_mulai <= ?'; $params[] = $sampai; $types .= 's'; }
if (in_array($tipe, ['retail', 'corporate', 'RO', 'RTR'], true)) { $where[] = 'o.tipe_pelanggan = ?'; $params[] = $tipe; $types .= 's'; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

/* urutan tampil: whitelist, di luar itu fallback ke default */
$orderSql = match ($urut) {
    'input' => 'o.created_at DESC, o.id DESC',
    'nomor' => 'o.nomor_order DESC, o.id DESC',
    default => 'o.tgl_mulai DESC, o.id DESC',
};

$s = $db->prepare("SELECT COUNT(*) c FROM orders o $whereSql");
if ($types !== '') $s->bind_param($types, ...$params);
$s->execute();
$total = (int) $s->get_result()->fetch_assoc()['c'];
$pg = paginasi($total, $perHal, $hal);

$sqlData = "SELECT o.*,
        (SELECT GROUP_CONCAT(DISTINCT i.nama_unit SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) AS unit_list,
        (SELECT GROUP_CONCAT(DISTINCT i.nopol SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) AS nopol_list,
        inv.nomor_invoice, inv.status AS inv_status, inv.sisa AS inv_sisa
    FROM orders o
    LEFT JOIN invoices inv ON inv.id = (SELECT id FROM invoices WHERE order_id = o.id AND status <> 'batal' ORDER BY id DESC LIMIT 1)
    $whereSql
    ORDER BY $orderSql
    LIMIT ? OFFSET ?";

$paramsData = $params;
$typesData = $types . 'ii';
$paramsData[] = $perHal;
$paramsData[] = $pg['offset'];
$s = $db->prepare($sqlData);
$s->bind_param($typesData, ...$paramsData);
$s->execute();
$rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);

/* export CSV */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pesanan_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Nomor Order', 'Tanggal Mulai', 'Tanggal Selesai', 'Hari', 'Pesanan', 'PIC', 'HP PIC', 'Kota',
        'Wilayah', 'Unit', 'Nopol', 'Driver', 'Status', 'Total Jual', 'Total Modal', 'Margin',
        'Nomor Invoice', 'Status Invoice', 'Sisa']);
    $s = $db->prepare(str_replace('LIMIT ? OFFSET ?', '', $sqlData));
    if ($types !== '') $s->bind_param($types, ...$params);
    $s->execute();
    $all = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($all as $r) {
        $drv = $db->query('SELECT GROUP_CONCAT(nama_driver SEPARATOR ", ") d FROM order_items WHERE order_id = ' . (int) $r['id'])->fetch_assoc()['d'] ?? '';
        fputcsv($out, [
            $r['nomor_order'], $r['tgl_mulai'], $r['tgl_finish'], $r['jumlah_hari'], $r['nama_pesanan'],
            $r['nama_pic'], $r['hp_pic'], $r['kota'], labelWilayah($r['wilayah_pelayanan']),
            $r['unit_list'], $r['nopol_list'], $drv, statusLabel($r['status']),
            $r['total_jual'], $r['total_modal'], $r['margin'], $r['nomor_invoice'],
            $r['inv_status'] ? statusLabel($r['inv_status']) : '', $r['inv_sisa'],
        ]);
    }
    fclose($out);
    exit;
}

/* daftar bulan untuk filter */
$bulanList = $db->query("SELECT DISTINCT DATE_FORMAT(tgl_mulai, '%Y-%m') b FROM orders WHERE deleted_at IS NULL ORDER BY b DESC")->fetch_all(MYSQLI_ASSOC);

/* respons htmx: kirim partial saja (tabel + paginasi), tanpa header/footer */
$isHtmx = (($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true');
if ($isHtmx) {
    include __DIR__ . '/../includes/partial_pesanan_tabel.php';
    exit;
}

$judulHalaman = 'Data Pesanan & Invoice';
$menuAktif = 'data';
include __DIR__ . '/../includes/header.php';
?>
<div class="card-box">
    <form class="row g-2 align-items-end" method="get"
          hx-get="<?= BASE_URL ?>/pages/pesanan_list.php" hx-target="#daftar"
          hx-trigger="submit" hx-push-url="true">
        <div class="col-md-3">
            <label class="form-label" for="cari">Cari</label>
            <input type="text" class="form-control form-control-sm" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="no. order / invoice / pesanan / PIC / nopol / kota">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="status">Status</label>
            <select class="form-select form-select-sm" id="status" name="status">
                <option value="">Semua</option>
                <?php foreach (daftarStatus() as $st): ?>
                    <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(statusLabel($st)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="tipe">Tipe</label>
            <select class="form-select form-select-sm" id="tipe" name="tipe">
                <option value="">Semua</option>
                <option value="retail" <?= $tipe === 'retail' ? 'selected' : '' ?>>Retail</option>
                <option value="corporate" <?= $tipe === 'corporate' ? 'selected' : '' ?>>Corporate</option>
                <option value="RO" <?= $tipe === 'RO' ? 'selected' : '' ?>>Repeat Order</option>
                <option value="RTR" <?= $tipe === 'RTR' ? 'selected' : '' ?>>RTR (Rent to Rent)</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="urut">Urutkan</label>
            <select class="form-select form-select-sm" id="urut" name="urut">
                <option value="sewa" <?= $urut === 'sewa' ? 'selected' : '' ?>>Sewa terbaru</option>
                <option value="input" <?= $urut === 'input' ? 'selected' : '' ?>>Baru diinput</option>
                <option value="nomor" <?= $urut === 'nomor' ? 'selected' : '' ?>>No. order terbaru</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="bulan">Bulan</label>
            <select class="form-select form-select-sm" id="bulan" name="bulan">
                <option value="">Semua</option>
                <?php foreach ($bulanList as $b): ?>
                    <?php [$yy, $mm] = explode('-', $b['b']); ?>
                    <option value="<?= e($b['b']) ?>" <?= $bulan === $b['b'] ? 'selected' : '' ?>><?= e(bulanPanjang((int) $mm) . ' ' . $yy) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="dari">Dari tgl</label>
            <input type="date" class="form-control form-control-sm" id="dari" name="dari" value="<?= e($dari) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="sampai">Sampai tgl</label>
            <input type="date" class="form-control form-control-sm" id="sampai" name="sampai" value="<?= e($sampai) ?>">
        </div>
        <div class="col-md-5">
            <div class="baris-aksi">
                <button type="submit" class="btn btn-sm btn-primary">Terapkan</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/pages/pesanan_list.php"
                   hx-get="<?= BASE_URL ?>/pages/pesanan_list.php" hx-target="#daftar" hx-push-url="true">Reset</a>
                <a class="btn btn-sm btn-outline-secondary" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">Export CSV</a>
                <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/pages/pesanan_form.php">+ Input Pesanan</a>
            </div>
        </div>
    </form>
</div>

<div id="daftar">
<?php include __DIR__ . '/../includes/partial_pesanan_tabel.php'; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
