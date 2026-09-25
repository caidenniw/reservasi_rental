<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();

// autoload PhpSpreadsheet jika ada
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PhpOffice\PhpSpreadsheet\IOFactory;

$judulHalaman = 'Import Excel (Orderan)';
$menuAktif = 'import';
include __DIR__ . '/../includes/header.php';

function excelTrim($v): string { return trim((string)($v ?? '')); }
function excelAngka($v): int {
    if ($v === null || $v === '') return 0;
    if (is_numeric($v)) return (int) round((float)$v);
    $s = (string)$v;
    if (trim($s) === '-' || trim($s) === '') return 0;
    // ambil angka, handle "Ovt 6 Jam 900rb" -> 900000?
    // untuk harga, ambil semua digit
    $s = preg_replace('/[^0-9]/', '', $s);
    return (int) ($s === '' ? 0 : $s);
}
function parseHargaJualPerHari($q, $sTotal, $hari, $tambahanTeks): int {
    $qVal = excelAngka($q);
    $sVal = excelAngka($sTotal);
    if ($hari <= 0) return $qVal > 0 ? $qVal : $sVal;
    if ($qVal <= 0) {
        // tidak ada harga jual, pakai total / hari
        return $sVal > 0 ? (int) round($sVal / $hari) : 0;
    }
    // deteksi apakah q adalah per-hari atau total
    // jika q*hari + tambahan ≈ sTotal (±15% toleransi) maka q = per-hari
    $tambahan = 0;
    if (trim((string)$tambahanTeks) !== '' && trim((string)$tambahanTeks) !== '-') {
        // coba ekstrak angka dari teks tambahan: "Ovt 6 Jam 900rb" -> 900000, "26 Jun Ovt 1 Jam 02 Juli Ovt 4 Jam Rp. 100rb/jam"
        // ambil angka terbesar yang mirip harga
        // sederhana: jika mengandung "Ovt" ambil angka setelahnya, kalau gagal ambil semua digit / hari
        if (preg_match_all('/([0-9][0-9\.\,]*)\s*(rb|ribu|jt|juta)?/i', (string)$tambahanTeks, $m)) {
            // ambil nilai terakhir yang besar (>100rb) sebagai nominal tambahan
            $kandidat = 0;
            foreach ($m[0] as $idx => $raw) {
                $num = (int) preg_replace('/[^0-9]/','',$m[1][$idx]);
                $sat = strtolower($m[2][$idx] ?? '');
                if ($sat === 'rb' || $sat === 'ribu') $num *= 1000;
                if ($sat === 'jt' || $sat === 'juta') $num *= 1000000;
                // jika satuan kosong tapi angka kecil (<5000) anggap jam, bukan rupiah -> skip
                if ($sat === '' && $num < 10000) continue;
                if ($num > $kandidat) $kandidat = $num;
            }
            if ($kandidat > 0) $tambahan = $kandidat;
            else $tambahan = excelAngka($tambahanTeks);
        } else {
            $tambahan = excelAngka($tambahanTeks);
        }
    }
    $expected = $qVal * $hari + $tambahan;
    if ($sVal > 0 && abs($expected - $sVal) < max(50000, $sVal * 0.15)) {
        return $qVal;
    }
    // jika q == sVal dan tambahan '-' maka q adalah total, bukan per-hari
    if ($sVal > 0 && $qVal === $sVal && $tambahan === 0) {
        return (int) round($qVal / $hari);
    }
    // fallback: jika q jauh lebih besar dari s/hari (mis q 10jt sedangkan s/hari 350rb) -> q adalah total
    $perHariDariTotal = (int) round($sVal / $hari);
    if ($qVal > 0 && $perHariDariTotal > 0 && $qVal > $perHariDariTotal * 3) {
        return $perHariDariTotal;
    }
    return $qVal;
}
function parseTanggalExcel($val, int $defaultYear = 2026): ?string {
    if ($val === null || $val === '') return null;
    if ($val instanceof DateTime) return $val->format('Y-m-d');
    // PhpSpreadsheet kadang jadi DateTime object sudah handled di atas, tapi ada juga float excel date
    if (is_numeric($val) && (float)$val > 30000 && (float)$val < 60000) {
        // excel serial date
        $base = new DateTime('1899-12-30');
        $base->modify('+' . (int)$val . ' days');
        return $base->format('Y-m-d');
    }
    $s = trim((string)$val);
    if ($s === '' || $s === '-') return null;
    // normalisasi: "01 Ags " -> "01 Ags", "25 Mei " -> "25 Mei"
    $s = preg_replace('/\s+/', ' ', $s);
    $mapBulan = [
        'jan'=>1,'januari'=>1,
        'feb'=>2,'februari'=>2,
        'mar'=>3,'maret'=>3,
        'apr'=>4,'april'=>4,
        'mei'=>5,
        'jun'=>6,'juni'=>6,
        'jul'=>7,'juli'=>7,
        'agu'=>8,'ags'=>8,'agustus'=>8,
        'sep'=>9,'sept'=>9,'september'=>9,
        'okt'=>10,'oktober'=>10,
        'nov'=>11,'november'=>11,
        'des'=>12,'desember'=>12,
    ];
    // coba format "28 Juni" atau "01 Ags" atau "25 Mei"
    if (preg_match('/^(\d{1,2})\s+([A-Za-z]+)$/u', $s, $m)) {
        $d = (int)$m[1];
        $bStr = strtolower($m[2]);
        $b = $mapBulan[$bStr] ?? null;
        if ($b) return sprintf('%04d-%02d-%02d', $defaultYear, $b, $d);
    }
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})$/', $s, $m)) {
        $d=(int)$m[1]; $mo=(int)$m[2]; $y=(int)$m[3];
        if ($y<100) $y+=2000;
        return sprintf('%04d-%02d-%02d', $y,$mo,$d);
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1],$m[2],$m[3]);
    }
    $ts = strtotime($s);
    if ($ts) return date('Y-m-d', $ts);
    return null;
}

$hasilPreview = [];
$ringkas = ['total'=>0,'siap'=>0,'lewati'=>0,'error'=>0];
$sheetName = 'Orderan';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_preview'])) {
    verifyCsrfToken();
    if (!isset($_FILES['xlsx']) || $_FILES['xlsx']['error'] !== UPLOAD_ERR_OK) {
        setFlash('danger', 'File Excel belum dipilih atau upload gagal.');
    } else {
        $tmp = $_FILES['xlsx']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['xlsx']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx' && $ext !== 'xls') {
            setFlash('danger', 'File harus .xlsx atau .xls.');
        } else {
            try {
                $ss = IOFactory::load($tmp);
                $ws = $ss->getSheetByName($sheetName) ?: $ss->getActiveSheet();
                $maxRow = $ws->getHighestDataRow();
                $maxRow = min($maxRow, 950);
                $previewCount = 0;
                for ($r=4; $r<=$maxRow; $r++) {
                    $keterangan = excelTrim($ws->getCell('A'.$r)->getCalculatedValue());
                    $handleBy   = excelTrim($ws->getCell('B'.$r)->getCalculatedValue());
                    $asalUser   = excelTrim($ws->getCell('C'.$r)->getCalculatedValue());
                    $unit       = excelTrim($ws->getCell('D'.$r)->getCalculatedValue());
                    $nopol      = excelTrim($ws->getCell('E'.$r)->getCalculatedValue());
                    $dataPemesan= excelTrim($ws->getCell('K'.$r)->getCalculatedValue());
                    $mulaiRaw   = $ws->getCell('M'.$r)->getCalculatedValue();
                    $finishRaw  = $ws->getCell('N'.$r)->getCalculatedValue();
                    if ($dataPemesan === '' && $unit === '' && $nopol === '') continue;
                    if ($dataPemesan === '') continue;
                    $ringkas['total']++;
                    $hariExcel = (int) excelAngka($ws->getCell('O'.$r)->getCalculatedValue());
                    $tglMulai = parseTanggalExcel($mulaiRaw);
                    $tglFinish= parseTanggalExcel($finishRaw);
                    $hariHitung = ($tglMulai && $tglFinish) ? hitungHari($tglMulai,$tglFinish) : $hariExcel;
                    $masalah = [];
                    if (!$tglMulai) $masalah[]='Mulai ?';
                    if (!$tglFinish) $masalah[]='Finish ?';
                    if (!$nopol) $masalah[]='Nopol ?';
                    if (!$unit) $masalah[]='Unit ?';
                    if ($masalah) $ringkas['error']++; else $ringkas['siap']++;
                    if ($previewCount < 25) {
                        $hasilPreview[] = [
                            'row'=>$r,'keterangan'=>$keterangan,'handle'=>$handleBy,'asal'=>$asalUser,
                            'unit'=>$unit,'nopol'=>$nopol,'pemesan'=>$dataPemesan,
                            'tamu'=>excelTrim($ws->getCell('L'.$r)->getCalculatedValue()),
                            'mulai'=>$tglMulai,'finish'=>$tglFinish,'hari'=>$hariHitung,'hariExcel'=>$hariExcel,
                            'panjar'=>excelAngka($ws->getCell('P'.$r)->getCalculatedValue()),
                            'q'=>excelTrim($ws->getCell('Q'.$r)->getCalculatedValue()),
                            'r'=>excelTrim($ws->getCell('R'.$r)->getCalculatedValue()),
                            'total'=>excelAngka($ws->getCell('S'.$r)->getCalculatedValue()),
                            'masalah'=>implode(', ',$masalah)
                        ];
                        $previewCount++;
                    }
                }
                // simpan file ke tmp untuk konfirmasi import
                $dest = sys_get_temp_dir() . '/import_orderan_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.xlsx';
                move_uploaded_file($tmp, $dest);
                // simpan path di session untuk langkah berikutnya
                $_SESSION['import_xlsx_path'] = $dest;
                $_SESSION['import_xlsx_sheet'] = $sheetName;
            } catch (Throwable $e) {
                setFlash('danger','Gagal baca Excel: '.$e->getMessage());
            }
        }
    }
}

$hasilImport = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_import'])) {
    verifyCsrfToken();
    $path = $_SESSION['import_xlsx_path'] ?? '';
    if (!$path || !file_exists($path)) {
        setFlash('danger','File preview sudah kadaluarsa. Upload ulang.');
    } else {
        try {
            $ss = IOFactory::load($path);
            $ws = $ss->getSheetByName($_SESSION['import_xlsx_sheet'] ?? 'Orderan') ?: $ss->getActiveSheet();
            $maxRow = min($ws->getHighestDataRow(), 950);
            $masuk=0; $lewati=0; $gagal=0;
            $logLines=[];
            for ($r=4; $r<=$maxRow; $r++) {
                $keterangan  = excelTrim($ws->getCell('A'.$r)->getCalculatedValue());
                $handleBy    = excelTrim($ws->getCell('B'.$r)->getCalculatedValue());
                $asalUserRaw = excelTrim($ws->getCell('C'.$r)->getCalculatedValue());
                $unitNama    = excelTrim($ws->getCell('D'.$r)->getCalculatedValue());
                $nopolRaw    = strtoupper(excelTrim($ws->getCell('E'.$r)->getCalculatedValue()));
                $asalUnit    = excelTrim($ws->getCell('F'.$r)->getCalculatedValue());
                $driverNamaRaw = excelTrim($ws->getCell('G'.$r)->getCalculatedValue());
                $rute        = excelTrim($ws->getCell('H'.$r)->getCalculatedValue());
                $upgrade     = excelTrim($ws->getCell('I'.$r)->getCalculatedValue());
                if ($upgrade==='-') $upgrade='';
                $includeRaw  = excelTrim($ws->getCell('J'.$r)->getCalculatedValue());
                $pemesan     = excelTrim($ws->getCell('K'.$r)->getCalculatedValue());
                $tamu        = excelTrim($ws->getCell('L'.$r)->getCalculatedValue());
                $mulaiRaw    = $ws->getCell('M'.$r)->getCalculatedValue();
                $finishRaw   = $ws->getCell('N'.$r)->getCalculatedValue();
                $hariExcel   = (int) excelAngka($ws->getCell('O'.$r)->getCalculatedValue());
                $panjar      = excelAngka($ws->getCell('P'.$r)->getCalculatedValue());
                $qRaw        = $ws->getCell('Q'.$r)->getCalculatedValue();
                $rRaw        = $ws->getCell('R'.$r)->getCalculatedValue();
                $totalRp     = excelAngka($ws->getCell('S'.$r)->getCalculatedValue());
                $modalUnit   = excelAngka($ws->getCell('T'.$r)->getCalculatedValue());
                $gajiDriver  = excelAngka($ws->getCell('U'.$r)->getCalculatedValue());
                $bbm         = excelAngka($ws->getCell('V'.$r)->getCalculatedValue());
                $tollParkir  = excelAngka($ws->getCell('W'.$r)->getCalculatedValue());
                $rpLain      = excelAngka($ws->getCell('X'.$r)->getCalculatedValue());
                $ketBiaya    = excelTrim($ws->getCell('Y'.$r)->getCalculatedValue());
                $totalPengeluaran = excelAngka($ws->getCell('Z'.$r)->getCalculatedValue());
                $statusBayar = excelTrim($ws->getCell('AD'.$r)->getCalculatedValue());
                $statusUnit  = excelTrim($ws->getCell('AE'.$r)->getCalculatedValue());

                if ($pemesan === '' && $unitNama === '' && $nopolRaw === '') continue;
                if ($pemesan === '') { $lewati++; continue; }

                $tglMulai = parseTanggalExcel($mulaiRaw);
                $tglFinish= parseTanggalExcel($finishRaw);
                if (!$tglMulai || !$tglFinish) { $gagal++; $logLines[]="Baris $r: tanggal tidak valid ($pemesan)"; continue; }
                if (!$nopolRaw || !$unitNama) { $gagal++; $logLines[]="Baris $r: unit/nopol kosong"; continue; }
                // normalisasi nopol
                $nopolRaw = preg_replace('/\s+/', '', $nopolRaw);
                $hari = hitungHari($tglMulai,$tglFinish);
                if ($hari < 1) $hari = max(1,$hariExcel);

                // cek duplikat sederhana: pemesan + nopol + tgl_mulai sudah ada?
                $dup = $db->prepare('SELECT id FROM orders WHERE nama_pesanan=? AND tgl_mulai=? AND deleted_at IS NULL LIMIT 1');
                // cek via join order_items nopol juga biar lebih akurat
                $dup2 = $db->prepare('SELECT o.id FROM orders o JOIN order_items i ON i.order_id=o.id WHERE o.nama_pesanan=? AND o.tgl_mulai=? AND i.nopol=? AND o.deleted_at IS NULL LIMIT 1');
                $dup2->bind_param('sss', $pemesan, $tglMulai, $nopolRaw);
                $dup2->execute();
                if ($dup2->get_result()->fetch_assoc()) { $lewati++; continue; }

                // mapping Asal User -> tipe + sumber
                $map = mapAsalUser($asalUserRaw);
                $tipePelanggan = $map['tipe'];
                $sumber = $map['sumber'];

                // kota + wilayah dari rute
                $kota = $rute;
                if (strpos($rute,' - ') !== false) $kota = trim(explode(' - ',$rute)[0]);
                if (strpos($rute,'-') !== false && $kota===$rute) {
                    $parts = explode('-',$rute);
                    $kota = trim($parts[0]);
                }
                $kota = mb_substr($kota,0,100);
                if ($kota==='') $kota='-';
                $wilayah = 'dalam_kota';
                $ruteLower = strtolower($rute);
                if (str_contains($ruteLower,'sumatera') || str_contains($ruteLower,'palembang') || str_contains($ruteLower,'tapanuli') || str_contains($ruteLower,'se') || str_contains($ruteLower,'luar')) {
                    // heuristic: kalau rute mengandung kota luar Medan / ada "Sumatera Utara" anggap luar_kota
                    if (str_contains($ruteLower,'sumatera') || str_contains($ruteLower,'palembang') || str_contains($ruteLower,'tapanuli') || str_contains($ruteLower,'brastagi') || str_contains($ruteLower,'sibolangit') || str_contains($ruteLower,'siantar')) $wilayah='luar_kota';
                }
                // simpel: jika kota mengandung "Medan" dan rute juga dalam kota saja -> dalam_kota, else luar_kota jika ada "Sumatera" etc
                // biarkan default dalam_kota, admin bisa koreksi di form

                // harga
                $hargaJualPerHari = parseHargaJualPerHari($qRaw, $totalRp, $hari, $rRaw);
                // modal per hari dari total pengeluaran (Z) yang lebih lengkap, fallback ke T
                $modalTotal = $totalPengeluaran > 0 ? $totalPengeluaran : $modalUnit;
                // jika totalPengeluaran 0 tapi ada rincian gaji/bbm/toll, jumlahkan
                if ($modalTotal <= 0 && ($gajiDriver+$bbm+$tollParkir+$rpLain) > 0) {
                    $modalTotal = $modalUnit + $gajiDriver + $bbm + $tollParkir + $rpLain;
                }
                $hargaModalPerHari = $hari>0 ? (int) round($modalTotal / $hari) : $modalTotal;

                // status mapping
                $statusUnitLower = strtolower(trim($statusUnit));
                $statusBayarLower= strtolower(trim($statusBayar));
                if ($statusUnitLower==='cancel' || $statusUnitLower==='batal') $statusOrder='cancelled';
                elseif ($statusBayarLower==='lunas') $statusOrder='paid';
                elseif ($statusUnitLower==='finish' || $statusUnitLower==='selesai') $statusOrder='completed';
                else $statusOrder='completed'; // historis default

                // partner / support by dari Asal Unit
                $partnerId = null;
                $asalUnitTrim = trim($asalUnit);
                if ($asalUnitTrim !== '' && strtolower($asalUnitTrim) !== '1000 rent' && strtolower($asalUnitTrim) !== '1000rent') {
                    $st = $db->prepare('SELECT id FROM partners WHERE LOWER(nama)=LOWER(?) AND deleted_at IS NULL LIMIT 1');
                    $st->bind_param('s', $asalUnitTrim);
                    $st->execute();
                    $prow = $st->get_result()->fetch_assoc();
                    if ($prow) $partnerId = (int)$prow['id'];
                    else {
                        $insP = $db->prepare('INSERT INTO partners (nama,status) VALUES (?,"aktif")');
                        $insP->bind_param('s', $asalUnitTrim);
                        $insP->execute();
                        $partnerId = (int)$db->insert_id;
                    }
                }

                // unit lookup / create
                $unitId = null;
                $st = $db->prepare('SELECT id, harga_modal_default, harga_jual_default FROM units WHERE nopol=? AND deleted_at IS NULL LIMIT 1');
                $st->bind_param('s', $nopolRaw);
                $st->execute();
                $urow = $st->get_result()->fetch_assoc();
                if ($urow) $unitId = (int)$urow['id'];
                else {
                    $kodeUnit = 'IMP-' . preg_replace('/[^A-Z0-9]/','',$nopolRaw);
                    $jenis='MPV'; $statusUnitDb='aktif';
                    $insU = $db->prepare('INSERT INTO units (kode_unit,nama_unit,nopol,jenis,pemilik,harga_modal_default,harga_jual_default,status) VALUES (?,?,?,?,?,?,?,?)');
                    $pemilik = $asalUnitTrim ?: '1000 Rent';
                    $insU->bind_param('sssssiis', $kodeUnit, $unitNama, $nopolRaw, $jenis, $pemilik, $hargaModalPerHari, $hargaJualPerHari, $statusUnitDb);
                    $insU->execute();
                    $unitId = (int)$db->insert_id;
                }

                // driver lookup / create
                $driverId = null;
                $driverNama = $driverNamaRaw;
                if ($driverNamaRaw !== '' && $driverNamaRaw !== '-') {
                    $st = $db->prepare('SELECT id, hp FROM drivers WHERE LOWER(nama)=LOWER(?) AND deleted_at IS NULL LIMIT 1');
                    $st->bind_param('s', $driverNamaRaw);
                    $st->execute();
                    $drow = $st->get_result()->fetch_assoc();
                    if ($drow) $driverId = (int)$drow['id'];
                    else {
                        $insD = $db->prepare('INSERT INTO drivers (nama,status) VALUES (?,"aktif")');
                        $insD->bind_param('s', $driverNamaRaw);
                        $insD->execute();
                        $driverId = (int)$db->insert_id;
                    }
                }

                // customer lookup / create
                $namaPic = '';
                // Data Tamu internal sudah di $tamu, PIC tidak ada terpisah di Excel -> pakai Data Tamu sebagai PIC jika perlu
                $st = $db->prepare('SELECT id FROM customers WHERE LOWER(nama_pesanan)=LOWER(?) AND deleted_at IS NULL LIMIT 1');
                $st->bind_param('s', $pemesan);
                $st->execute();
                $crow = $st->get_result()->fetch_assoc();
                if ($crow) $customerId = (int)$crow['id'];
                else {
                    $tipeCust = preg_match('/\b(PT|CV|UD|Dinas|Kantor|Badan|Otoritas|Bank|Universitas|Sekolah|Prov|Kab)\b/i', $pemesan) ? 'instansi' : 'perorangan';
                    $hpPicCust='';
                    $insC = $db->prepare('INSERT INTO customers (tipe,nama_pesanan,nama_pic,hp_pic,sumber,status) VALUES (?,?,?,?,?,"baru")');
                    $insC->bind_param('sssss', $tipeCust, $pemesan, $namaPic, $hpPicCust, $sumber);
                    $insC->execute();
                    $customerId = (int)$db->insert_id;
                }

                $nomor = nomorDokumen('order');
                $createdBy = idUser();
                $tujuan = $rute;
                $jam=''; $jamKoor=0; $standby=''; $flight='';
                $catatanOrder = '';
                if ($ketBiaya !== '' && $ketBiaya !== '-') $catatanOrder = $ketBiaya;
                if ($rRaw !== '' && $rRaw !== '-' && strlen($rRaw) < 200) {
                    $catatanOrder = trim($catatanOrder . ' | Tambahan: ' . $rRaw, ' |');
                }

                $db->begin_transaction();
                try {
                    $fields = [
                        ['nomor_order',$nomor,'s'],['customer_id',$customerId,'i'],
                        ['tipe_pelanggan',$tipePelanggan,'s'],['wilayah_pelayanan',$wilayah,'s'],
                        ['kota',$kota,'s'],['tgl_mulai',$tglMulai,'s'],['tgl_finish',$tglFinish,'s'],
                        ['jumlah_hari',$hari,'i'],['jam',$jam,'s'],['jam_koordinasi',$jamKoor,'i'],
                        ['standby_point',$standby,'s'],['flight',$flight,'s'],['tujuan',$tujuan,'s'],
                        ['nama_pesanan',$pemesan,'s'],['nama_pic',$namaPic,'s'],['hp_pic','','s'],
                        ['data_tamu',$tamu,'s'],['sumber',$sumber,'s'],['asal_user_raw',$asalUserRaw,'s'],
                        ['handle_by',$handleBy,'s'],['partner_id',null,'i'],['panjar',$panjar,'i'],
                        ['keterangan',$keterangan,'s'],['status',$statusOrder,'s'],['catatan',$catatanOrder,'s'],['created_by',$createdBy,'i'],
                    ];
                    $kol=[]; $types=''; $vals=[];
                    foreach($fields as $fl){ $kol[]='`'.$fl[0].'`'; $types.=$fl[2]; $vals[]=$fl[1]; }
                    $st = $db->prepare('INSERT INTO orders ('.implode(',',$kol).') VALUES ('.implode(',',array_fill(0,count($kol),'?')).')');
                    $st->bind_param($types, ...$vals);
                    $st->execute();
                    $orderId=(int)$db->insert_id;

                    $driverIdBind = $driverId;
                    $partnerIdBind = $partnerId;
                    $subModal = $hargaModalPerHari * $hari;
                    $subJual  = $hargaJualPerHari * $hari;
                    $catUnit = '';
                    $insItem = $db->prepare('INSERT INTO order_items (order_id,unit_id,driver_id,partner_id,nama_unit,nopol,upgrade,nama_driver,hp_driver,harga_modal_per_hari,harga_jual_per_hari,jumlah_hari,subtotal_modal,subtotal_jual,catatan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $insItem->bind_param('iiiisssssiiiiis', $orderId,$unitId,$driverIdBind,$partnerIdBind,$unitNama,$nopolRaw,$upgrade,$driverNama,'',$hargaModalPerHari,$hargaJualPerHari,$hari,$subModal,$subJual,$catUnit);
                    $insItem->execute();

                    // include
                    if ($includeRaw !== '' && $includeRaw !== '-') {
                        $parts = preg_split('/[+,;]/', $includeRaw);
                        foreach($parts as $pinc){
                            $pinc=trim($pinc);
                            if($pinc==='') continue;
                            // cari id include yang mirip
                            $like = '%'. $pinc .'%';
                            $st2=$db->prepare('SELECT id,nama FROM includes WHERE nama LIKE ? LIMIT 1');
                            $st2->bind_param('s',$like);
                            $st2->execute();
                            $incRow=$st2->get_result()->fetch_assoc();
                            if($incRow){
                                $iid=(int)$incRow['id']; $nm=$incRow['nama'];
                                $bi=0;
                                $insInc=$db->prepare('INSERT INTO order_includes (order_id,include_id,nama,biaya) VALUES (?,?,?,?)');
                                $insInc->bind_param('iisi',$orderId,$iid,$nm,$bi);
                                $insInc->execute();
                            } else {
                                $iid=null; $bi=0;
                                $insInc=$db->prepare('INSERT INTO order_includes (order_id,include_id,nama,biaya) VALUES (?,?,?,?)');
                                $insInc->bind_param('iisi',$orderId,$iid,$pinc,$bi);
                                $insInc->execute();
                            }
                        }
                    }
                    // biaya tambahan dari kolom R jika mengandung nominal
                    if ($rRaw !== '' && $rRaw !== '-') {
                        $nomTamb = 0;
                        // ekstrak nominal: jika R = "Ovt 6 Jam 900rb" -> 900000
                        if (preg_match('/([0-9][0-9\.,]*)\s*(rb|ribu|jt|juta)?/i', $rRaw, $m2)) {
                            // ambil angka terbesar sebagai nominal jika ada kata Ovt
                            if (stripos($rRaw,'ovt')!==false || stripos($rRaw,'overtime')!==false) {
                                // cari angka 900rb style
                                if (preg_match_all('/([0-9]+)\s*(rb|ribu)/i',$rRaw,$mm)) {
                                    $last = end($mm[1]); $nomTamb = (int)$last * 1000;
                                } else $nomTamb = excelAngka($rRaw);
                            }
                            // jika total masih 0 tapi R mengandung angka besar >10000 anggap nominal
                            if ($nomTamb===0) {
                                $cand = excelAngka($rRaw);
                                if ($cand>10000) $nomTamb=$cand;
                            }
                        }
                        if ($nomTamb>0) {
                            $namaB = 'Overtime / Tambahan (dari Excel)';
                            $insB=$db->prepare('INSERT INTO order_biaya (order_id,nama,nominal) VALUES (?,?,?)');
                            $insB->bind_param('isi',$orderId,$namaB,$nomTamb);
                            $insB->execute();
                        } elseif (strlen($rRaw) < 120) {
                            // simpan sebagai catatan saja sudah di atas
                        }
                    }
                    // jika totalRp tidak match dengan hitungan, tambahkan koreksi biaya
                    // hitung expected grand lalu selisih
                    $expectedGrand = $subJual;
                    // tambah biaya overtime yang sudah diinsert
                    $db->query("SELECT 1");
                    // biarkan hitungOrder yang hitung total_tambahan

                    $db->commit();
                    hitungOrder($orderId);
                    // panjar tidak langsung jadi payment di historis; biarkan status paid menandakan lunas.
                    // kalau panjar >0 dan status lunas, kita bisa anggap sudah lunas, tidak perlu invoice terpisah untuk histori.
                    catatStatus($orderId,null,$statusOrder,'Import XLSX baris '.$r.' ('.$pemesan.')');
                    $masuk++;
                } catch (Throwable $e) {
                    $db->rollback();
                    $gagal++; $logLines[]="Baris $r gagal: ".$e->getMessage();
                }
            }
            $hasilImport=['masuk'=>$masuk,'lewati'=>$lewati,'gagal'=>$gagal,'log'=>$logLines];
            @unlink($path);
            unset($_SESSION['import_xlsx_path']);
            setFlash('success', "Import selesai: $masuk masuk, $lewati dilewati (duplikat), $gagal gagal.");
        } catch (Throwable $e) {
            setFlash('danger','Gagal import: '.$e->getMessage());
        }
    }
}
?>

<div class="card-box">
    <h2 class="card-title">Import langsung dari Excel Juli 2026</h2>
    <p class="text-soft">Upload file <code>Rental Bulan Juli 2026.xlsx</code> sheet <b>Orderan</b>. Sistem membaca kolom KETERANGAN s/d Status Unit (31 kolom), mapping otomatis ke form kita (Data Tamu internal, Upgrade, Asal User, Panjar). Baris duplikat (pemesan+nopol+tgl_mulai) akan dilewati.</p>
    <?php if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')): ?>
        <div class="alert alert-danger">PhpSpreadsheet belum terpasang. Jalankan <code>composer install</code>.</div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="mt-3">
        <?= csrfField() ?>
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label">File Excel (.xlsx)</label>
                <input type="file" name="xlsx" accept=".xlsx,.xls" class="form-control" required>
                <div class="form-text">Sheet: Orderan, header di baris 2, data mulai baris 4.</div>
            </div>
            <div class="col-md-3">
                <button type="submit" name="aksi_preview" value="1" class="btn btn-outline-secondary btn-sm">Pratinjau 25 Baris</button>
            </div>
        </div>
    </form>
</div>

<?php if ($hasilPreview): ?>
<div class="card-box">
    <h3 class="card-title">Pratinjau (<?= count($hasilPreview) ?> baris pertama) — total terdeteksi <?= (int)$ringkas['total'] ?>, siap <?= (int)$ringkas['siap'] ?>, error <?= (int)$ringkas['error'] ?></h3>
    <div class="table-wrap">
        <table class="tabel">
            <thead><tr><th>Row</th><th>Pemesan</th><th>Unit/Nopol</th><th>Asal User</th><th>Mulai–Finish (hari)</th><th>Panjar</th><th>Total</th><th>Masalah</th></tr></thead>
            <tbody>
            <?php foreach($hasilPreview as $pr): ?>
                <tr>
                    <td><?= (int)$pr['row'] ?></td>
                    <td><?= e($pr['pemesan']) ?><?= $pr['tamu'] ? '<div class="text-soft">Tamu: '.e($pr['tamu']).'</div>':'' ?></td>
                    <td><?= e($pr['unit']) ?><div class="mono"><?= e($pr['nopol']) ?></div></td>
                    <td><?= e($pr['asal']) ?></td>
                    <td><?= e($pr['mulai'] ?? '-') ?> s/d <?= e($pr['finish'] ?? '-') ?> (<?= (int)$pr['hari'] ?>, excel <?= (int)$pr['hariExcel'] ?>)</td>
                    <td><?= $pr['panjar']? rupiah($pr['panjar']):'-' ?></td>
                    <td><?= $pr['total']? rupiah($pr['total']):'-' ?></td>
                    <td><?= e($pr['masalah'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form method="post" class="mt-3" data-konfirmasi="Import semua baris yang terdeteksi? Duplikat akan dilewati.">
        <?= csrfField() ?>
        <button type="submit" name="aksi_import" value="1" class="btn btn-primary btn-sm">Import Sekarang (<?= (int)$ringkas['total'] ?> baris)</button>
        <span class="text-soft ms-2">File sudah disimpan sementara — tidak perlu upload ulang.</span>
    </form>
</div>
<?php endif; ?>

<?php if ($hasilImport): ?>
<div class="card-box">
    <h3 class="card-title">Hasil Import</h3>
    <div class="alert alert-info">Masuk: <b><?= (int)$hasilImport['masuk'] ?></b> · Dilewati: <?= (int)$hasilImport['lewati'] ?> · Gagal: <?= (int)$hasilImport['gagal'] ?></div>
    <?php if ($hasilImport['log']): ?>
        <pre style="max-height:220px;overflow:auto;background:var(--bg-soft);padding:10px;border-radius:6px;font-size:12px"><?php foreach($hasilImport['log'] as $l) echo e($l)."\n"; ?></pre>
    <?php endif; ?>
    <a class="btn btn-sm btn-outline-secondary mt-2" href="<?= BASE_URL ?>/pages/pesanan_list.php">Lihat Data Pesanan</a>
</div>
<?php endif; ?>

<div class="card-box">
    <h3 class="card-title">Catatan mapping (best practice)</h3>
    <ul class="text-soft" style="margin:0;padding-left:18px;line-height:1.6">
        <li><b>KETERANGAN</b> → <code>keterangan</code> (opsional, mis Ketua Apkasi)</li>
        <li><b>Asal User</b> (RTR/Corp/RO/Apkasi/IG/Web/Bu Tika) → disimpan mentah di <code>asal_user_raw</code> + auto-map ke Tipe Pelanggan & Sumber (RTR sementara → corporate/lainnya, tanya reservasi untuk pastinya)</li>
        <li><b>Asal Unit</b> (Aksa/Kak Maria/Galih/1000 Rent) → <code>order_items.partner_id</code> (Support By per unit); 1000 Rent = armada sendiri</li>
        <li><b>Upgrade</b> (Up Reborn etc) → <code>order_items.upgrade</code></li>
        <li><b>Data Tamu</b> → <code>data_tamu</code> internal, tidak cetak invoice</li>
        <li><b>Harga Jual & Modal</b> → di Excel ada total & per-hari campur; importer deteksi otomatis (jika Q*hari+tambahan ≈ Total maka Q=per-hari, else Q=total/hari). Modal diambil dari TOTAL PENGELUARAN/hari.</li>
        <li><b>Panjar</b> → <code>orders.panjar</code>; saat <b>Terbitkan Invoice</b> otomatis jadi pembayaran DP — tidak perlu input dua kali.</li>
        <li><b>Status Bayar/Unit</b> → Lunas+Finish=paid, Cancel=batal, else completed (histori)</li>
    </ul>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
