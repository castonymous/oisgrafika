<?php
// ============================================
// FUNGSI BANTUAN
// ============================================

require_once __DIR__ . '/../config/config.php';

// Cek apakah user sudah login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Cek apakah user adalah admin
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Wajib login, kalau belum redirect
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

// Wajib admin
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

// Generate kode referral unik
function generateReferralCode($name) {
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 4));
    if (strlen($prefix) < 3) $prefix = 'OIS';
    return $prefix . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
}

// Generate kode pesanan
function generateOrderCode() {
    return 'OIS-' . date('ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
}

// Format Rupiah
function rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Sanitize input
function clean($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// Redirect dengan flash message
function redirect($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
    header('Location: ' . $url);
    exit;
}

// Tampilkan flash message
function showFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return '<div class="flash flash-' . $flash['type'] . '">' . $flash['message'] . '</div>';
    }
    return '';
}

// Hitung jumlah item di keranjang user
function getCartCount($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

// Get current user data
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Generate CSRF token
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Singkatkan teks
function truncate($text, $length = 100) {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

// ============================================
// CLEAN URL HELPERS
// ============================================

/**
 * Generate clean URL berdasar tipe.
 * Contoh: url('produk', 'lanyard-ut') → /produk/lanyard-ut
 * Contoh: url('home') → /
 */
function url($type, $param = '') {
    $base = SITE_URL;
    switch ($type) {
        case 'home':         return $base . '/';
        case 'produk':       return $param ? "$base/produk/" . urlencode($param) : "$base/produk";
        case 'kategori':     return "$base/kategori/" . urlencode($param);
        case 'order':        return "$base/order/" . urlencode($param);
        case 'tracking':     return "$base/lacak/" . urlencode($param);
        case 'detail-produk':return "$base/produk/" . urlencode($param);  // alias
        // Fallback - clean URL untuk halaman biasa (tanpa .php)
        default:             return "$base/" . trim($type, '/');
    }
}
