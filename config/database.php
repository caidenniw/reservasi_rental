<?php
/**
 * config/database.php
 * Koneksi database, konstanta aplikasi, session, zona waktu.
 * Semua halaman memuat file ini lewat includes/functions.php.
 */

date_default_timezone_set('Asia/Jakarta'); // php.ini Laragon masih UTC - jangan dihapus

define('APP_NAME', '1000 Nusantara Rental');
define('APP_SUB',  'Dashboard Reservasi');
define('DB_HOST',  '127.0.0.1');
define('DB_USER',  'root');
define('DB_PASS',  '');
define('DB_NAME',  'rentalnusantara');
define('UPLOAD_DIR', realpath(__DIR__ . '/..') . '/assets/uploads');

/* BASE_URL dihitung otomatis, dua cara akses sama-sama jalan:
     http://rentalnusantara.test/       -> BASE_URL ''
     http://localhost/rentalnusantara/  -> BASE_URL '/rentalnusantara'   */
$__appRoot = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
$__docRoot = str_replace('\\', '/', rtrim((string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
$__base = '';
if ($__docRoot !== '' && $__appRoot !== '' && strpos($__appRoot, $__docRoot) === 0) {
    $__base = substr($__appRoot, strlen($__docRoot));
}
define('BASE_URL', rtrim($__base, '/'));

function getDB(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $conn->set_charset('utf8mb4');
        } catch (Throwable $e) {
            die('Koneksi database gagal: ' . $e->getMessage());
        }
    }
    return $conn;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
