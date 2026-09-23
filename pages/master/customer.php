<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../../includes/master_crud.php';
require_once __DIR__ . '/../../includes/master_tampilan.php';

$cfg = [
    'tabel' => 'customers',
    'url'   => '/pages/master/customer.php',
    'judul' => 'Customer',
    'soft_delete' => true,
    'order' => 'nama_pesanan',
    'cari_kolom' => ['nama_pesanan', 'nama_pic', 'hp_pic', 'alamat'],
    'kolom' => [
        ['name' => 'nama_pesanan', 'label' => 'Nama Pesanan / Instansi', 'tipe' => 'text', 'wajib' => true, 'lebar' => 'col-md-6'],
        ['name' => 'tipe', 'label' => 'Tipe', 'tipe' => 'select', 'opsi' => ['perorangan' => 'Perorangan', 'perusahaan' => 'Perusahaan', 'instansi' => 'Instansi', 'RO' => 'Repeat Order']],
        ['name' => 'nama_pic', 'label' => 'Nama PIC', 'tipe' => 'text'],
        ['name' => 'hp_pic', 'label' => 'HP / WA PIC', 'tipe' => 'tel'],
        ['name' => 'email', 'label' => 'Email', 'tipe' => 'text'],
        ['name' => 'sumber', 'label' => 'Sumber Order', 'tipe' => 'select', 'opsi' => ['wa' => 'WhatsApp', 'telepon' => 'Telepon', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'website' => 'Website', 'referral' => 'Referral', 'lainnya' => 'Lainnya']],
        ['name' => 'status', 'label' => 'Status Customer', 'tipe' => 'select', 'opsi' => ['baru' => 'Baru', 'tetap' => 'Tetap', 'RO' => 'Repeat Order']],
        ['name' => 'alamat', 'label' => 'Alamat', 'tipe' => 'text', 'lebar' => 'col-12'],
        ['name' => 'catatan', 'label' => 'Catatan', 'tipe' => 'textarea', 'lebar' => 'col-12'],
    ],
    'kolom_list' => ['nama_pesanan', 'tipe', 'nama_pic', 'hp_pic', 'sumber', 'status'],
];

$errors  = mcrudHandle($cfg);
$cari    = trim((string) ($_GET['cari'] ?? ''));
$rows    = mcrudList($cfg, $cari);
$editRow = mcrudAmbil($cfg, (int) ($_GET['id'] ?? 0));

$nilaiForm = [];
foreach ($cfg['kolom'] as $c) {
    $nilaiForm[$c['name']] = $editRow[$c['name']] ?? ($_POST[$c['name']] ?? ($c['tipe'] === 'select' ? (string) array_key_first(mcrudOpsi($c)) : ''));
}

$judulHalaman = 'Master Customer';
$menuAktif = 'customer';
include __DIR__ . '/../../includes/header.php';
masterRender($cfg, $errors, $rows, $cari, $editRow, $nilaiForm, 'cari nama pesanan / PIC / HP');
include __DIR__ . '/../../includes/footer.php';
