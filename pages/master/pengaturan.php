<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $res = $db->query('SELECT `key` FROM settings');
    while ($r = $res->fetch_assoc()) {
        $k = $r['key'];
        if (array_key_exists($k, $_POST)) {
            simpanSetting($k, trim((string) $_POST[$k]));
        }
    }
    setFlash('success', 'Pengaturan tersimpan. Kop invoice akan memakai nilai terbaru.');
    redirect(BASE_URL . '/pages/master/pengaturan.php');
}

$grup = [];
$res = $db->query('SELECT * FROM settings ORDER BY grup, urutan, `key`');
while ($r = $res->fetch_assoc()) {
    $grup[$r['grup']][] = $r;
}
$judulGrup = ['kop' => 'Kop Invoice & Identitas', 'bayar' => 'Pembayaran & DP', 'nomor' => 'Format Nomor Dokumen'];

$judulHalaman = 'Pengaturan';
$menuAktif = '';
include __DIR__ . '/../../includes/header.php';
?>
<form method="post">
    <?= csrfField() ?>
    <?php foreach ($grup as $nama => $rows): ?>
        <div class="card-box">
            <h2 class="card-title"><?= e($judulGrup[$nama] ?? ucfirst($nama)) ?></h2>
            <div class="form-grid">
                <?php foreach ($rows as $s): ?>
                    <div class="<?= in_array($s['key'], ['footer_invoice', 'tagline', 'catatan_bank'], true) ? 'col-12' : 'col-md-6' ?>">
                        <label class="form-label" for="s_<?= e($s['key']) ?>"><?= e($s['label'] ?: $s['key']) ?></label>
                        <?php if (in_array($s['key'], ['footer_invoice', 'tagline', 'catatan_bank'], true)): ?>
                            <textarea class="form-control" id="s_<?= e($s['key']) ?>" name="<?= e($s['key']) ?>" rows="3"><?= e($s['value']) ?></textarea>
                        <?php else: ?>
                            <input type="text" class="form-control" id="s_<?= e($s['key']) ?>" name="<?= e($s['key']) ?>" value="<?= e($s['value']) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="card-box">
        <button type="submit" class="btn btn-primary btn-sm">Simpan Pengaturan</button>
    </div>
</form>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
