<?php
/**
 * Halaman cetak invoice (standalone, tanpa sidebar) — mengikuti template referensi:
 * D:\maganghub\invoice\New folder\cetak.php
 *   ?id=ID                -> invoice customer (tanpa modal & margin)
 *   ?id=ID&mode=internal  -> lembar order internal (dengan modal, margin, partner)
 *   ?id=ID&embed=1        -> mode pratinjau (tanpa toolbar, skala kecil)
 */
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();

$id    = (int) ($_GET['id'] ?? 0);
$mode  = ($_GET['mode'] ?? '') === 'internal' ? 'internal' : 'customer';
$embed = (($_GET['embed'] ?? '') === '1');

$st = $db->prepare('SELECT * FROM invoices WHERE id = ?');
$st->bind_param('i', $id);
$st->execute();
$inv = $st->get_result()->fetch_assoc();
if (!$inv) die('Invoice tidak ditemukan.');

$order = ambilOrder((int) $inv['order_id']);
if (!$order) die('Pesanan untuk invoice ini tidak ditemukan.');

$st = $db->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY no, id');
$st->bind_param('i', $id);
$st->execute();
$items = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$st = $db->prepare('SELECT COALESCE(SUM(CASE WHEN tipe = "dp" THEN nominal ELSE 0 END),0) dp,
                           COALESCE(SUM(nominal),0) total FROM payments WHERE invoice_id = ?');
$st->bind_param('i', $id);
$st->execute();
$p = $st->get_result()->fetch_assoc();
$dpSum  = (int) $p['dp'];
$dibayar = (int) $p['total'];
$sisa   = max(0, (int) $inv['total'] - $dibayar);

$custSnap  = json_decode((string) $inv['customer_snapshot'], true) ?: [];
$snap      = json_decode((string) $inv['order_snapshot'], true) ?: [];
$batal     = $inv['status'] === 'batal';

function tglSingkat(?string $tgl): string
{
    if (!$tgl) return '-';
    $t = strtotime($tgl);
    return $t ? date('d', $t) . '-' . bulanSingkat((int) date('n', $t)) . '-' . date('y', $t) : '-';
}

$namaPT = getSetting('nama_pt', 'PT. Seribu Nusantara Rental');
$totalInv = (int) $inv['total'];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title><?= $mode === 'internal' ? 'Lembar Internal' : 'Invoice' ?> <?= e($inv['nomor_invoice']) ?></title>
<style>
    @page { size: A4 portrait; margin: 12mm 16mm 12mm 16mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Aptos Narrow', 'Segoe UI', Calibri, Arial, sans-serif;
        font-size: 11pt; color: #000; background: #e0e0e0;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
    .invoice-page {
        width: 210mm; min-height: 297mm; margin: 8mm auto;
        background: #fff; padding: 16mm 16mm; box-shadow: 0 0 15px rgba(0,0,0,.15); position: relative;
    }
    .no-print { max-width: 210mm; margin: 14px auto 0; display: flex; gap: 10px; flex-wrap: wrap; }
    .no-print button, .no-print a {
        font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; padding: 8px 16px;
        border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-weight: 600;
    }
    .no-print .cetak { background: #FFC000; color: #000; }
    .no-print .abu { background: #555; color: #fff; }

    @media print {
        body { background: #fff; }
        .invoice-page { margin: 0; padding: 0; box-shadow: none; width: 100%; min-height: auto; }
        .no-print { display: none !important; }
    }
    body.embed .no-print { display: none !important; }
    body.embed { background: #fff; }
    body.embed .invoice-page { width: 100%; min-height: 0; margin: 0; box-shadow: none; padding: 5mm 6mm; zoom: .55; }

    @media screen and (max-width: 768px) {
        body:not(.embed) { padding: 10px 8px; }
        body:not(.embed) .invoice-page {
            width: 100%;
            min-height: auto;
            margin: 8px auto;
            padding: 12px 10px;
            box-shadow: 0 1px 6px rgba(0,0,0,.1);
        }
        body:not(.embed) .no-print {
            max-width: 100%;
            margin: 6px auto;
            gap: 6px;
        }
        body:not(.embed) .no-print button, body:not(.embed) .no-print a {
            flex: 1 1 auto;
            text-align: center;
            padding: 7px 10px;
            font-size: 11.5px;
        }
        body:not(.embed) .header {
            flex-direction: column;
            gap: 8px;
        }
        body:not(.embed) .header-right {
            text-align: left;
        }
        body:not(.embed) .info-section {
            flex-direction: column;
        }
        body:not(.embed) .info-left {
            flex: none;
            width: 100%;
            border-bottom: none;
        }
        body:not(.embed) .main-table {
            display: block;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        body:not(.embed) .footer {
            flex-direction: column;
            gap: 16px;
        }
        body:not(.embed) .footer-left {
            flex: none;
            width: 100%;
        }
    }

    /* ===== HEADER ===== */
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
    .header-left { flex: 1; }
    .header-left img { max-height: 52px; max-width: 187px; }
    .header-right { text-align: right; }
    .header-right .invoice-title { font-size: 24pt; font-weight: 700; letter-spacing: 2px; }
    .header-right .company-name { font-size: 12pt; font-weight: 700; margin-top: 2px; }
    .header-right .company-info { font-size: 9pt; margin-top: 2px; line-height: 1.4; }

    /* ===== INFO ===== */
    .info-section { display: flex; margin-bottom: 10px; }
    .info-left, .info-right { border: 1px solid #000; padding: 8px 10px; }
    .info-left { flex: 0 0 46%; }
    .info-right { flex: 1; }
    .info-left .label { font-weight: 700; font-size: 11pt; margin-bottom: 4px; }
    .info-left .client-name { font-weight: 700; font-size: 14pt; line-height: 1.3; }
    .info-right table { width: 100%; }
    .info-right td { padding: 2px 0; font-size: 11pt; vertical-align: top; }
    .info-right td:first-child { font-weight: 700; width: 42%; }
    .info-right td.colon { width: 5%; text-align: center; font-weight: 700; }
    .info-right td:last-child { font-weight: 700; }

    /* ===== TABEL UTAMA ===== */
    .main-table { width: 100%; border-collapse: collapse; font-size: 10pt; }
    .main-table thead th {
        background: #FFC000 !important; font-weight: 700; font-size: 11pt;
        padding: 6px 4px; border: 1px solid #000; text-align: center; vertical-align: middle;
        -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
    }
    .main-table tbody td { border-left: 1px solid #000; border-right: 1px solid #000; padding: 5px 6px; vertical-align: top; }
    .main-table .col-no { text-align: center; width: 24px; }
    .main-table .col-ket { width: 100px; }
    .main-table .col-driver { width: 52px; }
    .main-table .col-tgl { width: 64px; }
    .main-table .col-rute { width: 96px; }
    .main-table .col-harga { text-align: right; width: 76px; }
    .main-table .col-modal { text-align: right; width: 70px; }
    .main-table .col-hari { text-align: center; width: 32px; }
    .main-table .col-total { text-align: right; width: 88px; }
    .main-table tbody tr:not(:first-child) td { border-top: 1px solid #000; }

    .main-table tfoot td { border: 1px solid #000; padding: 4px 6px; font-size: 10pt; font-weight: 700; }
    .main-table tfoot .label-cell { text-align: center; }
    .main-table tfoot .hari-cell { text-align: center; }
    .main-table tfoot .amount-cell { text-align: right; }

    /* ===== TERBILANG ===== */
    .terbilang { margin-top: 10px; font-size: 10pt; font-style: italic; font-weight: 700; }
    .terbilang .label { font-style: italic; font-weight: 700; }

    /* ===== FOOTER ===== */
    .footer { display: flex; justify-content: space-between; margin-top: 22px; }
    .footer-left { flex: 0 0 46%; border: 1px solid #000; padding: 8px 10px; font-size: 10.5pt; font-weight: 700; line-height: 1.5; }
    .footer-left .catatan-title { font-weight: 700; margin-bottom: 2px; }
    .footer-right { text-align: center; font-size: 11pt; padding-top: 4px; }
    .footer-right .hormat { font-weight: 700; margin-bottom: 4px; }
    .footer-right .company { font-weight: 700; }
    .footer-right .ttd-space { height: 58px; display: flex; align-items: center; justify-content: center; }
    .footer-right .ttd-space img { max-height: 58px; }
    .footer-right .nama { font-weight: 700; }

    .internal-box { border: 1px solid #000; padding: 8px 10px; margin-top: 10px; font-size: 10.5pt; }
    .internal-box table { width: 100%; }
    .internal-box td { padding: 2px 0; }
    .internal-box td.num { text-align: right; }
    .tanda-internal { text-align: center; font-size: 11pt; font-weight: 700; color: #c22424; letter-spacing: 1px; margin-bottom: 8px; }
    .watermark { position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 64pt; color: rgba(192,57,43,.15); font-weight: 700; transform: rotate(-18deg); letter-spacing: 8px; }
</style>
</head>
<body<?= $embed ? ' class="embed"' : '' ?>>

<div class="no-print">
    <button class="cetak" onclick="window.print()">Print / Simpan PDF</button>
    <a class="abu" href="<?= BASE_URL ?>/pages/pesanan_detail.php?id=<?= (int) $order['id'] ?>">Kembali ke Pesanan</a>
    <?php if ($mode === 'customer'): ?>
        <a class="abu" href="?id=<?= (int) $id ?>&mode=internal">Lihat Lembar Internal</a>
    <?php else: ?>
        <a class="abu" href="?id=<?= (int) $id ?>">Lihat Versi Customer</a>
    <?php endif; ?>
</div>

<div class="invoice-page">
    <?php if ($batal): ?><div class="watermark">BATAL</div><?php endif; ?>

    <div class="header">
        <div class="header-left">
            <img src="<?= BASE_URL ?>/<?= e(getSetting('logo')) ?>" alt="Logo" onerror="this.style.display='none'">
        </div>
        <div class="header-right">
            <div class="invoice-title">INVOICE</div>
            <div class="company-name"><?= e($namaPT) ?></div>
            <div class="company-info">
                <?= e(getSetting('website_pt')) ?> <?= e(getSetting('email_pt')) ?><br>
                <?= e(getSetting('email_pt2')) ?>
            </div>
        </div>
    </div>

    <?php if ($mode === 'internal'): ?>
        <div class="tanda-internal">LEMBAR ORDER INTERNAL — TIDAK UNTUK CUSTOMER</div>
    <?php endif; ?>

    <div class="info-section">
        <div class="info-left">
            <div class="label">DITAGIH KEPADA</div>
            <div class="client-name">
                <?= e($custSnap['nama'] ?? $order['nama_pesanan']) ?><br>
                <?= e($custSnap['pic'] ?? $order['nama_pic'] ?: '') ?>
            </div>
        </div>
        <div class="info-right">
            <table>
                <tr>
                    <td>No. Faktur</td><td class="colon">:</td>
                    <td><?= e($inv['nomor_invoice']) ?><?= (int) $inv['nomor_revisi_ke'] > 0 ? ' (revisi ke-' . (int) $inv['nomor_revisi_ke'] . ')' : '' ?></td>
                </tr>
                <tr>
                    <td>Tanggal</td><td class="colon">:</td>
                    <td><?= tglSingkat($inv['tanggal_invoice']) ?></td>
                </tr>
                <tr>
                    <td>Jatuh Tempo</td><td class="colon">:</td>
                    <td><?= tglSingkat($inv['jatuh_tempo']) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <table class="main-table">
        <thead>
            <tr>
                <th class="col-no">No.</th>
                <th class="col-ket">Keterangan</th>
                <th class="col-driver">Driver</th>
                <th class="col-tgl">Tanggal<br>Pemakaian</th>
                <th class="col-rute">Rute Perjalanan /<br>Keterangan</th>
                <th class="col-harga">Harga/Hari<br>(Rp.)</th>
                <?php if ($mode === 'internal'): ?><th class="col-modal">Modal/Hari<br>(Rp.)</th><?php endif; ?>
                <th class="col-hari">Total<br>Hari</th>
                <th class="col-total">Total Harga</th>
            </tr>
        </thead>
        <tbody>
            <?php $maxRow = max(count($items), 3); $idx = 0; ?>
            <?php for ($i = 0; $i < $maxRow; $i++): ?>
                <?php $it = $items[$i] ?? null; ?>
                <?php if ($it): ?>
                <tr>
                    <td class="col-no"><?= (int) $it['no'] ?></td>
                    <td class="col-ket"><?= nl2br(e($it['keterangan'])) ?></td>
                    <td class="col-driver"><?= nl2br(e($it['driver'])) ?></td>
                    <td class="col-tgl"><?= e($it['tanggal_pakai']) ?></td>
                    <td class="col-rute"><?= nl2br(e($it['rute'])) ?></td>
                    <td class="col-harga">Rp <?= rupiah($it['harga_hari'], false) ?></td>
                    <?php if ($mode === 'internal'): ?>
                        <td class="col-modal">
                            <?php
                            $modal = 0;
                            if (isset($order['items'][$idx])) $modal = (int) $order['items'][$idx]['harga_modal_per_hari'];
                            echo rupiah($modal, false);
                            $idx++;
                            ?>
                        </td>
                    <?php endif; ?>
                    <td class="col-hari"><?= (int) $it['total_hari'] ?></td>
                    <td class="col-total">Rp <?= rupiah($it['total_harga'], false) ?></td>
                </tr>
                <?php else: ?>
                <tr>
                    <td class="col-no">&nbsp;</td>
                    <td class="col-ket">&nbsp;</td>
                    <td class="col-driver">&nbsp;</td>
                    <td class="col-tgl">&nbsp;</td>
                    <td class="col-rute">&nbsp;</td>
                    <td class="col-harga">&nbsp;</td>
                    <?php if ($mode === 'internal'): ?><td class="col-modal">&nbsp;</td><?php endif; ?>
                    <td class="col-hari">&nbsp;</td>
                    <td class="col-total">&nbsp;</td>
                </tr>
                <?php endif; ?>
            <?php endfor; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="<?= $mode === 'internal' ? 7 : 6 ?>" class="label-cell">Total</td>
                <td class="hari-cell">-</td>
                <td class="amount-cell">Rp <?= rupiah($totalInv, false) ?></td>
            </tr>
            <tr>
                <td colspan="<?= $mode === 'internal' ? 7 : 6 ?>" class="label-cell">Down Payment</td>
                <td class="hari-cell">-</td>
                <td class="amount-cell"><?= $dpSum > 0 ? 'Rp ' . rupiah($dpSum, false) : '-' ?></td>
            </tr>
            <tr>
                <td colspan="<?= $mode === 'internal' ? 7 : 6 ?>" class="label-cell">Total Yang Harus Di Bayar</td>
                <td class="hari-cell">-</td>
                <td class="amount-cell">Rp <?= rupiah($sisa, false) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="terbilang"><span class="label">Terbilang :</span> <?= e(terbilang($sisa)) ?> Rupiah</div>

    <?php if ($mode === 'internal'): ?>
    <div class="internal-box">
        <table>
            <tr><td>Total modal unit</td><td class="num">Rp <?= rupiah($order['total_modal'], false) ?></td></tr>
            <tr><td>Biaya tambahan</td><td class="num">Rp <?= rupiah($order['total_tambahan'], false) ?></td></tr>
            <tr><td><b>Margin</b></td><td class="num"><b>Rp <?= rupiah($order['margin'], false) ?></b></td></tr>
            <tr><td>Support By / partner</td><td class="num"><?= e(partnerList($order) ?: '-') ?></td></tr>
            <tr><td>Handle By</td><td class="num"><?= e($order['handle_by'] ?: '-') ?></td></tr>
            <?php if ($order['catatan']): ?><tr><td>Catatan</td><td class="num"><?= e($order['catatan']) ?></td></tr><?php endif; ?>
        </table>
    </div>
    <?php endif; ?>

    <div class="footer">
        <div class="footer-left">
            <div class="catatan-title">CATATAN</div>
            <?= nl2br(e(getSetting('catatan_bank'))) ?>
        </div>
        <div class="footer-right">
            <div class="hormat">Hormat Saya</div>
            <div class="company"><?= e($namaPT) ?></div>
            <div class="ttd-space">
                <img src="<?= BASE_URL ?>/<?= e(getSetting('ttd')) ?>" alt="Tanda Tangan" onerror="this.parentElement.innerHTML='<br><br><br>'">
            </div>
            <div class="nama"><?= e(getSetting('penandatangan', 'Yuswanto SH')) ?></div>
        </div>
    </div>
</div>
</body>
</html>
