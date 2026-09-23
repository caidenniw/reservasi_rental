<?php
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/auth/login.php');
}
verifyCsrfToken();

/* pembatas sederhana: 5x gagal -> tunggu 60 detik (cukup untuk pemakaian lokal) */
function gagalLogin(string $pesan): void
{
    $_SESSION['login_fail'] = (int) ($_SESSION['login_fail'] ?? 0) + 1;
    $_SESSION['login_fail_time'] = time();
    setFlash('danger', $pesan);
    redirect(BASE_URL . '/auth/login.php');
}
if ((int) ($_SESSION['login_fail'] ?? 0) >= 5) {
    $tunggu = 60 - (time() - (int) ($_SESSION['login_fail_time'] ?? 0));
    if ($tunggu > 0) {
        setFlash('danger', 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . $tunggu . ' detik.');
        redirect(BASE_URL . '/auth/login.php');
    }
    unset($_SESSION['login_fail'], $_SESSION['login_fail_time']);
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    setFlash('danger', 'Username dan password wajib diisi.');
    redirect(BASE_URL . '/auth/login.php');
}

$db = getDB();
$stmt = $db->prepare('SELECT id, username, nama, password, is_active FROM users WHERE username = ?');
$stmt->bind_param('s', $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    gagalLogin('Username tidak ditemukan.');
}
if (!isset($user['is_active']) || (int) $user['is_active'] !== 1) {
    gagalLogin('Akun Anda dinonaktifkan. Hubungi admin.');
}
if (!password_verify($password, $user['password'])) {
    gagalLogin('Password salah.');
}

unset($_SESSION['login_fail'], $_SESSION['login_fail_time']);
session_regenerate_id(true);
$_SESSION['user_id']  = (int) $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['nama']     = $user['nama'];

$now = date('Y-m-d H:i:s');
$upd = $db->prepare('UPDATE users SET last_login = ? WHERE id = ?');
$upd->bind_param('si', $now, $user['id']);
$upd->execute();

setFlash('success', 'Selamat datang, ' . $user['nama'] . '.');
redirect(BASE_URL . '/pages/beranda.php');
