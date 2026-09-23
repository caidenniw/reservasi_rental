<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$tipe = (string) ($_GET['tipe'] ?? '');
$q    = trim((string) ($_GET['q'] ?? ''));
$db   = getDB();

if (mb_strlen($q) < 2) {
    echo json_encode(['hasil' => []]);
    exit;
}
$like = '%' . $q . '%';

if ($tipe === 'customer') {
    $stmt = $db->prepare('SELECT id, nama_pesanan, nama_pic, hp_pic, tipe, alamat
                          FROM customers
                          WHERE deleted_at IS NULL AND (nama_pesanan LIKE ? OR nama_pic LIKE ?)
                          ORDER BY nama_pesanan LIMIT 8');
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    echo json_encode(['hasil' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
    exit;
}

if ($tipe === 'unit') {
    $stmt = $db->prepare('SELECT id, nama_unit, nopol, harga_modal_default, harga_jual_default, status
                          FROM units
                          WHERE deleted_at IS NULL AND (nama_unit LIKE ? OR nopol LIKE ?)
                          ORDER BY nama_unit LIMIT 8');
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    echo json_encode(['hasil' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
    exit;
}

echo json_encode(['hasil' => []]);
