<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../../includes/master_crud.php';
require_once __DIR__ . '/../../includes/master_tampilan.php';

$cfg = [
    'tabel' => 'units',
    'url'   => '/pages/master/unit.php',
    'judul' => 'Unit',
    'soft_delete' => true,
    'order' => 'nama_unit',
    'cari_kolom' => ['nama_unit', 'nopol', 'kode_unit', 'merek'],
    'kolom' => [
        ['name' => 'kode_unit', 'label' => 'Kode Unit', 'tipe' => 'text', 'help' => 'opsional, misal INV-01'],
        ['name' => 'nama_unit', 'label' => 'Nama Unit', 'tipe' => 'text', 'wajib' => true, 'help' => 'contoh: Innova Reborn'],
        ['name' => 'nopol', 'label' => 'Nomor Polisi', 'tipe' => 'text', 'wajib' => true, 'help' => 'contoh: BK 1507 LIN'],
        ['name' => 'jenis', 'label' => 'Jenis', 'tipe' => 'select', 'opsi' => ['MPV' => 'MPV', 'SUV' => 'SUV', 'Hiace' => 'Hiace', 'Bus' => 'Bus', 'Sedan' => 'Sedan', 'Pickup' => 'Pickup', 'Lain' => 'Lain']],
        ['name' => 'tahun', 'label' => 'Tahun', 'tipe' => 'number'],
        ['name' => 'transmisi', 'label' => 'Transmisi', 'tipe' => 'select', 'opsi' => ['' => '-- pilih --', 'manual' => 'Manual', 'matic' => 'Matic']],
        ['name' => 'kapasitas', 'label' => 'Kapasitas (orang)', 'tipe' => 'number'],
        ['name' => 'pemilik', 'label' => 'Pemilik', 'tipe' => 'select', 'opsi' => ['sendiri' => 'Milik sendiri', 'partner' => 'Partner']],
        ['name' => 'partner_id', 'label' => 'Partner', 'tipe' => 'select', 'opsi_sql' => ['from' => 'partners WHERE deleted_at IS NULL', 'value' => 'id', 'label' => 'nama']],
        ['name' => 'harga_modal_default', 'label' => 'Harga Modal / Hari', 'tipe' => 'rupiah', 'help' => 'internal - tidak muncul di invoice customer'],
        ['name' => 'harga_jual_default', 'label' => 'Harga Jual / Hari', 'tipe' => 'rupiah'],
        ['name' => 'status', 'label' => 'Status', 'tipe' => 'select', 'opsi' => ['ready' => 'Siap', 'keluar' => 'Sedang Keluar', 'maintenance' => 'Perawatan', 'nonaktif' => 'Nonaktif']],
        ['name' => 'catatan', 'label' => 'Catatan', 'tipe' => 'textarea', 'lebar' => 'col-12'],
    ],
    'kolom_list' => ['nama_unit', 'nopol', 'jenis', 'pemilik', 'harga_modal_default', 'harga_jual_default', 'status'],
    'status_map' => ['ready' => 'Siap', 'keluar' => 'Sedang Keluar', 'maintenance' => 'Perawatan', 'nonaktif' => 'Nonaktif'],
];

$errors  = mcrudHandle($cfg);
$cari    = trim((string) ($_GET['cari'] ?? ''));
$rows    = mcrudList($cfg, $cari);
$editRow = mcrudAmbil($cfg, (int) ($_GET['id'] ?? 0));

$nilaiForm = [];
foreach ($cfg['kolom'] as $c) {
    $nilaiForm[$c['name']] = $editRow[$c['name']] ?? ($_POST[$c['name']] ?? ($c['tipe'] === 'select' ? (string) array_key_first(mcrudOpsi($c)) : ''));
}

$judulHalaman = 'Master Unit';
$menuAktif = 'unit';
include __DIR__ . '/../../includes/header.php';
masterRender($cfg, $errors, $rows, $cari, $editRow, $nilaiForm, 'cari nama / nopol / kode');
include __DIR__ . '/../../includes/footer.php';
