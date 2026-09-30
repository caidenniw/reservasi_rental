/* Asisten internal dashboard reservasi.
   Dipakai dua tempat: widget mengambang (semua halaman) dan halaman penuh.
   Murni membaca data lewat api/asisten.php. */
(function () {
    'use strict';

    var cfg = window.RN_ASISTEN || {};
    if (!cfg.base) { cfg.base = ''; }

    function buat(tag, kelas, teks) {
        var n = document.createElement(tag);
        if (kelas) n.className = kelas;
        if (teks !== undefined) n.textContent = teks;
        return n;
    }

    function bersihkan(teks) {
        /* buang penanda markdown supaya tampil rapi sebagai teks biasa */
        return String(teks || '')
            .replace(/\*\*(.+?)\*\*/g, '$1')
            .replace(/^[ \t]*[*\-]\s+/gm, '- ')
            .replace(/`+/g, '')
            .trim();
    }

    function kirim(teks, riwayat) {
        var fd = new FormData();
        fd.append('token', cfg.token || '');
        fd.append('tanya', teks);
        fd.append('riwayat', JSON.stringify(riwayat || []));
        return fetch(cfg.base + '/api/asisten.php', {
            method: 'POST', body: fd, credentials: 'same-origin'
        }).then(function (r) {
            return r.json().catch(function () {
                return { ok: false, error: 'Jawaban tidak terbaca (HTTP ' + r.status + ').' };
            });
        });
    }

    function pasang(root) {
        if (root.dataset.rnaSiap === '1') return;
        root.dataset.rnaSiap = '1';

        var body = root.querySelector('[data-rna-body]');
        var form = root.querySelector('[data-rna-form]');
        var input = root.querySelector('[data-rna-input]');
        var tombol = root.querySelector('[data-rna-send]');
        var riwayat = [];
        var sibuk = false;

        function tambah(kelas, teks, meta) {
            var wrap = buat('div', 'rna-msg ' + kelas);
            wrap.appendChild(buat('div', null, bersihkan(teks)));
            if (meta) wrap.appendChild(buat('div', 'rna-meta', meta));
            body.appendChild(wrap);
            body.scrollTop = body.scrollHeight;
            return wrap;
        }

        function saran() {
            var daftar = [
                'Pesanan apa saja yang berjalan hari ini?',
                'Invoice mana yang belum lunas?',
                'Rekap nilai pesanan 6 bulan terakhir',
                'Unit BK 1261 OOO kosong tanggal 5-7 November 2026?'
            ];
            var box = buat('div', null, '');
            daftar.forEach(function (t) {
                var c = buat('button', 'rna-chip', t);
                c.type = 'button';
                c.addEventListener('click', function () { input.value = t; form.dispatchEvent(new Event('submit')); });
                box.appendChild(c);
            });
            box.appendChild(buat('div', 'rna-note', 'Asisten hanya membaca data. Pertanyaan dikirim ke layanan Google Gemini untuk dijawab.'));
            body.appendChild(box);
        }

        function tanyaKe(teks) {
            if (sibuk) return;
            sibuk = true;
            if (tombol) tombol.disabled = true;
            tambah('rna-msg-user', teks);
            var tunggu = tambah('rna-msg-info', 'Menghitung data...');
            riwayat.push({ role: 'user', text: teks });

            kirim(teks, riwayat.slice(0, -1)).then(function (res) {
                tunggu.remove();
                if (res && res.ok) {
                    tambah('rna-msg-bot', res.jawaban,
                        (res.sumber ? 'Sumber data: ' + res.sumber + ' · ' : '') + 'model ' + (res.model || '-') + ' · ' + (res.detik || '?') + 's');
                    riwayat.push({ role: 'asisten', text: res.jawaban });
                } else {
                    tambah('rna-msg-err', (res && res.error) || 'Gagal menghubungi asisten.');
                }
            }).catch(function () {
                tunggu.remove();
                tambah('rna-msg-err', 'Gagal menghubungi asisten. Periksa koneksi internet.');
            }).then(function () {
                sibuk = false;
                if (tombol) tombol.disabled = false;
                input.focus();
            });
        }

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var teks = String(input.value || '').trim();
            if (teks === '') return;
            input.value = '';
            tanyaKe(teks);
        });

        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && !ev.shiftKey) {
                ev.preventDefault();
                form.dispatchEvent(new Event('submit'));
            }
        });

        if (String(root.dataset.rnaSaran || '') === '1') saran();

        var buka = root.dataset.rnaBuka;
        if (buka) {
            var panel = document.getElementById(buka);
            var pemicu = root.dataset.rnaPemicu ? document.getElementById(root.dataset.rnaPemicu) : null;
            if (pemicu && panel) {
                pemicu.addEventListener('click', function () {
                    panel.classList.toggle('rna-open');
                    if (panel.classList.contains('rna-open')) { input.focus(); }
                });
                var x = panel.querySelector('.rna-x');
                if (x) x.addEventListener('click', function () { panel.classList.remove('rna-open'); });
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var daftar = document.querySelectorAll('[data-rna-root]');
        for (var i = 0; i < daftar.length; i++) pasang(daftar[i]);
    });
})();
