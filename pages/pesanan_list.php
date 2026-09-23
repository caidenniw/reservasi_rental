<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();

$cari   = trim((string) ($_GET['cari'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$bulan  = trim((string) ($_GET['bulan'] ?? ''));
$hal    = max(1, (int) ($_GET['hal'] ?? 1));
$perHal = 15;

$where  = ['o.deleted_at IS NULL'];
$params = [];
$types  = '';

if ($cari !== '') {
    $where[] = '(o.nomor_order LIKE ? OR o.nama_pesanan LIKE ? OR o.nama_pic LIKE ? OR o.kota LIKE ?
                 OR EXISTS (SELECT 1 FROM order_items x WHERE x.order_id = o.id AND x.nopol LIKE ?))';
    for ($i = 0; $i < 5; $i++) { $params[] = '%' . $cari . '%'; $types .= 's'; }
}
if ($status !== '') { $where[] = 'o.status = ?'; $params[] = $status; $types .= 's'; }
if ($bulan !== '')  { $where[] = "DATE_FORMAT(o.tgl_mulai, '%Y-%m') = ?"; $params[] = $bulan; $types .= 's'; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

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
    ORDER BY o.tgl_mulai DESC, o.id DESC
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

$judulHalaman = 'Data Pesanan & Invoice';
$menuAktif = 'data';
include __DIR__ . '/../includes/header.php';
?>
<div class="card-box">
    <form class="row g-2 align-items-end" method="get">
        <div class="col-md-4">
            <label class="form-label" for="cari">Cari</label>
            <input type="text" class="form-control form-control-sm" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="no. order / pesanan / PIC / nopol / kota">
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
            <label class="form-label" for="bulan">Bulan</label>
            <select class="form-select form-select-sm" id="bulan" name="bulan">
                <option value="">Semua</option>
                <?php foreach ($bulanList as $b): ?>
                    <?php [$yy, $mm] = explode('-', $b['b']); ?>
                    <option value="<?= e($b['b']) ?>" <?= $bulan === $b['b'] ? 'selected' : '' ?>><?= e(bulanPanjang((int) $mm) . ' ' . $yy) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <div class="baris-aksi">
                <button type="submit" class="btn btn-sm btn-primary">Terapkan</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/pages/pesanan_list.php">Reset</a>
                <a class="btn btn-sm btn-outline-secondary" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">Export CSV</a>
                <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/pages/pesanan_form.php">+ Input Pesanan</a>
            </div>
        </div>
    </form>
</div>

<div class="card-box">
    <h2 class="card-title"><?= $total ?> pesanan ditemukan</h2>
    <div class="table-wrap">
        <table class="tabel">
            <thead>
            <tr>
                <th>No. Order</th><th>Tanggal</th><th>Pesanan</th><th>Unit</th><th>Status</th>
                <th>Invoice</th><th class="num">Total</th><th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8"><div class="table-kosong">Belum ada pesanan yang cocok. Klik "+ Input Pesanan" untuk mulai.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="mono"><?= e($r['nomor_order']) ?><div class="muted"><?= e($r['kota']) ?></div></td>
                    <td><?= e(tglId($r['tgl_mulai'])) ?><div class="muted"><?= (int) $r['jumlah_hari'] ?> hari</div></td>
                    <td><?= e(potong($r['nama_pesanan'], 40)) ?>
                        <?php if ($r['nama_pic']): ?><div class="muted"><?= e($r['nama_pic']) ?></div><?php endif; ?></td>
                    <td><?= e(potong((string) $r['unit_list'], 28)) ?><div class="muted mono"><?= e((string) $r['nopol_list']) ?></div></td>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td>
                        <?php if ($r['nomor_invoice']): ?>
                            <span class="mono"><?= e($r['nomor_invoice']) ?></span>
                            <div><?= statusBadge((string) $r['inv_status']) ?></div>
                        <?php else: ?>
                            <span class="muted">belum terbit</span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= rupiah($r['grand_total'], false) ?></td>
                    <td><a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/pages/pesanan_detail.php?id=<?= (int) $r['id'] ?>">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pg['jumlah_halaman'] > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= $pg['jumlah_halaman']; $i++): ?>
                    <?php $q = http_build_query(array_merge($_GET, ['hal' => $i])); ?>
                    <li class="page-item <?= $i === $pg['halaman'] ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= e($q) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
