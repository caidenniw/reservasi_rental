<?php
/**
 * includes/asisten_widget.php — widget asisten mengambang.
 * Dipanggil dari includes/footer.php supaya tersedia di semua halaman dashboard.
 * Kalau halaman ingin menyembunyikannya (misal halaman Asisten sendiri),
 * set $tanpaAsistenWidget = true sebelum memuat footer.
 */
require_once __DIR__ . '/asisten_lib.php';

if (empty($tanpaAsistenWidget)) : ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/asisten.css?v=20260930b">
    <?php if (asistenSiap()): ?>
        <script>window.RN_ASISTEN = { base: "<?= BASE_URL ?>", token: "<?= asistenToken() ?>" };</script>

        <button type="button" class="rna-btn" id="rnaTombol" aria-label="Buka asisten data">
            <span>Asisten</span>
        </button>

        <div class="rna-panel" id="rnaPanel" role="dialog" aria-label="Asisten data dashboard"
             data-rna-root data-rna-buka="rnaPanel" data-rna-pemicu="rnaTombol" data-rna-saran="1">
            <div class="rna-head">
                <div>
                    <div class="rna-head-t">Asisten Dashboard</div>
                    <div class="rna-head-s">Baca data pesanan, invoice, unit &amp; driver. Tidak bisa mengubah data.</div>
                </div>
                <button type="button" class="rna-x" aria-label="Tutup">&times;</button>
            </div>
            <div class="rna-body" data-rna-body></div>
            <form class="rna-form" data-rna-form>
                <textarea class="rna-input" data-rna-input rows="1" placeholder="Tulis pertanyaan, mis: invoice mana yang belum lunas?"></textarea>
                <button class="rna-send" data-rna-send type="submit">Kirim</button>
            </form>
        </div>

        <script src="<?= BASE_URL ?>/assets/js/asisten.js?v=20260930b"></script>
    <?php endif; ?>
<?php endif; ?>
