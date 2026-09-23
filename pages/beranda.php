<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();

$hariIni = date('Y-m-d');
$awalBulan = date('Y-m-01');
$akhirBulan = date('Y-m-t');

/* kartu angka */
$q = fn(string $sql) => (int) $db->query($sql)->fetch_assoc()['c'];

$berjalan = $q("SELECT COUNT(*) c FROM orders WHERE deleted_at IS NULL
                AND status NOT IN ('cancelled','closed')
                AND tgl_mulai <= '$hariIni' AND tgl_finish >= '$hariIni'");
$bulanIni = $q("SELECT COUNT(*) c FROM orders WHERE deleted_at IS NULL
                AND tgl_mulai BETWEEN '$awalBulan' AND '$akhirBulan'");
$unitKeluar = $q("SELECT COUNT(DISTINCT i.unit_id) c FROM order_items i JOIN orders o ON o.id = i.order_id
                  WHERE o.deleted_at IS NULL AND i.unit_id IS NOT NULL
                  AND o.status NOT IN ('cancelled','closed')
                  AND o.tgl_mulai <= '$hariIni' AND o.tgl_finish >= '$hariIni'");

$inv = $db->query("SELECT COUNT(*) c, COALESCE(SUM(sisa),0) s FROM invoices WHERE status IN ('terbit','sebagian')")->fetch_assoc();
$pendapatanBulan = (int) ($db->query("SELECT COALESCE(SUM(grand_total),0) t FROM orders
                                      WHERE deleted_at IS NULL AND status NOT IN ('cancelled','closed')
                                      AND tgl_mulai BETWEEN '$awalBulan' AND '$akhirBulan'")->fetch_assoc()['t'] ?? 0);
$marginBulan = (int) ($db->query("SELECT COALESCE(SUM(margin),0) t FROM orders
                                  WHERE deleted_at IS NULL AND status NOT IN ('cancelled','closed')
                                  AND tgl_mulai BETWEEN '$awalBulan' AND '$akhirBulan'")->fetch_assoc()['t'] ?? 0);

/* papan status */
$papan = [];
$res = $db->query("SELECT status, COUNT(*) c FROM orders WHERE deleted_at IS NULL GROUP BY status");
while ($r = $res->fetch_assoc()) $papan[$r['status']] = (int) $r['c'];

/* grafik 6 bulan terakhir */
$grafik = [];
for ($i = 5; $i >= 0; $i--) {
    $awal = date('Y-m-01', strtotime("-$i month"));
    $akhir = date('Y-m-t', strtotime("-$i month"));
    $st = $db->prepare("SELECT COUNT(*) c, COALESCE(SUM(grand_total),0) t FROM orders
                        WHERE deleted_at IS NULL AND status NOT IN ('cancelled','closed')
                        AND tgl_mulai BETWEEN ? AND ?");
    $st->bind_param('ss', $awal, $akhir);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $grafik[] = ['bulan' => bulanSingkat((int) date('n', strtotime($awal))), 'jumlah' => (int) $r['c'], 'nilai' => (int) $r['t']];
}
$maxNilai = max(1, max(array_column($grafik, 'nilai')));

/* pesanan terbaru */
$terbaru = $db->query("SELECT o.id, o.nomor_order, o.tgl_mulai, o.nama_pesanan, o.status, o.grand_total,
        (SELECT GROUP_CONCAT(DISTINCT i.nopol SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) nopol
    FROM orders o WHERE o.deleted_at IS NULL
    ORDER BY o.created_at DESC, o.id DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

/* peringatan data belum lengkap */
$tanpaUnit = $q("SELECT COUNT(*) c FROM orders o WHERE o.deleted_at IS NULL AND o.status NOT IN ('cancelled','closed')
                 AND NOT EXISTS (SELECT 1 FROM order_items i WHERE i.order_id = o.id)");

$judulHalaman = 'Beranda';
$menuAktif = 'beranda';
include __DIR__ . '/../includes/header.php';
?>
<div class="stat-grid">
    <div class="stat">
        <div class="stat-label">Pesanan berjalan hari ini</div>
        <div class="stat-value"><?= $berjalan ?></div>
        <div class="stat-note"><?= e(tglId($hariIni)) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Pesanan bulan ini</div>
        <div class="stat-value"><?= $bulanIni ?></div>
        <div class="stat-note"><?= e(bulanPanjang((int) date('n')) . ' ' . date('Y')) ?></div>
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
</div>

<div class="card-box">
    <h2 class="card-title">Pendapatan &amp; margin bulan ini</h2>
    <div class="row">
        <div class="col-md-4">
            <div class="stat-label">Nilai pesanan (jual)</div>
            <div class="stat-value"><?= rupiah($pendapatanBulan) ?></div>
        </div>
        <div class="col-md-4">
            <div class="stat-label">Margin (internal)</div>
            <div class="stat-value"><?= rupiah($marginBulan) ?></div>
        </div>
        <div class="col-md-4">
            <div class="stat-label">Pesanan data belum lengkap</div>
            <div class="stat-value"><?= $tanpaUnit ?></div>
        </div>
    </div>
</div>

<div class="card-box">
    <h2 class="card-title">Papan status</h2>
    <div class="papan-status">
        <?php foreach (daftarStatus() as $st): ?>
            <a class="papan-item" href="<?= BASE_URL ?>/pages/pesanan_list.php?status=<?= $st ?>">
                <?= e(statusLabel($st)) ?> <b><?= (int) ($papan[$st] ?? 0) ?></b>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-box">
            <h2 class="card-title">6 bulan terakhir (nilai pesanan)</h2>
            <div class="grafik">
                <?php foreach ($grafik as $g): ?>
                    <div class="kolom">
                        <div class="kolom-nilai"><?= $g['jumlah'] ?> psn</div>
                        <div class="batang <?= $g['nilai'] === 0 ? 'dim' : '' ?>" style="height: <?= max(3, (int) round($g['nilai'] / $maxNilai * 100)) ?>%"></div>
                        <div class="kolom-label"><?= e($g['bulan']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="form-text mt-2">Nilai tertinggi: <?= rupiah($maxNilai) ?>. Angka batang = jumlah pesanan.</div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-box">
            <h2 class="card-title">Pesanan terbaru</h2>
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
<?php include __DIR__ . '/../includes/footer.php'; ?>
