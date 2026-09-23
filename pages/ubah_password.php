<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $lama  = (string) ($_POST['password_lama'] ?? '');
    $baru  = (string) ($_POST['password_baru'] ?? '');
    $ulang = (string) ($_POST['password_ulang'] ?? '');
    $uid   = idUser();

    $st = $db->prepare('SELECT password FROM users WHERE id = ?');
    $st->bind_param('i', $uid);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    if (!$u || !password_verify($lama, $u['password'])) {
        setFlash('danger', 'Password lama salah.');
        redirect(BASE_URL . '/pages/ubah_password.php');
    }
    if (strlen($baru) < 6) {
        setFlash('danger', 'Password baru minimal 6 karakter.');
        redirect(BASE_URL . '/pages/ubah_password.php');
    }
    if ($baru !== $ulang) {
        setFlash('danger', 'Konfirmasi password tidak sama.');
        redirect(BASE_URL . '/pages/ubah_password.php');
    }
    $hash = password_hash($baru, PASSWORD_DEFAULT);
    $upd = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
    $upd->bind_param('si', $hash, $uid);
    $upd->execute();
    setFlash('success', 'Password berhasil diubah.');
    redirect(BASE_URL . '/pages/beranda.php');
}

$judulHalaman = 'Ubah Password';
$menuAktif = '';
include __DIR__ . '/../includes/header.php';
?>
<div class="card-box" style="max-width:480px">
    <h2 class="card-title">Ubah Password</h2>
    <form method="post">
        <?= csrfField() ?>
        <div class="mb-3">
            <label class="form-label" for="password_lama">Password Lama</label>
            <input type="password" class="form-control" id="password_lama" name="password_lama" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password_baru">Password Baru</label>
            <input type="password" class="form-control" id="password_baru" name="password_baru" required minlength="6">
            <div class="form-text">Minimal 6 karakter.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password_ulang">Ulangi Password Baru</label>
            <input type="password" class="form-control" id="password_ulang" name="password_ulang" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Simpan Password</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
