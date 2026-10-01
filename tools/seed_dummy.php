<?php
/**
 * tools/seed_dummy.php — HAPUS SEMUA DATA lalu isi ulang dengan data dummy.
 * Membuat master kecil (unit, driver, pelanggan, partner, include) + 10 pesanan
 * dengan status beragam supaya dashboard, papan status, kalender ketersediaan,
 * dan Asisten tampak hidup untuk demo. Settings & users TIDAK dihapus.
 *
 * Jalankan: php tools/seed_dummy.php   (CLI only, HANYA saat sengaja di-reset)
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "Hanya lewat CLI.\n"); exit(1); }

require_once __DIR__ . '/../includes/functions.php';
$db = getDB();

function insRow($db, string $table, array $data): int {
    $cols = array_keys($data);
    $vals = array_map(
        fn($v) => $v === null ? 'NULL' : "'" . $db->real_escape_string((string) $v) . "'",
        array_values($data)
    );
    $colSql = implode(',', array_map(fn($c) => "`$c`", $cols));
    $sql = "INSERT INTO `$table` ($colSql) VALUES (" . implode(',', $vals) . ")";
    $db->query($sql);
    return (int) $db->insert_id;
}

/* ---------- 1. HAPUS SEMUA (child dulu), settings & users dipertahankan ---------- */
$hapus = ['payments', 'invoice_items', 'invoices', 'status_logs', 'order_biaya',
          'order_includes', 'order_items', 'orders', 'units', 'drivers', 'customers',
          'partners', 'includes', 'doc_counters'];
foreach ($hapus as $t) {
    $db->query("DELETE FROM `$t`");
    $db->query("ALTER TABLE `$t` AUTO_INCREMENT = 1");
}

/* ---------- 2. MASTER DUMMY ---------- */
$units = [
    ['kode_unit'=>'AVZ-01','nama_unit'=>'Toyota Avanza','nopol'=>'BK1001RN','merek'=>'Toyota','model'=>'Avanza 1.3 G','jenis'=>'MPV','transmisi'=>'matic','kapasitas'=>7,'pemilik'=>'sendiri','partner_id'=>null,'harga_modal_default'=>250000,'harga_jual_default'=>350000,'status'=>'ready'],
    ['kode_unit'=>'INN-01','nama_unit'=>'Toyota Innova Reborn','nopol'=>'BK1002RN','merek'=>'Toyota','model'=>'Innova Reborn 2.4 G','jenis'=>'MPV','transmisi'=>'matic','kapasitas'=>7,'pemilik'=>'sendiri','partner_id'=>null,'harga_modal_default'=>380000,'harga_jual_default'=>500000,'status'=>'ready'],
    ['kode_unit'=>'HIA-01','nama_unit'=>'Toyota Hiace Premio','nopol'=>'BK1003RN','merek'=>'Toyota','model'=>'Hiace Premio','jenis'=>'Hiace','transmisi'=>'matic','kapasitas'=>14,'pemilik'=>'sendiri','partner_id'=>null,'harga_modal_default'=>900000,'harga_jual_default'=>1200000,'status'=>'ready'],
    ['kode_unit'=>'ALP-01','nama_unit'=>'Toyota Alphard','nopol'=>'BK1004RN','merek'=>'Toyota','model'=>'Alphard 2.5 G','jenis'=>'MPV','transmisi'=>'matic','kapasitas'=>7,'pemilik'=>'sendiri','partner_id'=>null,'harga_modal_default'=>2000000,'harga_jual_default'=>2500000,'status'=>'ready'],
    ['kode_unit'=>'ZEN-01','nama_unit'=>'Toyota Innova Zenix','nopol'=>'BK1005RN','merek'=>'Toyota','model'=>'Innova Zenix Q','jenis'=>'MPV','transmisi'=>'matic','kapasitas'=>7,'pemilik'=>'partner','partner_id'=>null,'harga_modal_default'=>450000,'harga_jual_default'=>600000,'status'=>'ready'],
    ['kode_unit'=>'XEN-01','nama_unit'=>'Daihatsu Xenia','nopol'=>'BK1006RN','merek'=>'Daihatsu','model'=>'Xenia 1.3 R','jenis'=>'MPV','transmisi'=>'matic','kapasitas'=>7,'pemilik'=>'sendiri','partner_id'=>null,'harga_modal_default'=>220000,'harga_jual_default'=>300000,'status'=>'ready'],
];

$partners = [
    ['nama'=>'Bali Happy','tipe'=>'vendor','hp'=>'0812-0000-0001','alamat'=>'Denpasar, Bali'],
    ['nama'=>'Jeri Sembiring','tipe'=>'owner_unit','hp'=>'0812-0000-0002','alamat'=>'Medan'],
];
$partnerBali = 0; $partnerJeri = 0;

$drivers = [
    ['nama'=>'Ade Putra','hp'=>'0812-1000-0011','wilayah'=>'Medan','nomor_sim'=>'SIM A','status'=>'aktif'],
    ['nama'=>'Budi Santoso','hp'=>'0812-1000-0012','wilayah'=>'Medan','nomor_sim'=>'SIM A','status'=>'aktif'],
    ['nama'=>'Candra Wijaya','hp'=>'0812-1000-0013','wilayah'=>'Medan','nomor_sim'=>'SIM B1','status'=>'aktif'],
    ['nama'=>'Deni Kurniawan','hp'=>'0812-1000-0014','wilayah'=>'Medan','nomor_sim'=>'SIM A','status'=>'aktif'],
    ['nama'=>'Eko Prasetyo','hp'=>'0812-1000-0015','wilayah'=>'Medan','nomor_sim'=>'SIM A','status'=>'aktif'],
    ['nama'=>'Fajar Ramadhan','hp'=>'0812-1000-0016','wilayah'=>'Medan','nomor_sim'=>'SIM B1','status'=>'aktif'],
];

$customers = [
    ['tipe'=>'perusahaan','nama_pesanan'=>'PT Bank Mandiri (Persero) Tbk','nama_pic'=>'Ibu Rina','hp_pic'=>'0811-2000-0001','sumber'=>'wa','status'=>'tetap'],
    ['tipe'=>'perusahaan','nama_pesanan'=>'PT Pertamina Patra Niaga','nama_pic'=>'Bpk Hendra','hp_pic'=>'0811-2000-0002','sumber'=>'wa','status'=>'tetap'],
    ['tipe'=>'perorangan','nama_pesanan'=>'Bpk. Zulkifli','nama_pic'=>'Zulkifli','hp_pic'=>'0811-2000-0003','sumber'=>'wa','status'=>'tetap'],
    ['tipe'=>'perorangan','nama_pesanan'=>'Ibu Sari','nama_pic'=>'Sari','hp_pic'=>'0811-2000-0004','sumber'=>'telepon','status'=>'baru'],
    ['tipe'=>'instansi','nama_pesanan'=>'Dinas Pendidikan Kota Medan','nama_pic'=>'Bpk Rahmat','hp_pic'=>'0811-2000-0005','sumber'=>'wa','status'=>'tetap'],
    ['tipe'=>'perusahaan','nama_pesanan'=>'PT Agung Sedayu Group','nama_pic'=>'Bpk Toni','hp_pic'=>'0811-2000-0006','sumber'=>'website','status'=>'tetap'],
];

$includes = [
    ['nama'=>'Driver (all-in)','urutan'=>1,'is_default'=>1],
    ['nama'=>'BBM (full)','urutan'=>2,'is_default'=>0],
    ['nama'=>'Toll & parkir','urutan'=>3,'is_default'=>0],
    ['nama'=>'Antar-jemput bandara','urutan'=>4,'is_default'=>0],
];

$partnerBali = insRow($db, 'partners', $partners[0]);
$partnerJeri = insRow($db, 'partners', $partners[1]);

$unitId = [];   // nama_unit => id
foreach ($units as $u) {
    if ($u['nama_unit'] === 'Toyota Innova Zenix') $u['partner_id'] = $partnerBali;
    $unitId[$u['nama_unit']] = insRow($db, 'units', $u);
}

$driverId = []; // nama => id
foreach ($drivers as $d) $driverId[$d['nama']] = insRow($db, 'drivers', $d);

$custId = [];   // nama_pesanan => id
foreach ($customers as $c) $custId[$c['nama_pesanan']] = insRow($db, 'customers', $c);

foreach ($includes as $i) insRow($db, 'includes', $i);

/* ---------- 3. PESANAN DUMMY ---------- */
/* [status, customer, unit, driver, tgl_mulai, tgl_finish, tujuan, keterangan] */
$pesanan = [
    ['draft',      'Bpk. Zulkifli',               'Toyota Avanza',        null,               '2026-10-10', '2026-10-12', 'Berastagi', 'Rencana keluarga'],
    ['booked',     'PT Bank Mandiri (Persero) Tbk','Toyota Hiace Premio', 'Ade Putra',        '2026-10-05', '2026-10-08', 'Kuala Namu', 'Jemput tamu bank'],
    ['booked',     'Ibu Sari',                    'Daihatsu Xenia',       'Budi Santoso',     '2026-10-03', '2026-10-05', 'Danau Toba', 'Liburan'],
    ['in_trip',    'PT Bank Mandiri (Persero) Tbk','Toyota Alphard',      'Candra Wijaya',   '2026-09-30', '2026-10-02', 'Medan - Aceh', 'Perjalanan dinas'],
    ['in_trip',    'Dinas Pendidikan Kota Medan', 'Toyota Innova Zenix',  'Deni Kurniawan',  '2026-10-01', '2026-10-03', 'Sibolangit', 'Kunjungan sekolah'],
    ['completed',  'PT Agung Sedayu Group',       'Toyota Innova Reborn','Eko Prasetyo',    '2026-09-22', '2026-09-24', 'Medan - Pekanbaru', 'Proyek'],
    ['invoiced',   'PT Pertamina Patra Niaga',    'Toyota Hiace Premio', 'Fajar Ramadhan',  '2026-09-28', '2026-09-30', 'Medan - Siantar', 'Pelatihan'],
    ['paid',       'Dinas Pendidikan Kota Medan', 'Toyota Innova Reborn','Ade Putra',        '2026-09-15', '2026-09-17', 'Medan - Balige', 'Dinas'],
    ['completed',  'Bpk. Zulkifli',               'Toyota Avanza',        'Budi Santoso',     '2026-09-08', '2026-09-10', 'Tebing Tinggi', 'Keluarga'],
    ['cancelled',  'Ibu Sari',                    'Daihatsu Xenia',       null,               '2026-10-12', '2026-10-14', 'Danau Toba', 'Dibatalkan'],
];

$harga = []; // unit => [modal/hari, jual/hari]
$unitHarga = $db->query("SELECT id, nama_unit, harga_modal_default, harga_jual_default FROM units")->fetch_all(MYSQLI_ASSOC);
foreach ($unitHarga as $uh) {
    $harga[$uh['nama_unit']] = [(int)$uh['harga_modal_default'], (int)$uh['harga_jual_default']];
}

$orderIdByIndex = [];
foreach ($pesanan as $idx => $p) {
    [$status, $cust, $unitNama, $driver, $mulai, $selesai, $tujuan, $ket] = $p;
    $nomor = nomorDokumen('order');

    $tglMulai = strtotime($mulai);
    $tglSelesai = strtotime($selesai);
    $hari = (int) round(($tglSelesai - $tglMulai) / 86400) + 1;

    $custRow = $db->query("SELECT * FROM customers WHERE nama_pesanan = '" . $db->real_escape_string($cust) . "' LIMIT 1")->fetch_assoc();
    $tipe = $custRow['tipe'] === 'perorangan' ? 'retail' : ($custRow['tipe'] === 'RO' ? 'RO' : 'corporate');

    $oid = insRow($db, 'orders', [
        'nomor_order' => $nomor,
        'customer_id' => $custRow['id'],
        'tipe_pelanggan' => $tipe,
        'wilayah_pelayanan' => 'dalam_kota',
        'kota' => 'Medan',
        'tgl_mulai' => $mulai,
        'tgl_finish' => $selesai,
        'jumlah_hari' => $hari,
        'tujuan' => $tujuan,
        'nama_pesanan' => $cust,
        'nama_pic' => $custRow['nama_pic'],
        'hp_pic' => $custRow['hp_pic'],
        'sumber' => $custRow['sumber'],
        'status' => $status,
        'keterangan' => $ket,
        'created_by' => 1,
    ]);

    [$modalHari, $jualHari] = $harga[$unitNama];
    $subModal = $modalHari * $hari;
    $subJual = $jualHari * $hari;

    $item = [
        'order_id' => $oid,
        'unit_id' => $unitId[$unitNama],
        'driver_id' => $driver ? $driverId[$driver] : null,
        'nama_unit' => $unitNama,
        'nopol' => $db->query("SELECT nopol FROM units WHERE id = " . (int)$unitId[$unitNama])->fetch_assoc()['nopol'],
        'nama_driver' => $driver,
        'hp_driver' => $driver ? $db->query("SELECT hp FROM drivers WHERE id = " . (int)$driverId[$driver])->fetch_assoc()['hp'] : null,
        'harga_modal_per_hari' => $modalHari,
        'harga_jual_per_hari' => $jualHari,
        'jumlah_hari' => $hari,
        'subtotal_modal' => $subModal,
        'subtotal_jual' => $subJual,
    ];
    insRow($db, 'order_items', $item);

    hitungOrder($oid);

    // lengkapi laba / insentif (2,75%) / laba bersih
    $o = $db->query("SELECT grand_total, total_modal FROM orders WHERE id = " . (int)$oid)->fetch_assoc();
    $laba = (int)$o['grand_total'] - (int)$o['total_modal'];
    $insentif = (int) round($laba * 0.0275);
    $bersih = $laba - $insentif;
    $db->query("UPDATE orders SET laba=$laba, insentif=$insentif, laba_bersih=$bersih WHERE id=" . (int)$oid);

    insRow($db, 'status_logs', [
        'order_id' => $oid,
        'status_lama' => 'draft',
        'status_baru' => $status,
        'catatan' => 'Data dummy (seed)',
        'oleh' => 'Admin',
    ]);

    $orderIdByIndex[$idx] = $oid;
}

/* ---------- 4. INVOICE untuk pesanan invoiced & paid ---------- */
function buatInvoice($db, int $orderId, string $statusInv): int {
    $o = $db->query("SELECT o.*, i.nama_unit, i.nopol, i.nama_driver, i.harga_jual_per_hari, i.jumlah_hari, i.subtotal_jual
                     FROM orders o JOIN order_items i ON i.order_id = o.id WHERE o.id = " . (int)$orderId . " LIMIT 1")->fetch_assoc();
    $nomor = nomorDokumen('invoice');
    $sisa = $statusInv === 'lunas' ? 0 : (int)$o['grand_total'];
    $invId = insRow($db, 'invoices', [
        'order_id' => $orderId,
        'nomor_invoice' => $nomor,
        'nomor_revisi_ke' => 0,
        'tanggal_invoice' => date('Y-m-d'),
        'jatuh_tempo' => date('Y-m-d', strtotime('+7 days')),
        'total' => $o['grand_total'],
        'dp' => 0,
        'sisa' => $sisa,
        'status' => $statusInv,
        'customer_snapshot' => $o['nama_pesanan'],
        'order_snapshot' => $o['nomor_order'],
        'issued_at' => date('Y-m-d H:i:s'),
        'issued_by' => 1,
    ]);
    insRow($db, 'invoice_items', [
        'invoice_id' => $invId,
        'no' => 1,
        'keterangan' => $o['nama_unit'] . ' (' . $o['nopol'] . ')',
        'driver' => $o['nama_driver'],
        'tanggal_pakai' => $o['tgl_mulai'] . ' s/d ' . $o['tgl_finish'],
        'rute' => 'Medan - ' . $o['tujuan'],
        'harga_hari' => $o['harga_jual_per_hari'],
        'total_hari' => $o['jumlah_hari'],
        'total_harga' => $o['subtotal_jual'],
    ]);
    return $invId;
}

// pesanan invoiced (index 6) -> status 'invoiced', invoice terbit
$inv1 = buatInvoice($db, $orderIdByIndex[6], 'terbit');

// pesanan paid (index 7) -> status 'paid', invoice lunas + pembayaran
$inv2 = buatInvoice($db, $orderIdByIndex[7], 'lunas');
$o7 = $db->query("SELECT grand_total FROM orders WHERE id = " . (int)$orderIdByIndex[7])->fetch_assoc();
insRow($db, 'payments', [
    'invoice_id' => $inv2,
    'tanggal_bayar' => '2026-09-20',
    'tipe' => 'pelunasan',
    'nominal' => $o7['grand_total'],
    'metode' => 'transfer',
    'bank' => 'BCA',
    'catatan' => 'Pelunasan (dummy)',
    'created_by' => 1,
]);

echo "SELESAI.\n";
echo "Unit: " . $db->query("SELECT COUNT(*) c FROM units")->fetch_assoc()['c'] . "\n";
echo "Driver: " . $db->query("SELECT COUNT(*) c FROM drivers")->fetch_assoc()['c'] . "\n";
echo "Pelanggan: " . $db->query("SELECT COUNT(*) c FROM customers")->fetch_assoc()['c'] . "\n";
echo "Partner: " . $db->query("SELECT COUNT(*) c FROM partners")->fetch_assoc()['c'] . "\n";
echo "Pesanan: " . $db->query("SELECT COUNT(*) c FROM orders")->fetch_assoc()['c'] . "\n";
echo "Invoice: " . $db->query("SELECT COUNT(*) c FROM invoices")->fetch_assoc()['c'] . "\n";
echo "Pembayaran: " . $db->query("SELECT COUNT(*) c FROM payments")->fetch_assoc()['c'] . "\n";
