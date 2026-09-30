/* Pertahankan posisi scroll saat halaman memuat ulang karena filter / pindah
   halaman / paginasi (navigasi GET pada halaman yang sama), supaya tidak
   lompat balik ke atas. Aksi form POST (simpan/invoice/bayar) TIDAK dipertahankan
   — itu memang lebih baik dibuka dari atas supaya pesan konfirmasi terlihat. */
(function () {
    'use strict';

    if ('scrollRestoration' in history) {
        try { history.scrollRestoration = 'manual'; } catch (e) {}
    }

    var key = 'rn_scroll:' + location.pathname;

    function simpan() {
        try {
            sessionStorage.setItem(key, String(window.scrollY || window.pageYOffset || 0));
        } catch (e) {}
    }

    function pulihkan() {
        try {
            var v = sessionStorage.getItem(key);
            if (v !== null) {
                window.scrollTo(0, parseInt(v, 10) || 0);
                sessionStorage.removeItem(key);
            }
        } catch (e) {}
        /* tampilkan kembali halaman (disembunyikan sementara di <head> supaya
           tidak flash ke atas sebelum posisi scroll dipulihkan) */
        try { document.documentElement.classList.remove('rn-restore'); } catch (e2) {}
    }

    function halamanSama(href) {
        if (!href || href.charAt(0) === '#') return false;
        var a = document.createElement('a');
        a.href = href;
        return a.pathname === location.pathname;
    }

    /* form filter (GET) -> simpan posisi sebelum halaman dimuat ulang */
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f && f.tagName === 'FORM' && String(f.method || 'get').toLowerCase() === 'get') {
            simpan();
        }
    }, true);

    /* tautan pada halaman yang sama (filter, paginasi, ganti periode) -> simpan */
    document.addEventListener('click', function (e) {
        var t = e.target;
        while (t && t !== document && t.tagName !== 'A') t = t.parentNode;
        if (t && t.tagName === 'A' && halamanSama(t.getAttribute('href'))) {
            simpan();
        }
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', pulihkan);
    } else {
        pulihkan();
    }

    /* pengaman: pastikan halaman selalu tampil walau pemulihan gagal */
    setTimeout(function () {
        try { document.documentElement.classList.remove('rn-restore'); } catch (e) {}
    }, 1200);
})();
