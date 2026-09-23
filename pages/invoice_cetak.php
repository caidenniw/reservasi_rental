<?php
/**
 * Halaman cetak invoice (standalone, tanpa sidebar).
 *   ?id=ID                 -> invoice customer (tanpa modal & margin)
 *   ?id=ID&mode=internal   -> lembar order internal (dengan modal, margin, partner)
 */
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();

$id   = (int) ($_GET['id'] ?? 0);
$mode = ($_GET['mode'] ?? '') === 'internal' ? 'internal' : 'customer';
$embed = (($_GET['embed'] ?? '') === '1');   /* mode pratinjau di dalam iframe: tanpa toolbar, skala kecil */

$st = $db->prepare('SELECT * FROM invoices WHERE id = ?');
$st->bind_param('i', $id);
$st->execute();
$inv = $st->get_result()->fetch_assoc();
if (!$inv) {
    die('Invoice tidak ditemukan.');
}
$order = ambilOrder((int) $inv['order_id']);
if (!$order) {
    die('Pesanan untuk invoice ini tidak ditemukan.');
}
$st = $db->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY urutan, id');
$st->bind_param('i', $id);
$st->execute();
$items = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$st = $db->prepare('SELECT COALESCE(SUM(nominal),0) d FROM payments WHERE invoice_id = ?');
$st->bind_param('i', $id);
$st->execute();
$dibayar = (int) $st->get_result()->fetch_assoc()['d'];
$sisa = max(0, (int) $inv['total'] - $dibayar);

$snap = json_decode((string) $inv['order_snapshot'], true) ?: [];
$custSnap = json_decode((string) $inv['customer_snapshot'], true) ?: [];
$includeTeks = $snap['include'] ?? implode(' + ', array_map(fn($x) => $x['nama'], $order['includes']));
$batal = $inv['status'] === 'batal';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title><?= $mode === 'internal' ? 'Lembar Internal' : 'Invoice' ?> <?= e($inv['nomor_invoice']) ?></title>
<style>
    /* ---- layar ---- */
    body { font-family: "Times New Roman", Georgia, serif; font-size: 12pt; line-height: 1.5; background: #f0f0f0; margin: 0; color: #111; }
    .lembar { width: 210mm; min-height: 297mm; margin: 20px auto; padding: 18mm 18mm 14mm 18mm; background: #fff; box-shadow: 0 0 10px rgba(0,0,0,.2); position: relative; }
    .no-print { max-width: 210mm; margin: 16px auto 0; display: flex; gap: 8px; }
    .no-print button, .no-print a { font-family: "Segoe UI", Arial, sans-serif; font-size: 13px; padding: 8px 14px; border: 1px solid #e62e2e; background: #e62e2e; color: #fff; border-radius: 6px; cursor: pointer; text-decoration: none; }
    .no-print a.abu { background: #fff; color: #33475B; border-color: #CBD5E0; }

    .kop { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e62e2e; padding-bottom: 10px; }
    .kop .brand { font-size: 20pt; font-weight: bold; color: #e62e2e; letter-spacing: .5px; }
    .kop .brand small { display: block; font-size: 9.5pt; font-weight: normal; color: #33475B; letter-spacing: 0; }
    .kop .kontak { text-align: right; font-size: 9.5pt; color: #33475B; }
    .judul { text-align: center; margin: 16px 0 6px; font-size: 16pt; font-weight: bold; letter-spacing: 3px; }
    .nomor { text-align: center; font-size: 11pt; margin-bottom: 14px; }
    .tanda-internal { text-align: center; font-size: 10pt; color: #B7791F; font-weight: bold; letter-spacing: 1px; }

    .dua-kolom { display: flex; gap: 20px; margin-bottom: 14px; }
    .kotak { flex: 1; border: 1px solid #CBD5E0; padding: 8px 10px; font-size: 10.5pt; }
    .kotak h4 { margin: 0 0 6px; font-size: 10pt; text-transform: uppercase; letter-spacing: .5px; color: #4A5568; }
    .kotak table { width: 100%; font-size: 10.5pt; }
    .kotak td { vertical-align: top; padding: 1px 0; }
    .kotak td.k { width: 82px; color: #4A5568; }

    table.rincian { width: 100%; border-collapse: collapse; font-size: 10.5pt; margin-top: 6px; }
    table.rincian th { border: 1px solid #CBD5E0; background: #F2F6F4; padding: 6px; text-align: left; font-size: 10pt; }
    table.rincian td { border: 1px solid #CBD5E0; padding: 6px; vertical-align: top; }
    table.rincian td.num, table.rincian th.num { text-align: right; white-space: nowrap; }

    .total-box { width: 62mm; margin-left: auto; margin-top: 10px; font-size: 11pt; }
    .total-box table { width: 100%; }
    .total-box td { padding: 3px 0; }
    .total-box td.num { text-align: right; }
    .total-box tr.besar td { border-top: 1px solid #333; border-bottom: 1px double #333; font-weight: bold; font-size: 12pt; }

    .bayar { margin-top: 16px; border: 1px solid #CBD5E0; padding: 10px; font-size: 10.5pt; }
    .bayar h4 { margin: 0 0 6px; font-size: 10pt; text-transform: uppercase; letter-spacing: .5px; color: #4A5568; }
    .footer { margin-top: 18px; font-size: 10pt; text-align: center; color: #33475B; }
    .ttd { margin-top: 26px; display: flex; justify-content: flex-end; }
    .ttd div { text-align: center; font-size: 11pt; }
    .ttd .ruang { height: 60px; }
    .watermark { position: absolute; top: 42%; left: 0; right: 0; text-align: center; font-size: 60pt; color: rgba(192,57,43,.16); font-weight: bold; transform: rotate(-18deg); letter-spacing: 8px; }

    body.embed .no-print { display: none !important; }
    body.embed { background: #fff; }
    body.embed .lembar { width: 100%; min-height: 0; margin: 0; box-shadow: none; padding: 6mm 7mm; zoom: .52; }

    @media print {
        body { background: #fff; }
        .no-print { display: none !important; }
        .lembar { width: 100%; margin: 0; padding: 12mm 14mm; box-shadow: none; min-height: auto; }
        @page { size: A4; margin: 0; }
        table.rincian { page-break-inside: auto; }
        tr { page-break-inside: avoid; }
    }
</style>
</head>
<body<?= $embed ? ' class="embed"' : '' ?>>

<div class="no-print">
    <button onclick="window.print()">Cetak / Simpan PDF</button>
    <a href="<?= BASE_URL ?>/pages/pesanan_detail.php?id=<?= (int) $order['id'] ?>" class="abu">Kembali ke Pesanan</a>
    <?php if ($mode === 'customer'): ?>
        <a href="?id=<?= (int) $id ?>&mode=internal" class="abu">Lihat Lembar Internal</a>
    <?php else: ?>
        <a href="?id=<?= (int) $id ?>" class="abu">Lihat Versi Customer</a>
    <?php endif; ?>
</div>

<div class="lembar">
    <?php if ($batal): ?><div class="watermark">BATAL</div><?php endif; ?>

    <div class="kop">
        <div class="brand">
            <?= e(getSetting('nama_pt', 'PT. SERIBU NUSANTARA RENTAL')) ?>
            <small><?= e(getSetting('brand', '1000 RENT CAR')) ?><?= getSetting('tagline') ? ' | ' . e(getSetting('tagline')) : '' ?></small>
        </div>
        <div class="kontak">
            <?php if (getSetting('alamat_pt')): ?><?= e(getSetting('alamat_pt')) ?><br><?php endif; ?>
            <?php if (getSetting('telepon_pt')): ?><?= e(getSetting('telepon_pt')) ?><br><?php endif; ?>
            <?= e(getSetting('email_pt')) ?><br><?= e(getSetting('website_pt')) ?>
        </div>
    </div>

    <div class="judul">INVOICE</div>
    <div class="nomor">
        No: <b><?= e($inv['nomor_invoice']) ?></b>
        <?= (int) $inv['nomor_revisi_ke'] > 0 ? ' &middot; REVISI ke-' . (int) $inv['nomor_revisi_ke'] : '' ?>
        &middot; Tanggal: <?= e(tglAngka($inv['tanggal_invoice'])) ?>
        <?php if ($inv['jatuh_tempo']): ?> &middot; Jatuh tempo: <?= e(tglAngka($inv['jatuh_tempo'])) ?><?php endif; ?>
    </div>
    <?php if ($mode === 'internal'): ?>
        <div class="tanda-internal">LEMBAR ORDER INTERNAL - TIDAK UNTUK CUSTOMER</div>
    <?php endif; ?>

    <div class="dua-kolom">
        <div class="kotak">
            <h4>Ditagihkan kepada</h4>
            <table>
                <tr><td class="k">Nama</td><td>: <?= e($custSnap['nama'] ?? $order['nama_pesanan']) ?></td></tr>
                <tr><td class="k">PIC</td><td>: <?= e($custSnap['pic'] ?? $order['nama_pic'] ?: '-') ?></td></tr>
                <tr><td class="k">HP/WA</td><td>: <?= e($custSnap['hp'] ?? $order['hp_pic'] ?: '-') ?></td></tr>
                <?php if (!empty($custSnap['alamat'])): ?><tr><td class="k">Alamat</td><td>: <?= e($custSnap['alamat']) ?></td></tr><?php endif; ?>
            </table>
        </div>
        <div class="kotak">
            <h4>Detail pesanan</h4>
            <table>
                <tr><td class="k">No. Order</td><td>: <?= e($order['nomor_order']) ?></td></tr>
                <tr><td class="k">Pelayanan</td><td>: <?= e(labelWilayah($snap['wilayah'] ?? $order['wilayah_pelayanan'])) ?> <?= e($snap['kota'] ?? $order['kota']) ?></td></tr>
                <tr><td class="k">Standby</td><td>: <?= e(($snap['standby_point'] ?? $order['standby_point']) ?: '-') ?></td></tr>
                <tr><td class="k">Flight</td><td>: <?= e(($snap['flight'] ?? $order['flight']) ?: '-') ?></td></tr>
                <tr><td class="k">Jam</td><td>: <?= (($snap['jam_koordinasi'] ?? $order['jam_koordinasi']) ? 'Koordinasi dengan user' : e(($snap['jam'] ?? $order['jam']) ?: '-')) ?></td></tr>
            </table>
        </div>
    </div>

    <table class="rincian">
        <thead>
        <tr>
            <th style="width:8mm">No</th>
            <th>Deskripsi</th>
            <th class="num" style="width:16mm">Qty</th>
            <th class="num" style="width:28mm">Harga</th>
            <th class="num" style="width:28mm">Jumlah</th>
            <?php if ($mode === 'internal'): ?><th class="num" style="width:28mm">Modal</th><?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php $no = 0; foreach ($items as $it): $no++; ?>
            <tr>
                <td><?= $no ?></td>
                <td><?= e($it['deskripsi']) ?></td>
                <td class="num"><?= (int) $it['qty'] ?> <?= e($it['satuan']) ?></td>
                <td class="num"><?= rupiah($it['harga_satuan'], false) ?></td>
                <td class="num"><?= rupiah($it['jumlah'], false) ?></td>
                <?php if ($mode === 'internal'): ?>
                    <td class="num">
                        <?php
                        $baris = $no - 1;
                        $modal = 0;
                        if (isset($order['items'][$baris])) {
                            $modal = (int) $order['items'][$baris]['subtotal_modal'];
                        }
                        echo rupiah($modal, false);
                        ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total-box">
        <table>
            <tr><td>Subtotal</td><td class="num"><?= rupiah($order['total_jual'], false) ?></td></tr>
            <tr><td>Biaya tambahan</td><td class="num"><?= rupiah($order['total_tambahan'], false) ?></td></tr>
            <tr class="besar"><td>TOTAL</td><td class="num"><?= rupiah($inv['total'], false) ?></td></tr>
            <tr><td>Sudah dibayar</td><td class="num"><?= rupiah($dibayar, false) ?></td></tr>
            <tr><td>Sisa</td><td class="num"><?= rupiah($sisa, false) ?></td></tr>
        </table>
    </div>

    <?php if ($mode === 'internal'): ?>
        <div class="bayar">
            <h4>Ringkasan internal</h4>
            <table style="width:100%;font-size:10.5pt">
                <tr><td>Total modal unit</td><td class="num" style="text-align:right"><?= rupiah($order['total_modal'], false) ?></td></tr>
                <tr><td>Biaya tambahan</td><td class="num" style="text-align:right"><?= rupiah($order['total_tambahan'], false) ?></td></tr>
                <tr><td><b>Margin</b></td><td class="num" style="text-align:right"><b><?= rupiah($order['margin'], false) ?></b></td></tr>
                <tr><td>Support By / partner</td><td style="text-align:right"><?= e($order['partner_nama'] ?: '-') ?></td></tr>
                <tr><td>Include</td><td style="text-align:right"><?= e($includeTeks ?: '-') ?></td></tr>
                <tr><td>Handle By</td><td style="text-align:right"><?= e($order['handle_by'] ?: '-') ?></td></tr>
                <?php if ($order['catatan']): ?><tr><td>Catatan</td><td style="text-align:right"><?= e($order['catatan']) ?></td></tr><?php endif; ?>
            </table>
        </div>
    <?php endif; ?>

    <div class="bayar">
        <h4>Pembayaran</h4>
        <?= e(getSetting('bank_nama')) ?> <?= e(getSetting('bank_rekening')) ?><br>
        a/n <?= e(getSetting('bank_atas_nama')) ?>
        <?php if (getSetting('npwp')): ?><br>NPWP: <?= e(getSetting('npwp')) ?><?php endif; ?>
    </div>

    <div class="footer">
        <?= e(getSetting('footer_invoice')) ?><br>
        <?php if (getSetting('tagline')): ?><b><?= e(getSetting('tagline')) ?></b><?php endif; ?>
    </div>

    <div class="ttd">
        <div>
            <div><?= e($snap['kota'] ?? $order['kota']) ?>, <?= e(tglAngka($inv['tanggal_invoice'])) ?></div>
            <div><?= e(getSetting('ttd_jabatan', 'Admin Reservasi')) ?></div>
            <div class="ruang"></div>
            <div><?= e(getSetting('ttd_nama') ?: '____________________') ?></div>
        </div>
    </div>
</div>
</body>
</html>
