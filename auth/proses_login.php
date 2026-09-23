<?php
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/auth/login.php');
}
verifyCsrfToken();

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
    setFlash('danger', 'Username tidak ditemukan.');
    redirect(BASE_URL . '/auth/login.php');
}
if (!isset($user['is_active']) || (int) $user['is_active'] !== 1) {
    setFlash('danger', 'Akun Anda dinonaktifkan. Hubungi admin.');
    redirect(BASE_URL . '/auth/login.php');
}
if (!password_verify($password, $user['password'])) {
    setFlash('danger', 'Password salah.');
    redirect(BASE_URL . '/auth/login.php');
}

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
