<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();

$id = (int) ($_POST['id'] ?? ($_GET['id'] ?? 0));
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/pesanan_list.php');
}
verifyCsrfToken();

$aksi = (string) ($_POST['aksi'] ?? '');
$order = ambilOrder($id);
if (!$order) {
    setFlash('danger', 'Pesanan tidak ditemukan.');
    redirect(BASE_URL . '/pages/pesanan_list.php');
}
$kembali = BASE_URL . '/pages/pesanan_detail.php?id=' . $id;

/** Hitung ulang status pembayaran invoice dari tabel payments. */
function perbaruiInvoice(int $invoiceId, int $orderId): void
{
    $db = getDB();
    $s = $db->prepare('SELECT COALESCE(SUM(nominal),0) dibayar FROM payments WHERE invoice_id = ?');
    $s->bind_param('i', $invoiceId);
    $s->execute();
    $dibayar = (int) $s->get_result()->fetch_assoc()['dibayar'];

    $s = $db->prepare('SELECT total FROM invoices WHERE id = ?');
    $s->bind_param('i', $invoiceId);
    $s->execute();
    $total = (int) $s->get_result()->fetch_assoc()['total'];

    $sisa = $total - $dibayar;
    if ($sisa < 0) $sisa = 0;
    if ($dibayar <= 0)          $statusInv = 'terbit';
    elseif ($sisa === 0)        $statusInv = 'lunas';
    else                        $statusInv = 'sebagian';

    $u = $db->prepare('UPDATE invoices SET dp = ?, sisa = ?, status = ? WHERE id = ?');
    $u->bind_param('iisi', $dibayar, $sisa, $statusInv, $invoiceId);
    $u->execute();

    /* status order mengikuti pembayaran */
    if ($statusInv === 'lunas') {
        $s = $db->prepare('SELECT status FROM orders WHERE id = ?');
        $s->bind_param('i', $orderId);
        $s->execute();
        $lama = $s->get_result()->fetch_assoc()['status'] ?? '';
        if ($lama !== 'paid') {
            $u = $db->prepare('UPDATE orders SET status = "paid" WHERE id = ?');
            $u->bind_param('i', $orderId);
            $u->execute();
            catatStatus($orderId, $lama, 'paid', 'Invoice lunas');
        }
    }
}

/** Buat invoice baru dari data order (snapshot harga hari ini). */
function terbitkanInvoice(array $order, int $revisiKe = 0, ?string $menggantikan = null): array
{
    $db = getDB();
    $nomor = nomorDokumen('invoice');
    $hariJatuhTempo = (int) getSetting('invoice_jatuh_tempo_hari', '7');
    $tanggal   = date('Y-m-d');
    $jatuhTempo = date('Y-m-d', strtotime('+' . $hariJatuhTempo . ' days'));
    $total     = (int) $order['grand_total'];
    $catatan   = 'Pesanan ' . $order['nomor_order'] . ' - ' . $order['nama_pesanan'];
    if ($menggantikan) {
        $catatan .= ' | menggantikan ' . $menggantikan;
    }
    $custSnap  = json_encode([
        'nama'   => $order['nama_pesanan'],
        'pic'    => $order['nama_pic'],
        'hp'     => $order['hp_pic'],
        'alamat' => $order['customer_alamat'] ?? '',
        'email'  => $order['customer_email'] ?? '',
    ], JSON_UNESCAPED_UNICODE);
    $orderSnap = json_encode([
        'nomor_order' => $order['nomor_order'],
        'kota' => $order['kota'],
        'wilayah' => $order['wilayah_pelayanan'],
        'tgl_mulai' => $order['tgl_mulai'],
        'tgl_finish' => $order['tgl_finish'],
        'jumlah_hari' => $order['jumlah_hari'],
        'standby_point' => $order['standby_point'],
        'flight' => $order['flight'],
        'jam' => $order['jam'],
        'jam_koordinasi' => $order['jam_koordinasi'],
        'partner' => $order['partner_nama'] ?? '',
        'include' => implode('+', array_map(fn($x) => $x['nama'], $order['includes'])),
    ], JSON_UNESCAPED_UNICODE);
    $issuedBy = idUser();

    /* nilai disiapkan sebagai variabel lebih dulu (bind_param butuh referensi) */
    $orderId  = (int) $order['id'];
    $nilaiDp  = 0;
    $statusInv = 'terbit';
    $replacedBy = null;
    $fields = [
        ['order_id', $orderId, 'i'], ['nomor_invoice', $nomor, 's'], ['nomor_revisi_ke', $revisiKe, 'i'],
        ['tanggal_invoice', $tanggal, 's'], ['jatuh_tempo', $jatuhTempo, 's'], ['total', $total, 'i'],
        ['dp', $nilaiDp, 'i'], ['sisa', $total, 'i'], ['status', $statusInv, 's'],
        ['customer_snapshot', $custSnap, 's'], ['order_snapshot', $orderSnap, 's'], ['catatan', $catatan, 's'],
        ['issued_by', $issuedBy, 'i'], ['replaced_by', $replacedBy, 's'],
    ];
    $kol = []; $types = ''; $vals = [];
    foreach ($fields as $fl) { $kol[] = '`' . $fl[0] . '`'; $types .= $fl[2]; $vals[] = $fl[1]; }
    $kol[] = 'issued_at';
    $sql = 'INSERT INTO invoices (' . implode(', ', $kol) . ') VALUES (' . implode(', ', array_fill(0, count($kol) - 1, '?')) . ', NOW())';
    $st = $db->prepare($sql);
    $st->bind_param($types, ...$vals);
    $st->execute();
    $invoiceId = (int) $db->insert_id;

    /* kolom mengikuti template invoice referensi:
       No | Keterangan | Driver | Tgl Pemakaian | Rute | Harga/Hari | Total Hari | Total Harga */
    $ins = $db->prepare('INSERT INTO invoice_items (invoice_id, no, keterangan, driver, tanggal_pakai, rute, harga_hari, total_hari, total_harga)
                         VALUES (?,?,?,?,?,?,?,?,?)');
    $urut = 0;
    $incTeks = implode(' + ', array_map(fn($x) => $x['nama'], $order['includes']));
    foreach ($order['items'] as $it) {
        $urut++;
        $keterangan = $it['nama_unit'] . "\n" . $it['nopol'];
        if ($incTeks !== '') $keterangan .= "\nInclude: " . $incTeks;
        $driver = (string) ($it['nama_driver'] ?: '');
        $tanggal = formatRentang((string) $order['tgl_mulai'], (string) $order['tgl_finish']);
        $rute = labelWilayah($order['wilayah_pelayanan']) . ' ' . $order['kota'];
        if (!empty($order['tujuan'])) $rute .= "\n" . $order['tujuan'];
        if (!empty($order['standby_point'])) $rute .= "\nStandby: " . $order['standby_point'];
        $hargaHari = (int) $it['harga_jual_per_hari'];
        $hari = (int) $it['jumlah_hari'];
        $totalHarga = (int) $it['subtotal_jual'];
        $ins->bind_param('iissssiii', $invoiceId, $urut, $keterangan, $driver, $tanggal, $rute, $hargaHari, $hari, $totalHarga);
        $ins->execute();
    }
    foreach ($order['biaya'] as $b) {
        $urut++;
        $keterangan = (string) $b['nama'];
        $driver = '';
        $tanggal = '';
        $rute = '';
        $hargaHari = (int) $b['nominal'];
        $hari = 1;
        $totalHarga = (int) $b['nominal'];
        $ins->bind_param('iissssiii', $invoiceId, $urut, $keterangan, $driver, $tanggal, $rute, $hargaHari, $hari, $totalHarga);
        $ins->execute();
    }
    return ['id' => $invoiceId, 'nomor' => $nomor];
}

/**
 * Perbarui ISI invoice yang sudah terbit TANPA mengganti nomor invoice.
 * Dipakai tombol Revisi. Nomor invoice tetap sama; yang berubah hanya total,
 * rincian baris, dan snapshot. Pembayaran tidak disentuh (invoice_id sama),
 * jadi uang yang sudah masuk otomatis tetap menempel.
 */
function perbaruiIsiInvoice(array $order, array $invoice): array
{
    $db = getDB();
    $invoiceId = (int) $invoice['id'];
    $nomor     = (string) $invoice['nomor_invoice'];
    $total     = (int) $order['grand_total'];
    $revisiKe  = (int) $invoice['nomor_revisi_ke'] + 1;

    $catatan = 'Pesanan ' . $order['nomor_order'] . ' - ' . $order['nama_pesanan']
             . ($revisiKe > 0 ? ' | diperbarui ' . $revisiKe . 'x' : '');
    $custSnap = json_encode([
        'nama'   => $order['nama_pesanan'],
        'pic'    => $order['nama_pic'],
        'hp'     => $order['hp_pic'],
        'alamat' => $order['customer_alamat'] ?? '',
        'email'  => $order['customer_email'] ?? '',
    ], JSON_UNESCAPED_UNICODE);
    $orderSnap = json_encode([
        'nomor_order' => $order['nomor_order'],
        'kota' => $order['kota'],
        'wilayah' => $order['wilayah_pelayanan'],
        'tgl_mulai' => $order['tgl_mulai'],
        'tgl_finish' => $order['tgl_finish'],
        'jumlah_hari' => $order['jumlah_hari'],
        'standby_point' => $order['standby_point'],
        'flight' => $order['flight'],
        'jam' => $order['jam'],
        'jam_koordinasi' => $order['jam_koordinasi'],
        'partner' => $order['partner_nama'] ?? '',
        'include' => implode('+', array_map(fn($x) => $x['nama'], $order['includes'])),
    ], JSON_UNESCAPED_UNICODE);

    $st = $db->prepare('UPDATE invoices SET total = ?, nomor_revisi_ke = ?, customer_snapshot = ?, order_snapshot = ?, catatan = ? WHERE id = ?');
    $st->bind_param('iisssi', $total, $revisiKe, $custSnap, $orderSnap, $catatan, $invoiceId);
    $st->execute();

    /* ganti rincian baris supaya cocok dengan data order terbaru */
    $del = $db->prepare('DELETE FROM invoice_items WHERE invoice_id = ?');
    $del->bind_param('i', $invoiceId);
    $del->execute();

    $ins = $db->prepare('INSERT INTO invoice_items (invoice_id, no, keterangan, driver, tanggal_pakai, rute, harga_hari, total_hari, total_harga)
                         VALUES (?,?,?,?,?,?,?,?,?)');
    $urut = 0;
    $incTeks = implode(' + ', array_map(fn($x) => $x['nama'], $order['includes']));
    foreach ($order['items'] as $it) {
        $urut++;
        $keterangan = $it['nama_unit'] . "\n" . $it['nopol'];
        if ($incTeks !== '') $keterangan .= "\nInclude: " . $incTeks;
        $driver = (string) ($it['nama_driver'] ?: '');
        $tanggal = formatRentang((string) $order['tgl_mulai'], (string) $order['tgl_finish']);
        $rute = labelWilayah($order['wilayah_pelayanan']) . ' ' . $order['kota'];
        if (!empty($order['tujuan'])) $rute .= "\n" . $order['tujuan'];
        if (!empty($order['standby_point'])) $rute .= "\nStandby: " . $order['standby_point'];
        $hargaHari = (int) $it['harga_jual_per_hari'];
        $hari = (int) $it['jumlah_hari'];
        $totalHarga = (int) $it['subtotal_jual'];
        $ins->bind_param('iissssiii', $invoiceId, $urut, $keterangan, $driver, $tanggal, $rute, $hargaHari, $hari, $totalHarga);
        $ins->execute();
    }
    foreach ($order['biaya'] as $b) {
        $urut++;
        $keterangan = (string) $b['nama'];
        $kosong = '';
        $hargaHari = (int) $b['nominal'];
        $hari = 1;
        $totalHarga = (int) $b['nominal'];
        $ins->bind_param('iissssiii', $invoiceId, $urut, $keterangan, $kosong, $kosong, $kosong, $hargaHari, $hari, $totalHarga);
        $ins->execute();
    }

    return ['id' => $invoiceId, 'nomor' => $nomor];
}

switch ($aksi) {
    case 'status':
        $baru = (string) ($_POST['status_baru'] ?? '');
        if (!in_array($baru, daftarStatus(), true)) {
            setFlash('danger', 'Status tidak dikenal.');
            redirect($kembali);
        }
        if ($baru !== $order['status']) {
            $st = $db->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $st->bind_param('si', $baru, $id);
            $st->execute();
            catatStatus($id, $order['status'], $baru, trim((string) ($_POST['catatan_status'] ?? '')) ?: 'Diubah dari halaman detail');
            setFlash('success', 'Status pesanan diubah menjadi ' . statusLabel($baru) . '.');
        } else {
            setFlash('info', 'Status tidak berubah.');
        }
        break;

    case 'terbit':
        $aktif = array_values(array_filter($order['invoices'], fn($x) => $x['status'] !== 'batal'));
        if ($aktif) {
            setFlash('warning', 'Invoice sudah terbit: ' . $aktif[0]['nomor_invoice'] . '. Gunakan tombol Perbarui Invoice kalau ada yang perlu diubah (nomor tetap sama).');
            redirect($kembali);
        }
        $invBaru = terbitkanInvoice($order);
        $nomor = $invBaru['nomor'];
        // Panjar otomatis jadi DP — best practice: input sekali di form, tidak input lagi di halaman ini
        $panjar = (int) ($order['panjar'] ?? 0);
        if ($panjar > 0) {
            $panjar = min($panjar, (int) $order['grand_total']);
            $invIdBaru = (int) $invBaru['id'];
            // hindari duplikat jika karena satu dan lain hal sudah ada DP panjar
            $cek = $db->prepare('SELECT COUNT(*) c FROM payments WHERE invoice_id = ? AND catatan = "Panjar awal dari form pesanan"');
            $cek->bind_param('i', $invIdBaru);
            $cek->execute();
            $sudahAda = (int) ($cek->get_result()->fetch_assoc()['c'] ?? 0);
            if ($sudahAda === 0) {
                $tglBayar = date('Y-m-d');
                $tipePanjar = 'dp';
                $metodePanjar = 'transfer';
                $bankPanjar = '';
                $buktiPanjar = '';
                $catPanjar = 'Panjar awal dari form pesanan';
                $olehPanjar = idUser();
                $insPanjar = $db->prepare('INSERT INTO payments (invoice_id, tanggal_bayar, tipe, nominal, metode, bank, bukti_path, catatan, created_by) VALUES (?,?,?,?,?,?,?,?,?)');
                $insPanjar->bind_param('ississssi', $invIdBaru, $tglBayar, $tipePanjar, $panjar, $metodePanjar, $bankPanjar, $buktiPanjar, $catPanjar, $olehPanjar);
                $insPanjar->execute();
                perbaruiInvoice($invIdBaru, $id);
            }
        }
        $st = $db->prepare('UPDATE orders SET status = "invoiced" WHERE id = ? AND status NOT IN ("paid","reported")');
        $st->bind_param('i', $id);
        $st->execute();
        catatStatus($id, $order['status'], 'invoiced', 'Invoice diterbitkan: ' . $nomor . ($panjar > 0 ? ' (panjar Rp ' . number_format($panjar, 0, ',', '.') . ' otomatis jadi DP)' : ''));
        setFlash('success', 'Invoice ' . $nomor . ' diterbitkan.' . ($panjar > 0 ? ' Panjar Rp ' . number_format($panjar, 0, ',', '.') . ' otomatis tercatat sebagai DP.' : '') . ' Ada perubahan? Pakai Perbarui Invoice (nomor tetap sama).');
        break;

    case 'revisi':
        $aktif = array_values(array_filter($order['invoices'], fn($x) => $x['status'] !== 'batal'));
        if (!$aktif) {
            setFlash('danger', 'Belum ada invoice untuk diperbarui.');
            redirect($kembali);
        }
        $inv = $aktif[0];
        $nomor = (string) $inv['nomor_invoice'];
        $totalLama = (int) $inv['total'];
        $totalBaru = (int) $order['grand_total'];

        /* isi invoice diperbarui di tempat: nomor TETAP SAMA (permintaan internal).
           Pembayaran tidak dipindah karena invoice_id tidak berubah. */
        perbaruiIsiInvoice($order, $inv);
        perbaruiInvoice((int) $inv['id'], $id);

        $st = $db->prepare('UPDATE orders SET status = "invoiced" WHERE id = ? AND status NOT IN ("paid","reported")');
        $st->bind_param('i', $id);
        $st->execute();

        if ($totalLama !== $totalBaru) {
            $ubah = 'total ' . rupiah($totalLama) . ' -> ' . rupiah($totalBaru);
        } else {
            $ubah = 'rincian/tanggal diperbarui';
        }
        catatStatus($id, $order['status'], $order['status'], 'Invoice ' . $nomor . ' diperbarui (nomor tetap): ' . $ubah);
        setFlash('success', 'Invoice ' . $nomor . ' diperbarui. Nomor invoice TIDAK berubah. ' . ($totalLama !== $totalBaru ? 'Total: ' . rupiah($totalLama) . ' menjadi ' . rupiah($totalBaru) . '. ' : '') . 'Pembayaran yang sudah masuk tetap menempel.');
        break;

    case 'bayar':
        $aktif = array_values(array_filter($order['invoices'], fn($x) => $x['status'] !== 'batal'));
        if (!$aktif) {
            setFlash('danger', 'Terbitkan invoice dulu sebelum mencatat pembayaran.');
            redirect($kembali);
        }
        $invoice = $aktif[0];
        $nominal = angka($_POST['nominal'] ?? 0);
        if ($nominal <= 0) {
            setFlash('danger', 'Nominal pembayaran wajib lebih dari 0.');
            redirect($kembali);
        }
        $tglBayar = trim((string) ($_POST['tanggal_bayar'] ?? date('Y-m-d')));
        $tipeBayar = (string) ($_POST['tipe'] ?? 'pelunasan');
        $metode = (string) ($_POST['metode'] ?? 'transfer');
        $bank = trim((string) ($_POST['bank'] ?? ''));
        $catatanBayar = trim((string) ($_POST['catatan_bayar'] ?? ''));
        $bukti = '';
        if (!empty($_FILES['bukti']['name'])) {
            $up = uploadBukti($_FILES['bukti']);
            if (!$up['ok']) {
                setFlash('danger', $up['msg']);
                redirect($kembali);
            }
            $bukti = $up['path'];
        }
        $sisaSekarang = (int) $invoice['sisa'];
        if ($nominal > $sisaSekarang) {
            setFlash('danger', 'Nominal melebihi sisa tagihan (Rp ' . number_format($sisaSekarang, 0, ',', '.') . '). Periksa kembali.');
            redirect($kembali);
        }
        $invId = (int) $invoice['id'];
        $oleh = idUser();
        $st = $db->prepare('INSERT INTO payments (invoice_id, tanggal_bayar, tipe, nominal, metode, bank, bukti_path, catatan, created_by)
                            VALUES (?,?,?,?,?,?,?,?,?)');
        $st->bind_param('ississssi', $invId, $tglBayar, $tipeBayar, $nominal, $metode, $bank, $bukti, $catatanBayar, $oleh);
        $st->execute();
        perbaruiInvoice($invId, $id);
        setFlash('success', 'Pembayaran ' . rupiah($nominal) . ' dicatat.');
        break;

    case 'hapus_bayar':
        $pid = (int) ($_POST['payment_id'] ?? 0);
        $aktif = array_values(array_filter($order['invoices'], fn($x) => $x['status'] !== 'batal'));
        if (!$aktif || $pid <= 0) {
            setFlash('danger', 'Pembayaran tidak ditemukan.');
            redirect($kembali);
        }
        $invId = (int) $aktif[0]['id'];
        $st = $db->prepare('DELETE FROM payments WHERE id = ? AND invoice_id = ?');
        $st->bind_param('ii', $pid, $invId);
        $st->execute();
        perbaruiInvoice($invId, $id);
        setFlash('success', 'Catatan pembayaran dihapus.');
        break;

    case 'batal':
        $st = $db->prepare('UPDATE orders SET status = "cancelled" WHERE id = ?');
        $st->bind_param('i', $id);
        $st->execute();
        catatStatus($id, $order['status'], 'cancelled', trim((string) ($_POST['alasan'] ?? '')) ?: 'Dibatalkan');
        setFlash('success', 'Pesanan dibatalkan.');
        break;

    case 'hapus':
        $st = $db->prepare('UPDATE orders SET deleted_at = NOW() WHERE id = ?');
        $st->bind_param('i', $id);
        $st->execute();
        setFlash('success', 'Pesanan dihapus dari daftar (data tetap tersimpan di database).');
        redirect(BASE_URL . '/pages/pesanan_list.php');

    default:
        setFlash('danger', 'Aksi tidak dikenal.');
}

redirect($kembali);
