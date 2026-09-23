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

    $ins = $db->prepare('INSERT INTO invoice_items (invoice_id, deskripsi, qty, satuan, harga_satuan, jumlah, urutan) VALUES (?,?,?,?,?,?,?)');
    $urut = 0;
    foreach ($order['items'] as $it) {
        $urut++;
        $drv = $it['nama_driver'] ? ' + Driver ' . $it['nama_driver'] : '';
        $desk = 'Sewa ' . $it['nama_unit'] . ' (' . $it['nopol'] . ')' . $drv
              . ' - periode ' . tglAngka($order['tgl_mulai']) . ' s/d ' . tglAngka($order['tgl_finish']);
        $inc = implode(' + ', array_map(fn($x) => $x['nama'], $order['includes']));
        if ($inc !== '') $desk .= ' | Include: ' . $inc;
        $qty = (int) $it['jumlah_hari'];
        $hs  = (int) $it['harga_jual_per_hari'];
        $jml = (int) $it['subtotal_jual'];
        $sat = 'hari';
        $ins->bind_param('isisiis', $invoiceId, $desk, $qty, $sat, $hs, $jml, $urut);
        $ins->execute();
    }
    foreach ($order['biaya'] as $b) {
        $urut++;
        $desk = $b['nama'];
        $qty = 1; $sat = 'paket'; $hs = (int) $b['nominal']; $jml = (int) $b['nominal'];
        $ins->bind_param('isisiis', $invoiceId, $desk, $qty, $sat, $hs, $jml, $urut);
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
            setFlash('warning', 'Invoice sudah terbit: ' . $aktif[0]['nomor_invoice'] . '. Gunakan tombol Revisi kalau ada yang perlu diubah.');
            redirect($kembali);
        }
        $invBaru = terbitkanInvoice($order);
        $nomor = $invBaru['nomor'];
        $st = $db->prepare('UPDATE orders SET status = "invoiced" WHERE id = ? AND status NOT IN ("paid","reported")');
        $st->bind_param('i', $id);
        $st->execute();
        catatStatus($id, $order['status'], 'invoiced', 'Invoice diterbitkan: ' . $nomor);
        setFlash('success', 'Invoice ' . $nomor . ' diterbitkan. Nomor invoice terkunci - perubahan berikutnya lewat Revisi.');
        break;

    case 'revisi':
        $aktif = array_values(array_filter($order['invoices'], fn($x) => $x['status'] !== 'batal'));
        if (!$aktif) {
            setFlash('danger', 'Belum ada invoice untuk direvisi.');
            redirect($kembali);
        }
        $lama = $aktif[0];
        $invBaru = terbitkanInvoice($order, (int) $lama['nomor_revisi_ke'] + 1, $lama['nomor_invoice']);
        $nomorBaru = $invBaru['nomor'];

        /* uang yang sudah masuk tidak boleh hilang saat nomor invoice diganti:
           pembayaran ikut pindah ke nomor baru */
        $mv = $db->prepare('UPDATE payments SET invoice_id = ? WHERE invoice_id = ?');
        $invBaruId = (int) $invBaru['id'];
        $lamaId = (int) $lama['id'];
        $mv->bind_param('ii', $invBaruId, $lamaId);
        $mv->execute();

        $st = $db->prepare('UPDATE invoices SET status = "batal", replaced_by = ? WHERE id = ?');
        $st->bind_param('si', $nomorBaru, $lamaId);
        $st->execute();

        perbaruiInvoice($invBaruId, $id);
        catatStatus($id, $order['status'], $order['status'], 'Invoice direvisi: ' . $lama['nomor_invoice'] . ' -> ' . $nomorBaru . ' (pembayaran dipindahkan)');
        setFlash('success', 'Invoice direvisi. ' . $lama['nomor_invoice'] . ' ditandai batal, nomor baru: ' . $nomorBaru . '. Pembayaran yang sudah masuk ikut dipindahkan.');
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
