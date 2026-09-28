/* 1000 Nusantara Rental - helper UI kecil (tanpa framework JS) */

/* ---------- Form pesanan: hitung ringkasan otomatis ---------- */
function rnFormatRupiah(n) {
    n = Math.round(Number(n) || 0);
    return 'Rp ' + n.toLocaleString('id-ID');
}
function rnAngka(str) {
    return parseInt(String(str || '').replace(/[^0-9]/g, ''), 10) || 0;
}

function rnSiapkanFormPesanan() {
    var form = document.getElementById('formPesanan');
    if (!form) return;

    /* jumlah hari dari tanggal */
    var mulai = form.querySelector('[name="tgl_mulai"]');
    var finish = form.querySelector('[name="tgl_finish"]');
    var hari = form.querySelector('[name="jumlah_hari"]');

    function hitungHari() {
        if (!mulai.value || !finish.value) return;
        var a = new Date(mulai.value), b = new Date(finish.value);
        var d = Math.floor((b - a) / 86400000) + 1;
        if (d > 0) {
            hari.value = d;
            form.querySelectorAll('[name="item_jumlah_hari[]"]').forEach(function (el) { el.value = d; });
        }
    }
    mulai.addEventListener('change', hitungHari);
    finish.addEventListener('change', hitungHari);

    /* ringkasan biaya */
    function ringkas() {
        var totalJual = 0, totalModal = 0;
        form.querySelectorAll('.item-unit').forEach(function (kotak) {
            var jual = rnAngka(kotak.querySelector('[name="item_harga_jual[]"]').value);
            var modal = rnAngka(kotak.querySelector('[name="item_harga_modal[]"]').value);
            var h = parseInt(kotak.querySelector('[name="item_jumlah_hari[]"]').value, 10) || 0;
            kotak.querySelector('.sub-jual').textContent = rnFormatRupiah(jual * h);
            kotak.querySelector('.sub-modal').textContent = rnFormatRupiah(modal * h);
            totalJual += jual * h;
            totalModal += modal * h;
        });

        var tambahan = 0;
        form.querySelectorAll('[name="biaya_nominal[]"]').forEach(function (el) {
            tambahan += rnAngka(el.value);
        });
        form.querySelectorAll('[name^="include_biaya"]').forEach(function (el) {
            tambahan += rnAngka(el.value);
        });

        var grand = totalJual + tambahan;
        var panjarEl = document.getElementById('panjar');
        var panjar = panjarEl ? rnAngka(panjarEl.value) : 0;
        var sisa = Math.max(0, grand - panjar);
        document.getElementById('rkJual').textContent = rnFormatRupiah(totalJual);
        document.getElementById('rkModal').textContent = rnFormatRupiah(totalModal);
        document.getElementById('rkTambahan').textContent = rnFormatRupiah(tambahan);
        document.getElementById('rkTotal').textContent = rnFormatRupiah(grand);
        var rkPanjar = document.getElementById('rkPanjar');
        if (rkPanjar) rkPanjar.textContent = rnFormatRupiah(panjar);
        var rkSisa = document.getElementById('rkSisa');
        if (rkSisa) rkSisa.textContent = rnFormatRupiah(sisa);
        var rkSisa2 = document.getElementById('rkSisa2');
        if (rkSisa2) rkSisa2.textContent = rnFormatRupiah(sisa);
        document.getElementById('rkMargin').textContent = rnFormatRupiah(grand - totalModal);
    }

    form.addEventListener('input', ringkas);
    form.addEventListener('change', ringkas);

    /* tambah / hapus unit */
    var wadah = document.getElementById('wadahUnit');
    var tpl = document.getElementById('tplUnit');
    form.querySelector('#btnTambahUnit').addEventListener('click', function () {
        var node = tpl.content.cloneNode(true);
        wadah.appendChild(node);
        perbaruiNomorUnit();
        ringkas();
    });
    wadah.addEventListener('click', function (ev) {
        if (ev.target.classList.contains('btn-hapus-unit')) {
            var kotak = ev.target.closest('.item-unit');
            if (wadah.querySelectorAll('.item-unit').length <= 1) {
                alert('Minimal satu unit.');
                return;
            }
            kotak.remove();
            perbaruiNomorUnit();
            ringkas();
        }
        if (ev.target.classList.contains('btn-hapus-biaya')) {
            ev.target.closest('.baris-biaya').remove();
            ringkas();
        }
    });
    function perbaruiNomorUnit() {
        var items = wadah.querySelectorAll('.item-unit');
        items.forEach(function (k, i) {
            var label = k.querySelector('.unit-no');
            if (label) label.textContent = 'Armada / Mobil ' + (i + 1);
            var btnHapus = k.querySelector('.btn-hapus-unit');
            if (btnHapus) {
                btnHapus.style.display = (items.length > 1) ? 'inline-block' : 'none';
            }
        });
    }

    /* tambah baris biaya tambahan */
    var wadahBiaya = document.getElementById('wadahBiaya');
    var tplBiaya = document.getElementById('tplBiaya');
    var btnBiaya = document.getElementById('btnTambahBiaya');
    if (btnBiaya) {
        btnBiaya.addEventListener('click', function () {
            wadahBiaya.appendChild(tplBiaya.content.cloneNode(true));
            ringkas();
        });
    }

    /* pilih unit -> isi nama unit + nopol + harga default (hanya kalau masih kosong = opsi A) */
    form.addEventListener('change', function (ev) {
        if (ev.target.classList.contains('pilih-unit')) {
            var opt = ev.target.options[ev.target.selectedIndex];
            var kotak = ev.target.closest('.item-unit');
            var nmUnit = kotak.querySelector('[name="item_nama_unit[]"]');
            if (opt.dataset.nama && nmUnit.value.trim() === '') nmUnit.value = opt.dataset.nama;
            kotak.querySelector('[name="item_nopol[]"]').value = opt.dataset.nopol || '';
            var hm = kotak.querySelector('[name="item_harga_modal[]"]');
            var hj = kotak.querySelector('[name="item_harga_jual[]"]');
            if (opt.dataset.modal && rnAngka(hm.value) === 0) hm.value = opt.dataset.modal;
            if (opt.dataset.jual && rnAngka(hj.value) === 0) hj.value = opt.dataset.jual;
            ringkas();
        }
    });

    /* pilih driver -> isi nama + HP otomatis */
    form.addEventListener('change', function (ev) {
        if (ev.target.classList.contains('pilih-driver')) {
            var opt = ev.target.options[ev.target.selectedIndex];
            var kotak = ev.target.closest('.item-unit');
            var nm = kotak.querySelector('[name="item_nama_driver[]"]');
            var hp = kotak.querySelector('[name="item_hp_driver[]"]');
            if (opt.dataset.nama && nm.value.trim() === '') nm.value = opt.dataset.nama;
            if (opt.dataset.hp && hp.value.trim() === '') hp.value = opt.dataset.hp;
        }
    });

    perbaruiNomorUnit();
    ringkas();
}

/* ---------- salin teks WA ---------- */
function rnSalin(idTeks, idTombol) {
    var el = document.getElementById(idTeks);
    var btn = document.getElementById(idTombol);
    if (!el) return;
    var teks = el.value || el.textContent;
    function sukses() {
        var asli = btn.textContent;
        btn.textContent = 'Tersalin';
        setTimeout(function () { btn.textContent = asli; }, 1800);
    }
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(teks).then(sukses, function () { el.select(); document.execCommand('copy'); sukses(); });
    } else {
        el.select();
        document.execCommand('copy');
        sukses();
    }
}

/* ---------- konfirmasi aksi ---------- */
document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (f.dataset && f.dataset.konfirmasi) {
        if (!confirm(f.dataset.konfirmasi)) ev.preventDefault();
    }
});

document.addEventListener('DOMContentLoaded', rnSiapkanFormPesanan);
