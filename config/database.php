<?php
// ============================================================
// Konfigurasi Database
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'perpustakaan');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// Konfigurasi Aplikasi
// ============================================================
define('APP_NAME', 'Perpustakaan Digital');
define('APP_SCHOOL', 'SMKS Wira Harapan ');
define('DENDA_PER_HARI', 1000); // Rp 1.000 per hari
define('MAX_PINJAM', 3); // Maksimal buku dipinjam
define('MASA_PINJAM', 7); // Hari

// ============================================================
// Koneksi PDO
// ============================================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Koneksi database gagal: ' . $e->getMessage()]));
}
