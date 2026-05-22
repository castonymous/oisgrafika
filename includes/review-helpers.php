<?php
// ============================================
// REVIEW HELPER FUNCTIONS
// ============================================

/**
 * Hitung rata-rata rating produk + total review
 */
function getProductRatingStats($productId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_reviews,
            AVG(rating) as avg_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as r5,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as r4,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as r3,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as r2,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as r1
        FROM product_reviews
        WHERE product_id = ? AND is_active = 1
    ");
    $stmt->execute([$productId]);
    $stats = $stmt->fetch();
    return $stats ?: ['total_reviews' => 0, 'avg_rating' => 0, 'r5' => 0, 'r4' => 0, 'r3' => 0, 'r2' => 0, 'r1' => 0];
}

/**
 * Ambil reviews untuk produk (dengan images & replies)
 */
function getProductReviews($productId, $pdo, $limit = 20, $offset = 0, $filterStars = null) {
    $whereStars = $filterStars ? " AND r.rating = " . (int)$filterStars : "";
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name as user_name, u.email as user_email
        FROM product_reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.product_id = ? AND r.is_active = 1 $whereStars
        ORDER BY r.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$productId]);
    $reviews = $stmt->fetchAll();
    
    // Ambil images & replies
    foreach ($reviews as &$rev) {
        $imgStmt = $pdo->prepare("SELECT * FROM review_images WHERE review_id = ? ORDER BY sort_order");
        $imgStmt->execute([$rev['id']]);
        $rev['images'] = $imgStmt->fetchAll();
        
        $replyStmt = $pdo->prepare("SELECT * FROM review_replies WHERE review_id = ? ORDER BY created_at DESC LIMIT 1");
        $replyStmt->execute([$rev['id']]);
        $rev['reply'] = $replyStmt->fetch() ?: null;
    }
    unset($rev);
    
    return $reviews;
}

/**
 * Cek apakah user sudah review produk dalam order tertentu
 */
function hasReviewed($orderId, $productId, $userId, $pdo) {
    $stmt = $pdo->prepare("SELECT id FROM product_reviews WHERE order_id = ? AND product_id = ? AND user_id = ?");
    $stmt->execute([$orderId, $productId, $userId]);
    return (bool) $stmt->fetch();
}

/**
 * Ambil semua orders yang bisa di-review (status: selesai/delivered) tapi belum di-review semua
 */
function getReviewableOrders($userId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT o.*, 
            (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
            (SELECT COUNT(*) FROM product_reviews pr WHERE pr.order_id = o.id AND pr.user_id = ?) as reviewed_count
        FROM orders o
        WHERE o.user_id = ? 
            AND o.order_status IN ('selesai', 'delivered', 'completed')
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchAll();
}

/**
 * Ambil reviews yang udah dibuat user (untuk halaman "Penilaian Saya")
 */
function getUserReviews($userId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT r.*, p.name as product_name, p.slug as product_slug, p.image as product_image,
               o.order_code,
               (SELECT COUNT(*) FROM review_images WHERE review_id = r.id) as image_count,
               (SELECT id FROM review_replies WHERE review_id = r.id LIMIT 1) as reply_id
        FROM product_reviews r
        JOIN products p ON r.product_id = p.id
        JOIN orders o ON r.order_id = o.id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Render bintang HTML (untuk display)
 */
function renderStars($rating, $size = 16) {
    $rating = (float)$rating;
    $html = '<span class="star-rating" style="font-size:'.$size.'px;color:#fbbf24;">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= floor($rating)) {
            $html .= '★';
        } elseif ($i - 0.5 <= $rating) {
            $html .= '★'; // half star (simple - bisa di-improve dengan icon halfstar)
        } else {
            $html .= '<span style="color:#e5e7eb;">★</span>';
        }
    }
    $html .= '</span>';
    return $html;
}

/**
 * Auto-update rating produk berdasar reviews
 */
function updateProductRating($productId, $pdo) {
    $stats = getProductRatingStats($productId, $pdo);
    $avg = $stats['total_reviews'] > 0 ? round($stats['avg_rating'], 2) : 0;
    $pdo->prepare("UPDATE products SET rating = ? WHERE id = ?")->execute([$avg, $productId]);
}
