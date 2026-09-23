<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../../includes/master_crud.php';
require_once __DIR__ . '/../../includes/master_tampilan.php';

$cfg = [
    'tabel' => 'drivers',
    'url'   => '/pages/master/driver.php',
    'judul' => 'Driver',
    'soft_delete' => true,
    'order' => 'nama',
    'cari_kolom' => ['nama', 'hp', 'wilayah', 'nomor_sim'],
    'kolom' => [
        ['name' => 'nama', 'label' => 'Nama Driver', 'tipe' => 'text', 'wajib' => true],
        ['name' => 'hp', 'label' => 'HP / WA', 'tipe' => 'tel', 'help' => '08xx atau +62xx (otomatis dirapikan)'],
        ['name' => 'wilayah', 'label' => 'Wilayah', 'tipe' => 'text', 'help' => 'contoh: Gunung Sitoli / Medan'],
        ['name' => 'nomor_sim', 'label' => 'Nomor SIM', 'tipe' => 'text'],
        ['name' => 'bank', 'label' => 'Bank', 'tipe' => 'text'],
        ['name' => 'no_rekening', 'label' => 'No. Rekening', 'tipe' => 'text'],
        ['name' => 'status', 'label' => 'Status', 'tipe' => 'select', 'opsi' => ['aktif' => 'Aktif', 'izin' => 'Izin', 'sakit' => 'Sakit', 'nonaktif' => 'Nonaktif']],
        ['name' => 'catatan', 'label' => 'Catatan', 'tipe' => 'textarea', 'lebar' => 'col-12'],
    ],
    'kolom_list' => ['nama', 'hp', 'wilayah', 'bank', 'status'],
    'status_map' => ['aktif' => 'Aktif', 'izin' => 'Izin', 'sakit' => 'Sakit', 'nonaktif' => 'Nonaktif'],
];

$errors  = mcrudHandle($cfg);
$cari    = trim((string) ($_GET['cari'] ?? ''));
$rows    = mcrudList($cfg, $cari);
$editRow = mcrudAmbil($cfg, (int) ($_GET['id'] ?? 0));

$nilaiForm = [];
foreach ($cfg['kolom'] as $c) {
    $nilaiForm[$c['name']] = $editRow[$c['name']] ?? ($_POST[$c['name']] ?? ($c['tipe'] === 'select' ? (string) array_key_first(mcrudOpsi($c)) : ''));
}

$judulHalaman = 'Master Driver';
$menuAktif = '';
include __DIR__ . '/../../includes/header.php';
masterRender($cfg, $errors, $rows, $cari, $editRow, $nilaiForm, 'cari nama / HP / wilayah');
include __DIR__ . '/../../includes/footer.php';
