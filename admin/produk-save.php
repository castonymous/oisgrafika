<?php
// ============================================
// PROCESSOR: Save / Update Produk
// Dipanggil dari produk-form.php
// ============================================

// DEBUG: Log paling awal - untuk tau apakah file ke-execute sama sekali
@file_put_contents(__DIR__ . '/../debug-variasi.log',
    "\n===== REQUEST " . date('Y-m-d H:i:s') . " =====\n" .
    "METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . "\n" .
    "URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . "\n" .
    "POST count: " . count($_POST) . " keys\n" .
    "FILES count: " . count($_FILES) . " keys\n" .
    "Content-Length: " . ($_SERVER['CONTENT_LENGTH'] ?? '0') . "\n" .
    "Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'unknown') . "\n",
    FILE_APPEND
);

require_once __DIR__ . '/../includes/admin-helpers.php';
requireAdmin();

function uploadVariationImage(array $file, int $productId) {
    return uploadProductImage($file, $productId);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/admin/produk.php');
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    redirect(SITE_URL . '/admin/produk.php', 'Token tidak valid', 'error');
}

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? 'save_draft'; // save_draft atau publish

// ========== Ambil data dari POST ==========
$data = [
    'name' => clean($_POST['name'] ?? ''),
    'category_id' => (int)($_POST['category_id'] ?? 0),
    'type' => clean($_POST['type'] ?? 'fisik'),
    'gtin' => clean($_POST['gtin'] ?? ''),
    'no_gtin' => isset($_POST['no_gtin']) ? 1 : 0,
    'short_description' => clean($_POST['short_description'] ?? ''),
    'description' => $_POST['description'] ?? '',
    'base_price' => (float)str_replace(['.', ','], '', $_POST['base_price'] ?? '0'),
    'stock' => (int)($_POST['stock'] ?? 0),
    'sku' => clean($_POST['sku'] ?? ''),
    'min_purchase' => max(1, (int)($_POST['min_purchase'] ?? 1)),
    'max_purchase' => !empty($_POST['max_purchase']) ? (int)$_POST['max_purchase'] : null,
    'weight' => !empty($_POST['weight']) ? (int)$_POST['weight'] : null,
    'length_cm' => !empty($_POST['length_cm']) ? (float)$_POST['length_cm'] : null,
    'width_cm' => !empty($_POST['width_cm']) ? (float)$_POST['width_cm'] : null,
    'height_cm' => !empty($_POST['height_cm']) ? (float)$_POST['height_cm'] : null,
    'shipping_origin' => clean($_POST['shipping_origin'] ?? ''),
    'free_shipping' => isset($_POST['free_shipping']) ? 1 : 0,
    'preorder' => isset($_POST['preorder']) ? 1 : 0,
    'preorder_days' => !empty($_POST['preorder_days']) ? (int)$_POST['preorder_days'] : null,
    'tags' => clean($_POST['tags'] ?? ''),
    'internal_note' => clean($_POST['internal_note'] ?? ''),
    'seo_title' => clean($_POST['seo_title'] ?? ''),
    'seo_description' => clean($_POST['seo_description'] ?? ''),
    'use_tier_pricing' => isset($_POST['use_tier_pricing']) ? 1 : 0, // Final state akan di-set ulang setelah cek variasi di bawah
];

// Status
$data['status'] = $action === 'publish' ? 'active' : 'draft';
$data['is_active'] = $action === 'publish' ? 1 : 0;

// Slug
$customSlug = clean($_POST['slug'] ?? '');
$data['slug'] = !empty($customSlug) ? $customSlug : generateSlug($data['name'], $pdo, $id ?: null);

// PENTING: Rebuild $data dengan urutan PERSIS sesuai kolom di SQL UPDATE/INSERT
// Kalo urutan beda, array_values() bakal kasih nilai ke kolom yg salah
// (mis. category_id kena nilai type, FK constraint langsung fail)
$data = [
    'name'              => $data['name'],
    'slug'              => $data['slug'],
    'category_id'       => $data['category_id'],
    'type'              => $data['type'],
    'gtin'              => $data['gtin'],
    'no_gtin'           => $data['no_gtin'],
    'short_description' => $data['short_description'],
    'description'       => $data['description'],
    'base_price'        => $data['base_price'],
    'stock'             => $data['stock'],
    'sku'               => $data['sku'],
    'min_purchase'      => $data['min_purchase'],
    'max_purchase'      => $data['max_purchase'],
    'weight'            => $data['weight'],
    'length_cm'         => $data['length_cm'],
    'width_cm'          => $data['width_cm'],
    'height_cm'         => $data['height_cm'],
    'shipping_origin'   => $data['shipping_origin'],
    'free_shipping'     => $data['free_shipping'],
    'preorder'          => $data['preorder'],
    'preorder_days'     => $data['preorder_days'],
    'status'            => $data['status'],
    'is_active'         => $data['is_active'],
    'tags'              => $data['tags'],
    'internal_note'     => $data['internal_note'],
    'seo_title'         => $data['seo_title'],
    'seo_description'   => $data['seo_description'],
    'use_tier_pricing'  => $data['use_tier_pricing'],
];

// ========== Detect Variasi (DATA-driven, bukan checkbox) ==========
// PENTING: Detect by DATA bukan by checkbox enable_variation
// karena checkbox kadang missing dari POST (terutama saat upload file)
$hasVariation = false;
if (!empty($_POST['variation_name'])) {
    foreach ($_POST['variation_name'] as $idx => $vn) {
        if (trim((string)$vn) !== '' && !empty($_POST['variation_options'][$idx])) {
            $opts = array_filter(array_map('trim', explode(',', $_POST['variation_options'][$idx])));
            if (count($opts) > 0) {
                $hasVariation = true;
                break;
            }
        }
    }
}

// Force tier_pricing = 0 kalau ada variasi (mutually exclusive)
if ($hasVariation) {
    $data['use_tier_pricing'] = 0;
}

// ========== Validasi minimum (draft boleh kosong) ==========
$errors = [];
if (empty($data['name'])) {
    $errors[] = 'Nama produk wajib diisi';
}
if (empty($data['category_id'])) {
    $errors[] = 'Kategori wajib dipilih';
} else {
    // Cek kategori beneran exist di DB
    $catCheck = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
    $catCheck->execute([$data['category_id']]);
    if (!$catCheck->fetch()) {
        $errors[] = 'Kategori yang dipilih tidak ada di database. Pastikan kategori belum dihapus.';
    }
}

// Validasi ketat untuk PUBLISH
if ($action === 'publish') {
    if (strlen($data['name']) < 5) $errors[] = 'Nama produk min 5 karakter (untuk publish)';
    if (strlen($data['description']) < 20) $errors[] = 'Deskripsi min 20 karakter (untuk publish)';
    
    // DEBUG: log state validation untuk troubleshoot
    @file_put_contents(__DIR__.'/../debug-variasi.log',
        "===== VALIDATION " . date('Y-m-d H:i:s') . " =====\n" .
        "action=" . $action . "\n" .
        "hasVariation=" . ($hasVariation ? 'YES' : 'NO') . "\n" .
        "enable_variation isset=" . (isset($_POST['enable_variation']) ? 'YES' : 'NO') . "\n" .
        "enable_variation value=" . print_r($_POST['enable_variation'] ?? 'NULL', true) . "\n" .
        "variation_name=" . json_encode($_POST['variation_name'] ?? null) . "\n" .
        "variation_options=" . json_encode($_POST['variation_options'] ?? null) . "\n" .
        "vi_combination=" . json_encode($_POST['vi_combination'] ?? null) . "\n" .
        "vi_price=" . json_encode($_POST['vi_price'] ?? null) . "\n" .
        "base_price RAW=" . print_r($_POST['base_price'] ?? 'NULL', true) . "\n" .
        "base_price PARSED=" . $data['base_price'] . "\n" .
        "ALL POST KEYS: " . implode(', ', array_keys($_POST)) . "\n\n",
        FILE_APPEND
    );
    
    if (!$hasVariation && $data['base_price'] <= 0) {
        $errors[] = 'Harga produk wajib > 0';
    }
    // Kalau ada variasi, pastikan minimal 1 varian punya harga
    if ($hasVariation) {
        $hasValidVarPrice = false;
        foreach ($_POST['vi_price'] ?? [] as $vp) {
            if ((float)preg_replace('/[^0-9]/', '', $vp) > 0) {
                $hasValidVarPrice = true;
                break;
            }
        }
        if (!$hasValidVarPrice) {
            $errors[] = 'Minimal 1 varian harus punya harga > 0';
        }
    }
    
    if ($data['stock'] < 0) $errors[] = 'Stok ga boleh negatif';
    if ($data['type'] === 'fisik' && empty($data['weight'])) {
        $errors[] = 'Berat produk wajib untuk produk fisik';
    }
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    $redirectUrl = $id ? SITE_URL . "/admin/produk-form.php?id=$id" : SITE_URL . '/admin/produk-form.php';
    redirect($redirectUrl, implode('; ', $errors), 'error');
}

// ========== Save / Update ==========
try {
    $pdo->beginTransaction();
    
    if ($id > 0) {
        // UPDATE
        $sql = "UPDATE products SET 
            name = ?, slug = ?, category_id = ?, type = ?, gtin = ?, no_gtin = ?,
            short_description = ?, description = ?, base_price = ?, stock = ?, sku = ?,
            min_purchase = ?, max_purchase = ?, weight = ?, length_cm = ?, width_cm = ?, height_cm = ?,
            shipping_origin = ?, free_shipping = ?, preorder = ?, preorder_days = ?,
            status = ?, is_active = ?, tags = ?, internal_note = ?, seo_title = ?, seo_description = ?, use_tier_pricing = ?
            WHERE id = ?";
        $params = array_values($data);
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
    } else {
        // INSERT
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = "INSERT INTO products (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        $pdo->prepare($sql)->execute(array_values($data));
        $id = $pdo->lastInsertId();
    }
    
    // ========== Upload foto baru ==========
    if (!empty($_FILES['photos']['name'][0])) {
        // Cari sort_order tertinggi
        $maxSortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ?");
        $maxSortStmt->execute([$id]);
        $sortOrder = (int)$maxSortStmt->fetchColumn() + 1;
        
        // Cek apakah udah ada cover
        $hasCoverStmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_cover = 1");
        $hasCoverStmt->execute([$id]);
        $hasCover = $hasCoverStmt->fetchColumn() > 0;
        
        $photoCount = count($_FILES['photos']['name']);
        for ($i = 0; $i < $photoCount; $i++) {
            if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            
            $file = [
                'name' => $_FILES['photos']['name'][$i],
                'type' => $_FILES['photos']['type'][$i],
                'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                'error' => $_FILES['photos']['error'][$i],
                'size' => $_FILES['photos']['size'][$i],
            ];
            
            $path = uploadProductImage($file, $id);
            if ($path) {
                $isCover = !$hasCover && $i === 0 ? 1 : 0;
                if ($isCover) {
                    $pdo->prepare("UPDATE products SET image = ? WHERE id = ?")->execute([$path, $id]);
                    $hasCover = true;
                }
                $pdo->prepare("INSERT INTO product_images (product_id, image, is_cover, sort_order) VALUES (?, ?, ?, ?)")
                    ->execute([$id, $path, $isCover, $sortOrder++]);
            }
        }
    }
    
    // ========== Set cover dari foto yang udah ada ==========
    if (!empty($_POST['cover_image_id'])) {
        $coverId = (int)$_POST['cover_image_id'];
        $pdo->prepare("UPDATE product_images SET is_cover = 0 WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("UPDATE product_images SET is_cover = 1 WHERE id = ? AND product_id = ?")->execute([$coverId, $id]);
        
        $imgStmt = $pdo->prepare("SELECT image FROM product_images WHERE id = ?");
        $imgStmt->execute([$coverId]);
        $coverPath = $imgStmt->fetchColumn();
        if ($coverPath) {
            $pdo->prepare("UPDATE products SET image = ? WHERE id = ?")->execute([$coverPath, $id]);
        }
    }
    
    // ========== Hapus foto ==========
    if (!empty($_POST['delete_images'])) {
        foreach ($_POST['delete_images'] as $imgId) {
            $imgStmt = $pdo->prepare("SELECT image FROM product_images WHERE id = ? AND product_id = ?");
            $imgStmt->execute([(int)$imgId, $id]);
            $imgPath = $imgStmt->fetchColumn();
            if ($imgPath) {
                $fullPath = __DIR__ . '/../' . $imgPath;
                if (file_exists($fullPath)) unlink($fullPath);
            }
            $pdo->prepare("DELETE FROM product_images WHERE id = ? AND product_id = ?")->execute([(int)$imgId, $id]);
        }
    }
    
    // ========== Save Atribut (Spesifikasi) ==========
    $pdo->prepare("DELETE FROM product_attributes WHERE product_id = ?")->execute([$id]);
    if (!empty($_POST['attr_name'])) {
        $attrIns = $pdo->prepare("INSERT INTO product_attributes (product_id, attr_name, attr_value, is_required, sort_order) VALUES (?, ?, ?, ?, ?)");
        foreach ($_POST['attr_name'] as $idx => $attrName) {
            $attrName = clean($attrName);
            $attrValue = clean($_POST['attr_value'][$idx] ?? '');
            $isRequired = isset($_POST['attr_required'][$idx]) ? 1 : 0;
            if (!empty($attrName) && !empty($attrValue)) {
                $attrIns->execute([$id, $attrName, $attrValue, $isRequired, $idx]);
            }
        }
    }
    
    // ========== Save Variasi Produk ==========
    // DEBUG TEMPORARY: log POST + FILES untuk debug upload variasi (HAPUS setelah selesai)
    @file_put_contents(__DIR__.'/../debug-variasi.log', 
        "===== " . date('Y-m-d H:i:s') . " =====\n" .
        "Product ID: $id\n" .
        "POST keys: " . implode(', ', array_keys($_POST)) . "\n" .
        "variation_options: " . json_encode($_POST['variation_options'] ?? []) . "\n" .
        "variation_has_images: " . json_encode($_POST['variation_has_images'] ?? []) . "\n" .
        "FILES variation_option_image: " . print_r($_FILES['variation_option_image'] ?? 'NONE', true) . "\n\n",
        FILE_APPEND
    );
    // Backup gambar existing dulu sebelum delete
    $existingImageMaps = [];
    $existingVars = $pdo->prepare("SELECT id, sort_order, image_map FROM product_variations WHERE product_id = ?");
    $existingVars->execute([$id]);
    foreach ($existingVars->fetchAll() as $ev) {
        if (!empty($ev['image_map'])) {
            $decoded = json_decode($ev['image_map'], true);
            if (is_array($decoded)) {
                // Normalize keys (trim whitespace)
                $normalized = [];
                foreach ($decoded as $k => $v) {
                    $normalized[trim($k)] = $v;
                }
                $existingImageMaps[$ev['sort_order']] = $normalized;
            }
        }
    }
    
    $pdo->prepare("DELETE FROM product_variations WHERE product_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM product_variation_items WHERE product_id = ?")->execute([$id]);
    
    // Save variasi: detect by DATA, bukan checkbox enable_variation (kadang missing dari POST)
    $shouldSaveVariation = false;
    if (!empty($_POST['variation_name'])) {
        foreach ($_POST['variation_name'] as $idx => $vn) {
            if (trim((string)$vn) !== '' && !empty($_POST['variation_options'][$idx])) {
                $opts = array_filter(array_map('trim', explode(',', $_POST['variation_options'][$idx])));
                if (count($opts) > 0) {
                    $shouldSaveVariation = true;
                    break;
                }
            }
        }
    }
    
    if ($shouldSaveVariation) {
        $varIns = $pdo->prepare("INSERT INTO product_variations (product_id, name, options, sort_order, image_map, has_images) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($_POST['variation_name'] as $idx => $varName) {
            $varName = clean($varName);
            $varOptionsRaw = array_map('trim', explode(',', $_POST['variation_options'][$idx] ?? ''));
            $varOptions = array_values(array_filter($varOptionsRaw, fn($o) => $o !== ''));
            $hasImages = !empty($_POST['variation_has_images'][$idx]) ? 1 : 0;
            
            // Build image map untuk variasi 1 (hasImages=1)
            $imageMap = [];
            if ($hasImages) {
                // Step 1: Mulai dari existing image map (yang udah pernah di-upload sebelumnya)
                if (isset($existingImageMaps[$idx]) && is_array($existingImageMaps[$idx])) {
                    foreach ($existingImageMaps[$idx] as $optName => $imgPath) {
                        $imageMap[trim($optName)] = $imgPath;
                    }
                }
                
                // Step 2: Cek file uploads baru (override existing kalau ada upload baru)
                if (isset($_FILES['variation_option_image']) && isset($_FILES['variation_option_image']['name'][$idx])) {
                    foreach ($_FILES['variation_option_image']['name'][$idx] as $optName => $fname) {
                        $optName = trim($optName);
                        $errCode = $_FILES['variation_option_image']['error'][$idx][$optName] ?? -1;
                        
                        // Log per file (untuk debug)
                        @file_put_contents(__DIR__.'/../debug-variasi.log',
                            "  File [$idx][$optName]: name='$fname', error=$errCode\n",
                            FILE_APPEND
                        );
                        
                        if (!empty($fname) && $errCode === 0) {
                            $tmpName = $_FILES['variation_option_image']['tmp_name'][$idx][$optName];
                            $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                            
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                                $safeOptName = preg_replace('/[^a-zA-Z0-9]/', '', $optName);
                                $newName = 'var_' . $id . '_' . $idx . '_' . $safeOptName . '_' . time() . '_' . rand(100,999) . '.' . $ext;
                                $uploadDir = __DIR__ . '/../uploads/products';
                                $destPath = $uploadDir . '/' . $newName;
                                
                                // Pastikan folder ada & writable
                                if (!is_dir($uploadDir)) {
                                    @mkdir($uploadDir, 0755, true);
                                }
                                
                                if (!is_writable($uploadDir)) {
                                    @chmod($uploadDir, 0755);
                                }
                                
                                $moveResult = @move_uploaded_file($tmpName, $destPath);
                                
                                @file_put_contents(__DIR__.'/../debug-variasi.log',
                                    "    → ext='$ext', dest='$destPath', writable=" . (is_writable($uploadDir) ? 'YES' : 'NO') . ", move=" . ($moveResult ? 'SUCCESS' : 'FAIL') . "\n",
                                    FILE_APPEND
                                );
                                
                                if ($moveResult) {
                                    $imageMap[$optName] = 'uploads/products/' . $newName;
                                }
                            } else {
                                @file_put_contents(__DIR__.'/../debug-variasi.log',
                                    "    → SKIP: ext '$ext' tidak valid\n",
                                    FILE_APPEND
                                );
                            }
                        }
                    }
                }
                
                // Step 3: Filter — hanya keep image untuk option yang masih ada
                // Tapi gunakan case-sensitive trim matching
                $finalMap = [];
                foreach ($varOptions as $opt) {
                    $optTrimmed = trim($opt);
                    if (isset($imageMap[$optTrimmed])) {
                        $finalMap[$optTrimmed] = $imageMap[$optTrimmed];
                    }
                }
                $imageMap = $finalMap;
            }
            
            if (!empty($varName) && !empty($varOptions)) {
                $varIns->execute([
                    $id, 
                    $varName, 
                    json_encode(array_values($varOptions)), 
                    $idx,
                    !empty($imageMap) ? json_encode($imageMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                    $hasImages
                ]);
            }
        }
        
        // Save kombinasi items
        if (!empty($_POST['vi_combination'])) {
            $viIns = $pdo->prepare("INSERT INTO product_variation_items (product_id, combination, price, stock, sku, image) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($_POST['vi_combination'] as $idx => $combo) {
                $price = (float)str_replace(['.', ','], '', $_POST['vi_price'][$idx] ?? '0');
                $stock = (int)($_POST['vi_stock'][$idx] ?? 0);
                $sku = clean($_POST['vi_sku'][$idx] ?? '');
                $existingImage = clean($_POST['vi_image_existing'][$idx] ?? '');
                $imagePath = $existingImage;
                if (!empty($_FILES['vi_image']['name'][$idx]) && ($_FILES['vi_image']['error'][$idx] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['vi_image']['name'][$idx],
                        'type' => $_FILES['vi_image']['type'][$idx],
                        'tmp_name' => $_FILES['vi_image']['tmp_name'][$idx],
                        'error' => $_FILES['vi_image']['error'][$idx],
                        'size' => $_FILES['vi_image']['size'][$idx],
                    ];
                    $uploaded = uploadVariationImage($file, $id);
                    if ($uploaded) $imagePath = $uploaded;
                }
                $viIns->execute([$id, clean($combo), $price, $stock, $sku, $imagePath ?: null]);
            }
        }
        
        // Auto-update base_price & stock di tabel products
        // (untuk listing produk + checkout fallback)
        $sumStmt = $pdo->prepare("SELECT MIN(price) AS min_price, SUM(stock) AS total_stock FROM product_variation_items WHERE product_id = ? AND price > 0");
        $sumStmt->execute([$id]);
        $sums = $sumStmt->fetch();
        if ($sums && $sums['min_price'] > 0) {
            $pdo->prepare("UPDATE products SET base_price = ?, stock = ? WHERE id = ?")
                ->execute([$sums['min_price'], (int)$sums['total_stock'], $id]);
        }
    }
    
    // ========== Save Tier Prices (Harga Grosir) ==========
    $pdo->prepare("DELETE FROM product_tier_prices WHERE product_id = ?")->execute([$id]);
    if (!empty($_POST['use_tier_pricing']) && !empty($_POST['tier_min'])) {
        $tierIns = $pdo->prepare("INSERT INTO product_tier_prices (product_id, min_qty, max_qty, price, sort_order) VALUES (?, ?, ?, ?, ?)");
        foreach ($_POST['tier_min'] as $idx => $minQty) {
            $minQty = (int)$minQty;
            $maxQty = !empty($_POST['tier_max'][$idx]) ? (int)$_POST['tier_max'][$idx] : null;
            $tierPrice = (float)preg_replace('/[^0-9]/', '', $_POST['tier_price'][$idx] ?? '0');
            if ($minQty > 0 && $tierPrice > 0) {
                $tierIns->execute([$id, $minQty, $maxQty, $tierPrice, $idx]);
            }
        }
    }
    
    // ========== Update completeness score ==========
    $imgsStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ?");
    $imgsStmt->execute([$id]);
    $imgs = $imgsStmt->fetchAll();
    
    $attrsStmt = $pdo->prepare("SELECT * FROM product_attributes WHERE product_id = ?");
    $attrsStmt->execute([$id]);
    $attrs = $attrsStmt->fetchAll();
    
    $productStmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $productStmt->execute([$id]);
    $productData = $productStmt->fetch();
    
    $completeness = calculateProductCompleteness($productData, $imgs, $attrs);
    $pdo->prepare("UPDATE products SET completeness_score = ? WHERE id = ?")
        ->execute([$completeness['score'], $id]);
    
    $pdo->commit();
    
    $message = $action === 'publish' ? '🎉 Produk berhasil dipublish!' : '💾 Draft tersimpan';
    redirect(SITE_URL . '/admin/produk.php', $message);
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['form_data'] = $_POST;
    $redirectUrl = $id ? SITE_URL . "/admin/produk-form.php?id=$id" : SITE_URL . '/admin/produk-form.php';
    redirect($redirectUrl, 'Error: ' . $e->getMessage(), 'error');
}
