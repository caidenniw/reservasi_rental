        </div><!-- /page -->
    </main>
</div><!-- /app -->

<script src="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
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
