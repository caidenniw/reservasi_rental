<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$db = getDB();
$st = $db->prepare('SELECT bukti_path FROM payments WHERE id = ?');
$st->bind_param('i', $id);
$st->execute();
$b = $st->get_result()->fetch_assoc();
if (!$b || empty($b['bukti_path'])) {
    http_response_code(404);
    exit('Bukti tidak ditemukan.');
}

$file = realpath(__DIR__ . '/../' . $b['bukti_path']);
$rootUpload = realpath(UPLOAD_DIR);
if ($file === false || $rootUpload === false || strpos($file, $rootUpload) !== 0 || !is_file($file)) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}

$ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'][$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($file));
header('Content-Disposition: inline');
readfile($file);
