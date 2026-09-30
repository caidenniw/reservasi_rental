<?php
/* Partial: tabel + paginasi Data Pesanan.
   Dipakai oleh pesanan_list.php untuk render penuh, dan sebagai respons htmx
   (filter / cari / pindah halaman / urut) TANPA memuat ulang halaman.
   Mengandalkan variabel dari pemanggil: $rows, $pg, $total, dan $_GET. */
?>
<div class="card-box">
    <h2 class="card-title"><?= $total ?> pesanan ditemukan</h2>
    <div class="table-wrap">
        <table class="tabel">
            <thead>
            <tr>
                <th>No. Order</th><th>Tanggal</th><th>Pesanan</th><th>Unit</th><th>Status</th>
                <th>Invoice</th><th class="num">Total</th><th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8"><div class="table-kosong">Belum ada pesanan yang cocok. Klik "+ Input Pesanan" untuk mulai.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="mono"><?= e($r['nomor_order']) ?><div class="muted"><?= e($r['kota']) ?></div></td>
                    <td><?= e(tglId($r['tgl_mulai'])) ?><div class="muted"><?= (int) $r['jumlah_hari'] ?> hari</div></td>
                    <td><?= e(potong($r['nama_pesanan'], 40)) ?>
                        <?php if ($r['nama_pic']): ?><div class="muted"><?= e($r['nama_pic']) ?></div><?php endif; ?></td>
                    <td><?= e(potong((string) $r['unit_list'], 28)) ?><div class="muted mono"><?= e((string) $r['nopol_list']) ?></div></td>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td>
                        <?php if ($r['nomor_invoice']): ?>
                            <span class="mono"><?= e($r['nomor_invoice']) ?></span>
                            <div><?= statusBadge((string) $r['inv_status']) ?></div>
                        <?php else: ?>
                            <span class="muted"><?= $r['status'] === 'paid' ? 'tidak ada — data historis' : 'belum terbit' ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= rupiah($r['grand_total'], false) ?></td>
                    <td><a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/pages/pesanan_detail.php?id=<?= (int) $r['id'] ?>">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pg['jumlah_halaman'] > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= $pg['jumlah_halaman']; $i++): ?>
                    <?php $q = http_build_query(array_merge($_GET, ['hal' => $i])); ?>
                    <li class="page-item <?= $i === $pg['halaman'] ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= e($q) ?>"
                           hx-get="?<?= e($q) ?>" hx-target="#daftar" hx-push-url="true"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>
