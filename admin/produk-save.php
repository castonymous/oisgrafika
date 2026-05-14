<?php
// ============================================
// PROCESSOR: Save / Update Produk
// Dipanggil dari produk-form.php
// ============================================

require_once __DIR__ . '/../includes/admin-helpers.php';
requireAdmin();

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
];

// Status
$data['status'] = $action === 'publish' ? 'active' : 'draft';
$data['is_active'] = $action === 'publish' ? 1 : 0;

// Slug
$customSlug = clean($_POST['slug'] ?? '');
$data['slug'] = !empty($customSlug) ? $customSlug : generateSlug($data['name'], $pdo, $id ?: null);

// ========== Validasi minimum (draft boleh kosong) ==========
$errors = [];
if (empty($data['name'])) {
    $errors[] = 'Nama produk wajib diisi';
}
if (empty($data['category_id'])) {
    $errors[] = 'Kategori wajib dipilih';
}

// Validasi ketat untuk PUBLISH
if ($action === 'publish') {
    if (strlen($data['name']) < 10) $errors[] = 'Nama produk min 10 karakter (untuk publish)';
    if (strlen($data['description']) < 50) $errors[] = 'Deskripsi min 50 karakter (untuk publish)';
    if ($data['base_price'] <= 0) $errors[] = 'Harga produk wajib > 0';
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
            status = ?, is_active = ?, tags = ?, internal_note = ?, seo_title = ?, seo_description = ?
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
    $pdo->prepare("DELETE FROM product_variations WHERE product_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM product_variation_items WHERE product_id = ?")->execute([$id]);
    
    if (!empty($_POST['enable_variation']) && !empty($_POST['variation_name'])) {
        $varIns = $pdo->prepare("INSERT INTO product_variations (product_id, name, options, sort_order) VALUES (?, ?, ?, ?)");
        foreach ($_POST['variation_name'] as $idx => $varName) {
            $varName = clean($varName);
            $varOptions = array_map('trim', explode(',', $_POST['variation_options'][$idx] ?? ''));
            $varOptions = array_filter($varOptions);
            if (!empty($varName) && !empty($varOptions)) {
                $varIns->execute([$id, $varName, json_encode(array_values($varOptions)), $idx]);
            }
        }
        
        // Save kombinasi items
        if (!empty($_POST['vi_combination'])) {
            $viIns = $pdo->prepare("INSERT INTO product_variation_items (product_id, combination, price, stock, sku) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['vi_combination'] as $idx => $combo) {
                $price = (float)str_replace(['.', ','], '', $_POST['vi_price'][$idx] ?? '0');
                $stock = (int)($_POST['vi_stock'][$idx] ?? 0);
                $sku = clean($_POST['vi_sku'][$idx] ?? '');
                $viIns->execute([$id, clean($combo), $price, $stock, $sku]);
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
