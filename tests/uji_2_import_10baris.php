<?php
// Uji 2: simulasi import 10 baris Excel — dry run + insert skute + verifikasi
// Jalankan: php C:/laragon/www/rentalnusantara/tests/uji_2_import_10baris.php
require_once 'C:/laragon/www/rentalnusantara/vendor/autoload.php';
require_once 'C:/laragon/www/rentalnusantara/includes/functions.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;
$_SESSION['nama'] = $_SESSION['nama'] ?? 'Admin';

$path = 'D:/maganghub/New folder/Rental Bulan Juli 2026.xlsx';
if (!file_exists($path)) {
    $alt = 'D:\\maganghub\\New folder\\Rental Bulan Juli 2026.xlsx';
    $path = file_exists($alt) ? $alt : $path;
}
echo "=== UJI 2: Simulasi Import 10 Baris Excel (Orderan) ===\n";
echo "File: $path\n";
if (!file_exists($path)) {
    echo "File tidak ketemu, fallback cek C:/ ...\n";
    foreach (glob('D:/maganghub/**/*.xlsx') as $f) echo "  ketemu: $f\n";
    exit(1);
}

$db = getDB();

// helper yang sama dengan import_xlsx.php
function _excelTrim($v): string { return trim((string)($v ?? '')); }
function _excelAngka($v): int {
    if ($v === null || $v === '') return 0;
    if (is_numeric($v)) return (int) round((float)$v);
    $s = (string)$v;
    if (trim($s) === '-' || trim($s) === '') return 0;
    $s = preg_replace('/[^0-9]/', '', $s);
    return (int) ($s === '' ? 0 : $s);
}
function _parseTanggalExcel($val, int $defaultYear = 2026): ?string {
    if ($val === null || $val === '') return null;
    if ($val instanceof DateTime) return $val->format('Y-m-d');
    if (is_numeric($val) && (float)$val > 30000 && (float)$val < 60000) {
        $base = new DateTime('1899-12-30');
        $base->modify('+' . (int)$val . ' days');
        return $base->format('Y-m-d');
    }
    $s = trim((string)$val);
    if ($s === '' || $s === '-') return null;
    $s = preg_replace('/\s+/', ' ', $s);
    $mapBulan = ['jan'=>1,'januari'=>1,'feb'=>2,'februari'=>2,'mar'=>3,'maret'=>3,'apr'=>4,'april'=>4,'mei'=>5,'jun'=>6,'juni'=>6,'jul'=>7,'juli'=>7,'agu'=>8,'ags'=>8,'agustus'=>8,'sep'=>9,'sept'=>9,'september'=>9,'okt'=>10,'oktober'=>10,'nov'=>11,'november'=>11,'des'=>12,'desember'=>12];
    if (preg_match('/^(\d{1,2})\s+([A-Za-z]+)$/u', $s, $m)) {
        $d=(int)$m[1]; $bStr=strtolower($m[2]); $b=$mapBulan[$bStr] ?? null;
        if ($b) return sprintf('%04d-%02d-%02d', $defaultYear, $b, $d);
    }
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})$/', $s, $m)) {
        $d=(int)$m[1]; $mo=(int)$m[2]; $y=(int)$m[3]; if ($y<100) $y+=2000; return sprintf('%04d-%02d-%02d', $y,$mo,$d);
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) return sprintf('%04d-%02d-%02d', $m[1],$m[2],$m[3]);
    $ts=strtotime($s); if ($ts) return date('Y-m-d',$ts);
    return null;
}
function _parseHargaJualPerHari($q,$sTotal,$hari,$tambahanTeks): int {
    $qVal=_excelAngka($q); $sVal=_excelAngka($sTotal);
    if ($hari<=0) return $qVal>0?$qVal:$sVal;
    if ($qVal<=0) return $sVal>0?(int)round($sVal/$hari):0;
    $tambahan=0;
    if (trim((string)$tambahanTeks)!=='' && trim((string)$tambahanTeks)!=='-') {
        if (preg_match_all('/([0-9][0-9\.\,]*)\s*(rb|ribu|jt|juta)?/i',(string)$tambahanTeks,$m)) {
            $kandidat=0;
            foreach($m[0] as $idx=>$raw){ $num=(int)preg_replace('/[^0-9]/','',$m[1][$idx]); $sat=strtolower($m[2][$idx]??''); if($sat==='rb'||$sat==='ribu') $num*=1000; if($sat==='jt'||$sat==='juta') $num*=1000000; if($sat===''&&$num<10000) continue; if($num>$kandidat) $kandidat=$num; }
            if($kandidat>0) $tambahan=$kandidat; else $tambahan=_excelAngka($tambahanTeks);
        } else $tambahan=_excelAngka($tambahanTeks);
    }
    $expected=$qVal*$hari+$tambahan;
    if($sVal>0 && abs($expected-$sVal) < max(50000,$sVal*0.15)) return $qVal;
    if($sVal>0 && $qVal===$sVal && $tambahan===0) return (int)round($qVal/$hari);
    $perHari=(int)round($sVal/$hari);
    if($qVal>0 && $perHari>0 && $qVal > $perHari*3) return $perHari;
    return $qVal;
}

$ss = IOFactory::load($path);
$ws = $ss->getSheetByName('Orderan') ?: $ss->getActiveSheet();
echo "Sheet: ".$ws->getTitle()." maxRow=".$ws->getHighestDataRow()."\n";

// Header row 2 verifikasi
echo "\n--- Header row 2 (31 kolom) ---\n";
$hrow=[];
for($c=1;$c<=31;$c++){ $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c); $hrow[] = _excelTrim($ws->getCell($col.'2')->getValue()); }
echo implode(' | ', $hrow)."\n";

// Dry run 10 baris pertama yang punya Data Pemesan (mulai row 4)
echo "\n--- Dry-run 10 baris pertama (row 4+ yang isi Pemesan) ---\n";
$rows=[];
for($r=4;$r<=40 && count($rows)<10;$r++){
    $pemesan=_excelTrim($ws->getCell('K'.$r)->getCalculatedValue());
    $unit=_excelTrim($ws->getCell('D'.$r)->getCalculatedValue());
    $nopol=_excelTrim($ws->getCell('E'.$r)->getCalculatedValue());
    if($pemesan==='' && $unit==='' && $nopol==='') continue;
    if($pemesan==='') continue;
    $asal=_excelTrim($ws->getCell('C'.$r)->getCalculatedValue());
    $map=mapAsalUser($asal);
    $mulaiRaw=$ws->getCell('M'.$r)->getCalculatedValue();
    $finishRaw=$ws->getCell('N'.$r)->getCalculatedValue();
    $tglM=_parseTanggalExcel($mulaiRaw);
    $tglF=_parseTanggalExcel($finishRaw);
    $hariExcel=_excelAngka($ws->getCell('O'.$r)->getCalculatedValue());
    $hariHit = ($tglM && $tglF) ? hitungHari($tglM,$tglF) : $hariExcel;
    $panjar=_excelAngka($ws->getCell('P'.$r)->getCalculatedValue());
    $qVal=$ws->getCell('Q'.$r)->getCalculatedValue();
    $rVal=$ws->getCell('R'.$r)->getCalculatedValue();
    $total=_excelAngka($ws->getCell('S'.$r)->getCalculatedValue());
    $hargaPerHari=_parseHargaJualPerHari($qVal,$total,$hariHit,$rVal);
    $statusBayar=_excelTrim($ws->getCell('AD'.$r)->getCalculatedValue());
    $statusUnit=_excelTrim($ws->getCell('AE'.$r)->getCalculatedValue());
    $rows[]=['row'=>$r,'pemesan'=>$pemesan,'asal'=>$asal,'map'=>$map,'unit'=>$unit,'nopol'=>$nopol,'tglM'=>$tglM,'tglF'=>$tglF,'hari'=>$hariHit,'hariExcel'=>$hariExcel,'q'=>$qVal,'r'=>$rVal,'total'=>$total,'perHari'=>$hargaPerHari,'panjar'=>$panjar,'bayar'=>$statusBayar,'sUnit'=>$statusUnit];
}

foreach($rows as $r){
    echo sprintf("  Baris %2d: %-28s | Asal=%-8s => %-10s/%-9s | %s -> %s (%2d hr Excel:%2d) | Unit=%-16s Nopol=%-12s | Q=%s R=%s | Total %s => perHari %s | Panjar %s | %s/%s\n",
        $r['row'], mb_substr($r['pemesan'],0,28), $r['asal']?:'-', $r['map']['tipe'], $r['map']['sumber'],
        $r['tglM']??'?', $r['tglF']??'?', $r['hari'], $r['hariExcel'],
        mb_substr($r['unit'],0,16), $r['nopol'],
        is_numeric($r['q'])?rupiah($r['q']):('"'.$r['q'].'"'),
        $r['r']==='-'?'-':(mb_substr((string)$r['r'],0,22).(mb_strlen((string)$r['r'])>22?'..':'')),
        rupiah($r['total']), rupiah($r['perHari']),
        $r['panjar']?rupiah($r['panjar']):'-',
        $r['bayar']?:'-', $r['sUnit']?:'-'
    );
}
echo "  => ".count($rows)." baris ter-mapping\n";

// Rekap Asal User distribution 10 baris
$dist=[];
foreach($rows as $r){ $k=$r['map']['tipe'].'/'.$r['map']['sumber'].' ('.$r['asal'].')'; $dist[$k]=($dist[$k]??0)+1; }
echo "\n  Distribusi tipe (10 baris):\n";
foreach($dist as $k=>$v) echo "    $k : $v\n";

// Cek bentrok DB untuk 10 baris (pemesan+nopol+tglMulai sudah ada?)
echo "\n--- Cek duplikat DB (pemesan+nopol+tgl_mulai) ---\n";
$dupeCount=0;
foreach($rows as $r){
    $nopolNorm = preg_replace('/\s+/', '', strtoupper($r['nopol']));
    $st=$db->prepare('SELECT o.id, o.nomor_order, o.tgl_mulai FROM orders o JOIN order_items i ON i.order_id=o.id WHERE o.nama_pesanan=? AND o.tgl_mulai=? AND i.nopol=? AND o.deleted_at IS NULL LIMIT 1');
    $st->bind_param('sss', $r['pemesan'], $r['tglM'], $nopolNorm);
    $st->execute();
    $found=$st->get_result()->fetch_assoc();
    if($found){ echo "  DUPLIKAT: Baris {$r['row']} {$r['pemesan']} $nopolNorm {$r['tglM']} => sudah ada {$found['nomor_order']}\n"; $dupeCount++; }
    else echo "  BARU: Baris {$r['row']} {$r['pemesan']} $nopolNorm {$r['tglM']}\n";
}
echo "  Duplikat di DB: $dupeCount dari ".count($rows)."\n";

// Hari vs Excel cek
echo "\n--- Cek hari hitungan vs Excel (toleransi) ---\n";
foreach($rows as $r){
    $ok = $r['hari']===$r['hariExcel'] ? 'OK' : 'BEDA';
    echo "  Baris {$r['row']}: hitung={$r['hari']} excel={$r['hariExcel']} [$ok]\n";
}

echo "\n=== UJI 2 SELESAI (dry-run, belum insert DB) ===\n";
echo "Untuk uji insert beneran, lihat uji_2b_insert_10baris.php (dengan flag --insert dan cleanup)\n";
