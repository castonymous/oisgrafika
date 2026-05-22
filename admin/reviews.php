<?php
$pageTitle = 'Kelola Review Produk';
require_once __DIR__ . '/../includes/admin-helpers.php';
require_once __DIR__ . '/../includes/review-helpers.php';
requireAdmin();

$adminId = $_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        redirect($_SERVER['REQUEST_URI'], 'Token tidak valid', 'error');
    }
    
    $reviewId = (int)($_POST['review_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reply' && $reviewId > 0) {
        $replyText = trim($_POST['reply_text'] ?? '');
        if (empty($replyText)) {
            redirect($_SERVER['REQUEST_URI'], 'Balasan tidak boleh kosong', 'error');
        }
        
        // Cek apakah udah ada reply, kalau ada update, kalau ga ada insert
        $existStmt = $pdo->prepare("SELECT id FROM review_replies WHERE review_id = ?");
        $existStmt->execute([$reviewId]);
        $existing = $existStmt->fetch();
        
        if ($existing) {
            $pdo->prepare("UPDATE review_replies SET reply = ?, admin_user_id = ? WHERE review_id = ?")
                ->execute([$replyText, $adminId, $reviewId]);
        } else {
            $pdo->prepare("INSERT INTO review_replies (review_id, admin_user_id, reply) VALUES (?, ?, ?)")
                ->execute([$reviewId, $adminId, $replyText]);
        }
        redirect(SITE_URL . '/admin/reviews', '✓ Balasan tersimpan');
    } elseif ($action === 'toggle_active' && $reviewId > 0) {
        $pdo->prepare("UPDATE product_reviews SET is_active = NOT is_active WHERE id = ?")->execute([$reviewId]);
        // Update rating produk
        $prodStmt = $pdo->prepare("SELECT product_id FROM product_reviews WHERE id = ?");
        $prodStmt->execute([$reviewId]);
        $r = $prodStmt->fetch();
        if ($r) updateProductRating($r['product_id'], $pdo);
        redirect(SITE_URL . '/admin/reviews', '✓ Status review diupdate');
    }
}

// Filter
$filterStars = isset($_GET['stars']) ? (int)$_GET['stars'] : 0;
$filterUnreplied = isset($_GET['unreplied']) ? 1 : 0;

$where = ["1=1"];
$params = [];
if ($filterStars > 0) {
    $where[] = "r.rating = ?";
    $params[] = $filterStars;
}
if ($filterUnreplied) {
    $where[] = "NOT EXISTS (SELECT 1 FROM review_replies rr WHERE rr.review_id = r.id)";
}
$whereSql = implode(' AND ', $where);

$reviewStmt = $pdo->prepare("
    SELECT r.*, u.full_name as user_name, u.email as user_email,
        p.name as product_name, p.slug as product_slug, p.image as product_image,
        o.order_code,
        (SELECT COUNT(*) FROM review_images WHERE review_id = r.id) as image_count,
        (SELECT reply FROM review_replies WHERE review_id = r.id LIMIT 1) as existing_reply,
        (SELECT created_at FROM review_replies WHERE review_id = r.id LIMIT 1) as reply_at
    FROM product_reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    JOIN orders o ON r.order_id = o.id
    WHERE $whereSql
    ORDER BY r.created_at DESC
    LIMIT 50
");
$reviewStmt->execute($params);
$reviews = $reviewStmt->fetchAll();

// Stats
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM review_replies WHERE review_id = product_reviews.id) THEN 1 ELSE 0 END) as unreplied,
        AVG(rating) as avg_rating
    FROM product_reviews WHERE is_active = 1
")->fetch();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.admin-reviews { max-width: 1100px; margin: 0 auto; padding: 20px; }
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
.stat-card { background: white; border-radius: 8px; padding: 16px; border: 1px solid var(--border); text-align: center; }
.stat-value { font-size: 24px; font-weight: 800; color: var(--primary); }
.stat-label { font-size: 12px; color: var(--text-muted); margin-top: 4px; }

.filter-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; padding: 12px; background: white; border-radius: 8px; border: 1px solid var(--border); }
.filter-bar a { padding: 6px 12px; border: 1px solid var(--border); border-radius: 20px; font-size: 12px; text-decoration: none; color: var(--text); background: white; }
.filter-bar a.active, .filter-bar a:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }

.admin-review-card { background: white; border: 1px solid var(--border); border-radius: 8px; padding: 14px; margin-bottom: 12px; }
.admin-rev-head { display: flex; gap: 10px; align-items: flex-start; padding-bottom: 10px; border-bottom: 1px solid var(--border); margin-bottom: 10px; }
.admin-rev-thumb { width: 50px; height: 50px; border-radius: 6px; overflow: hidden; background: var(--bg-gray); flex-shrink: 0; }
.admin-rev-thumb img { width: 100%; height: 100%; object-fit: cover; }
.admin-rev-meta { flex: 1; }

.reply-form { margin-top: 10px; padding: 12px; background: #f9fafb; border-radius: 6px; }
.reply-form textarea { width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 4px; font-size: 13px; font-family: inherit; min-height: 60px; resize: vertical; }
.reply-form button { background: var(--primary); color: white; border: none; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; margin-top: 8px; }
</style>

<div class="admin-reviews">
    <h1 style="font-size:22px;margin-bottom:6px;">⭐ Kelola Review Produk</h1>
    <p style="color:var(--text-muted);margin-bottom:20px;">Bales review customer, hide/show review</p>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= (int)$stats['total'] ?></div>
            <div class="stat-label">Total Review</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:#f59e0b;"><?= (int)$stats['unreplied'] ?></div>
            <div class="stat-label">Belum Dibalas</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['avg_rating'] ?: 0, 2) ?> ★</div>
            <div class="stat-label">Rata-rata Rating</div>
        </div>
    </div>
    
    <div class="filter-bar">
        <span style="font-size:12px;color:var(--text-muted);align-self:center;margin-right:4px;">Filter:</span>
        <a href="?" class="<?= !$filterStars && !$filterUnreplied ? 'active' : '' ?>">Semua</a>
        <a href="?unreplied=1" class="<?= $filterUnreplied ? 'active' : '' ?>">Belum Dibalas</a>
        <?php for ($s = 5; $s >= 1; $s--): ?>
            <a href="?stars=<?= $s ?>" class="<?= $filterStars == $s ? 'active' : '' ?>"><?= $s ?>★</a>
        <?php endfor; ?>
    </div>
    
    <?php if (empty($reviews)): ?>
        <div style="text-align:center;padding:40px;color:var(--text-muted);">Belum ada review</div>
    <?php else: foreach ($reviews as $rev): ?>
        <div class="admin-review-card">
            <div class="admin-rev-head">
                <a href="<?= url('produk', $rev['product_slug']) ?>" class="admin-rev-thumb" target="_blank">
                    <?php if ($rev['product_image']): ?>
                        <img src="<?= SITE_URL ?>/<?= clean($rev['product_image']) ?>" alt="">
                    <?php endif; ?>
                </a>
                <div class="admin-rev-meta">
                    <div style="font-weight:600;font-size:13px;"><?= clean($rev['product_name']) ?></div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                        Order: <code><?= clean($rev['order_code']) ?></code> • 
                        <?= clean($rev['user_name'] ?: $rev['user_email']) ?> • 
                        <?= date('d M Y H:i', strtotime($rev['created_at'])) ?>
                    </div>
                </div>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                    <button type="submit" style="background:none;border:1px solid var(--border);padding:4px 10px;border-radius:4px;font-size:11px;cursor:pointer;color:<?= $rev['is_active'] ? '#10b981' : '#ef4444' ?>;">
                        <?= $rev['is_active'] ? '👁 Aktif' : '🚫 Sembunyikan' ?>
                    </button>
                </form>
            </div>
            
            <div>
                <?= renderStars($rev['rating'], 14) ?>
                <?php if (!empty($rev['variation_label'])): ?>
                    <span style="font-size:11px;color:var(--text-muted);margin-left:8px;">Varian: <?= clean(str_replace('|', ' / ', $rev['variation_label'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($rev['comment'])): ?>
                    <p style="font-size:13px;margin:8px 0;line-height:1.5;"><?= clean($rev['comment']) ?></p>
                <?php endif; ?>
                <?php if ($rev['image_count'] > 0): 
                    $imgStmt = $pdo->prepare("SELECT image_path FROM review_images WHERE review_id = ?");
                    $imgStmt->execute([$rev['id']]);
                    $images = $imgStmt->fetchAll();
                ?>
                    <div style="display:flex;gap:6px;margin-top:6px;">
                        <?php foreach ($images as $img): ?>
                            <a href="<?= SITE_URL ?>/<?= clean($img['image_path']) ?>" target="_blank">
                                <img src="<?= SITE_URL ?>/<?= clean($img['image_path']) ?>" style="width:60px;height:60px;object-fit:cover;border-radius:4px;border:1px solid var(--border);" loading="lazy">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="reply-form">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px;">
                        🏪 Balasan Toko <?= $rev['existing_reply'] ? '(edit balasan yang udah ada)' : '' ?>
                    </label>
                    <textarea name="reply_text" placeholder="Tulis balasan untuk customer..."><?= clean($rev['existing_reply'] ?? '') ?></textarea>
                    <button type="submit"><?= $rev['existing_reply'] ? 'Update Balasan' : 'Kirim Balasan' ?></button>
                    <?php if ($rev['reply_at']): ?>
                        <span style="font-size:11px;color:var(--text-muted);margin-left:10px;">Terakhir di-update: <?= date('d M Y H:i', strtotime($rev['reply_at'])) ?></span>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
