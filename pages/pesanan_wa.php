<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$order = ambilOrder($id);
if (!$order) {
    setFlash('danger', 'Pesanan tidak ditemukan.');
    redirect(BASE_URL . '/pages/pesanan_list.php');
}

$teks = teksWaOrder($order);
$waPic = preg_replace('/[^0-9]/', '', (string) $order['hp_pic']);

$judulHalaman = 'Teks Konfirmasi WA - ' . $order['nomor_order'];
$menuAktif = 'data';
include __DIR__ . '/../includes/header.php';
?>
<div class="card-box">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="card-title mb-0">Teks siap kirim ke customer</h2>
        <div class="baris-aksi">
            <button type="button" class="btn btn-sm btn-primary" id="btnSalin" onclick="rnSalin('teksWa','btnSalin')">Salin Teks</button>
            <?php if ($waPic): ?>
                <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                   href="https://wa.me/<?= e($waPic) ?>?text=<?= e(rawurlencode($teks)) ?>">Buka WhatsApp PIC</a>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/pages/pesanan_detail.php?id=<?= (int) $order['id'] ?>">Kembali</a>
        </div>
    </div>
    <p class="text-soft mt-2 mb-2">
        Format mengikuti template lama (Pelayanan, Driver, Unit, Nopol, Standby, Flight, Jam, Pesanan, PIC, Include).
        Baris modal & margin tidak ikut disalin.
    </p>
    <textarea id="teksWa" class="form-control mono" rows="26" spellcheck="false"><?= e($teks) ?></textarea>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
