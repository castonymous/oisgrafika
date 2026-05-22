<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
requireLogin();

$user = getCurrentUser();
$tab = $_GET['tab'] ?? 'overview';

// Handle update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $name = clean($_POST['name'] ?? '');
        $phone = clean($_POST['phone'] ?? '');
        $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?")
            ->execute([$name, $phone, $user['id']]);
        redirect(SITE_URL . '/dashboard.php?tab=profile', 'Profil diperbarui');
    }
}

// Stats
$statStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN final_amount ELSE 0 END), 0) as total_spent,
        COUNT(CASE WHEN order_status = 'pending' THEN 1 END) as pending_orders
    FROM orders WHERE user_id = ?
");
$statStmt->execute([$user['id']]);
$stats = $statStmt->fetch();

// Komisi referral
$refStmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT referred_user_id) as referral_count,
        COALESCE(SUM(commission), 0) as total_commission,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN commission ELSE 0 END), 0) as paid_commission
    FROM referral_commissions WHERE referrer_id = ?
");
$refStmt->execute([$user['id']]);
$refStats = $refStmt->fetch();

// Orders
if ($tab === 'orders') {
    // Ambil orders + first item info (preview)
    $orderStmt = $pdo->prepare("
        SELECT o.*,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
            (SELECT product_name FROM order_items WHERE order_id = o.id ORDER BY id ASC LIMIT 1) as first_product_name,
            (SELECT variation_label FROM order_items WHERE order_id = o.id ORDER BY id ASC LIMIT 1) as first_variation,
            (SELECT quantity FROM order_items WHERE order_id = o.id ORDER BY id ASC LIMIT 1) as first_qty,
            (SELECT p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = o.id ORDER BY oi.id ASC LIMIT 1) as first_image,
            (SELECT COUNT(*) FROM product_reviews WHERE order_id = o.id AND user_id = o.user_id) as reviewed_count
        FROM orders o 
        WHERE o.user_id = ? 
        ORDER BY o.created_at DESC
    ");
    $orderStmt->execute([$user['id']]);
    $orders = $orderStmt->fetchAll();
}

// Penilaian Saya
require_once __DIR__ . '/includes/review-helpers.php';
$myReviews = [];
if ($tab === 'reviews') {
    $myReviews = getUserReviews($user['id'], $pdo);
}

// Referral data
if ($tab === 'referral') {
    $myReferralsStmt = $pdo->prepare("SELECT u.name, u.email, u.created_at FROM users u WHERE u.referred_by = ? ORDER BY u.created_at DESC");
    $myReferralsStmt->execute([$user['id']]);
    $myReferrals = $myReferralsStmt->fetchAll();
}
?>

<div class="dashboard">
    <aside class="dash-sidebar">
        <div class="dash-user-info">
            <div class="dash-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <div class="dash-user-name"><?= clean($user['name']) ?></div>
            <div class="dash-user-email"><?= clean($user['email']) ?></div>
        </div>

        <ul class="dash-menu">
            <li><a href="?tab=overview" class="<?= $tab === 'overview' ? 'active' : '' ?>">📊 Ringkasan</a></li>
            <li><a href="?tab=orders" class="<?= $tab === 'orders' ? 'active' : '' ?>">📦 Pesanan Saya</a></li>
            <li><a href="?tab=reviews" class="<?= $tab === 'reviews' ? 'active' : '' ?>">⭐ Penilaian Saya</a></li>
            <li><a href="?tab=referral" class="<?= $tab === 'referral' ? 'active' : '' ?>">🎁 Program Referral</a></li>
            <li><a href="?tab=profile" class="<?= $tab === 'profile' ? 'active' : '' ?>">⚙️ Profil</a></li>
            <?php if (isAdmin()): ?>
                <li><a href="<?= SITE_URL ?>/admin/index.php" style="color: var(--primary); font-weight: 600;">🛠️ Admin Panel</a></li>
            <?php endif; ?>
            <li><a href="<?= SITE_URL ?>/logout.php" style="color: var(--danger);">🚪 Keluar</a></li>
        </ul>
    </aside>

    <div class="dash-content">
        <?php if ($tab === 'overview'): ?>
            <h1 class="dash-title">Selamat datang, <?= clean(explode(' ', $user['name'])[0]) ?>! 👋</h1>
            <p class="dash-subtitle">Pantau aktivitas akunmu di sini</p>

            <div class="stat-grid">
                <div class="stat-card primary">
                    <div class="stat-label">Total Pesanan</div>
                    <div class="stat-value"><?= $stats['total_orders'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Belanja</div>
                    <div class="stat-value" style="font-size: 18px;"><?= rupiah($stats['total_spent']) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?= $stats['pending_orders'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Komisi Referral</div>
                    <div class="stat-value" style="font-size: 18px;"><?= rupiah($refStats['total_commission']) ?></div>
                </div>
            </div>

            <div class="referral-box">
                <h3>🎁 Kode Referral Kamu</h3>
                <p>Bagikan ke teman, kamu dapat komisi <?= REFERRAL_COMMISSION_PERCENT ?>% dari setiap order mereka!</p>
                <div class="referral-code-box">
                    <span class="referral-code"><?= clean($user['referral_code']) ?></span>
                    <button type="button" class="btn-copy" data-copy="<?= clean($user['referral_code']) ?>">Salin</button>
                </div>
                <a href="?tab=referral" style="display: inline-block; margin-top: 12px; color: white; font-size: 13px; text-decoration: underline;">Detail program →</a>
            </div>

        <?php elseif ($tab === 'orders'): ?>
            <h1 class="dash-title">Pesanan Saya</h1>
            <p class="dash-subtitle">Riwayat pesanan kamu di Ois Grafika</p>

            <?php if (empty($orders)): ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 48px; margin-bottom: 16px;">📦</div>
                    <p style="color: var(--text-muted); margin-bottom: 16px;">Belum ada pesanan</p>
                    <a href="<?= SITE_URL ?>/produk" class="btn btn-primary-solid">Mulai Belanja</a>
                </div>
            <?php else: ?>
                <div class="order-list-shopee">
                    <?php foreach ($orders as $o): 
                        $statusLabel = [
                            'pending' => ['label' => 'Menunggu Pembayaran', 'color' => '#f59e0b'],
                            'paid' => ['label' => 'Dibayar', 'color' => '#3b82f6'],
                            'processing' => ['label' => 'Diproses', 'color' => '#8b5cf6'],
                            'shipped' => ['label' => 'Dikirim', 'color' => '#06b6d4'],
                            'dikirim' => ['label' => 'Dikirim', 'color' => '#06b6d4'],
                            'delivered' => ['label' => 'Diterima', 'color' => '#10b981'],
                            'selesai' => ['label' => 'Selesai', 'color' => '#10b981'],
                            'completed' => ['label' => 'Selesai', 'color' => '#10b981'],
                            'cancelled' => ['label' => 'Dibatalkan', 'color' => '#ef4444'],
                            'refund' => ['label' => 'Pengembalian', 'color' => '#ef4444'],
                        ];
                        $statusInfo = $statusLabel[$o['order_status']] ?? ['label' => ucfirst($o['order_status']), 'color' => '#888'];
                        $isCompleted = in_array($o['order_status'], ['selesai', 'completed', 'delivered']);
                        $needsReview = $isCompleted && $o['reviewed_count'] < $o['item_count'];
                    ?>
                        <a href="<?= SITE_URL ?>/order/<?= clean($o['order_code']) ?>" class="order-card-shopee">
                            <div class="order-card-head">
                                <div class="order-card-head-left">
                                    <span class="store-tag">🏪 Ois Grafika</span>
                                </div>
                                <div class="order-card-head-right" style="color:<?= $statusInfo['color'] ?>;">
                                    <?= strtoupper($statusInfo['label']) ?>
                                </div>
                            </div>
                            
                            <div class="order-card-body">
                                <div class="order-card-thumb">
                                    <?php if (!empty($o['first_image'])): ?>
                                        <img src="<?= SITE_URL ?>/<?= clean($o['first_image']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="thumb-placeholder">📦</div>
                                    <?php endif; ?>
                                </div>
                                <div class="order-card-info">
                                    <div class="product-name-line"><?= clean($o['first_product_name'] ?: 'Produk') ?></div>
                                    <?php if (!empty($o['first_variation'])): ?>
                                        <div class="variation-line">Variasi: <?= clean(str_replace('|', ' / ', $o['first_variation'])) ?></div>
                                    <?php endif; ?>
                                    <div class="qty-line">x<?= (int)$o['first_qty'] ?></div>
                                    <?php if ($o['item_count'] > 1): ?>
                                        <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">+ <?= $o['item_count'] - 1 ?> produk lainnya</div>
                                    <?php endif; ?>
                                </div>
                                <div class="order-card-price">
                                    <div style="font-size:11px;color:var(--text-muted);">Total Pesanan:</div>
                                    <div class="total-amount"><?= rupiah($o['final_amount']) ?></div>
                                </div>
                            </div>
                            
                            <div class="order-card-foot">
                                <div class="order-meta">
                                    <span style="color:var(--text-muted);font-size:11px;">No. Pesanan:</span>
                                    <span style="font-family:monospace;font-size:11px;font-weight:600;"><?= clean($o['order_code']) ?></span>
                                    <span style="color:var(--text-muted);font-size:11px;margin-left:10px;"><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></span>
                                </div>
                                <div class="order-actions">
                                    <?php if ($needsReview): ?>
                                        <span class="badge-review">⭐ Beri Penilaian</span>
                                    <?php endif; ?>
                                    <span style="color:var(--primary);font-size:12px;font-weight:600;">Lihat Detail →</span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($tab === 'reviews'): ?>
            <h1 class="dash-title">Penilaian Saya</h1>
            <p class="dash-subtitle">Review yang sudah kamu berikan</p>

            <?php if (empty($myReviews)): ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 48px; margin-bottom: 16px;">⭐</div>
                    <p style="color: var(--text-muted); margin-bottom: 16px;">Belum ada penilaian yang kamu berikan</p>
                    <a href="?tab=orders" class="btn btn-primary-solid">Lihat Pesanan</a>
                </div>
            <?php else: ?>
                <div class="my-reviews-list">
                    <?php foreach ($myReviews as $rev): ?>
                        <div class="my-review-item">
                            <div class="review-product-info">
                                <a href="<?= url('produk', $rev['product_slug']) ?>" class="review-product-thumb">
                                    <?php if ($rev['product_image']): ?>
                                        <img src="<?= SITE_URL ?>/<?= clean($rev['product_image']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="thumb-placeholder">📦</div>
                                    <?php endif; ?>
                                </a>
                                <div class="review-product-detail">
                                    <a href="<?= url('produk', $rev['product_slug']) ?>" style="color:var(--text);text-decoration:none;">
                                        <div style="font-weight:600;font-size:13px;line-height:1.4;"><?= clean($rev['product_name']) ?></div>
                                    </a>
                                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                                        No. Pesanan: <code><?= clean($rev['order_code']) ?></code>
                                    </div>
                                    <?php if (!empty($rev['variation_label'])): ?>
                                        <div style="font-size:11px;color:var(--text-muted);">Varian: <?= clean(str_replace('|', ' / ', $rev['variation_label'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="review-content">
                                <div style="margin-bottom:6px;">
                                    <?= renderStars($rev['rating'], 16) ?>
                                    <span style="color:var(--text-muted);font-size:11px;margin-left:8px;"><?= date('d M Y', strtotime($rev['created_at'])) ?></span>
                                </div>
                                <?php if (!empty($rev['comment'])): ?>
                                    <p style="margin:0;color:var(--text);font-size:13px;line-height:1.5;"><?= nl2br(clean($rev['comment'])) ?></p>
                                <?php endif; ?>
                                <?php if ($rev['image_count'] > 0): ?>
                                    <div style="margin-top:6px;font-size:11px;color:var(--text-muted);">📷 <?= $rev['image_count'] ?> foto</div>
                                <?php endif; ?>
                                <?php if ($rev['reply_id']): ?>
                                    <div style="margin-top:8px;padding:8px 10px;background:#f9fafb;border-left:3px solid var(--primary);border-radius:4px;font-size:12px;">
                                        💬 <strong>Toko sudah membalas review-mu</strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($tab === 'referral'): ?>
            <h1 class="dash-title">Program Referral</h1>
            <p class="dash-subtitle">Ajak teman, dapat komisi <?= REFERRAL_COMMISSION_PERCENT ?>% setiap order</p>

            <div class="referral-box">
                <h3>🎁 Kode Referral Pribadimu</h3>
                <p>Bagikan kode atau link ini ke teman</p>
                <div class="referral-code-box">
                    <span class="referral-code"><?= clean($user['referral_code']) ?></span>
                    <button type="button" class="btn-copy" data-copy="<?= clean($user['referral_code']) ?>">Salin Kode</button>
                </div>
                <div style="margin-top: 12px; padding: 12px; background: rgba(255,255,255,0.15); border-radius: var(--radius); font-size: 12px; word-break: break-all;">
                    🔗 <strong>Link Referral:</strong><br>
                    <?= SITE_URL ?>/register.php?ref=<?= clean($user['referral_code']) ?>
                    <button type="button" class="btn-copy" data-copy="<?= SITE_URL ?>/register.php?ref=<?= clean($user['referral_code']) ?>" style="margin-left: 8px;">Salin Link</button>
                </div>
            </div>

            <div class="stat-grid" style="grid-template-columns: repeat(3, 1fr);">
                <div class="stat-card">
                    <div class="stat-label">Total Referral</div>
                    <div class="stat-value"><?= $refStats['referral_count'] ?> orang</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Komisi</div>
                    <div class="stat-value" style="font-size: 18px;"><?= rupiah($refStats['total_commission']) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Sudah Dibayar</div>
                    <div class="stat-value" style="font-size: 18px;"><?= rupiah($refStats['paid_commission']) ?></div>
                </div>
            </div>

            <h3 style="font-size: 16px; margin: 24px 0 12px;">Daftar Referral Kamu</h3>
            <?php if (empty($myReferrals)): ?>
                <div style="background: var(--bg-gray); padding: 24px; border-radius: var(--radius); text-align: center; color: var(--text-muted);">
                    Belum ada referral. Yuk ajak teman gabung!
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <th style="text-align: left; padding: 10px;">Nama</th>
                                <th style="text-align: left; padding: 10px;">Email</th>
                                <th style="text-align: left; padding: 10px;">Tanggal Daftar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myReferrals as $r): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 10px;"><?= clean($r['name']) ?></td>
                                    <td style="padding: 10px; color: var(--text-light);"><?= clean($r['email']) ?></td>
                                    <td style="padding: 10px; color: var(--text-muted);"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        <?php elseif ($tab === 'profile'): ?>
            <h1 class="dash-title">Profil Saya</h1>
            <p class="dash-subtitle">Kelola informasi akun kamu</p>

            <form method="POST" style="max-width: 500px;">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                
                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="name" class="form-input" value="<?= clean($user['name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" value="<?= clean($user['email']) ?>" disabled style="background: var(--bg-gray);">
                    <div class="form-help">Email tidak bisa diubah</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">No. HP</label>
                    <input type="tel" name="phone" class="form-input" value="<?= clean($user['phone']) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Kode Referral Pribadi</label>
                    <input type="text" class="form-input" value="<?= clean($user['referral_code']) ?>" disabled style="background: var(--bg-gray); font-family: monospace;">
                </div>

                <button type="submit" name="update_profile" class="btn btn-primary-solid btn-lg">
                    Simpan Perubahan
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
