<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();

/* ---------- template CSV ---------- */
$template = (string) ($_GET['template'] ?? '');
if ($template !== '') {
    $kolom = [
        'unit'     => ['nama_unit', 'nopol', 'kode_unit', 'jenis', 'tahun', 'transmisi', 'kapasitas', 'pemilik', 'harga_modal_default', 'harga_jual_default', 'status'],
        'driver'   => ['nama', 'hp', 'wilayah', 'nomor_sim', 'bank', 'no_rekening', 'status'],
        'customer' => ['nama_pesanan', 'tipe', 'nama_pic', 'hp_pic', 'email', 'alamat', 'sumber', 'status'],
        'pesanan'  => ['nama_pesanan', 'nama_pic', 'hp_pic', 'kota', 'wilayah_pelayanan', 'tgl_mulai', 'tgl_finish',
                       'jam', 'standby_point', 'flight', 'unit', 'nopol', 'driver', 'harga_modal_per_hari',
                       'harga_jual_per_hari', 'include', 'status', 'catatan'],
    ];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template_' . $template . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $kolom[$template] ?? ['kosong']);
    fclose($out);
    exit;
}

/** ubah "27/09/2026" atau "2026-09-27" jadi Y-m-d */
function tglCsv(string $s): ?string
{
    $s = trim($s);
    if ($s === '') return null;
    if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $s, $m)) return "$m[1]-$m[2]-$m[3]";
    if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2,4})$#', $s, $m)) {
        $hh = (int) $m[1]; $bb = (int) $m[2]; $tt = (int) $m[3];
        if ($tt < 100) $tt += 2000;
        if ($hh > 12) return sprintf('%04d-%02d-%02d', $tt, $bb, $hh);
        return sprintf('%04d-%02d-%02d', $tt, $bb, $hh);
    }
    $t = strtotime($s);
    return $t ? date('Y-m-d', $t) : null;
}

$hasil = [];
$ringkas = ['masuk' => 0, 'lewati' => 0, 'gagal' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $jenis = (string) ($_POST['jenis'] ?? '');
    if (empty($_FILES['csv']['name'])) {
        setFlash('danger', 'File CSV belum dipilih.');
        redirect(BASE_URL . '/pages/import.php');
    }
    $isi = file_get_contents($_FILES['csv']['tmp_name']);
    $isi = preg_replace('/^\xEF\xBB\xBF/', '', (string) $isi);
    $baris = preg_split("/\r\n|\n|\r/", (string) $isi);
    $head = str_getcsv((string) array_shift($baris), ';');
    if (count($head) < 2) {
        $head = str_getcsv((string) implode(';', $head), ',');
    }
    $head = array_map(fn($h) => strtolower(trim((string) $h)), $head);

    $db->begin_transaction();
    try {
        foreach ($baris as $no => $line) {
            if (trim((string) $line) === '') continue;
            $kolomBaris = str_getcsv((string) $line, ';');
            if (count($kolomBaris) < 2) $kolomBaris = str_getcsv((string) $line, ',');
            $row = [];
            foreach ($head as $i => $nama) $row[$nama] = trim((string) ($kolomBaris[$i] ?? ''));
            $barisKe = $no + 2;
            try {
                if ($jenis === 'unit') {
                    $nopol = strtoupper($row['nopol'] ?? '');
                    $namaUnit = $row['nama_unit'] ?? '';
                    if ($namaUnit === '' || $nopol === '') throw new Exception('nama_unit dan nopol wajib ada.');
                    $st = $db->prepare('SELECT id FROM units WHERE nopol = ?');
                    $st->bind_param('s', $nopol);
                    $st->execute();
                    if ($st->get_result()->fetch_assoc()) {
                        $hasil[] = [$barisKe, 'lewati', "$namaUnit ($nopol) sudah ada"];
                        $ringkas['lewati']++;
                        continue;
                    }
                    $jenisUnit = strtoupper($row['jenis'] ?? 'MPV');
                    $tahun = angka($row['tahun'] ?? 0);
                    $transmisi = strtolower($row['transmisi'] ?? '');
                    $kapasitas = angka($row['kapasitas'] ?? 0);
                    $pemilik = ($row['pemilik'] ?? 'sendiri') === 'partner' ? 'partner' : 'sendiri';
                    $modal = angka($row['harga_modal_default'] ?? 0);
                    $jual = angka($row['harga_jual_default'] ?? 0);
                    $status = $row['status'] ?: 'ready';
                    $kode = $row['kode_unit'] ?? '';
                    $pid = 0;
                    $partnerId = null;
                    /* type string dibangun otomatis - jangan ditulis manual,
                       satu karakter salah bikin ENUM terisi 0 (Data truncated) */
                    $fld = [
                        ['kode_unit', $kode, 's'], ['nama_unit', $namaUnit, 's'], ['nopol', $nopol, 's'],
                        ['jenis', $jenisUnit, 's'], ['tahun', $tahun, 'i'], ['transmisi', $transmisi, 's'],
                        ['kapasitas', $kapasitas, 'i'], ['pemilik', $pemilik, 's'], ['partner_id', $partnerId, 's'],
                        ['harga_modal_default', $modal, 'i'], ['harga_jual_default', $jual, 'i'], ['status', $status, 's'],
                    ];
                    $kol = []; $types = ''; $vals = [];
                    foreach ($fld as $fl) { $kol[] = '`' . $fl[0] . '`'; $types .= $fl[2]; $vals[] = $fl[1]; }
                    $ins = $db->prepare('INSERT INTO units (' . implode(', ', $kol) . ') VALUES (' . implode(', ', array_fill(0, count($kol), '?')) . ')');
                    $ins->bind_param($types, ...$vals);
                    $ins->execute();
                    $hasil[] = [$barisKe, 'ok', "$namaUnit ($nopol) ditambahkan"];
                    $ringkas['masuk']++;
                } elseif ($jenis === 'driver') {
                    $nama = $row['nama'] ?? '';
                    if ($nama === '') throw new Exception('nama wajib ada.');
                    $st = $db->prepare('SELECT id FROM drivers WHERE LOWER(nama) = LOWER(?)');
                    $st->bind_param('s', $nama);
                    $st->execute();
                    if ($st->get_result()->fetch_assoc()) {
                        $hasil[] = [$barisKe, 'lewati', "$nama sudah ada"];
                        $ringkas['lewati']++;
                        continue;
                    }
                    $hp = normalisasiHp($row['hp'] ?? '');
                    $wilayah = $row['wilayah'] ?? '';
                    $sim = $row['nomor_sim'] ?? '';
                    $bank = $row['bank'] ?? '';
                    $rek = $row['no_rekening'] ?? '';
                    $status = $row['status'] ?: 'aktif';
                    $ins = $db->prepare('INSERT INTO drivers (nama, hp, wilayah, nomor_sim, bank, no_rekening, status) VALUES (?,?,?,?,?,?,?)');
                    $ins->bind_param('sssssss', $nama, $hp, $wilayah, $sim, $bank, $rek, $status);
                    $ins->execute();
                    $hasil[] = [$barisKe, 'ok', "$nama ditambahkan"];
                    $ringkas['masuk']++;
                } elseif ($jenis === 'customer') {
                    $nama = $row['nama_pesanan'] ?? '';
                    if ($nama === '') throw new Exception('nama_pesanan wajib ada.');
                    $st = $db->prepare('SELECT id FROM customers WHERE LOWER(nama_pesanan) = LOWER(?) AND deleted_at IS NULL');
                    $st->bind_param('s', $nama);
                    $st->execute();
                    if ($st->get_result()->fetch_assoc()) {
                        $hasil[] = [$barisKe, 'lewati', "$nama sudah ada"];
                        $ringkas['lewati']++;
                        continue;
                    }
                    $tipe = $row['tipe'] ?: 'perorangan';
                    $pic = $row['nama_pic'] ?? '';
                    $hp = normalisasiHp($row['hp_pic'] ?? '');
                    $email = $row['email'] ?? '';
                    $alamat = $row['alamat'] ?? '';
                    $sumber = $row['sumber'] ?: 'wa';
                    $status = $row['status'] ?: 'baru';
                    $ins = $db->prepare('INSERT INTO customers (tipe, nama_pesanan, nama_pic, hp_pic, email, alamat, sumber, status) VALUES (?,?,?,?,?,?,?,?)');
                    $ins->bind_param('ssssssss', $tipe, $nama, $pic, $hp, $email, $alamat, $sumber, $status);
                    $ins->execute();
                    $hasil[] = [$barisKe, 'ok', "$nama ditambahkan"];
                    $ringkas['masuk']++;
                } elseif ($jenis === 'pesanan') {
                    $nama = $row['nama_pesanan'] ?? '';
                    $tglMulai = tglCsv($row['tgl_mulai'] ?? '');
                    $tglFinish = tglCsv($row['tgl_finish'] ?? '');
                    if ($nama === '' || !$tglMulai || !$tglFinish) throw new Exception('nama_pesanan, tgl_mulai, tgl_finish wajib ada.');
                    $hari = hitungHari($tglMulai, $tglFinish);
                    if ($hari < 1) throw new Exception('tanggal selesai lebih awal dari tanggal mulai.');

                    $nomor = trim($row['nomor_order'] ?? '');
                    if ($nomor !== '') {
                        $st = $db->prepare('SELECT id FROM orders WHERE nomor_order = ?');
                        $st->bind_param('s', $nomor);
                        $st->execute();
                        if ($st->get_result()->fetch_assoc()) {
                            $hasil[] = [$barisKe, 'lewati', "order $nomor sudah ada"];
                            $ringkas['lewati']++;
                            continue;
                        }
                    } else {
                        $nomor = nomorDokumen('order');
                    }

                    /* customer */
                    $st = $db->prepare('SELECT id FROM customers WHERE LOWER(nama_pesanan) = LOWER(?) AND deleted_at IS NULL LIMIT 1');
                    $st->bind_param('s', $nama);
                    $st->execute();
                    $cust = $st->get_result()->fetch_assoc();
                    if ($cust) {
                        $customerId = (int) $cust['id'];
                    } else {
                        $tipeCust = 'instansi'; $picC = $row['nama_pic'] ?? ''; $hpC = normalisasiHp($row['hp_pic'] ?? '');
                        $sumberC = 'lainnya'; $statusC = 'baru';
                        $ins = $db->prepare('INSERT INTO customers (tipe, nama_pesanan, nama_pic, hp_pic, sumber, status) VALUES (?,?,?,?,?,?)');
                        $ins->bind_param('ssssss', $tipeCust, $nama, $picC, $hpC, $sumberC, $statusC);
                        $ins->execute();
                        $customerId = (int) $db->insert_id;
                    }

                    /* unit + driver */
                    $nopol = strtoupper($row['nopol'] ?? '');
                    $namaUnit = $row['unit'] ?? '';
                    $unitId = null;
                    if ($nopol !== '') {
                        $st = $db->prepare('SELECT id, nama_unit FROM units WHERE nopol = ?');
                        $st->bind_param('s', $nopol);
                        $st->execute();
                        $u = $st->get_result()->fetch_assoc();
                        if ($u) {
                            $unitId = (int) $u['id'];
                            if ($namaUnit === '') $namaUnit = $u['nama_unit'];
                        } elseif ($namaUnit !== '') {
                            $ins = $db->prepare('INSERT INTO units (nama_unit, nopol, harga_modal_default, harga_jual_default) VALUES (?,?,?,?)');
                            $mn = angka($row['harga_modal_per_hari'] ?? 0); $jn = angka($row['harga_jual_per_hari'] ?? 0);
                            $ins->bind_param('ssii', $namaUnit, $nopol, $mn, $jn);
                            $ins->execute();
                            $unitId = (int) $db->insert_id;
                        }
                    }
                    if ($namaUnit === '' || $nopol === '') throw new Exception('unit dan nopol wajib ada.');

                    $namaDriver = $row['driver'] ?? '';
                    $driverId = null; $hpDriver = '';
                    if ($namaDriver !== '') {
                        $st = $db->prepare('SELECT id, hp FROM drivers WHERE LOWER(nama) = LOWER(?)');
                        $st->bind_param('s', $namaDriver);
                        $st->execute();
                        $d = $st->get_result()->fetch_assoc();
                        if ($d) { $driverId = (int) $d['id']; $hpDriver = normalisasiHp($d['hp']); }
                        else {
                            $ins = $db->prepare('INSERT INTO drivers (nama, status) VALUES (?, "aktif")');
                            $ins->bind_param('s', $namaDriver);
                            $ins->execute();
                            $driverId = (int) $db->insert_id;
                        }
                    }

                    $wilayah = ($row['wilayah_pelayanan'] ?? '') === 'luar_kota' ? 'luar_kota' : 'dalam_kota';
                    $kota = $row['kota'] ?? '';
                    $jam = $row['jam'] ?? '';
                    $jamKoord = 0;
                    if (stripos($jam, 'kordinas') !== false || $jam === '-') { $jamKoord = 1; $jam = ''; }
                    $standby = $row['standby_point'] ?? '';
                    $flight = $row['flight'] ?? '';
                    $pic = $row['nama_pic'] ?? '';
                    $hpPic = normalisasiHp($row['hp_pic'] ?? '');
                    $statusOrder = ($row['status'] ?: 'booked');
                    if (!in_array($statusOrder, daftarStatus(), true)) $statusOrder = 'booked';
                    $handleBy = namaUser();
                    $catatan = $row['catatan'] ?? '';
                    $partnerId = null;
                    $createdBy = idUser();
                    $tipePelanggan = 'retail';
                    $tujuan = '';

                    $fields = [
                        ['nomor_order', $nomor, 's'], ['customer_id', $customerId, 'i'],
                        ['tipe_pelanggan', $tipePelanggan, 's'], ['wilayah_pelayanan', $wilayah, 's'],
                        ['kota', $kota, 's'], ['tgl_mulai', $tglMulai, 's'], ['tgl_finish', $tglFinish, 's'],
                        ['jumlah_hari', $hari, 'i'], ['jam', $jam, 's'], ['jam_koordinasi', $jamKoord, 'i'],
                        ['standby_point', $standby, 's'], ['flight', $flight, 's'], ['tujuan', $tujuan, 's'],
                        ['nama_pesanan', $nama, 's'], ['nama_pic', $pic, 's'], ['hp_pic', $hpPic, 's'],
                        ['sumber', 'lainnya', 's'], ['handle_by', $handleBy, 's'], ['partner_id', $partnerId, 'i'],
                        ['status', $statusOrder, 's'], ['catatan', $catatan, 's'], ['created_by', $createdBy, 'i'],
                    ];
                    $kol = []; $types = ''; $vals = [];
                    foreach ($fields as $fl) { $kol[] = '`' . $fl[0] . '`'; $types .= $fl[2]; $vals[] = $fl[1]; }
                    $st = $db->prepare('INSERT INTO orders (' . implode(', ', $kol) . ') VALUES (' . implode(', ', array_fill(0, count($kol), '?')) . ')');
                    $st->bind_param($types, ...$vals);
                    $st->execute();
                    $orderId = (int) $db->insert_id;

                    $modalHari = angka($row['harga_modal_per_hari'] ?? 0);
                    $jualHari = angka($row['harga_jual_per_hari'] ?? 0);
                    $namaUnitSnapshot = $namaUnit; $nopolSnapshot = $nopol;
                    $namaDriverSnapshot = $namaDriver; $catatanItem = '';
                    $subModal = $modalHari * $hari; $subJual = $jualHari * $hari;
                    $ins = $db->prepare('INSERT INTO order_items (order_id, unit_id, driver_id, nama_unit, nopol, nama_driver, hp_driver,
                        harga_modal_per_hari, harga_jual_per_hari, jumlah_hari, subtotal_modal, subtotal_jual, catatan)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $ins->bind_param('iiissssiiiiis', $orderId, $unitId, $driverId, $namaUnitSnapshot, $nopolSnapshot,
                        $namaDriverSnapshot, $hpDriver, $modalHari, $jualHari, $hari, $subModal, $subJual, $catatanItem);
                    $ins->execute();

                    $incTeks = $row['include'] ?? '';
                    if ($incTeks !== '') {
                        $insInc = $db->prepare('INSERT INTO order_includes (order_id, include_id, nama, biaya) VALUES (?, NULL, ?, 0)');
                        foreach (preg_split('/[+,;]/', $incTeks) as $nm) {
                            $nm = trim((string) $nm);
                            if ($nm === '') continue;
                            $insInc->bind_param('is', $orderId, $nm);
                            $insInc->execute();
                        }
                    }

                    hitungOrder($orderId);
                    catatStatus($orderId, null, $statusOrder, 'Import CSV');
                    $hasil[] = [$barisKe, 'ok', "order $nomor dibuat ($nama)"];
                    $ringkas['masuk']++;
                } else {
                    throw new Exception('Jenis import tidak dikenal.');
                }
            } catch (Throwable $e) {
                $hasil[] = [$barisKe, 'gagal', $e->getMessage()];
                $ringkas['gagal']++;
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        setFlash('danger', 'Import dibatalkan: ' . $e->getMessage());
        redirect(BASE_URL . '/pages/import.php');
    }

    setFlash($ringkas['gagal'] > 0 ? 'warning' : 'success',
        'Import selesai: ' . $ringkas['masuk'] . ' masuk, ' . $ringkas['lewati'] . ' dilewati, ' . $ringkas['gagal'] . ' gagal.');
}

$judulHalaman = 'Import CSV';
$menuAktif = '';
include __DIR__ . '/../includes/header.php';
?>
<div class="card-box">
    <h2 class="card-title">Import dari CSV (hasil ekspor Google Sheet)</h2>
    <p class="text-soft">
        Simpan sheet sebagai <b>CSV</b> (File &rarr; Download &rarr; Comma Separated Values).
        Baris pertama harus nama kolom. Data yang sudah ada akan <b>dilewati</b>, tidak ditimpa dan tidak dihapus.
        Setelah import, semua input dilakukan dari dashboard (sheet hanya cadangan).
    </p>
    <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
        <?= csrfField() ?>
        <div class="col-md-3">
            <label class="form-label" for="jenis">Jenis data</label>
            <select class="form-select form-select-sm" id="jenis" name="jenis" required>
                <option value="unit">Unit / Mobil</option>
                <option value="driver">Driver</option>
                <option value="customer">Customer</option>
                <option value="pesanan">Pesanan + Invoice</option>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label" for="csv">File CSV</label>
            <input type="file" class="form-control form-control-sm" id="csv" name="csv" accept=".csv,text/csv" required>
        </div>
        <div class="col-md-4">
            <div class="baris-aksi">
                <button type="submit" class="btn btn-sm btn-primary">Import</button>
                <a class="btn btn-sm btn-outline-secondary" href="?template=unit">Template Unit</a>
                <a class="btn btn-sm btn-outline-secondary" href="?template=driver">Template Driver</a>
                <a class="btn btn-sm btn-outline-secondary" href="?template=pesanan">Template Pesanan</a>
            </div>
        </div>
    </form>
</div>

<?php if ($hasil): ?>
    <div class="card-box">
        <h2 class="card-title">Hasil import</h2>
        <div class="table-wrap">
            <table class="tabel">
                <thead><tr><th>Baris</th><th>Status</th><th>Keterangan</th></tr></thead>
                <tbody>
                <?php foreach ($hasil as [$b, $st, $ket]): ?>
                    <tr>
                        <td><?= (int) $b ?></td>
                        <td><span class="badge bg-<?= $st === 'ok' ? 'success' : ($st === 'lewati' ? 'secondary' : 'danger') ?>"><?= e($st) ?></span></td>
                        <td><?= e($ket) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="card-box">
    <h2 class="card-title">Nama kolom yang dikenali</h2>
    <dl class="dl-2">
        <dt>Unit</dt><dd class="mono">nama_unit, nopol, kode_unit, jenis, tahun, transmisi, kapasitas, pemilik, harga_modal_default, harga_jual_default, status</dd>
        <dt>Driver</dt><dd class="mono">nama, hp, wilayah, nomor_sim, bank, no_rekening, status</dd>
        <dt>Customer</dt><dd class="mono">nama_pesanan, tipe, nama_pic, hp_pic, email, alamat, sumber, status</dd>
        <dt>Pesanan</dt><dd class="mono">nama_pesanan, nama_pic, hp_pic, kota, wilayah_pelayanan, tgl_mulai, tgl_finish, jam, standby_point, flight, unit, nopol, driver, harga_modal_per_hari, harga_jual_per_hari, include, status, catatan</dd>
    </dl>
    <div class="form-text">
        Tanggal boleh format <span class="mono">2026-09-27</span> atau <span class="mono">27/09/2026</span>.
        Pemisah kolom koma atau titik koma, keduanya dibaca.
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
