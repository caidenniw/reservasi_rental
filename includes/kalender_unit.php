<?php
/**
 * includes/kalender_unit.php — papan ketersediaan unit (kisi unit x tanggal).
 * HANYA MEMBACA data. Status yang dianggap menempati unit: semua kecuali
 * cancelled, closed, dan draft — sama dengan aturan cekBentrokUnit() di functions.php.
 *
 * Pengaturan lewat URL: ?hari=7|14|30  &unit=<cari>  &semua=1
 */
$db = getDB();
$hariIni = date('Y-m-d');

$pilihanHari = [7 => '7 hari', 14 => '14 hari', 30 => '30 hari'];
$jumlahHari = (int) ($_GET['hari'] ?? 14);
if (!isset($pilihanHari[$jumlahHari])) $jumlahHari = 14;

$cari        = trim((string) ($_GET['unit'] ?? ''));
$tampilSemua = (string) ($_GET['semua'] ?? '') === '1';

$tanggal = [];
for ($i = 0; $i < $jumlahHari; $i++) {
    $tanggal[] = date('Y-m-d', strtotime("+$i days", strtotime($hariIni)));
}
$awal  = $tanggal[0];
$akhir = $tanggal[count($tanggal) - 1];

/* pemakaian unit pada rentang ini */
$st = $db->prepare("SELECT o.id, o.nomor_order, o.nama_pesanan, o.status, o.tgl_mulai, o.tgl_finish,
                           i.unit_id, i.nopol, i.nama_unit, i.nama_driver
                    FROM order_items i JOIN orders o ON o.id = i.order_id
                    WHERE o.deleted_at IS NULL AND o.status NOT IN ('cancelled','closed','draft')
                      AND i.unit_id IS NOT NULL
                      AND NOT (o.tgl_finish < ? OR o.tgl_mulai > ?)
                    ORDER BY o.tgl_mulai, o.id");
$st->bind_param('ss', $awal, $akhir);
$st->execute();
$pakai = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$jadwal = [];   /* unit_id => 'Y-m-d' => daftar pesanan */
foreach ($pakai as $p) {
    $uid = (int) $p['unit_id'];
    $t1 = max((string) $p['tgl_mulai'], $awal);
    $t2 = min((string) $p['tgl_finish'], $akhir);
    for ($t = strtotime($t1); $t <= strtotime($t2); $t += 86400) {
        $jadwal[$uid][date('Y-m-d', $t)][] = $p;
    }
}

/* daftar unit yang ditampilkan */
$batasBaris = 80;
$unitTampil = [];
if ($cari !== '') {
    $like = '%' . $cari . '%';
    $st = $db->prepare("SELECT id, nama_unit, nopol FROM units
                        WHERE deleted_at IS NULL
                          AND (nopol LIKE ? OR REPLACE(nopol, ' ', '') LIKE ? OR nama_unit LIKE ?)
                        ORDER BY nama_unit LIMIT 40");
    $st->bind_param('sss', $like, $like, $like);
    $st->execute();
    $unitTampil = $st->get_result()->fetch_all(MYSQLI_ASSOC);
} elseif ($tampilSemua) {
    $unitTampil = $db->query("SELECT id, nama_unit, nopol FROM units WHERE deleted_at IS NULL
                              ORDER BY nama_unit LIMIT $batasBaris")->fetch_all(MYSQLI_ASSOC);
} else {
    $idTerpakai = array_map('intval', array_keys($jadwal));
    if ($idTerpakai) {
        $unitTampil = $db->query("SELECT id, nama_unit, nopol FROM units
                                  WHERE deleted_at IS NULL AND id IN (" . implode(',', $idTerpakai) . ")
                                  ORDER BY nama_unit")->fetch_all(MYSQLI_ASSOC);
    }
}

/* ringkasan hari ini */
$unitHariIni = 0;
foreach ($jadwal as $uid => $perTanggal) {
    if (!empty($perTanggal[$hariIni])) $unitHariIni++;
}
$totalUnit = (int) $db->query("SELECT COUNT(*) c FROM units WHERE deleted_at IS NULL")->fetch_assoc()['c'];
?>
<div class="card-box">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="card-title mb-0">Ketersediaan unit (<?= (int) $jumlahHari ?> hari ke depan)</h2>
        <div class="periode-bar mb-0">
            <?php foreach ($pilihanHari as $kh => $lh): ?>
                <a class="periode-chip <?= $jumlahHari === $kh ? 'active' : '' ?>"
                   href="?<?= e(http_build_query(array_merge($_GET, ['hari' => $kh, 'semua' => $tampilSemua ? 1 : 0]))) ?>"><?= e($lh) ?></a>
            <?php endforeach; ?>
            <a class="periode-chip <?= $tampilSemua ? 'active' : '' ?>"
               href="?<?= e(http_build_query(array_merge($_GET, ['semua' => $tampilSemua ? 0 : 1]))) ?>">
                <?= $tampilSemua ? 'Hanya yang ada jadwal' : 'Tampilkan semua unit' ?>
            </a>
        </div>
    </div>

    <form class="periode-form mt-2" method="get">
        <input type="hidden" name="hari" value="<?= (int) $jumlahHari ?>">
        <label for="unit">Cari unit</label>
        <input type="text" id="unit" name="unit" class="form-control form-control-sm" style="max-width:220px"
               value="<?= e($cari) ?>" placeholder="nopol atau nama unit">
        <button class="btn btn-sm btn-outline-secondary" type="submit">Cari</button>
        <?php if ($cari !== ''): ?>
            <a class="btn btn-sm btn-outline-secondary"
               href="?<?= e(http_build_query(array_merge($_GET, ['unit' => '', 'semua' => 0]))) ?>">Reset</a>
        <?php endif; ?>
        <span class="form-text mb-0">
            <?= $unitHariIni ?> unit terisi hari ini dari <?= $totalUnit ?> unit terdaftar.
            <span class="kal-legenda"><i class="kal-tanda isi"></i> terisi</span>
            <span class="kal-legenda"><i class="kal-tanda"></i> bebas</span>
        </span>
    </form>

    <?php if (!$unitTampil): ?>
        <div class="table-kosong">
            <?= $cari !== ''
                ? 'Tidak ada unit yang cocok dengan pencarian "' . e($cari) . '".'
                : 'Belum ada unit yang punya jadwal pada ' . (int) $jumlahHari . ' hari ke depan. Klik "Tampilkan semua unit" untuk melihat seluruh armada.' ?>
        </div>
    <?php else: ?>
        <div class="kal-wrap">
            <table class="kal-tabel">
                <thead>
                <tr>
                    <th class="kal-unit">Unit / Nopol</th>
                    <?php foreach ($tanggal as $t):
                        $akhir_pekan = (int) date('N', strtotime($t)) >= 6; ?>
                        <th class="<?= $akhir_pekan ? 'kal-libur' : '' ?>" title="<?= e(tglAngka($t)) ?>">
                            <?= date('j', strtotime($t)) ?><span><?= e(['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'][(int) date('w', strtotime($t))]) ?></span>
                        </th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($unitTampil as $u):
                    $uid = (int) $u['id']; ?>
                    <tr>
                        <td class="kal-unit">
                            <a href="<?= BASE_URL ?>/pages/pesanan_list.php?cari=<?= urlencode((string) $u['nopol']) ?>">
                                <?= e($u['nopol']) ?>
                            </a>
                            <span class="kal-nama"><?= e($u['nama_unit']) ?></span>
                        </td>
                        <?php foreach ($tanggal as $t):
                            $isi = $jadwal[$uid][$t] ?? []; ?>
                            <?php if ($isi):
                                $p0 = $isi[0];
                                $teks = $p0['nomor_order'] . ' - ' . $p0['nama_pesanan'] . ' (' . statusLabel($p0['status']) . ')';
                                if (count($isi) > 1) $teks .= ' +' . (count($isi) - 1) . ' pesanan lain'; ?>
                                <td>
                                    <a class="kal-sel isi" href="<?= BASE_URL ?>/pages/pesanan_detail.php?id=<?= (int) $p0['id'] ?>"
                                       title="<?= e($teks) ?>">&nbsp;</a>
                                </td>
                            <?php else: ?>
                                <td><span class="kal-sel"></span></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-text mt-2">
            Klik kotak merah untuk membuka pesanan yang menempati unit pada tanggal tersebut.
            <?php if (!$tampilSemua && $cari === ''): ?>
                Yang ditampilkan hanya unit yang punya jadwal pada rentang ini.
            <?php elseif ($tampilSemua): ?>
                Ditampilkan maksimal <?= $batasBaris ?> unit pertama (urut nama).
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
