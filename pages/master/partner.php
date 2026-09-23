<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../../includes/master_crud.php';
require_once __DIR__ . '/../../includes/master_tampilan.php';

$cfg = [
    'tabel' => 'partners',
    'url'   => '/pages/master/partner.php',
    'judul' => 'Partner (Support By)',
    'soft_delete' => true,
    'order' => 'nama',
    'cari_kolom' => ['nama', 'hp', 'alamat'],
    'kolom' => [
        ['name' => 'nama', 'label' => 'Nama Partner', 'tipe' => 'text', 'wajib' => true, 'help' => 'contoh: Om Karius'],
        ['name' => 'tipe', 'label' => 'Tipe', 'tipe' => 'select', 'opsi' => ['vendor' => 'Vendor unit', 'perantara' => 'Perantara', 'owner_unit' => 'Pemilik unit']],
        ['name' => 'hp', 'label' => 'HP / WA', 'tipe' => 'tel'],
        ['name' => 'bank', 'label' => 'Bank', 'tipe' => 'text'],
        ['name' => 'no_rekening', 'label' => 'No. Rekening', 'tipe' => 'text'],
        ['name' => 'alamat', 'label' => 'Alamat', 'tipe' => 'text', 'lebar' => 'col-12'],
        ['name' => 'catatan', 'label' => 'Catatan', 'tipe' => 'textarea', 'lebar' => 'col-12'],
    ],
    'kolom_list' => ['nama', 'tipe', 'hp', 'bank'],
];

$errors  = mcrudHandle($cfg);
$cari    = trim((string) ($_GET['cari'] ?? ''));
$rows    = mcrudList($cfg, $cari);
$editRow = mcrudAmbil($cfg, (int) ($_GET['id'] ?? 0));

$nilaiForm = [];
foreach ($cfg['kolom'] as $c) {
    $nilaiForm[$c['name']] = $editRow[$c['name']] ?? ($_POST[$c['name']] ?? ($c['tipe'] === 'select' ? (string) array_key_first(mcrudOpsi($c)) : ''));
}

$judulHalaman = 'Master Partner';
$menuAktif = 'partner';
include __DIR__ . '/../../includes/header.php';
masterRender($cfg, $errors, $rows, $cari, $editRow, $nilaiForm, 'cari nama partner / HP');
include __DIR__ . '/../../includes/footer.php';
