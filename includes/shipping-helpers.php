<?php
// ============================================
// SHIPPING CALCULATOR & ADDRESS HELPERS
// ============================================

require_once __DIR__ . '/functions.php';

/**
 * Hitung ongkir berdasarkan berat (gram) dan courier code
 * Return: ['cost' => 10000, 'method' => array, 'final_weight_kg' => 1]
 */
function calculateShipping($weightGram, $courierCode, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM shipping_methods WHERE code = ? AND is_active = 1");
    $stmt->execute([$courierCode]);
    $method = $stmt->fetch();
    
    if (!$method) return null;
    
    // Convert gram ke kg (ceil)
    $weightKg = max(1, ceil($weightGram / 1000));
    
    // Cek minimum weight (Cargo min 5kg)
    if ($weightKg < $method['min_weight_kg']) {
        $weightKg = $method['min_weight_kg'];
    }
    
    $cost = 0;
    if ($method['type'] === 'regular') {
        // Regular: base_cost per kg (mis. JNT 10rb/kg)
        $cost = $method['cost_per_kg'] * $weightKg;
    } else {
        // Cargo: base_cost untuk min_weight_kg + cost_per_kg untuk sisa
        $extraKg = $weightKg - $method['min_weight_kg'];
        $cost = $method['base_cost'] + ($method['cost_per_kg'] * $extraKg);
    }
    
    return [
        'cost' => $cost,
        'method' => $method,
        'final_weight_kg' => $weightKg,
    ];
}

/**
 * Get semua shipping options untuk produk dengan berat tertentu
 */
function getShippingOptions($weightGram, $pdo) {
    $methods = $pdo->query("SELECT * FROM shipping_methods WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
    $options = [];
    
    foreach ($methods as $m) {
        $result = calculateShipping($weightGram, $m['code'], $pdo);
        if ($result) {
            $options[] = [
                'code' => $m['code'],
                'name' => $m['name'],
                'type' => $m['type'],
                'cost' => $result['cost'],
                'final_weight_kg' => $result['final_weight_kg'],
                'estimate_min' => $m['estimate_days_min'],
                'estimate_max' => $m['estimate_days_max'],
                'estimate_label' => 'Tiba ' . date('d M', strtotime('+' . $m['estimate_days_min'] . ' days')) . ' - ' . date('d M', strtotime('+' . $m['estimate_days_max'] . ' days')),
            ];
        }
    }
    
    return $options;
}

/**
 * Get alamat default user
 */
function getDefaultAddress($userId, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Format alamat singkat
 */
function formatAddressShort($addr) {
    if (!$addr) return '';
    $parts = [$addr['address_line']];
    if (!empty($addr['village'])) $parts[] = $addr['village'];
    $parts[] = $addr['district'];
    $parts[] = $addr['city'];
    return implode(', ', $parts);
}

/**
 * Cek voucher applicability
 */
function checkVoucher($code, $subtotal, $userId, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE code = ? AND is_active = 1 AND NOW() BETWEEN start_date AND end_date");
    $stmt->execute([$code]);
    $v = $stmt->fetch();
    
    if (!$v) return ['ok' => false, 'msg' => 'Kode voucher tidak ditemukan atau sudah expired'];
    
    if ($v['quota'] !== null && $v['used_count'] >= $v['quota']) {
        return ['ok' => false, 'msg' => 'Kuota voucher habis'];
    }
    
    if ($subtotal < $v['min_purchase']) {
        $kurang = $v['min_purchase'] - $subtotal;
        return ['ok' => false, 'msg' => 'Min. belanja Rp ' . number_format($v['min_purchase'], 0, ',', '.') . '. Kurang Rp ' . number_format($kurang, 0, ',', '.')];
    }
    
    // Cek user belum pakai
    $usedStmt = $pdo->prepare("SELECT COUNT(*) FROM voucher_usages WHERE voucher_id = ? AND user_id = ?");
    $usedStmt->execute([$v['id'], $userId]);
    if ($usedStmt->fetchColumn() > 0) {
        return ['ok' => false, 'msg' => 'Voucher ini sudah pernah kamu gunakan'];
    }
    
    // Hitung discount
    $discount = 0;
    if ($v['discount_type'] === 'percentage') {
        $discount = $subtotal * ($v['discount_value'] / 100);
        if ($v['max_discount']) $discount = min($discount, $v['max_discount']);
    } else {
        $discount = $v['discount_value'];
    }
    
    return [
        'ok' => true,
        'voucher' => $v,
        'discount' => $discount,
    ];
}

/**
 * Hitung total berat produk di keranjang
 */
function calculateCartWeight($userId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(c.quantity * COALESCE(p.weight, 500)), 0) as total_weight
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}
