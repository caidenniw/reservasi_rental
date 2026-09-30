<?php
require_once __DIR__ . '/functions.php';
requireLogin();

$judulHalaman = $judulHalaman ?? 'Beranda';
$menuAktif    = $menuAktif ?? '';
$flash        = getFlash();

/* Struktur navigasi modern dikelompokkan dengan ikon SVG */
$sidebarSections = [
    'MENU UTAMA' => [
        [
            'key'   => 'beranda',
            'label' => 'Dashboard',
            'url'   => '/pages/beranda.php',
            'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>'
        ],
        [
            'key'   => 'input',
            'label' => 'Input Pesanan',
            'url'   => '/pages/pesanan_form.php',
            'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>'
        ],
        [
            'key'   => 'data',
            'label' => 'Data Pesanan & Faktur',
            'url'   => '/pages/pesanan_list.php',
            'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>'
        ],
    ],
    'DATA MASTER' => [
        [
            'key'   => 'unit',
            'label' => 'Armada Mobil',
            'url'   => '/pages/master/unit.php',
            'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 3c-.1.2-.1.5-.1.7V16c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>'
        ],
        [
            'key'   => 'driver',
            'label' => 'Data Driver',
            'url'   => '/pages/master/driver.php',
            'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'
        ],
        [
            'key'   => 'customer',
            'label' => 'Data Pelanggan',
            'url'   => '/pages/master/customer.php',
            'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M8 10h.01"/><path d="M16 10h.01"/><path d="M8 14h.01"/><path d="M16 14h.01"/></svg>'
        ],
        [
            'key'   => 'partner',
            'label' => 'Partner (Support By)',
            'url'   => '/pages/master/partner.php',
            'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m11 17 2 2a1 1 0 0 0 1.4 0l4.3-4.3a1 1 0 0 0 0-1.4l-2-2a1 1 0 0 0-1.4 0L11 15.6"/><path d="m13 15-2-2a1 1 0 0 0-1.4 0l-4.3 4.3a1 1 0 0 0 0 1.4l2 2a1 1 0 0 0 1.4 0l4.3-4.3"/><path d="m18 8 2-2a1 1 0 0 0 0-1.4l-2-2a1 1 0 0 0-1.4 0l-2 2a1 1 0 0 0 0 1.4l2 2a1 1 0 0 0 1.4 0Z"/><path d="M2 12h5"/><path d="M17 12h5"/></svg>'
        ],
        [
            'key'   => 'include',
            'label' => 'Item Include',
            'url'   => '/pages/master/include_master.php',
            'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>'
        ],
    ],
    'SISTEM & TOOLS' => [
        [
            'key'   => 'asisten',
            'label' => 'Asisten Data',
            'url'   => '/pages/asisten.php',
            'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="13" y2="13"/></svg>'
        ],
        [
            'key'   => 'import_xlsx',
            'label' => 'Import Excel (Orderan)',
            'url'   => '/pages/import_xlsx.php',
            'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><path d="M10 9H8"/><path d="M16 9H14"/></svg>'
        ],
        [
            'key'   => 'import',
            'label' => 'Import CSV',
            'url'   => '/pages/import.php',
            'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>'
        ],
        [
            'key'   => 'pengaturan',
            'label' => 'Pengaturan Faktur',
            'url'   => '/pages/master/pengaturan.php',
            'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>'
        ],
        [
            'key'   => 'ubah_password',
            'label' => 'Ubah Password',
            'url'   => '/pages/ubah_password.php',
            'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>'
        ],
    ],
];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judulHalaman) ?> &middot; <?= e(APP_SUB) ?></title>
<style>html.rn-restore{visibility:hidden}</style>
<script>
/* Supaya tidak "flash ke atas" saat memulihkan posisi scroll:
   sembunyikan halaman dulu bila ada posisi tersimpan, lalu scroll-keep.js
   akan memulihkannya dan menampilkan kembali di akhir halaman. */
if ('scrollRestoration' in history) { try { history.scrollRestoration = 'manual'; } catch (e) {} }
(function () {
    try {
        if (sessionStorage.getItem('rn_scroll:' + location.pathname) !== null) {
            document.documentElement.classList.add('rn-restore');
        }
    } catch (e) {}
})();
</script>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body>
<!-- Backdrop Overlay untuk Mobile Sidebar Drawer -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="rnTutupSidebarMobile()"></div>

<div class="app">
    <aside class="sidebar" id="sidebarApp">
        <div class="sidebar-brand">
            <a href="<?= BASE_URL ?>/pages/beranda.php" class="brand-link">
                <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="1000 Nusantara" class="brand-logo">
            </a>
            <div class="brand-sub">Dashboard Reservasi</div>
            <button type="button" class="btn-sidebar-close d-lg-none" onclick="rnTutupSidebarMobile()" aria-label="Tutup Menu">&times;</button>
        </div>

        <div class="sidebar-scrollable">
            <nav class="sidebar-nav">
                <?php foreach ($sidebarSections as $sectionTitle => $navItems): ?>
                    <div class="sidebar-section-title"><?= e($sectionTitle) ?></div>
                    <?php foreach ($navItems as $nav): ?>
                        <?php $isActive = ($menuAktif === $nav['key']); ?>
                        <a href="<?= BASE_URL . $nav['url'] ?>" class="nav-item <?= $isActive ? 'active' : '' ?>">
                            <span class="nav-icon"><?= $nav['icon'] ?></span>
                            <span class="nav-label"><?= e($nav['label']) ?></span>
                            <?php if ($isActive): ?>
                                <span class="nav-dot"></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <?php unset($navItems, $nav, $isActive); ?>
            </nav>

            <div class="sidebar-foot">
                <div class="sidebar-slogan">"Satu Sistem Seribu Perjalanan"</div>
                <div class="user-profile-box">
                    <div class="user-avatar-circle">
                        <?= e(strtoupper(mb_substr(namaUser(), 0, 1))) ?>
                    </div>
                    <div class="user-info-text">
                        <div class="user-display-name"><?= e(namaUser()) ?></div>
                        <div class="user-role-badge">Admin Reservasi</div>
                    </div>
                </div>
                <form method="post" action="<?= BASE_URL ?>/auth/logout.php" class="mt-2">
                    <?= csrfField() ?>
                    <button type="submit" class="btn-logout-sidebar">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        <span>Keluar Sistem</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <main class="content">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="btn-hamburger d-lg-none" onclick="rnBukaSidebarMobile()" aria-label="Buka Menu">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <h1 class="page-title"><?= e($judulHalaman) ?></h1>
            </div>

            <div class="topbar-right">
                <span class="topbar-date"><?= e(hariPanjang()) ?></span>
                <a href="<?= BASE_URL ?>/pages/pesanan_form.php" class="btn btn-sm btn-primary btn-quick-order">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="me-1"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>Pesanan Baru</span>
                </a>
                <div class="user-chip">
                    <span class="avatar"><?= e(strtoupper(mb_substr(namaUser(), 0, 1))) ?></span>
                    <span class="d-none d-sm-inline"><?= e(namaUser()) ?></span>
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
