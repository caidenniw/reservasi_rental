<?php
require_once __DIR__ . '/functions.php';
requireLogin();

$judulHalaman = $judulHalaman ?? 'Beranda';
$menuAktif    = $menuAktif ?? '';
$flash        = getFlash();
$menu = [
    'beranda' => ['label' => 'Beranda',                'url' => '/pages/beranda.php'],
    'input'   => ['label' => 'Input Pesanan',          'url' => '/pages/pesanan_form.php'],
    'data'    => ['label' => 'Data Pesanan & Invoice', 'url' => '/pages/pesanan_list.php'],
];
$masterMenu = [
    ['label' => 'Unit',      'url' => '/pages/master/unit.php'],
    ['label' => 'Driver',    'url' => '/pages/master/driver.php'],
    ['label' => 'Customer',  'url' => '/pages/master/customer.php'],
    ['label' => 'Partner',   'url' => '/pages/master/partner.php'],
    ['label' => 'Include',   'url' => '/pages/master/include_master.php'],
    ['divider' => true],
    ['label' => 'Import CSV','url' => '/pages/import.php'],
    ['label' => 'Pengaturan','url' => '/pages/master/pengaturan.php'],
];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judulHalaman) ?> &middot; <?= e(APP_SUB) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-mark">1000</div>
            <div>
                <div class="brand-name">Nusantara Rental</div>
                <div class="brand-sub">Dashboard Reservasi</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($menu as $key => $m): ?>
                <a href="<?= BASE_URL . $m['url'] ?>" class="nav-item <?= $menuAktif === $key ? 'active' : '' ?>">
                    <?= e($m['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <div class="user-name"><?= e(namaUser()) ?></div>
            <form method="post" action="<?= BASE_URL ?>/auth/logout.php">
                <?= csrfField() ?>
                <button type="submit" class="btn-logout">Keluar</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <header class="topbar">
            <h1 class="page-title"><?= e($judulHalaman) ?></h1>
            <div class="topbar-right">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" type="button">
                        Master Data
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($masterMenu as $mm): ?>
                            <?php if (!empty($mm['divider'])): ?>
                                <li><hr class="dropdown-divider"></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="<?= BASE_URL . $mm['url'] ?>"><?= e($mm['label']) ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </header>

        <div class="page">
            <?php foreach ($flash as $f): ?>
                <div class="alert alert-<?= e($f['tipe']) ?> alert-dismissible fade show" role="alert">
                    <?= e($f['pesan']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
