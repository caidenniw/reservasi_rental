        </div><!-- /page -->
    </main>
</div><!-- /app -->

<script src="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<!-- Modal konfirmasi generik: dipakai semua form data-konfirmasi, teks pesan + label tombol diambil otomatis -->
<div class="modal fade" id="rnKonfirmasi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rn-modal">
            <div class="modal-body rn-modal-body">
                <div class="rn-modal-ikon" id="rnKonfirmasiIkon" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div class="rn-modal-teks">
                    <div class="rn-modal-judul" id="rnKonfirmasiJudul">Konfirmasi</div>
                    <p class="rn-modal-pesan" id="rnKonfirmasiPesan"></p>
                    <p class="rn-modal-konteks" id="rnKonfirmasiKonteks"></p>
                </div>
            </div>
            <div class="modal-footer rn-modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn" id="rnKonfirmasiYa">Ya, lanjutkan</button>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=20260928e"></script>
<script>
/* Mobile Drawer Handlers */
function rnBukaSidebarMobile() {
    var sb = document.getElementById('sidebarApp');
    var bd = document.getElementById('sidebarBackdrop');
    if (sb) sb.classList.add('mobile-open');
    if (bd) bd.classList.add('mobile-open');
    document.body.style.overflow = 'hidden';
}

function rnTutupSidebarMobile() {
    var sb = document.getElementById('sidebarApp');
    var bd = document.getElementById('sidebarBackdrop');
    if (sb) sb.classList.remove('mobile-open');
    if (bd) bd.classList.remove('mobile-open');
    document.body.style.overflow = '';
}

/* Close drawer on Escape key */
document.addEventListener('keydown', function(ev) {
    if (ev.key === 'Escape') rnTutupSidebarMobile();
});
</script>
<script src="<?= BASE_URL ?>/assets/vendor/htmx/htmx.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/scroll-keep.js?v=20260930b"></script>
<?php require __DIR__ . '/asisten_widget.php'; ?>
</body>
</html>
