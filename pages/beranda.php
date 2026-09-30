<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();

$hariIni = date('Y-m-d');
$q = fn(string $sql) => (int) $db->query($sql)->fetch_assoc()['c'];

/* ======================= PENYARING PERIODE =======================
   Semua angka pada bagian "Ringkasan periode" mengikuti pilihan ini.
   Bagian lain (pesanan berjalan hari ini, papan status, invoice belum lunas)
   sengaja tetap kondisi saat ini dan diberi label agar tidak tertukar. */
$periodePilihan = [
    'hari'   => 'Hari ini',
    '7hari'  => '7 hari',
    'bulan'  => 'Bulan ini',
    '3bulan' => '3 bulan',
    'tahun'  => 'Tahun ini',
    'custom' => 'Rentang sendiri',
];
$periode = (string) ($_GET['periode'] ?? 'bulan');
if (!isset($periodePilihan[$periode])) $periode = 'bulan';

$dari   = (string) ($_GET['dari'] ?? '');
$sampai = (string) ($_GET['sampai'] ?? '');
$validTgl = function (string $t): bool { return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $t); };

switch ($periode) {
    case 'hari':
        $mulai = $sampai = $hariIni;
        break;
    case '7hari':
        $mulai  = date('Y-m-d', strtotime('-6 days'));
        $sampai = $hariIni;
        break;
    case '3bulan':
        $mulai  = date('Y-m-01', strtotime('-2 month'));
        $sampai = date('Y-m-t');
        break;
    case 'tahun':
        $mulai  = date('Y-01-01');
        $sampai = date('Y-12-31');
        break;
    case 'custom':
        $mulai  = $validTgl($dari) ? $dari : date('Y-m-01');
        $sampai = $validTgl($sampai) ? $sampai : $hariIni;
        if ($sampai < $mulai) { $t = $mulai; $mulai = $sampai; $sampai = $t; }
        break;
    default:
        $periode = 'bulan';
        $mulai   = date('Y-m-01');
        $sampai  = date('Y-m-t');
}
$labelPeriode = tglAngka($mulai) . ' s/d ' . tglAngka($sampai);

/* syarat: pesanan historis impor (Lunas tanpa invoice) tidak dihitung pendapatan */
$syaratHistoris = "(NOT (o.status = 'paid' AND NOT EXISTS (SELECT 1 FROM invoices iv WHERE iv.order_id = o.id AND iv.status <> 'batal')))";

/* ======================= RINGKASAN PERIODE ======================= */
$st = $db->prepare("SELECT COUNT(*) c, COALESCE(SUM(o.grand_total),0) j, COALESCE(SUM(o.margin),0) m
                    FROM orders o
                    WHERE o.deleted_at IS NULL AND o.status NOT IN ('cancelled','closed')
                      AND o.tgl_mulai BETWEEN ? AND ?");
$st->bind_param('ss', $mulai, $sampai);
$st->execute();
$ringkas = $st->get_result()->fetch_assoc();

$st = $db->prepare("SELECT COUNT(DISTINCT i.unit_id) c FROM order_items i JOIN orders o ON o.id = i.order_id
                    WHERE o.deleted_at IS NULL AND i.unit_id IS NOT NULL
                      AND o.status NOT IN ('cancelled','closed')
                      AND o.tgl_mulai BETWEEN ? AND ?");
$st->bind_param('ss', $mulai, $sampai);
$st->execute();
$unitPeriode = (int) $st->get_result()->fetch_assoc()['c'];

/* pendapatan & margin periode (historis dikecualikan) */
$st = $db->prepare("SELECT COALESCE(SUM(o.grand_total),0) j, COALESCE(SUM(o.margin),0) m,
                           COUNT(*) c
                    FROM orders o
                    WHERE o.deleted_at IS NULL AND o.status NOT IN ('cancelled','closed')
                      AND $syaratHistoris
                      AND o.tgl_mulai BETWEEN ? AND ?");
$st->bind_param('ss', $mulai, $sampai);
$st->execute();
$nilaiPeriode = $st->get_result()->fetch_assoc();

/* ======================= KONDISI SAAT INI ======================= */
$berjalan = $q("SELECT COUNT(*) c FROM orders WHERE deleted_at IS NULL
                AND status NOT IN ('cancelled','closed')
                AND tgl_mulai <= '$hariIni' AND tgl_finish >= '$hariIni'");

$unitKeluar = $q("SELECT COUNT(DISTINCT i.unit_id) c FROM order_items i JOIN orders o ON o.id = i.order_id
                  WHERE o.deleted_at IS NULL AND i.unit_id IS NOT NULL
                  AND o.status NOT IN ('cancelled','closed')
                  AND o.tgl_mulai <= '$hariIni' AND o.tgl_finish >= '$hariIni'");

$inv = $db->query("SELECT COUNT(*) c, COALESCE(SUM(i.sisa),0) s FROM invoices i
                   JOIN orders o ON o.id = i.order_id
                   WHERE i.status IN ('terbit','sebagian')
                     AND o.deleted_at IS NULL AND o.status NOT IN ('cancelled','closed')")->fetch_assoc();

/* pesanan historis lunas tanpa invoice (untuk keterangan) */
$historis = $db->query("SELECT COUNT(*) c, COALESCE(SUM(o.grand_total),0) t FROM orders o
                        WHERE o.deleted_at IS NULL AND o.status = 'paid'
                          AND NOT EXISTS (SELECT 1 FROM invoices iv WHERE iv.order_id = o.id AND iv.status <> 'batal')")->fetch_assoc();

/* papan status (kondisi saat ini) */
$papan = [];
$res = $db->query("SELECT status, COUNT(*) c, COALESCE(SUM(grand_total),0) t FROM orders
                   WHERE deleted_at IS NULL GROUP BY status");
while ($r = $res->fetch_assoc()) $papan[$r['status']] = ['c' => (int) $r['c'], 't' => (int) $r['t']];

/* data belum lengkap */
$tanpaUnit = $q("SELECT COUNT(*) c FROM orders o WHERE o.deleted_at IS NULL AND o.status NOT IN ('cancelled','closed')
                 AND NOT EXISTS (SELECT 1 FROM order_items i WHERE i.order_id = o.id)");

/* ======================= TREN 6 BULAN ======================= */
$grafik = [];
for ($i = 5; $i >= 0; $i--) {
    $awal  = date('Y-m-01', strtotime("-$i month"));
    $akhir = date('Y-m-t', strtotime("-$i month"));
    $st = $db->prepare("SELECT COUNT(*) c, COALESCE(SUM(o.grand_total),0) j, COALESCE(SUM(o.margin),0) m
                        FROM orders o
                        WHERE o.deleted_at IS NULL AND o.status NOT IN ('cancelled','closed')
                          AND $syaratHistoris
                          AND o.tgl_mulai BETWEEN ? AND ?");
    $st->bind_param('ss', $awal, $akhir);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $grafik[] = [
        'bulan'  => bulanSingkat((int) date('n', strtotime($awal))) . ' ' . date('y', strtotime($awal)),
        'kode'   => date('Y-m', strtotime($awal)),
        'jumlah' => (int) $r['c'],
        'nilai'  => (int) $r['j'],
        'margin' => (int) $r['m'],
    ];
}
$maxNilai = max(1, max(array_column($grafik, 'nilai')));

/* ======================= DAFTAR PESANAN PERIODE ======================= */
$st = $db->prepare("SELECT o.id, o.nomor_order, o.tgl_mulai, o.tgl_finish, o.jumlah_hari, o.nama_pesanan,
                           o.status, o.grand_total, o.kota, o.nama_pic,
                           (SELECT GROUP_CONCAT(DISTINCT i.nopol SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) nopol,
                           (SELECT GROUP_CONCAT(DISTINCT i.nama_driver SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) driver
                    FROM orders o
                    WHERE o.deleted_at IS NULL AND o.status NOT IN ('cancelled','closed')
                      AND o.tgl_mulai BETWEEN ? AND ?
                    ORDER BY o.tgl_mulai DESC, o.id DESC LIMIT 12");
$st->bind_param('ss', $mulai, $sampai);
$st->execute();
$pesananPeriode = $st->get_result()->fetch_all(MYSQLI_ASSOC);

/* pesanan terbaru diinput */
$terbaru = $db->query("SELECT o.id, o.nomor_order, o.tgl_mulai, o.nama_pesanan, o.status, o.grand_total,
        (SELECT GROUP_CONCAT(DISTINCT i.nopol SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) nopol
    FROM orders o WHERE o.deleted_at IS NULL
    ORDER BY o.created_at DESC, o.id DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

$judulHalaman = 'Beranda';
$menuAktif = 'beranda';
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/beranda.css?v=20260930d">
<div class="card-box">
    <h2 class="card-title">Ringkasan periode</h2>
    <div class="periode-bar">
        <?php foreach ($periodePilihan as $k => $lbl): ?>
            <a class="periode-chip <?= $periode === $k ? 'active' : '' ?>"
               href="?periode=<?= e($k) ?><?= $k === 'custom' ? '&dari=' . e($mulai) . '&sampai=' . e($sampai) : '' ?>"><?= e($lbl) ?></a>
        <?php endforeach; ?>
        <span class="periode-label"><?= e($labelPeriode) ?></span>
    </div>
    <form class="periode-form" method="get">
        <input type="hidden" name="periode" value="custom">
        <label for="dari">Dari</label>
        <input type="date" id="dari" name="dari" class="form-control form-control-sm" value="<?= e($mulai) ?>">
        <label for="sampai">Sampai</label>
        <input type="date" id="sampai" name="sampai" class="form-control form-control-sm" value="<?= e($sampai) ?>">
        <button class="btn btn-sm btn-outline-secondary" type="submit">Terapkan rentang</button>
    </form>

    <div class="stat-grid">
        <div class="stat">
            <div class="stat-label">Pesanan dalam periode</div>
            <div class="stat-value"><?= (int) $ringkas['c'] ?></div>
            <div class="stat-note"><?= e($labelPeriode) ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Unit bertugas dalam periode</div>
            <div class="stat-value"><?= $unitPeriode ?></div>
            <div class="stat-note">unit berbeda yang dipakai</div>
        </div>
        <div class="stat">
            <div class="stat-label">Nilai pesanan (jual)</div>
            <div class="stat-value" style="font-size:22px"><?= rupiah($nilaiPeriode['j']) ?></div>
            <div class="stat-note"><?= (int) $nilaiPeriode['c'] ?> pesanan dihitung</div>
        </div>
        <div class="stat">
            <div class="stat-label">Margin (internal)</div>
            <div class="stat-value" style="font-size:22px"><?= rupiah($nilaiPeriode['m']) ?></div>
            <div class="stat-note">hanya untuk internal</div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <h2 class="card-title">Pesanan dalam periode (terbaru 12)</h2>
            <div class="table-wrap">
                <table class="tabel">
                    <thead><tr><th>No. Order</th><th>Tanggal</th><th>Pesanan</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    <?php if (!$pesananPeriode): ?>
                        <tr><td colspan="4"><div class="table-kosong">Tidak ada pesanan pada rentang tanggal ini.</div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($pesananPeriode as $r): ?>
                        <tr>
                            <td class="mono"><a href="<?= BASE_URL ?>/pages/pesanan_detail.php?id=<?= (int) $r['id'] ?>"><?= e($r['nomor_order']) ?></a>
                                <div class="muted"><?= e($r['nopol'] ?: '-') ?> &middot; <?= e($r['driver'] ?: '-') ?></div></td>
                            <td><?= e(tglId($r['tgl_mulai'])) ?><div class="muted"><?= (int) $r['jumlah_hari'] ?> hari</div></td>
                            <td><?= e(potong($r['nama_pesanan'], 30)) ?><div><?= statusBadge($r['status']) ?></div></td>
                            <td class="num"><?= rupiah($r['grand_total'], false) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <a class="btn btn-sm btn-outline-secondary mt-2"
               href="<?= BASE_URL ?>/pages/pesanan_list.php?dari=<?= e($mulai) ?>&sampai=<?= e($sampai) ?>">Buka di Data Pesanan</a>
        </div>
        <div class="col-lg-6">
            <h2 class="card-title">Kondisi saat ini (<?= e(tglId($hariIni)) ?>)</h2>
            <div class="stat-grid">
                <div class="stat">
                    <div class="stat-label">Pesanan berjalan hari ini</div>
                    <div class="stat-value"><?= $berjalan ?></div>
                    <div class="stat-note">sedang dipakai hari ini</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Unit keluar hari ini</div>
                    <div class="stat-value"><?= $unitKeluar ?></div>
                    <div class="stat-note">unit yang sedang bertugas</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Invoice belum lunas</div>
                    <div class="stat-value"><?= (int) $inv['c'] ?></div>
                    <div class="stat-note">Sisa tagihan <?= rupiah($inv['s']) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-label">Pesanan data belum lengkap</div>
                    <div class="stat-value"><?= $tanpaUnit ?></div>
                    <div class="stat-note">belum ada unit terpasang</div>
                </div>
            </div>
            <?php if ((int) $historis['c'] > 0): ?>
                <div class="form-text">
                    Catatan: <?= (int) $historis['c'] ?> pesanan historis (impor arsip) bertanda Lunas tanpa invoice,
                    senilai <?= rupiah($historis['t']) ?>, tidak ikut dihitung sebagai pendapatan.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card-box">
    <h2 class="card-title">Papan status (kondisi saat ini)</h2>
    <div class="papan-status">
        <?php foreach (daftarStatus() as $st):
            $jml = (int) ($papan[$st]['c'] ?? 0);
            $nil = (int) ($papan[$st]['t'] ?? 0); ?>
            <a class="papan-item" href="<?= BASE_URL ?>/pages/pesanan_list.php?status=<?= $st ?>">
                <?= e(statusLabel($st)) ?> <b><?= $jml ?></b>
                <?php if ($nil > 0): ?><span class="papan-nilai"><?= rupiah($nil, false) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-box">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2 class="card-title mb-0">Tren 6 bulan terakhir</h2>
                <div class="graf-bar">
                    <button type="button" class="periode-chip active" data-metrik="nilai">Nilai jual</button>
                    <button type="button" class="periode-chip" data-metrik="margin">Margin</button>
                    <button type="button" class="periode-chip" data-metrik="jumlah">Jumlah pesanan</button>
                    <button type="button" class="periode-chip" data-tipe="bar">Batang</button>
                    <button type="button" class="periode-chip" data-tipe="line">Garis</button>
                </div>
            </div>
            <div class="graf-kotak">
                <canvas id="kanvasGrafik" data-url="<?= BASE_URL ?>/pages/pesanan_list.php"></canvas>
            </div>
            <script type="application/json" id="dataGrafik"><?= json_encode($grafik, JSON_UNESCAPED_UNICODE) ?></script>
            <div class="form-text mt-2">
                Arahkan kursor untuk melihat rincian nilai, margin, dan jumlah pesanan.
                Klik batang/titik untuk membuka daftar pesanan bulan tersebut.
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-box">
            <h2 class="card-title">Pesanan terbaru diinput</h2>
            <div class="table-wrap">
                <table class="tabel">
                    <thead><tr><th>No. Order</th><th>Pesanan</th><th>Nopol</th><th>Status</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    <?php if (!$terbaru): ?>
                        <tr><td colspan="5"><div class="table-kosong">Belum ada pesanan. Mulai dari menu "Input Pesanan".</div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($terbaru as $r): ?>
                        <tr>
                            <td class="mono"><a href="<?= BASE_URL ?>/pages/pesanan_detail.php?id=<?= (int) $r['id'] ?>"><?= e($r['nomor_order']) ?></a>
                                <div class="muted"><?= e(tglId($r['tgl_mulai'])) ?></div></td>
                            <td><?= e(potong($r['nama_pesanan'], 26)) ?></td>
                            <td class="mono"><?= e((string) $r['nopol']) ?></td>
                            <td><?= statusBadge($r['status']) ?></td>
                            <td class="num"><?= rupiah($r['grand_total'], false) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/kalender_unit.php'; ?>

<script src="<?= BASE_URL ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/beranda_chart.js?v=20260930a"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
