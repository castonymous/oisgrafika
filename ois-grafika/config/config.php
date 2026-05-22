<?php
// ============================================
// KONFIGURASI DATABASE
// Edit sesuai info Hostinger lu
// ============================================

define('DB_HOST', 'localhost');           // Biasanya localhost
define('DB_NAME', 'ois_grafika');         // Nama database
define('DB_USER', 'root');                // Username database
define('DB_PASS', '');                    // Password database

// Konfigurasi situs
define('SITE_NAME', 'Ois Grafika');
define('SITE_URL', 'http://localhost/ois-grafika'); // Ganti ke domain lu nanti
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// Komisi referral (persen dari subtotal)
define('REFERRAL_COMMISSION_PERCENT', 5);

// ============================================
// KONEKSI PDO
// ============================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Mulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone Indonesia
date_default_timezone_set('Asia/Jakarta');
