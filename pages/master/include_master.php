<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../../includes/master_crud.php';
require_once __DIR__ . '/../../includes/master_tampilan.php';

/* include tidak punya kolom deleted_at -> dihapus permanen.
   Cek dulu: kalau sudah dipakai di order, jangan dihapus. */
$cfg = [
    'tabel' => 'includes',
    'url'   => '/pages/master/include_master.php',
    'judul' => 'Item Include',
    'order' => 'urutan',
    'cari_kolom' => ['nama'],
    'kolom' => [
        ['name' => 'nama', 'label' => 'Nama Include', 'tipe' => 'text', 'wajib' => true, 'help' => 'contoh: BBM, Parkir, Tol'],
        ['name' => 'urutan', 'label' => 'Urutan Tampil', 'tipe' => 'number'],
        ['name' => 'is_default', 'label' => 'Terpilih otomatis di form?', 'tipe' => 'select', 'opsi' => ['0' => 'Tidak', '1' => 'Ya']],
    ],
    'kolom_list' => ['nama', 'urutan', 'is_default'],
];

$errors  = mcrudHandle($cfg);
$cari    = trim((string) ($_GET['cari'] ?? ''));
$rows    = mcrudList($cfg, $cari);
$editRow = mcrudAmbil($cfg, (int) ($_GET['id'] ?? 0));

$nilaiForm = [];
foreach ($cfg['kolom'] as $c) {
    $nilaiForm[$c['name']] = $editRow[$c['name']] ?? ($_POST[$c['name']] ?? ($c['tipe'] === 'select' ? (string) array_key_first(mcrudOpsi($c)) : ''));
}

$judulHalaman = 'Master Include';
$menuAktif = '';
include __DIR__ . '/../../includes/header.php';
masterRender($cfg, $errors, $rows, $cari, $editRow, $nilaiForm, 'cari nama include');
include __DIR__ . '/../../includes/footer.php';
