        </div><!-- /page -->
    </main>
</div><!-- /app -->

<script src="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<!-- Modal konfirmasi generik: dipakai semua form data-konfirmasi, teks pesan + label tombol diambil otomatis -->
<div class="modal fade" id="rnKonfirmasi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rn-modal">
            <div class="modal-body">
                <div class="rn-modal-judul" id="rnKonfirmasiJudul">Konfirmasi</div>
                <p class="rn-modal-pesan" id="rnKonfirmasiPesan"></p>
            </div>
            <div class="modal-footer rn-modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm" id="rnKonfirmasiYa">Ya, lanjutkan</button>
            </div>
        </div>
    </div>
</div>
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
</body>
</html>
