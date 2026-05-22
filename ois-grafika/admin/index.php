<?php
$pageTitle = 'Admin Panel';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

// Stats
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(final_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();

// Order terbaru
$recentOrders = $pdo->query("
    SELECT o.*, u.name AS user_name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
")->fetchAll();
?>

<div class="dashboard">
    <aside class="dash-sidebar">
        <div style="text-align: center; padding-bottom: 16px; border-bottom: 1px solid var(--border); margin-bottom: 16px;">
            <div style="font-family: var(--font-display); font-weight: 800; color: var(--primary);">🛠️ ADMIN PANEL</div>
        </div>
        <ul class="dash-menu">
            <li><a href="<?= SITE_URL ?>/admin/index.php" class="active">📊 Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/admin/produk.php">🛍️ Kelola Produk</a></li>
            <li><a href="<?= SITE_URL ?>/admin/pesanan.php">📦 Pesanan</a></li>
            <li><a href="<?= SITE_URL ?>/admin/users.php">👥 Pengguna</a></li>
            <li><a href="<?= SITE_URL ?>/admin/kategori.php">🏷️ Kategori</a></li>
            <li><a href="<?= SITE_URL ?>/dashboard.php">👤 User Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/index.php">🏠 Beranda</a></li>
            <li><a href="<?= SITE_URL ?>/logout.php" style="color: var(--danger);">🚪 Keluar</a></li>
        </ul>
    </aside>

    <div class="dash-content">
        <h1 class="dash-title">Dashboard Admin</h1>
        <p class="dash-subtitle">Ringkasan operasi toko</p>

        <div class="stat-grid">
            <div class="stat-card primary">
                <div class="stat-label">Total Pendapatan</div>
                <div class="stat-value" style="font-size: 20px;"><?= rupiah($totalRevenue) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pesanan</div>
                <div class="stat-value"><?= $totalOrders ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pengguna</div>
                <div class="stat-value"><?= $totalUsers ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Produk Aktif</div>
                <div class="stat-value"><?= $totalProducts ?></div>
            </div>
        </div>

        <?php if ($pendingOrders > 0): ?>
            <div class="flash flash-warning" style="margin: 0 0 20px;">
                ⚠️ Ada <strong><?= $pendingOrders ?> pesanan</strong> menunggu diproses. <a href="<?= SITE_URL ?>/admin/pesanan.php?status=pending" style="color: inherit; text-decoration: underline;">Lihat sekarang →</a>
            </div>
        <?php endif; ?>

        <h2 style="font-size: 18px; margin: 24px 0 12px;">Pesanan Terbaru</h2>
        
        <?php if (empty($recentOrders)): ?>
            <p style="color: var(--text-muted); padding: 20px; text-align: center;">Belum ada pesanan.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border); background: var(--bg-gray);">
                            <th style="text-align: left; padding: 10px;">Kode</th>
                            <th style="text-align: left; padding: 10px;">User</th>
                            <th style="text-align: left; padding: 10px;">Total</th>
                            <th style="text-align: left; padding: 10px;">Status</th>
                            <th style="text-align: left; padding: 10px;">Tanggal</th>
                            <th style="text-align: left; padding: 10px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $o): ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 10px; font-family: monospace;"><?= clean($o['order_code']) ?></td>
                                <td style="padding: 10px;"><?= clean($o['user_name']) ?></td>
                                <td style="padding: 10px; color: var(--primary); font-weight: 700;"><?= rupiah($o['final_amount']) ?></td>
                                <td style="padding: 10px;"><span class="status-badge status-<?= $o['order_status'] ?>"><?= ucfirst($o['order_status']) ?></span></td>
                                <td style="padding: 10px; color: var(--text-muted);"><?= date('d M, H:i', strtotime($o['created_at'])) ?></td>
                                <td style="padding: 10px;">
                                    <a href="<?= SITE_URL ?>/admin/pesanan.php?id=<?= $o['id'] ?>" style="color: var(--primary); font-weight: 600;">Detail</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
