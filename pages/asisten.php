<?php
/**
 * pages/asisten.php — halaman penuh asisten data dashboard.
 * Endpoint: api/asisten.php (baca data saja).
 */
require_once __DIR__ . '/../includes/asisten_lib.php';
requireLogin();

$siap = asistenSiap();
$judulHalaman = 'Asisten Data';
$menuAktif = 'asisten';
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/asisten.css">
<?php if (!$siap): ?>
    <div class="alert alert-warning">
        Asisten belum aktif: kunci API Gemini belum diisi di <b>config/asisten.local.php</b>.
        Hubungi Caai / admin sistem untuk mengaktifkannya.
    </div>
<?php else: ?>
    <script>window.RN_ASISTEN = { base: "<?= BASE_URL ?>", token: "<?= asistenToken() ?>" };</script>

    <div class="rna-page">
        <div class="card-box">
            <h2 class="card-title">Tanya data dashboard</h2>
            <p class="text-soft mb-3">
                Ajukan pertanyaan dengan bahasa sehari-hari. Asisten menjawab dari data asli sistem
                (pesanan, invoice, unit, driver). Asisten <b>hanya membaca</b> &mdash; tidak bisa membuat,
                mengubah, atau menghapus data.
            </p>

            <div class="rna-chat" data-rna-root data-rna-saran="1">
                <div class="rna-body" data-rna-body></div>
                <form class="rna-form" data-rna-form>
                    <textarea class="rna-input" data-rna-input rows="2"
                              placeholder="Contoh: invoice mana yang belum lunas? / pesanan hari ini apa saja?"></textarea>
                    <button class="rna-send" data-rna-send type="submit">Kirim</button>
                </form>
            </div>

            <div class="rna-note mt-2">
                Pertanyaan yang kamu kirim diproses oleh layanan Google Gemini. Jangan menuliskan data
                pribadi yang tidak perlu. Jawaban selalu berdasarkan data sistem &mdash; kalau datanya tidak ada,
                asisten akan bilang tidak ada.
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/asisten.js?v=20260930a"></script>
<?php endif; ?>
<?php
$tanpaAsistenWidget = true; /* widget mengambang tidak perlu di halaman ini */
include __DIR__ . '/../includes/footer.php';
