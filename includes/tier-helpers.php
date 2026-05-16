<?php
// ============================================
// TIER PRICING & FILTER HELPERS
// ============================================

require_once __DIR__ . '/functions.php';

/**
 * Get harga produk sesuai quantity
 * Kalo pakai tier, cari tier yang match
 * Kalo ga, return base_price
 */
function getProductPrice($product, $qty, $pdo) {
    if (empty($product['use_tier_pricing'])) {
        return (float)$product['base_price'];
    }
    
    $stmt = $pdo->prepare("
        SELECT price FROM product_tier_prices 
        WHERE product_id = ? AND min_qty <= ? AND (max_qty IS NULL OR max_qty >= ?)
        ORDER BY min_qty DESC LIMIT 1
    ");
    $stmt->execute([$product['id'], $qty, $qty]);
    $tier = $stmt->fetchColumn();
    
    return $tier !== false ? (float)$tier : (float)$product['base_price'];
}

/**
 * Get semua tier prices untuk display
 */
function getProductTiers($productId, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM product_tier_prices WHERE product_id = ? ORDER BY min_qty ASC");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/**
 * Format tier range untuk display
 * "1-9 unit", "10-49 unit", "100+ unit"
 */
function formatTierRange($tier) {
    if ($tier['max_qty']) {
        return $tier['min_qty'] . '-' . $tier['max_qty'] . ' unit';
    }
    return $tier['min_qty'] . '+ unit';
}

/**
 * Konversi harga format Rp ke int
 * "Rp 50.000" -> 50000
 */
function parseRupiah($str) {
    return (int)preg_replace('/[^0-9]/', '', $str);
}
