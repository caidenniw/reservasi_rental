<?php
/**
 * includes/master_tampilan.php
 * Tampilan standar halaman master: form di atas, tabel di bawah.
 * Dipanggil setelah mcrudHandle() (redirect sudah terjadi sebelum output).
 */

function masterRender(array $cfg, array $errors, array $rows, string $cari, ?array $editRow, array $nilaiForm, string $placeholder = 'cari data'): void
{
    $url = BASE_URL . $cfg['url'];
    $petaKolom = [];
    foreach ($cfg['kolom'] as $c) $petaKolom[$c['name']] = $c;

    $kolomList = $cfg['kolom_list'] ?? [];
    if (!$kolomList) {
        foreach ($cfg['kolom'] as $c) {
            if ($c['tipe'] !== 'textarea') $kolomList[] = $c['name'];
        }
    }
    $statusMap = $cfg['status_map'] ?? [];
    ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="card-box">
        <h2 class="card-title"><?= $editRow ? 'Ubah ' . e($cfg['judul']) : 'Tambah ' . e($cfg['judul']) ?></h2>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" value="<?= (int) ($editRow['id'] ?? 0) ?>">
            <div class="form-grid">
                <?php foreach ($cfg['kolom'] as $c): ?>
                    <?= mcrudField($c, $nilaiForm[$c['name']] ?? '') ?>
                <?php endforeach; ?>
            </div>
            <div class="baris-aksi mt-3">
                <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                <?php if ($editRow): ?>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= $url ?>">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card-box">
        <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
            <h2 class="card-title mb-0">Daftar <?= e($cfg['judul']) ?> (<?= count($rows) ?>)</h2>
            <form class="d-flex gap-2" method="get">
                <input type="text" class="form-control form-control-sm" name="cari" value="<?= e($cari) ?>" placeholder="<?= e($placeholder) ?>">
                <button class="btn btn-sm btn-outline-secondary" type="submit">Cari</button>
                <?php if ($cari !== ''): ?><a class="btn btn-sm btn-link" href="<?= $url ?>">Reset</a><?php endif; ?>
            </form>
        </div>
        <div class="table-wrap">
            <table class="tabel">
                <thead>
                <tr>
                    <?php foreach ($kolomList as $nama): ?>
                        <th><?= e($petaKolom[$nama]['label'] ?? $nama) ?></th>
                    <?php endforeach; ?>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="<?= count($kolomList) + 1 ?>"><div class="table-kosong">Belum ada data. Tambahkan lewat form di atas.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <?php foreach ($kolomList as $nama): ?>
                            <?php $c = $petaKolom[$nama] ?? null; ?>
                            <td>
                                <?php if ($nama === 'status' && $statusMap): ?>
                                    <?= e($statusMap[$r['status']] ?? $r['status']) ?>
                                <?php elseif ($c): ?>
                                    <?= mcrudTampilNilai($c, $r[$nama] ?? '', $r) ?>
                                <?php else: ?>
                                    <?= e((string) ($r[$nama] ?? '')) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <div class="d-flex gap-1">
                                <a class="btn btn-sm btn-outline-secondary" href="<?= $url ?>?id=<?= (int) $r['id'] ?><?= $cari !== '' ? '&cari=' . urlencode($cari) : '' ?>">Ubah</a>
                                <form method="post" data-konfirmasi="Nonaktifkan data ini?">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
