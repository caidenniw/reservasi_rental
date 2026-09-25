<?php
// Uji 3 FIX: simulasi end-to-end order RTR -> invoice -> DP otomatis -> pelunasan
// Jalankan: php C:/laragon/www/rentalnusantara/tests/uji_3_e2e.php
require_once 'C:/laragon/www/rentalnusantara/vendor/autoload.php';
require_once 'C:/laragon/www/rentalnusantara/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = 1;
$_SESSION['nama'] = 'Admin';
$_SESSION['username'] = 'admin';

$db = getDB();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

echo "=== UJI 3: End-to-End Order RTR -> Invoice -> DP -> Pelunasan ===\n";
echo "Waktu: ".date('Y-m-d H:i:s')." | DB: ".DB_NAME." @ ".DB_HOST."\n\n";

function uji_perbaruiInvoice(int $invoiceId, int $orderId): void {
    $db = getDB();
    $s = $db->prepare('SELECT COALESCE(SUM(nominal),0) dibayar FROM payments WHERE invoice_id = ?');
    $s->bind_param('i', $invoiceId); $s->execute();
    $dibayar = (int) $s->get_result()->fetch_assoc()['dibayar'];
    $s = $db->prepare('SELECT total FROM invoices WHERE id = ?');
    $s->bind_param('i', $invoiceId); $s->execute();
    $total = (int) $s->get_result()->fetch_assoc()['total'];
    $sisa = max(0, $total - $dibayar);
    $statusInv = ($dibayar <= 0) ? 'terbit' : ($sisa === 0 ? 'lunas' : 'sebagian');
    $u = $db->prepare('UPDATE invoices SET dp = ?, sisa = ?, status = ? WHERE id = ?');
    $u->bind_param('iisi', $dibayar, $sisa, $statusInv, $invoiceId); $u->execute();
    if ($statusInv === 'lunas') {
        $s = $db->prepare('SELECT status FROM orders WHERE id = ?'); $s->bind_param('i',$orderId); $s->execute();
        $lama = $s->get_result()->fetch_assoc()['status'] ?? '';
        if ($lama !== 'paid') {
            $u = $db->prepare('UPDATE orders SET status = "paid" WHERE id = ?'); $u->bind_param('i',$orderId); $u->execute();
            catatStatus($orderId, $lama, 'paid', 'Invoice lunas (uji E2E)');
        }
    }
}
function uji_terbitkanInvoice(array $order, int $revisiKe = 0, ?string $menggantikan = null): array {
    $db = getDB();
    $nomor = nomorDokumen('invoice');
    $hariJatuhTempo = (int) getSetting('invoice_jatuh_tempo_hari', '7');
    $tanggal = date('Y-m-d');
    $jatuhTempo = date('Y-m-d', strtotime('+' . $hariJatuhTempo . ' days'));
    $total = (int) $order['grand_total'];
    $catatan = 'Pesanan ' . $order['nomor_order'] . ' - ' . $order['nama_pesanan'];
    if ($menggantikan) $catatan .= ' | menggantikan ' . $menggantikan;
    $custSnap = json_encode(['nama'=>$order['nama_pesanan'],'pic'=>$order['nama_pic'],'hp'=>$order['hp_pic'],'alamat'=>$order['customer_alamat']??'','email'=>$order['customer_email']??''], JSON_UNESCAPED_UNICODE);
    $orderSnap = json_encode(['nomor_order'=>$order['nomor_order'],'kota'=>$order['kota'],'wilayah'=>$order['wilayah_pelayanan'],'tgl_mulai'=>$order['tgl_mulai'],'tgl_finish'=>$order['tgl_finish'],'jumlah_hari'=>$order['jumlah_hari'],'standby_point'=>$order['standby_point'],'flight'=>$order['flight'],'jam'=>$order['jam'],'jam_koordinasi'=>$order['jam_koordinasi'],'partner'=>$order['partner_nama']??'','include'=>implode('+', array_map(fn($x)=>$x['nama'],$order['includes']))], JSON_UNESCAPED_UNICODE);
    $issuedBy = idUser();
    $orderId=(int)$order['id']; $nilaiDp=0; $statusInv='terbit'; $replacedBy=null;
    $fields=[['order_id',$orderId,'i'],['nomor_invoice',$nomor,'s'],['nomor_revisi_ke',$revisiKe,'i'],['tanggal_invoice',$tanggal,'s'],['jatuh_tempo',$jatuhTempo,'s'],['total',$total,'i'],['dp',$nilaiDp,'i'],['sisa',$total,'i'],['status',$statusInv,'s'],['customer_snapshot',$custSnap,'s'],['order_snapshot',$orderSnap,'s'],['catatan',$catatan,'s'],['issued_by',$issuedBy,'i'],['replaced_by',$replacedBy,'s']];
    $kol=[]; $types=''; $vals=[];
    foreach($fields as $fl){ $kol[]='`'.$fl[0].'`'; $types.=$fl[2]; $vals[]=$fl[1]; }
    $kol[]='issued_at';
    $sql='INSERT INTO invoices ('.implode(', ',$kol).') VALUES ('.implode(', ', array_fill(0,count($kol)-1,'?')).', NOW())';
    $st=$db->prepare($sql); $st->bind_param($types, ...$vals); $st->execute();
    $invoiceId=(int)$db->insert_id;
    $ins=$db->prepare('INSERT INTO invoice_items (invoice_id, no, keterangan, driver, tanggal_pakai, rute, harga_hari, total_hari, total_harga) VALUES (?,?,?,?,?,?,?,?,?)');
    $urut=0; $incTeks=implode(' + ', array_map(fn($x)=>$x['nama'],$order['includes']));
    foreach($order['items'] as $it){
        $urut++; $keterangan=$it['nama_unit']."\n".$it['nopol']; if($incTeks!=='') $keterangan.="\nInclude: ".$incTeks;
        $driver=(string)($it['nama_driver']?:''); $tanggalFmt=formatRentang((string)$order['tgl_mulai'],(string)$order['tgl_finish']);
        $rute=labelWilayah($order['wilayah_pelayanan']).' '.$order['kota']; if(!empty($order['tujuan'])) $rute.="\n".$order['tujuan']; if(!empty($order['standby_point'])) $rute.="\nStandby: ".$order['standby_point'];
        $hargaHari=(int)$it['harga_jual_per_hari']; $hari=(int)$it['jumlah_hari']; $totalHarga=(int)$it['subtotal_jual'];
        $ins->bind_param('iissssiii', $invoiceId, $urut, $keterangan, $driver, $tanggalFmt, $rute, $hargaHari, $hari, $totalHarga); $ins->execute();
    }
    foreach($order['biaya'] as $b){
        $urut++; $keterangan=(string)$b['nama']; $driver=''; $tanggalFmt=''; $rute=''; $hargaHari=(int)$b['nominal']; $hari=1; $totalHarga=(int)$b['nominal'];
        $ins->bind_param('iissssiii', $invoiceId, $urut, $keterangan, $driver, $tanggalFmt, $rute, $hargaHari, $hari, $totalHarga); $ins->execute();
    }
    return ['id'=>$invoiceId,'nomor'=>$nomor];
}
function buatOrder(array $p): int {
    $db=getDB();
    // p: nomor, customerId, tipe, wilayah, kota, tglMulai, tglFinish, hari, jam, jamKoor, standby, flight, tujuan, namaPesanan, namaPic, hpPic, dataTamu, sumber, asalRaw, handleBy, partnerId, panjar, keterangan, status, catatan, createdBy
    $fields=[
        ['nomor_order',$p['nomor'],'s'],['customer_id',$p['customerId'],'i'],
        ['tipe_pelanggan',$p['tipe'],'s'],['wilayah_pelayanan',$p['wilayah'],'s'],
        ['kota',$p['kota'],'s'],['tgl_mulai',$p['tglMulai'],'s'],['tgl_finish',$p['tglFinish'],'s'],
        ['jumlah_hari',$p['hari'],'i'],['jam',$p['jam'],'s'],['jam_koordinasi',$p['jamKoor'],'i'],
        ['standby_point',$p['standby'],'s'],['flight',$p['flight'],'s'],['tujuan',$p['tujuan'],'s'],
        ['nama_pesanan',$p['namaPesanan'],'s'],['nama_pic',$p['namaPic'],'s'],['hp_pic',$p['hpPic'],'s'],
        ['data_tamu',$p['dataTamu'],'s'],['sumber',$p['sumber'],'s'],['asal_user_raw',$p['asalRaw'],'s'],
        ['handle_by',$p['handleBy'],'s'],['partner_id',$p['partnerId'],'i'],
        ['status',$p['status'],'s'],
        ['total_modal',0,'i'],['total_jual',0,'i'],['total_tambahan',0,'i'],['grand_total',0,'i'],['margin',0,'i'],
        ['panjar',$p['panjar'],'i'],['catatan',$p['catatan'],'s'],['keterangan',$p['keterangan'],'s'],['created_by',$p['createdBy'],'i'],
    ];
    $kol=[]; $types=''; $vals=[];
    foreach($fields as $fl){ $kol[]='`'.$fl[0].'`'; $types.=$fl[2]; $vals[]=$fl[1]; }
    $st=$db->prepare('INSERT INTO orders ('.implode(',',$kol).') VALUES ('.implode(',',array_fill(0,count($kol),'?')).')');
    $st->bind_param($types, ...$vals); $st->execute();
    return (int)$db->insert_id;
}
function assertEq($got,$exp,$msg){
    $ok=$got===$exp;
    echo ($ok?"  [PASS] ":"  [FAIL] ")."$msg => got=".json_encode($got,JSON_UNESCAPED_UNICODE)." expect=".json_encode($exp,JSON_UNESCAPED_UNICODE)."\n";
    return $ok;
}
function section($t){ echo "\n---- $t ----\n"; }

$unitRow = $db->query("SELECT id, nama_unit, nopol, harga_modal_default, harga_jual_default FROM units WHERE deleted_at IS NULL LIMIT 1")->fetch_assoc();
$driverRow = $db->query("SELECT id, nama FROM drivers WHERE deleted_at IS NULL LIMIT 1")->fetch_assoc();
if(!$unitRow || !$driverRow){ echo "Butuh minimal 1 unit & 1 driver\n"; exit(1); }
echo "Unit: {$unitRow['nama_unit']} {$unitRow['nopol']} (modal ".rupiah($unitRow['harga_modal_default'])." jual ".rupiah($unitRow['harga_jual_default']).")\n";
echo "Driver: {$driverRow['nama']} (id {$driverRow['id']})\n";

$cntOrderBefore = (int)($db->query("SELECT urut FROM doc_counters WHERE jenis='order' AND periode='".date('Y-m')."'")->fetch_assoc()['urut'] ?? 0);
$cntInvBefore   = (int)($db->query("SELECT urut FROM doc_counters WHERE jenis='invoice' AND periode='".date('Y-m')."'")->fetch_assoc()['urut'] ?? 0);
echo "Counter sebelum: order=$cntOrderBefore invoice=$cntInvBefore\n";
$createdOrderIds=[]; $createdInvoiceIds=[];

// ================= SKENARIO A =================
section("SKENARIO A: RTR (Rent to Rent) + Panjar 1jt — Boavista style 3 hari");
$ts = date('His');
$namaPesananA = "UJI-RTR-Boavista-$ts";
$tglMulaiA = date('Y-m-d', strtotime('+7 days'));
$tglFinishA = date('Y-m-d', strtotime('+9 days'));
$hariA = hitungHari($tglMulaiA,$tglFinishA);
assertEq($hariA,3,"hitungHari $tglMulaiA s/d $tglFinishA");
$mapA = mapAsalUser('RTR');
echo "  Asal RTR => ".json_encode($mapA,JSON_UNESCAPED_UNICODE)." label=".labelTipePelanggan($mapA['tipe'])." / ".labelSumber($mapA['sumber'])."\n";
assertEq($mapA['tipe'],'RTR',"RTR tipe");
assertEq(labelTipePelanggan('RTR'),'RTR (Rent to Rent)',"label RTR");
$nomorA = nomorDokumen('order');
echo "  Nomor order: $nomorA\n";
$bentrok = cekBentrokUnit((int)$unitRow['id'], $tglMulaiA, $tglFinishA, 0);
echo "  Bentrok unit {$unitRow['nopol']} $tglMulaiA s/d $tglFinishA: ".(empty($bentrok)?"TIDAK BENTROK":"BENTROK")."\n";
$panjarA = 1000000;
$tipeCustA='instansi'; $namaPicA='Budi (PIC Boavista)'; $hpPicA='+6281234567890';
$db->begin_transaction();
try{
    $st=$db->prepare('INSERT INTO customers (tipe,nama_pesanan,nama_pic,hp_pic,sumber,status) VALUES (?,?,?,?,?, "baru")');
    $st->bind_param('sssss', $tipeCustA, $namaPesananA, $namaPicA, $hpPicA, $mapA['sumber']); $st->execute(); $custA=(int)$db->insert_id;
    $orderIdA = buatOrder(['nomor'=>$nomorA,'customerId'=>$custA,'tipe'=>$mapA['tipe'],'wilayah'=>'luar_kota','kota'=>'Medan','tglMulai'=>$tglMulaiA,'tglFinish'=>$tglFinishA,'hari'=>$hariA,'jam'=>'08:00','jamKoor'=>0,'standby'=>'Bandara Kualanamu','flight'=>'GA-123','tujuan'=>'Medan - L. Pakam','namaPesanan'=>$namaPesananA,'namaPic'=>$namaPicA,'hpPic'=>$hpPicA,'dataTamu'=>'Imigrasi (Tamu Dinas X) — internal','sumber'=>$mapA['sumber'],'asalRaw'=>'RTR','handleBy'=>'Admin','partnerId'=>null,'panjar'=>$panjarA,'keterangan'=>'UJI-RTR','status'=>'booked','catatan'=>'UJI E2E A','createdBy'=>idUser()]);
    $hargaModalA=(int)($unitRow['harga_modal_default']?:850000); $hargaJualA=(int)($unitRow['harga_jual_default']?:1200000);
    $subModalA=$hargaModalA*$hariA; $subJualA=$hargaJualA*$hariA;
    $st=$db->prepare('INSERT INTO order_items (order_id,unit_id,driver_id,partner_id,nama_unit,nopol,upgrade,nama_driver,hp_driver,harga_modal_per_hari,harga_jual_per_hari,jumlah_hari,subtotal_modal,subtotal_jual,catatan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $drvIdA=(int)$driverRow['id']; $nmDrA=$driverRow['nama']; $hpDrA=''; $upA='Up Reborn'; $catA=''; $partnerNull=null;
    $st->bind_param('iiiisssssiiiiis', $orderIdA, $unitRow['id'], $drvIdA, $partnerNull, $unitRow['nama_unit'], $unitRow['nopol'], $upA, $nmDrA, $hpDrA, $hargaModalA, $hargaJualA, $hariA, $subModalA, $subJualA, $catA); $st->execute();
    $st=$db->prepare('INSERT INTO order_biaya (order_id,nama,nominal) VALUES (?,?,?)');
    $nmB='Overtime 2 jam'; $nomB=200000; $st->bind_param('isi', $orderIdA, $nmB, $nomB); $st->execute();
    $db->commit(); echo "  Order A id=$orderIdA cust=$custA\n"; $createdOrderIds[]=$orderIdA;
}catch(Throwable $e){ $db->rollback(); echo "  GAGAL A: ".$e->getMessage()."\n"; exit(1); }
$hitA = hitungOrder($orderIdA);
echo "  hitung A grand=".rupiah($hitA['grand_total'])." margin=".rupiah($hitA['margin'])."\n";
assertEq($hitA['grand_total'], $hargaJualA*$hariA + 200000, "grand A");
$orderA = ambilOrder($orderIdA);
echo "  order A tipe=".$orderA['tipe_pelanggan']." panjar=".rupiah($orderA['panjar'])." data_tamu='".$orderA['data_tamu']."' upgrade='".$orderA['items'][0]['upgrade']."'\n";
assertEq($orderA['tipe_pelanggan'],'RTR',"tipe RTR tersimpan");
section("Terbitkan Invoice A (DP otomatis dari panjar)");
$invA = uji_terbitkanInvoice($orderA); $createdInvoiceIds[]=$invA['id'];
echo "  Invoice A {$invA['nomor']} id={$invA['id']}\n";
$panjarEf = min($panjarA, (int)$orderA['grand_total']);
$cek=$db->prepare('SELECT COUNT(*) c FROM payments WHERE invoice_id=? AND catatan="Panjar awal dari form pesanan"'); $cek->bind_param('i',$invA['id']); $cek->execute(); $sudah=(int)$cek->get_result()->fetch_assoc()['c'];
if($sudah===0){
    $tglBayar=date('Y-m-d'); $tipeP='dp'; $metodeP='transfer'; $bankP=''; $buktiP=''; $catP='Panjar awal dari form pesanan'; $olehP=idUser();
    $ins=$db->prepare('INSERT INTO payments (invoice_id,tanggal_bayar,tipe,nominal,metode,bank,bukti_path,catatan,created_by) VALUES (?,?,?,?,?,?,?,?,?)');
    $ins->bind_param('ississssi', $invA['id'], $tglBayar, $tipeP, $panjarEf, $metodeP, $bankP, $buktiP, $catP, $olehP); $ins->execute();
    echo "  DP panjar ".rupiah($panjarEf)." tercatat\n";
}
uji_perbaruiInvoice($invA['id'], $orderIdA);
$db->query("UPDATE orders SET status='invoiced' WHERE id=$orderIdA AND status NOT IN ('paid','reported')");
catatStatus($orderIdA, $orderA['status'], 'invoiced', 'Invoice A terbit (uji): '.$invA['nomor']);
$invRowA = $db->query("SELECT total, dp, sisa, status FROM invoices WHERE id={$invA['id']}")->fetch_assoc();
echo "  Invoice A: dp=".rupiah($invRowA['dp'])." sisa=".rupiah($invRowA['sisa'])." status=".$invRowA['status']."\n";
assertEq((int)$invRowA['dp'],1000000,"DP A 1jt");
assertEq($invRowA['status'],'sebagian',"A sebagian");
section("Pelunasan sisa A");
$sisaA=(int)$invRowA['sisa'];
$st=$db->prepare('INSERT INTO payments (invoice_id,tanggal_bayar,tipe,nominal,metode,bank,bukti_path,catatan,created_by) VALUES (?,?,?,?,?,?,?,?,?)');
$tglL=date('Y-m-d'); $tipeL='pelunasan'; $metL='transfer'; $bankL='BCA'; $buktiL=''; $catL='Pelunasan sisa A (uji)'; $olehL=idUser();
$st->bind_param('ississssi', $invA['id'], $tglL, $tipeL, $sisaA, $metL, $bankL, $buktiL, $catL, $olehL); $st->execute();
echo "  Pelunasan ".rupiah($sisaA)." tercatat\n";
uji_perbaruiInvoice($invA['id'], $orderIdA);
$invRowA2=$db->query("SELECT dp, sisa, status FROM invoices WHERE id={$invA['id']}")->fetch_assoc();
$orderA2=$db->query("SELECT status FROM orders WHERE id=$orderIdA")->fetch_assoc();
echo "  Setelah lunas: dp=".rupiah($invRowA2['dp'])." sisa=".rupiah($invRowA2['sisa'])." inv=".$invRowA2['status']." order=".$orderA2['status']."\n";
assertEq((int)$invRowA2['sisa'],0,"sisa 0");
assertEq($invRowA2['status'],'lunas',"inv lunas");
assertEq($orderA2['status'],'paid',"order paid");

// ================= SKENARIO B =================
section("SKENARIO B: Corporate (CO) tanpa panjar — 2 hari");
$namaPesananB = "UJI-CORP-PT Guthrie-$ts";
$tglMulaiB=date('Y-m-d', strtotime('+10 days')); $tglFinishB=date('Y-m-d', strtotime('+11 days')); $hariB=hitungHari($tglMulaiB,$tglFinishB);
$mapB=mapAsalUser('CO'); echo "  CO => ".json_encode($mapB,JSON_UNESCAPED_UNICODE)."\n"; assertEq($mapB['tipe'],'corporate',"CO corporate");
$nomorB=nomorDokumen('order'); echo "  Nomor B: $nomorB\n";
$db->begin_transaction();
try{
    $st=$db->prepare('INSERT INTO customers (tipe,nama_pesanan,nama_pic,hp_pic,sumber,status) VALUES (?,?,?,?,?, "baru")');
    $tipeCustB='instansi'; $namaPicB='Pak Agus'; $hpPicB='+628123450000'; $sumberB=$mapB['sumber'];
    $st->bind_param('sssss', $tipeCustB, $namaPesananB, $namaPicB, $hpPicB, $sumberB); $st->execute(); $custB=(int)$db->insert_id;
    $orderIdB=buatOrder(['nomor'=>$nomorB,'customerId'=>$custB,'tipe'=>$mapB['tipe'],'wilayah'=>'luar_kota','kota'=>'Deli Serdang','tglMulai'=>$tglMulaiB,'tglFinish'=>$tglFinishB,'hari'=>$hariB,'jam'=>'07:00','jamKoor'=>0,'standby'=>'Kantor Bupati','flight'=>'','tujuan'=>'Lubuk Pakam','namaPesanan'=>$namaPesananB,'namaPic'=>$namaPicB,'hpPic'=>$hpPicB,'dataTamu'=>'Rombongan Dinas','sumber'=>$sumberB,'asalRaw'=>'CO','handleBy'=>'Admin','partnerId'=>null,'panjar'=>0,'keterangan'=>'UJI-CORP','status'=>'booked','catatan'=>'UJI E2E B','createdBy'=>idUser()]);
    $hargaModalB=900000; $hargaJualB=1200000; $subMB=$hargaModalB*$hariB; $subJB=$hargaJualB*$hariB;
    $st=$db->prepare('INSERT INTO order_items (order_id,unit_id,driver_id,partner_id,nama_unit,nopol,upgrade,nama_driver,hp_driver,harga_modal_per_hari,harga_jual_per_hari,jumlah_hari,subtotal_modal,subtotal_jual,catatan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $catB=''; $hpDrB=''; $upB=''; $partnerNull=null;
    $st->bind_param('iiiisssssiiiiis', $orderIdB, $unitRow['id'], $driverRow['id'], $partnerNull, $unitRow['nama_unit'], $unitRow['nopol'], $upB, $driverRow['nama'], $hpDrB, $hargaModalB, $hargaJualB, $hariB, $subMB, $subJB, $catB); $st->execute();
    $db->commit(); echo "  Order B id=$orderIdB\n"; $createdOrderIds[]=$orderIdB;
}catch(Throwable $e){ $db->rollback(); echo "  GAGAL B: ".$e->getMessage()."\n"; exit(1); }
$hitB=hitungOrder($orderIdB); echo "  hitung B grand=".rupiah($hitB['grand_total'])."\n";
$orderB=ambilOrder($orderIdB);
$invB=uji_terbitkanInvoice($orderB); $createdInvoiceIds[]=$invB['id'];
echo "  Invoice B {$invB['nomor']} tanpa DP\n";
uji_perbaruiInvoice($invB['id'],$orderIdB);
$invRowB=$db->query("SELECT total, dp, sisa, status FROM invoices WHERE id={$invB['id']}")->fetch_assoc();
echo "  Invoice B: dp=".rupiah($invRowB['dp'])." sisa=".rupiah($invRowB['sisa'])." status=".$invRowB['status']."\n";
assertEq((int)$invRowB['dp'],0,"B dp 0");
$db->query("UPDATE orders SET status='invoiced' WHERE id=$orderIdB");
catatStatus($orderIdB,'booked','invoiced','Invoice B terbit (uji)');

// ================= SKENARIO C =================
section("SKENARIO C: Retail IG panjar = grand (lunas langsung) 1 hari");
$namaPesananC="UJI-RETAIL-IG-$ts"; $tglMulaiC=date('Y-m-d', strtotime('+14 days')); $tglFinishC=$tglMulaiC; $hariC=1;
$mapC=mapAsalUser('IG'); echo "  IG => ".json_encode($mapC,JSON_UNESCAPED_UNICODE)."\n"; assertEq($mapC['tipe'],'retail',"IG retail");
$nomorC=nomorDokumen('order'); echo "  Nomor C: $nomorC\n";
$db->begin_transaction();
try{
    $st=$db->prepare('INSERT INTO customers (tipe,nama_pesanan,nama_pic,hp_pic,sumber,status) VALUES (?,?,?,?,?, "baru")');
    $tipeCustC='perorangan'; $namaPicC='Dewi'; $hpPicC='+628199999999'; $sumberC=$mapC['sumber'];
    $st->bind_param('sssss', $tipeCustC, $namaPesananC, $namaPicC, $hpPicC, $sumberC); $st->execute(); $custC=(int)$db->insert_id;
    $orderIdC=buatOrder(['nomor'=>$nomorC,'customerId'=>$custC,'tipe'=>$mapC['tipe'],'wilayah'=>'dalam_kota','kota'=>'Medan','tglMulai'=>$tglMulaiC,'tglFinish'=>$tglFinishC,'hari'=>$hariC,'jam'=>'09:00','jamKoor'=>0,'standby'=>'Hotel','flight'=>'','tujuan'=>'Hotel Santika','namaPesanan'=>$namaPesananC,'namaPic'=>$namaPicC,'hpPic'=>$hpPicC,'dataTamu'=>'-','sumber'=>$sumberC,'asalRaw'=>'IG','handleBy'=>'Admin','partnerId'=>null,'panjar'=>0,'keterangan'=>'UJI-RETAIL','status'=>'booked','catatan'=>'UJI E2E C','createdBy'=>idUser()]);
    $hargaModalC=600000; $hargaJualC=850000; $subMC=$hargaModalC*$hariC; $subJC=$hargaJualC*$hariC;
    $st=$db->prepare('INSERT INTO order_items (order_id,unit_id,driver_id,partner_id,nama_unit,nopol,upgrade,nama_driver,hp_driver,harga_modal_per_hari,harga_jual_per_hari,jumlah_hari,subtotal_modal,subtotal_jual,catatan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $catC=''; $hpDrC=''; $upC='Avanza'; $partnerNull=null;
    $st->bind_param('iiiisssssiiiiis', $orderIdC, $unitRow['id'], $driverRow['id'], $partnerNull, $unitRow['nama_unit'], $unitRow['nopol'], $upC, $driverRow['nama'], $hpDrC, $hargaModalC, $hargaJualC, $hariC, $subMC, $subJC, $catC); $st->execute();
    $db->commit(); echo "  Order C id=$orderIdC\n"; $createdOrderIds[]=$orderIdC;
}catch(Throwable $e){ $db->rollback(); echo "  GAGAL C: ".$e->getMessage()."\n"; exit(1); }
$hitC=hitungOrder($orderIdC); $grandC=$hitC['grand_total']; echo "  hitung C grand=".rupiah($grandC)."\n";
$db->query("UPDATE orders SET panjar=$grandC WHERE id=$orderIdC");
$orderC=ambilOrder($orderIdC); echo "  Panjar diset = grand => ".rupiah($orderC['panjar'])."\n";
$invC=uji_terbitkanInvoice($orderC); $createdInvoiceIds[]=$invC['id']; echo "  Invoice C {$invC['nomor']}\n";
$panjarEfC=min((int)$orderC['panjar'], (int)$orderC['grand_total']);
$ins=$db->prepare('INSERT INTO payments (invoice_id,tanggal_bayar,tipe,nominal,metode,bank,bukti_path,catatan,created_by) VALUES (?,?,?,?,?,?,?,?,?)');
$tglP=date('Y-m-d'); $tipeP='dp'; $metP='transfer'; $bankP=''; $buktiP=''; $catP='Panjar awal dari form pesanan'; $olehP=idUser();
$ins->bind_param('ississssi', $invC['id'], $tglP, $tipeP, $panjarEfC, $metP, $bankP, $buktiP, $catP, $olehP); $ins->execute();
uji_perbaruiInvoice($invC['id'],$orderIdC);
$db->query("UPDATE orders SET status='invoiced' WHERE id=$orderIdC");
catatStatus($orderIdC,'booked','invoiced','Invoice C terbit lunas langsung (uji)');
$invRowC=$db->query("SELECT total, dp, sisa, status FROM invoices WHERE id={$invC['id']}")->fetch_assoc();
echo "  Invoice C: dp=".rupiah($invRowC['dp'])." sisa=".rupiah($invRowC['sisa'])." status=".$invRowC['status']."\n";
assertEq($invRowC['status'],'lunas',"C lunas langsung");

// Revisi A
section("Revisi Invoice A (perbaikan harga, pembayaran ikut pindah)");
$lamaInv=$db->query("SELECT id, nomor_invoice, nomor_revisi_ke FROM invoices WHERE id={$invA['id']}")->fetch_assoc();
$orderA3=ambilOrder($orderIdA);
$invA_rev=uji_terbitkanInvoice($orderA3, (int)$lamaInv['nomor_revisi_ke']+1, $lamaInv['nomor_invoice']);
echo "  Revisi: {$lamaInv['nomor_invoice']} -> {$invA_rev['nomor']}\n"; $createdInvoiceIds[]=$invA_rev['id'];
$db->query("UPDATE payments SET invoice_id={$invA_rev['id']} WHERE invoice_id={$lamaInv['id']}");
$db->query("UPDATE invoices SET status='batal', replaced_by='{$invA_rev['nomor']}' WHERE id={$lamaInv['id']}");
uji_perbaruiInvoice($invA_rev['id'], $orderIdA);
$revRow=$db->query("SELECT status, dp, sisa FROM invoices WHERE id={$invA_rev['id']}")->fetch_assoc();
echo "  Revisi A: status={$revRow['status']} dp=".rupiah($revRow['dp'])." sisa=".rupiah($revRow['sisa'])."\n";
$lamaRow=$db->query("SELECT status FROM invoices WHERE id={$lamaInv['id']}")->fetch_assoc();
echo "  Lama A status=".$lamaRow['status']." (batal)\n";

section("RINGKASAN UJI 3");
echo "Order: ".implode(', ',$createdOrderIds)."\n";
echo "Invoice: ".implode(', ',$createdInvoiceIds)."\n";
echo "Counter order ".($db->query("SELECT urut FROM doc_counters WHERE jenis='order' AND periode='".date('Y-m')."'")->fetch_assoc()['urut'])." invoice ".($db->query("SELECT urut FROM doc_counters WHERE jenis='invoice' AND periode='".date('Y-m')."'")->fetch_assoc()['urut'])."\n";

section("CLEANUP (hapus data uji, kembalikan counter)");
foreach($createdInvoiceIds as $iid){ $db->query("DELETE FROM payments WHERE invoice_id=$iid"); }
foreach($createdInvoiceIds as $iid){ $db->query("DELETE FROM invoice_items WHERE invoice_id=$iid"); $db->query("DELETE FROM invoices WHERE id=$iid"); }
foreach($createdOrderIds as $oid){
    $db->query("DELETE FROM order_biaya WHERE order_id=$oid");
    $db->query("DELETE FROM order_includes WHERE order_id=$oid");
    $db->query("DELETE FROM order_items WHERE order_id=$oid");
    $db->query("DELETE FROM status_logs WHERE order_id=$oid");
    $cid=$db->query("SELECT customer_id FROM orders WHERE id=$oid")->fetch_assoc()['customer_id'] ?? null;
    $db->query("DELETE FROM orders WHERE id=$oid");
    if($cid) $db->query("DELETE FROM customers WHERE id=$cid AND nama_pesanan LIKE 'UJI-%'");
}
$db->query("UPDATE doc_counters SET urut=$cntOrderBefore WHERE jenis='order' AND periode='".date('Y-m')."'");
$db->query("UPDATE doc_counters SET urut=$cntInvBefore WHERE jenis='invoice' AND periode='".date('Y-m')."'");
echo "  Counter dikembalikan ke $cntOrderBefore / $cntInvBefore\n";
echo "  Sisa UJI orders=".($db->query("SELECT COUNT(*) c FROM orders WHERE nama_pesanan LIKE 'UJI-%'")->fetch_assoc()['c'])."\n";
echo "\n=== UJI 3 SELESAI ===\n";
