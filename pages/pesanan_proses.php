<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/pesanan_list.php');
}
verifyCsrfToken();
$db = getDB();

$id   = (int) ($_POST['id'] ?? 0);
$aksi = (string) ($_POST['aksi'] ?? 'simpan');

/* ---------------- ambil input ---------------- */
$nama_pesanan = trim((string) ($_POST['nama_pesanan'] ?? ''));
$kota         = trim((string) ($_POST['kota'] ?? ''));
$tgl_mulai    = trim((string) ($_POST['tgl_mulai'] ?? ''));
$tgl_finish   = trim((string) ($_POST['tgl_finish'] ?? ''));
$jumlah_hari  = hitungHari($tgl_mulai, $tgl_finish);
$status       = (string) ($_POST['status'] ?? 'booked');
if ($aksi === 'draft') {
    $status = 'draft';
}

$errors = [];
if ($nama_pesanan === '') $errors[] = 'Nama pesanan/instansi wajib diisi.';
if ($kota === '')         $errors[] = 'Kota/lokasi wajib diisi.';
if (!$tgl_mulai || !$tgl_finish) $errors[] = 'Tanggal mulai dan tanggal selesai wajib diisi.';
if ($tgl_mulai && $tgl_finish && $jumlah_hari < 1) $errors[] = 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.';

/* ---------------- item unit ---------------- */
$arrUnitId  = $_POST['item_unit_id'] ?? [];
$arrNopol   = $_POST['item_nopol'] ?? [];
$arrNama    = $_POST['item_nama_unit'] ?? [];
$arrDriverId = $_POST['item_driver_id'] ?? [];
$arrNamaDrv = $_POST['item_nama_driver'] ?? [];
$arrHpDrv   = $_POST['item_hp_driver'] ?? [];
$arrModal   = $_POST['item_harga_modal'] ?? [];
$arrJual    = $_POST['item_harga_jual'] ?? [];
$arrHari    = $_POST['item_jumlah_hari'] ?? [];
$arrCatatan   = $_POST['item_catatan'] ?? [];
$arrPartnerId = $_POST['item_partner_id'] ?? [];

$items = [];
foreach ($arrNopol as $i => $nopolRaw) {
    $unitId = (int) ($arrUnitId[$i] ?? 0);
    $nopol  = strtoupper(trim((string) $nopolRaw));
    $nama   = trim((string) ($arrNama[$i] ?? ''));
    $drvId  = (int) ($arrDriverId[$i] ?? 0);
    $namaDrv = trim((string) ($arrNamaDrv[$i] ?? ''));
    $hpDrv   = trim((string) ($arrHpDrv[$i] ?? ''));

    if ($unitId > 0) {
        $st = $db->prepare('SELECT nama_unit, nopol FROM units WHERE id = ?');
        $st->bind_param('i', $unitId);
        $st->execute();
        $u = $st->get_result()->fetch_assoc();
        if ($u) {
            if ($nama === '')  $nama  = $u['nama_unit'];
            if ($nopol === '') $nopol = strtoupper($u['nopol']);
        }
    }
    if ($drvId > 0) {
        $st = $db->prepare('SELECT nama, hp FROM drivers WHERE id = ?');
        $st->bind_param('i', $drvId);
        $st->execute();
        $d = $st->get_result()->fetch_assoc();
        if ($d) {
            if ($namaDrv === '') $namaDrv = $d['nama'];
            if ($hpDrv === '')   $hpDrv   = normalisasiHp($d['hp']);
        }
    }
    if ($hpDrv !== '') $hpDrv = normalisasiHp($hpDrv); // rapikan manual ke +62
    if ($nama === '' && $nopol === '' && $unitId === 0) {
        continue; // baris kosong
    }
    if ($nama === '' || $nopol === '') {
        $errors[] = 'Baris unit ' . ($i + 1) . ': nama unit dan nomor polisi wajib lengkap.';
        continue;
    }
    $hari  = (int) ($arrHari[$i] ?? 0);
    if ($hari <= 0) $hari = max(1, $jumlah_hari);
    $modal = angka($arrModal[$i] ?? 0);
    $jual  = angka($arrJual[$i] ?? 0);

    if ($drvId === 0 && $namaDrv === '') {
        $errors[] = 'Baris unit ' . ($i + 1) . ': driver wajib dipilih.';
    }
    $partnerItem = (int) ($arrPartnerId[$i] ?? 0);
    $partnerItem = $partnerItem > 0 ? $partnerItem : null;

    $items[] = [
        'unit_id' => $unitId, 'driver_id' => $drvId ?: null, 'partner_id' => $partnerItem,
        'nama_unit' => $nama, 'nopol' => $nopol,
        'nama_driver' => $namaDrv, 'hp_driver' => $hpDrv,
        'harga_modal' => $modal, 'harga_jual' => $jual, 'jumlah_hari' => $hari,
        'subtotal_modal' => $modal * $hari, 'subtotal_jual' => $jual * $hari,
        'catatan' => trim((string) ($arrCatatan[$i] ?? '')),
    ];
}
if (!$items) {
    $errors[] = 'Minimal satu unit harus diisi (nama unit + nomor polisi).';
}

/* ---------------- bentrok jadwal unit ---------------- */
if ($items && $status !== 'draft' && $tgl_mulai && $tgl_finish) {
    foreach ($items as $it) {
        if (!$it['unit_id']) continue;
        foreach (cekBentrokUnit((int) $it['unit_id'], $tgl_mulai, $tgl_finish, $id) as $b) {
            $errors[] = 'Unit ' . $it['nopol'] . ' sudah dipakai pada ' . $b['nomor_order']
                      . ' (' . tglAngka($b['tgl_mulai']) . ' s/d ' . tglAngka($b['tgl_finish']) . ' - ' . $b['nama_pesanan'] . ').';
        }
    }
}

if ($errors) {
    foreach ($errors as $er) setFlash('danger', $er);
    redirect(BASE_URL . '/pages/pesanan_form.php' . ($id > 0 ? '?id=' . $id : ''));
}

/* ---------------- customer: cari atau buat ---------------- */
$namaPic = trim((string) ($_POST['nama_pic'] ?? ''));
$hpPic   = normalisasiHp((string) ($_POST['hp_pic'] ?? ''));
$sumber  = (string) ($_POST['sumber'] ?? 'wa');

$st = $db->prepare('SELECT id FROM customers WHERE deleted_at IS NULL AND LOWER(nama_pesanan) = LOWER(?) LIMIT 1');
$st->bind_param('s', $nama_pesanan);
$st->execute();
$cust = $st->get_result()->fetch_assoc();
if ($cust) {
    $customerId = (int) $cust['id'];
} else {
    $tipeCust = $nama_pesanan && preg_match('/\b(PT|CV|UD|Dinas|Kantor|Badan|Otoritas|Bank|Universitas|Sekolah|Prov|Kab)\b/i', $nama_pesanan) ? 'instansi' : 'perorangan';
    $ins = $db->prepare('INSERT INTO customers (tipe, nama_pesanan, nama_pic, hp_pic, sumber, status) VALUES (?, ?, ?, ?, ?, "baru")');
    $ins->bind_param('sssss', $tipeCust, $nama_pesanan, $namaPic, $hpPic, $sumber);
    $ins->execute();
    $customerId = (int) $db->insert_id;
}

/* ---------------- simpan order ---------------- */
$partnerId = null; /* Support By sekarang per unit (order_items.partner_id) */
$jam           = trim((string) ($_POST['jam'] ?? ''));
$jamKoordinasi = isset($_POST['jam_koordinasi']) ? 1 : 0;
$standby       = trim((string) ($_POST['standby_point'] ?? ''));
$flight        = trim((string) ($_POST['flight'] ?? ''));
$tujuan        = trim((string) ($_POST['tujuan'] ?? ''));
$handleBy      = trim((string) ($_POST['handle_by'] ?? namaUser()));
$tipePelanggan = (string) ($_POST['tipe_pelanggan'] ?? 'retail');
$wilayah       = (string) ($_POST['wilayah_pelayanan'] ?? 'dalam_kota');
$catatanOrder  = trim((string) ($_POST['catatan'] ?? ''));
$statusLama    = null;

$db->begin_transaction();
try {
    if ($id > 0) {
        $st = $db->prepare('SELECT status FROM orders WHERE id = ?');
        $st->bind_param('i', $id);
        $st->execute();
        $rowLama = $st->get_result()->fetch_assoc();
        $statusLama = $rowLama['status'] ?? null;

        $fields = [
            ['customer_id', $customerId, 'i'], ['tipe_pelanggan', $tipePelanggan, 's'],
            ['wilayah_pelayanan', $wilayah, 's'], ['kota', $kota, 's'],
            ['tgl_mulai', $tgl_mulai, 's'], ['tgl_finish', $tgl_finish, 's'],
            ['jumlah_hari', $jumlah_hari, 'i'], ['jam', $jam, 's'],
            ['jam_koordinasi', $jamKoordinasi, 'i'], ['standby_point', $standby, 's'],
            ['flight', $flight, 's'], ['tujuan', $tujuan, 's'],
            ['nama_pesanan', $nama_pesanan, 's'], ['nama_pic', $namaPic, 's'],
            ['hp_pic', $hpPic, 's'], ['sumber', $sumber, 's'],
            ['handle_by', $handleBy, 's'], ['partner_id', $partnerId, 'i'],
            ['status', $status, 's'], ['catatan', $catatanOrder, 's'],
        ];
        $set = []; $types = ''; $vals = [];
        foreach ($fields as $fl) { $set[] = '`' . $fl[0] . '` = ?'; $types .= $fl[2]; $vals[] = $fl[1]; }
        $types .= 'i';
        $vals[] = $id;
        $st = $db->prepare('UPDATE orders SET ' . implode(', ', $set) . ' WHERE id = ?');
        $st->bind_param($types, ...$vals);
        $st->execute();

        foreach (['order_items', 'order_includes', 'order_biaya'] as $t) {
            $del = $db->prepare("DELETE FROM $t WHERE order_id = ?");
            $del->bind_param('i', $id);
            $del->execute();
        }
        $orderId = $id;
    } else {
        $nomor = nomorDokumen('order');
        $createdBy = idUser();
        $fields = [
            ['nomor_order', $nomor, 's'], ['customer_id', $customerId, 'i'],
            ['tipe_pelanggan', $tipePelanggan, 's'], ['wilayah_pelayanan', $wilayah, 's'],
            ['kota', $kota, 's'], ['tgl_mulai', $tgl_mulai, 's'], ['tgl_finish', $tgl_finish, 's'],
            ['jumlah_hari', $jumlah_hari, 'i'], ['jam', $jam, 's'],
            ['jam_koordinasi', $jamKoordinasi, 'i'], ['standby_point', $standby, 's'],
            ['flight', $flight, 's'], ['tujuan', $tujuan, 's'],
            ['nama_pesanan', $nama_pesanan, 's'], ['nama_pic', $namaPic, 's'],
            ['hp_pic', $hpPic, 's'], ['sumber', $sumber, 's'], ['handle_by', $handleBy, 's'],
            ['partner_id', $partnerId, 'i'], ['status', $status, 's'],
            ['catatan', $catatanOrder, 's'], ['created_by', $createdBy, 'i'],
        ];
        $kol = []; $types = ''; $vals = [];
        foreach ($fields as $fl) { $kol[] = '`' . $fl[0] . '`'; $types .= $fl[2]; $vals[] = $fl[1]; }
        $st = $db->prepare('INSERT INTO orders (' . implode(', ', $kol) . ') VALUES (' . implode(', ', array_fill(0, count($kol), '?')) . ')');
        $st->bind_param($types, ...$vals);
        $st->execute();
        $orderId = (int) $db->insert_id;
    }

    /* item */
    $insItem = $db->prepare('INSERT INTO order_items (order_id, unit_id, driver_id, partner_id, nama_unit, nopol, nama_driver, hp_driver,
        harga_modal_per_hari, harga_jual_per_hari, jumlah_hari, subtotal_modal, subtotal_jual, catatan)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($items as $it) {
        $drvId = $it['driver_id'] === null ? 0 : (int) $it['driver_id'];
        $drvId = $drvId > 0 ? $drvId : null;
        $pid   = $it['partner_id'];
        $insItem->bind_param('iiiissssiiiiis',
            $orderId, $it['unit_id'], $drvId, $pid, $it['nama_unit'], $it['nopol'], $it['nama_driver'], $it['hp_driver'],
            $it['harga_modal'], $it['harga_jual'], $it['jumlah_hari'], $it['subtotal_modal'], $it['subtotal_jual'], $it['catatan']);
        $insItem->execute();
    }

    /* include */
    $incIds = $_POST['include_id'] ?? [];
    $incBiaya = $_POST['include_biaya'] ?? [];
    if ($incIds) {
        $insInc = $db->prepare('INSERT INTO order_includes (order_id, include_id, nama, biaya) VALUES (?,?,?,?)');
        foreach ($incIds as $iid) {
            $iid = (int) $iid;
            $st = $db->prepare('SELECT nama FROM includes WHERE id = ?');
            $st->bind_param('i', $iid);
            $st->execute();
            $nm = $st->get_result()->fetch_assoc()['nama'] ?? '';
            if ($nm === '') continue;
            $bi = angka($incBiaya[$iid] ?? 0);
            $insInc->bind_param('iisi', $orderId, $iid, $nm, $bi);
            $insInc->execute();
        }
    }

    /* biaya tambahan */
    $bNama = $_POST['biaya_nama'] ?? [];
    $bNom  = $_POST['biaya_nominal'] ?? [];
    if ($bNama) {
        $insB = $db->prepare('INSERT INTO order_biaya (order_id, nama, nominal) VALUES (?,?,?)');
        foreach ($bNama as $i => $bn) {
            $bn = trim((string) $bn);
            $nom = angka($bNom[$i] ?? 0);
            if ($bn === '' && $nom === 0) continue;
            if ($bn === '') $bn = 'Biaya tambahan';
            $insB->bind_param('isi', $orderId, $bn, $nom);
            $insB->execute();
        }
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    setFlash('danger', 'Gagal menyimpan pesanan: ' . $e->getMessage());
    redirect(BASE_URL . '/pages/pesanan_form.php' . ($id > 0 ? '?id=' . $id : ''));
}

$total = hitungOrder($orderId);

if ($id > 0) {
    if ($statusLama !== $status) {
        catatStatus($orderId, $statusLama, $status, 'Status diubah dari form pesanan');
    } else {
        catatStatus($orderId, $status, $status, 'Data pesanan diperbarui');
    }
    setFlash('success', 'Pesanan diperbarui. Total tagihan: ' . rupiah($total['grand_total']) . '.');
} else {
    catatStatus($orderId, null, $status, 'Pesanan dibuat');
    setFlash('success', 'Pesanan tersimpan dengan nomor ' . (function () use ($db, $orderId) {
        $s = $db->prepare('SELECT nomor_order FROM orders WHERE id = ?');
        $s->bind_param('i', $orderId);
        $s->execute();
        return $s->get_result()->fetch_assoc()['nomor_order'] ?? '';
    })() . '.');
}

redirect(BASE_URL . '/pages/pesanan_detail.php?id=' . $orderId);
