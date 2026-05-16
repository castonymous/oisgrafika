<?php
// ============================================================
// OIS GRAFIKA – Konfigurasi & Helper Global
// ============================================================

// Mulai session (sekali aja, sebelum output apapun)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Paths ──
define('BASE_DIR', __DIR__);
define('DATA_DIR', BASE_DIR . '/data');

// Auto-create data folder
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
    // Proteksi: cegah akses langsung file JSON via browser
    file_put_contents(DATA_DIR . '/.htaccess', "Deny from all\n");
}

define('INVENTORY_FILE', DATA_DIR . '/inventory.json');
define('INVOICES_FILE',  DATA_DIR . '/invoices.json');
define('USERS_FILE',     DATA_DIR . '/users.json');
define('SETTINGS_FILE',  DATA_DIR . '/settings.json');

// ── JSON Helpers ──
function load_json($file, $default = []) {
    if (!file_exists($file)) return $default;
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}

function save_json($file, $data) {
    file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

// ── Seed Users (default admin) ──
function ensure_users() {
    if (!file_exists(USERS_FILE)) {
        $admin = [[
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'name'     => 'Admin OIS',
            'created'  => date('Y-m-d H:i:s'),
        ]];
        save_json(USERS_FILE, $admin);
    }
}

// ── Seed Settings ──
function ensure_settings() {
    if (!file_exists(SETTINGS_FILE)) {
        $default = [
            'business_name' => 'OIS GRAFIKA',
            'tagline'       => 'solusi desain & cetak anda',
            'address'       => 'Kapek, RT.05/RW.01, Bantarpanjang, Cimanggu, Kab. Cilacap, Jawa Tengah 53256',
            'phone'         => '08229999114',
            'facebook'      => 'Ois Grafika',
            'youtube'       => 'Ois Grafika',
            'instagram'     => 'oisgrafika',
            'tiktok'        => 'oisgrafika',
            'twitter'       => 'oisgrafika',
            'accent_color'  => '#ff7aa8',
            'services'      => [
                'BANNER','CUTTING STICKER','SABLON','UNDANGAN','BUKU YASIN',
                'CETAK FOTO','EDIT FOTO','CETAK A3+','EDIT VIDEO','KALENDER',
                'PERCETAKAN','DTF','POSTER','DESAIN','STEMPEL','IDCARD','NAMETAG',
                'KARTU NAMA','KARTU BAYI','LANYARD','MEDALI','PLAKAT','NOTA',
                'GANCI','PIN','NEON BOX','BROSUR','DLL'
            ],
            'attention_text'=> 'Barang-barang yang sudah dibeli tidak dapat ditukar/dikembalikan, kecuali ada perjanjian dengan seller.',
        ];
        save_json(SETTINGS_FILE, $default);
    }
}

// ── Seed Inventory (kalau belum ada) ──
function ensure_inventory() {
    if (!file_exists(INVENTORY_FILE)) {
        $seed = [
            ["id"=>1,"nama"=>"Banner 60x160cm","harga"=>35000,"satuan"=>"pcs","kategori"=>"Banner"],
            ["id"=>2,"nama"=>"Sticker Cutting A3","harga"=>25000,"satuan"=>"lembar","kategori"=>"Sticker"],
            ["id"=>3,"nama"=>"Sablon Kaos","harga"=>45000,"satuan"=>"pcs","kategori"=>"Sablon"],
            ["id"=>4,"nama"=>"Undangan Pernikahan (50pcs)","harga"=>150000,"satuan"=>"paket","kategori"=>"Undangan"],
            ["id"=>5,"nama"=>"ID Card + Lanyard","harga"=>15000,"satuan"=>"pcs","kategori"=>"ID Card"],
            ["id"=>6,"nama"=>"Cetak Foto 4R","harga"=>3000,"satuan"=>"lembar","kategori"=>"Foto"],
            ["id"=>7,"nama"=>"Cetak Foto A4","harga"=>12000,"satuan"=>"lembar","kategori"=>"Foto"],
            ["id"=>8,"nama"=>"Kartu Nama 1 Sisi (100pcs)","harga"=>35000,"satuan"=>"paket","kategori"=>"Kartu Nama"],
            ["id"=>9,"nama"=>"Kartu Nama 2 Sisi (100pcs)","harga"=>50000,"satuan"=>"paket","kategori"=>"Kartu Nama"],
            ["id"=>10,"nama"=>"Spanduk 1x3m","harga"=>75000,"satuan"=>"pcs","kategori"=>"Banner"],
            ["id"=>11,"nama"=>"Brosur A5 (100lbr)","harga"=>65000,"satuan"=>"paket","kategori"=>"Cetak"],
            ["id"=>12,"nama"=>"Poster A3","harga"=>18000,"satuan"=>"lembar","kategori"=>"Cetak"],
            ["id"=>13,"nama"=>"Kalender Meja","harga"=>25000,"satuan"=>"pcs","kategori"=>"Kalender"],
            ["id"=>14,"nama"=>"Plakat Akrilik","harga"=>120000,"satuan"=>"pcs","kategori"=>"Plakat"],
            ["id"=>15,"nama"=>"Nota/Kwitansi (100lbr)","harga"=>55000,"satuan"=>"buku","kategori"=>"Cetak"],
        ];
        save_json(INVENTORY_FILE, $seed);
    }
}

// Run seeds on every request (cepat karena cuma cek file_exists)
ensure_users();
ensure_settings();
ensure_inventory();

// ── Auth ──
function is_logged_in() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . app_url('auth/login.php'));
        exit;
    }
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

// ── URL Helper ──
function app_url($path = '') {
    // Detect base path otomatis (works di subfolder atau root)
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    // Kalau kita ada di subfolder /nota/ atau /auth/, naik 1 level
    if (preg_match('#/(nota|auth|admin)$#', $base)) {
        $base = dirname($base);
    }
    $base = $base === '/' ? '' : $base;
    return $base . '/' . ltrim($path, '/');
}

// ── Output Helpers ──
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function rupiah($n) {
    return 'Rp ' . number_format((int)$n, 0, ',', '.');
}

// ── Invoice Number Generator ──
function next_invoice_number($invoices) {
    $year = date('Y');
    $maxSeq = 0;
    foreach ($invoices as $inv) {
        if (preg_match('/^OG-' . $year . '-(\d+)$/', $inv['invoice_number'] ?? '', $m)) {
            $maxSeq = max($maxSeq, intval($m[1]));
        }
    }
    return sprintf('OG-%s-%04d', $year, $maxSeq + 1);
}
