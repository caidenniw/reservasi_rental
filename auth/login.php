<?php
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/pages/beranda.php');
}
$flash = getFlash();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk &middot; <?= e(APP_SUB) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <div class="login-brand">
            <div class="brand-mark">1000</div>
            <h1><?= e(APP_NAME) ?></h1>
            <p><?= e(APP_SUB) ?></p>
        </div>

        <?php foreach ($flash as $f): ?>
            <div class="alert alert-<?= e($f['tipe']) ?>"><?= e($f['pesan']) ?></div>
        <?php endforeach; ?>

        <form method="post" action="<?= BASE_URL ?>/auth/proses_login.php">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Masuk</button>
        </form>
    </div>
</div>
<script src="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
