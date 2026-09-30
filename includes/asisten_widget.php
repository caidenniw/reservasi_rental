<?php
/**
 * includes/asisten_widget.php — widget asisten mengambang.
 * Dipanggil dari includes/footer.php supaya tersedia di semua halaman dashboard.
 * Kalau halaman ingin menyembunyikannya (misal halaman Asisten sendiri),
 * set $tanpaAsistenWidget = true sebelum memuat footer.
 */
require_once __DIR__ . '/asisten_lib.php';

if (empty($tanpaAsistenWidget)) : ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/asisten.css?v=20260930c">
    <?php if (asistenSiap()): ?>
        <script>window.RN_ASISTEN = { base: "<?= BASE_URL ?>", token: "<?= asistenToken() ?>" };</script>

        <button type="button" class="rna-btn" id="rnaTombol" aria-label="Buka asisten data" title="Asisten data">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                <line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="13" y2="13"/>
            </svg>
            <span>Asisten</span>
        </button>

        <div class="rna-panel" id="rnaPanel" role="dialog" aria-label="Asisten data dashboard"
             data-rna-root data-rna-buka="rnaPanel" data-rna-pemicu="rnaTombol" data-rna-saran="1">
            <div class="rna-head">
                <div class="rna-head-info">
                    <span class="rna-head-ikon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            <line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="13" y2="13"/>
                        </svg>
                    </span>
                    <div>
                        <div class="rna-head-t">Asisten Dashboard</div>
                        <div class="rna-head-s">Baca data pesanan, invoice, unit &amp; driver. Tidak bisa mengubah data.</div>
                    </div>
                </div>
                <button type="button" class="rna-x" aria-label="Tutup">&times;</button>
            </div>
            <div class="rna-body" data-rna-body></div>
            <form class="rna-form" data-rna-form>
                <textarea class="rna-input" data-rna-input rows="1" placeholder="Tulis pertanyaan, mis: invoice mana yang belum lunas?"></textarea>
                <button class="rna-send" data-rna-send type="submit">Kirim</button>
            </form>
        </div>

        <script src="<?= BASE_URL ?>/assets/js/asisten.js?v=20260930d"></script>
    <?php endif; ?>
<?php endif; ?>
