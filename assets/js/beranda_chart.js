/* Grafik tren beranda (Chart.js lokal, tanpa animasi).
   Data dikirim dari server lewat <script type="application/json" id="dataGrafik">.
   Interaksi: ganti metrik (nilai/margin/jumlah), ganti tipe (batang/garis),
   tooltip rincian, klik batang/titik -> daftar pesanan bulan itu. */
(function () {
    'use strict';

    var elData = document.getElementById('dataGrafik');
    var kanvas = document.getElementById('kanvasGrafik');
    if (!elData || !kanvas) return;

    var data = [];
    try { data = JSON.parse(elData.textContent || '[]'); } catch (e) { data = []; }
    if (!data.length) return;

    var kotak = kanvas.parentNode;
    if (typeof Chart === 'undefined') {
        kotak.innerHTML = '<div class="graf-kosong">Grafik tidak dapat dimuat (berkas chart.js tidak ditemukan). ' +
            'Angka rinciannya tetap tersedia di tabel pesanan dan menu Data Pesanan &amp; Faktur.</div>';
        return;
    }

    var metrik = 'nilai';
    var tipe = 'bar';
    var grafik = null;
    var labelMetrik = { nilai: 'Nilai jual', margin: 'Margin', jumlah: 'Jumlah pesanan' };
    var warna = { garis: '#e62e2e', isiBatang: 'rgba(230, 46, 46, .85)', isiGaris: 'rgba(230, 46, 46, .12)' };

    function rupiah(n) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(n) || 0); }

    function ringkas(n) {
        n = Number(n) || 0;
        if (n >= 1e9) return (n / 1e9).toFixed(1).replace('.', ',') + ' M';
        if (n >= 1e6) return Math.round(n / 1e6) + ' jt';
        if (n >= 1e3) return Math.round(n / 1e3) + ' rb';
        return String(n);
    }

    function nilaiTerpilih() {
        return data.map(function (d) { return Number(d[metrik]) || 0; });
    }

    function gambar() {
        if (grafik) grafik.destroy();
        var ctx = kanvas.getContext('2d');
        if (!ctx) return;

        grafik = new Chart(ctx, {
            type: tipe,
            data: {
                labels: data.map(function (d) { return d.bulan; }),
                datasets: [{
                    label: labelMetrik[metrik],
                    data: nilaiTerpilih(),
                    backgroundColor: tipe === 'bar' ? warna.isiBatang : warna.isiGaris,
                    borderColor: warna.garis,
                    borderWidth: tipe === 'bar' ? 0 : 2,
                    borderRadius: 6,
                    fill: tipe === 'line',
                    tension: 0,
                    pointRadius: 4,
                    pointBackgroundColor: warna.garis
                }]
            },
            options: {
                animation: false,
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function (items) {
                                var d = data[items[0].dataIndex];
                                return d.bulan + ' (' + d.kode + ')';
                            },
                            label: function (item) {
                                var d = data[item.dataIndex];
                                var baris = [];
                                baris.push('Nilai jual: ' + rupiah(d.nilai));
                                baris.push('Margin: ' + rupiah(d.margin));
                                baris.push('Jumlah pesanan: ' + d.jumlah);
                                baris.push('Klik untuk buka daftar pesanan bulan ini');
                                return baris;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (v) { return metrik === 'jumlah' ? v + ' psn' : ringkas(v); },
                            color: '#6b7280',
                            font: { size: 11 }
                        },
                        grid: { color: '#f1f3f5' },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#6b7280', font: { size: 11 } },
                        border: { display: false }
                    }
                },
                onClick: function (evt, items) {
                    if (!items || !items.length) return;
                    var d = data[items[0].index];
                    var url = kanvas.getAttribute('data-url');
                    if (d && d.kode && url) window.location.href = url + '?bulan=' + encodeURIComponent(d.kode);
                }
            }
        });
    }

    var tombolMetrik = document.querySelectorAll('[data-metrik]');
    var tombolTipe = document.querySelectorAll('[data-tipe]');

    function tandai(tombol, aktif) {
        for (var i = 0; i < tombol.length; i++) tombol[i].classList.toggle('active', tombol[i] === aktif);
    }

    for (var i = 0; i < tombolMetrik.length; i++) {
        tombolMetrik[i].addEventListener('click', function () {
            metrik = this.getAttribute('data-metrik') || 'nilai';
            tandai(tombolMetrik, this);
            gambar();
        });
    }
    for (var j = 0; j < tombolTipe.length; j++) {
        tombolTipe[j].addEventListener('click', function () {
            tipe = this.getAttribute('data-tipe') || 'bar';
            tandai(tombolTipe, this);
            gambar();
        });
    }

    gambar();
})();
