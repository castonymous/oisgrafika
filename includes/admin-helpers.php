<?php
// ============================================
// HELPERS UNTUK ADMIN PRODUK
// ============================================

require_once __DIR__ . '/functions.php';

/**
 * Hitung kelengkapan produk (untuk progress bar)
 * Return array: ['score' => 75, 'items' => [...]]
 */
function calculateProductCompleteness($product, $images = [], $attributes = []) {
    $checklist = [
        'photo' => [
            'label' => 'Min. 1 foto produk',
            'done' => count($images) >= 1,
            'required' => true,
        ],
        'photos_3' => [
            'label' => 'Upload 3+ foto (rekomendasi)',
            'done' => count($images) >= 3,
            'required' => false,
        ],
        'name' => [
            'label' => 'Nama produk (min 10 char)',
            'done' => !empty($product['name']) && strlen($product['name']) >= 10,
            'required' => true,
        ],
        'category' => [
            'label' => 'Kategori produk',
            'done' => !empty($product['category_id']),
            'required' => true,
        ],
        'description' => [
            'label' => 'Deskripsi (min 50 char)',
            'done' => !empty($product['description']) && strlen($product['description']) >= 50,
            'required' => true,
        ],
        'price' => [
            'label' => 'Harga produk',
            'done' => !empty($product['base_price']) && $product['base_price'] > 0,
            'required' => true,
        ],
        'stock' => [
            'label' => 'Stok produk',
            'done' => isset($product['stock']) && $product['stock'] >= 0,
            'required' => true,
        ],
        'weight' => [
            'label' => 'Berat (untuk produk fisik)',
            'done' => $product['type'] !== 'fisik' || !empty($product['weight']),
            'required' => $product['type'] === 'fisik',
        ],
        'attributes' => [
            'label' => 'Spesifikasi (min 3 atribut)',
            'done' => count($attributes) >= 3,
            'required' => false,
        ],
    ];
    
    $total = count($checklist);
    $done = count(array_filter($checklist, fn($c) => $c['done']));
    $requiredTotal = count(array_filter($checklist, fn($c) => $c['required']));
    $requiredDone = count(array_filter($checklist, fn($c) => $c['required'] && $c['done']));
    
    return [
        'score' => $total > 0 ? round(($done / $total) * 100) : 0,
        'items' => $checklist,
        'done' => $done,
        'total' => $total,
        'required_done' => $requiredDone,
        'required_total' => $requiredTotal,
        'can_publish' => $requiredDone === $requiredTotal,
    ];
}

/**
 * Generate slug dari nama
 */
function generateSlug($name, $pdo = null, $excludeId = null) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($name)));
    $slug = trim($slug, '-');
    
    if ($pdo) {
        // Pastikan unique
        $baseSlug = $slug;
        $i = 1;
        while (true) {
            $sql = "SELECT id FROM products WHERE slug = ?";
            $params = [$slug];
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if (!$stmt->fetch()) break;
            $i++;
            $slug = $baseSlug . '-' . $i;
        }
    }
    
    return $slug;
}

/**
 * Upload gambar produk
 * Return path relatif atau false
 */
function uploadProductImage($file, $productId) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    if (!in_array($file['type'], $allowed)) {
        return false;
    }
    
    // Max 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        return false;
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'p' . $productId . '_' . uniqid() . '.' . strtolower($ext);
    $uploadPath = __DIR__ . '/../uploads/products/';
    
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath . $filename)) {
        return 'uploads/products/' . $filename;
    }
    
    return false;
}

/**
 * Daftar atribut spesifikasi default (untuk MVP)
 */
function getDefaultAttributes() {
    return [
        // Required
        ['name' => 'Merek', 'required' => true, 'type' => 'text', 'placeholder' => 'Contoh: Custom'],
        ['name' => 'Bahan', 'required' => true, 'type' => 'text', 'placeholder' => 'Contoh: Vinyl, Cotton'],
        ['name' => 'Kondisi', 'required' => true, 'type' => 'select', 'options' => ['Baru', 'Bekas']],
        
        // Optional
        ['name' => 'Gaya', 'required' => false, 'type' => 'text', 'placeholder' => 'Modern, Klasik'],
        ['name' => 'Jenis Produk', 'required' => false, 'type' => 'text'],
        ['name' => 'Jenis Kelamin', 'required' => false, 'type' => 'select', 'options' => ['Unisex', 'Pria', 'Wanita']],
        ['name' => 'Negara Asal', 'required' => false, 'type' => 'text', 'placeholder' => 'Indonesia'],
        ['name' => 'Masa Garansi', 'required' => false, 'type' => 'text', 'placeholder' => '1 Bulan'],
        ['name' => 'Produk Custom', 'required' => false, 'type' => 'select', 'options' => ['Ya', 'Tidak']],
        ['name' => 'Sertifikat Halal', 'required' => false, 'type' => 'select', 'options' => ['Ya', 'Tidak']],
        ['name' => 'No. Sertifikasi Halal', 'required' => false, 'type' => 'text'],
        ['name' => 'Ukuran Diameter', 'required' => false, 'type' => 'text'],
        ['name' => 'Quantity per Pack', 'required' => false, 'type' => 'text'],
        ['name' => 'Desain', 'required' => false, 'type' => 'text'],
        ['name' => 'Certification/License', 'required' => false, 'type' => 'text'],
    ];
}
