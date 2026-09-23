<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();

$id = (int) ($_GET['id'] ?? 0);
$order = ambilOrder($id);
if (!$order) {
    setFlash('danger', 'Pesanan tidak ditemukan.');
    redirect(BASE_URL . '/pages/pesanan_list.php');
}

/* invoice aktif = invoice terbaru yang tidak batal */
$invAktif = null;
foreach ($order['invoices'] as $iv) {
    if ($iv['status'] !== 'batal') { $invAktif = $iv; break; }
}
$pembayaran = [];
$dibayar = 0;
if ($invAktif) {
    $st = $db->prepare('SELECT * FROM payments WHERE invoice_id = ? ORDER BY tanggal_bayar, id');
    $st->bind_param('i', $invAktif['id']);
    $st->execute();
    $pembayaran = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($pembayaran as $p) $dibayar += (int) $p['nominal'];
}
$sisa = $invAktif ? max(0, (int) $invAktif['total'] - $dibayar) : 0;

$judulHalaman = 'Detail Pesanan ' . $order['nomor_order'];
$menuAktif = 'data';
include __DIR__ . '/../includes/header.php';
?>
<?php if ($invAktif && (int) $order['grand_total'] !== (int) $invAktif['total']): ?>
    <div class="alert alert-warning">
        <b>Perhatian:</b> data pesanan (<?= rupiah($order['grand_total']) ?>) berbeda dari invoice yang sudah
        terbit (<?= rupiah($invAktif['total']) ?>). Kalau perubahan ini memang harus masuk ke dokumen,
        klik <b>Revisi Invoice</b> di bawah supaya invoice diperbarui dengan nomor baru.
    </div>
<?php endif; ?>
<div class="card-box">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="mono" style="font-size:16px;font-weight:600"><?= e($order['nomor_order']) ?></span>
                <?= statusBadge($order['status']) ?>
            </div>
            <div class="text-soft">
                <?= e($order['nama_pesanan']) ?> &middot;
                <?= e(tglId($order['tgl_mulai'])) ?> s/d <?= e(tglId($order['tgl_finish'])) ?> (<?= (int) $order['jumlah_hari'] ?> hari) &middot;
                <?= e(labelWilayah($order['wilayah_pelayanan'])) ?> <?= e($order['kota']) ?>
            </div>
        </div>
        <div class="baris-aksi">
            <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/pages/pesanan_form.php?id=<?= (int) $order['id'] ?>">Ubah Data</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/pages/pesanan_wa.php?id=<?= (int) $order['id'] ?>">Salin Teks WA</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/pages/pesanan_list.php">Kembali ke Daftar</a>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-box">
            <h2 class="card-title">Data Pelayanan</h2>
            <dl class="dl-2">
                <dt>Tipe Pelanggan</dt><dd><?= e(labelTipePelanggan($order['tipe_pelanggan'])) ?></dd>
                <dt>Standby Point</dt><dd><?= e($order['standby_point'] ?: '-') ?></dd>
                <dt>Flight</dt><dd><?= e($order['flight'] ?: '-') ?></dd>
                <dt>Jam</dt><dd><?= $order['jam_koordinasi'] ? 'Koordinasi dengan user' : e($order['jam'] ?: '-') ?></dd>
                <dt>Tujuan / Rute</dt><dd><?= e($order['tujuan'] ?: '-') ?></dd>
                <dt>Support By</dt><dd><?= e(partnerList($order) ?: '-') ?></dd>
                <dt>Include</dt><dd><?= e(implode(' + ', array_map(fn($x) => $x['nama'], $order['includes'])) ?: '-') ?></dd>
                <dt>Handle By</dt><dd><?= e($order['handle_by'] ?: '-') ?></dd>
                <dt>Sumber Order</dt><dd><?= e($order['sumber'] ? labelSumber($order['sumber']) : '-') ?></dd>
                <dt>Catatan</dt><dd><?= $order['catatan'] ? nl2br(e($order['catatan'])) : '-' ?></dd>
            </dl>
        </div>

        <div class="card-box">
            <h2 class="card-title">Customer & PIC</h2>
            <dl class="dl-2">
                <dt>Nama Pesanan</dt><dd><?= e($order['nama_pesanan']) ?></dd>
                <dt>PIC</dt><dd><?= e($order['nama_pic'] ?: '-') ?></dd>
                <dt>HP / WA PIC</dt>
                <dd>
                    <?php if ($order['hp_pic']): ?>
                        <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $order['hp_pic'])) ?>" target="_blank" rel="noopener"><?= e($order['hp_pic']) ?></a>
                    <?php else: ?>-<?php endif; ?>
                </dd>
                <dt>Alamat</dt><dd><?= e($order['customer_alamat'] ?: '-') ?></dd>
            </dl>
        </div>

        <div class="card-box">
            <h2 class="card-title">Unit & Driver</h2>
            <div class="table-wrap">
                <table class="tabel">
                    <thead><tr><th>Unit</th><th>Nopol</th><th>Driver</th><th class="num">Hari</th><th class="num">Modal/hari</th><th class="num">Jual/hari</th><th class="num">Subtotal jual</th></tr></thead>
                    <tbody>
                    <?php foreach ($order['items'] as $it): ?>
                        <tr>
                            <td><?= e($it['nama_unit']) ?></td>
                            <td class="mono"><?= e($it['nopol']) ?></td>
                            <td><?= e($it['nama_driver'] ?: '-') ?><?php if ($it['hp_driver']): ?><div class="muted"><?= e($it['hp_driver']) ?></div><?php endif; ?></td>
                            <td class="num"><?= (int) $it['jumlah_hari'] ?></td>
                            <td class="num"><?= rupiah($it['harga_modal_per_hari'], false) ?></td>
                            <td class="num"><?= rupiah($it['harga_jual_per_hari'], false) ?></td>
                            <td class="num"><?= rupiah($it['subtotal_jual'], false) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($order['biaya']): ?>
                <div class="mt-3">
                    <div class="form-label">Biaya Tambahan</div>
                    <?php foreach ($order['biaya'] as $b): ?>
                        <div class="d-flex justify-content-between" style="max-width:320px">
                            <span><?= e($b['nama']) ?></span><span><?= rupiah($b['nominal']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-box">
            <h2 class="card-title">Ringkasan Biaya</h2>
            <div class="ringkas">
                <div class="ringkas-row"><span>Subtotal modal (internal)</span><span><?= rupiah($order['total_modal']) ?></span></div>
                <div class="ringkas-row"><span>Subtotal jual</span><span><?= rupiah($order['total_jual']) ?></span></div>
                <div class="ringkas-row"><span>Biaya tambahan</span><span><?= rupiah($order['total_tambahan']) ?></span></div>
                <div class="ringkas-row total"><span>Total Tagihan</span><span><?= rupiah($order['grand_total']) ?></span></div>
                <div class="ringkas-row margin"><span>Margin (internal)</span><span><?= rupiah($order['margin']) ?></span></div>
            </div>
        </div>

        <div class="card-box">
            <h2 class="card-title">Invoice</h2>
            <?php if (!$invAktif): ?>
                <p class="text-soft">Invoice belum diterbitkan. Setelah diterbitkan, nomor invoice terkunci.</p>
                <form method="post" action="<?= BASE_URL ?>/pages/pesanan_aksi.php" data-konfirmasi="Terbitkan invoice untuk pesanan ini?">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                    <input type="hidden" name="aksi" value="terbit">
                    <button class="btn btn-sm btn-primary" type="submit">Terbitkan Invoice</button>
                </form>
            <?php else: ?>
                <dl class="dl-2">
                    <dt>Nomor</dt><dd class="mono"><?= e($invAktif['nomor_invoice']) ?><?= (int) $invAktif['nomor_revisi_ke'] > 0 ? ' (revisi ke-' . (int) $invAktif['nomor_revisi_ke'] . ')' : '' ?></dd>
                    <dt>Tanggal</dt><dd><?= e(tglId($invAktif['tanggal_invoice'])) ?></dd>
                    <dt>Jatuh Tempo</dt><dd><?= e(tglId($invAktif['jatuh_tempo'])) ?></dd>
                    <dt>Status</dt><dd><?= statusBadge($invAktif['status']) ?></dd>
                    <dt>Total</dt><dd><?= rupiah($invAktif['total']) ?></dd>
                    <dt>Dibayar</dt><dd><?= rupiah($dibayar) ?></dd>
                    <dt>Sisa</dt><dd><b><?= rupiah($sisa) ?></b></dd>
                </dl>
                <div class="baris-aksi mt-2">
                    <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/pages/invoice_cetak.php?id=<?= (int) $invAktif['id'] ?>" target="_blank">Cetak Invoice</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/pages/invoice_cetak.php?id=<?= (int) $invAktif['id'] ?>&mode=internal" target="_blank">Cetak Lembar Internal</a>
                    <form method="post" action="<?= BASE_URL ?>/pages/pesanan_aksi.php" data-konfirmasi="Revisi invoice? Nomor lama akan ditandai batal dan nomor baru diterbitkan.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                        <input type="hidden" name="aksi" value="revisi">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">Revisi Invoice</button>
                    </form>
                </div>
                <iframe class="mt-3" title="Pratinjau invoice"
                        src="<?= BASE_URL ?>/pages/invoice_cetak.php?id=<?= (int) $invAktif['id'] ?>&embed=1"
                        style="width:100%;height:430px;border:1px solid var(--line);border-radius:8px;background:#fff"></iframe>

                <hr>
                <div class="form-label">Catat Pembayaran</div>
                <form method="post" action="<?= BASE_URL ?>/pages/pesanan_aksi.php" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                    <input type="hidden" name="aksi" value="bayar">
                    <div class="row g-2">
                        <div class="col-6"><input type="date" class="form-control form-control-sm" name="tanggal_bayar" value="<?= date('Y-m-d') ?>" required></div>
                        <div class="col-6">
                            <select class="form-select form-select-sm" name="tipe">
                                <option value="dp">DP</option>
                                <option value="pelunasan">Pelunasan</option>
                                <option value="lain">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-6"><input type="text" class="form-control form-control-sm" name="nominal" placeholder="nominal" value="<?= $sisa > 0 ? number_format($sisa, 0, ',', '.') : '' ?>" required></div>
                        <div class="col-6">
                            <select class="form-select form-select-sm" name="metode">
                                <option value="transfer">Transfer</option>
                                <option value="cash">Cash</option>
                                <option value="qris">QRIS</option>
                                <option value="lain">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-6"><input type="text" class="form-control form-control-sm" name="bank" value="<?= e(getSetting('bank_nama')) ?>" placeholder="bank"></div>
                        <div class="col-6"><input type="file" class="form-control form-control-sm" name="bukti" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
                        <div class="col-12"><input type="text" class="form-control form-control-sm" name="catatan_bayar" placeholder="catatan (opsional)"></div>
                    </div>
                    <button class="btn btn-sm btn-primary mt-2" type="submit">Simpan Pembayaran</button>
                </form>

                <?php if ($pembayaran): ?>
                    <div class="table-wrap mt-3">
                        <table class="tabel">
                            <thead><tr><th>Tgl</th><th>Tipe</th><th class="num">Nominal</th><th>Bukti</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($pembayaran as $p): ?>
                                <tr>
                                    <td><?= e(tglAngka($p['tanggal_bayar'])) ?></td>
                                    <td><?= e(ucfirst($p['tipe'])) ?><div class="muted"><?= e($p['metode']) ?></div></td>
                                    <td class="num"><?= rupiah($p['nominal'], false) ?></td>
                                    <td>
                                        <?php if ($p['bukti_path']): ?>
                                            <a href="<?= BASE_URL ?>/pages/bukti.php?id=<?= (int) $p['id'] ?>" target="_blank" rel="noopener">lihat</a>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" action="<?= BASE_URL ?>/pages/pesanan_aksi.php" data-konfirmasi="Hapus catatan pembayaran ini?">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                                            <input type="hidden" name="aksi" value="hapus_bayar">
                                            <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">x</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="card-box">
            <h2 class="card-title">Ubah Status Pesanan</h2>
            <form method="post" action="<?= BASE_URL ?>/pages/pesanan_aksi.php">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                <input type="hidden" name="aksi" value="status">
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" name="status_baru">
                        <?php foreach (daftarStatus() as $st): ?>
                            <option value="<?= $st ?>" <?= $order['status'] === $st ? 'selected' : '' ?>><?= e(statusLabel($st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-primary" type="submit">Simpan</button>
                </div>
                <input type="text" class="form-control form-control-sm mt-2" name="catatan_status" placeholder="catatan perubahan (opsional)">
            </form>
            <hr>
            <div class="baris-aksi">
                <form method="post" action="<?= BASE_URL ?>/pages/pesanan_aksi.php" data-konfirmasi="Batalkan pesanan ini?">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                    <input type="hidden" name="aksi" value="batal">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Batalkan Pesanan</button>
                </form>
                <form method="post" action="<?= BASE_URL ?>/pages/pesanan_aksi.php" data-konfirmasi="Hapus pesanan dari daftar?">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                    <input type="hidden" name="aksi" value="hapus">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                </form>
            </div>
        </div>

        <div class="card-box">
            <h2 class="card-title">Riwayat Status</h2>
            <div class="table-wrap">
                <table class="tabel">
                    <thead><tr><th>Waktu</th><th>Dari</th><th>Ke</th><th>Oleh</th></tr></thead>
                    <tbody>
                    <?php if (!$order['logs']): ?>
                        <tr><td colspan="4"><div class="table-kosong">Belum ada riwayat.</div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($order['logs'] as $lg): ?>
                        <tr>
                            <td><?= e(date('d/m H:i', strtotime($lg['created_at']))) ?></td>
                            <td><?= e($lg['status_lama'] ? statusLabel($lg['status_lama']) : '-') ?></td>
                            <td><?= e(statusLabel($lg['status_baru'])) ?><?php if ($lg['catatan']): ?><div class="muted"><?= e($lg['catatan']) ?></div><?php endif; ?></td>
                            <td><?= e($lg['oleh']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
