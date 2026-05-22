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
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $orderStmt->execute([$user['id']]);
    $orders = $orderStmt->fetchAll();
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
                    <a href="<?= SITE_URL ?>/produk.php" class="btn btn-primary-solid">Mulai Belanja</a>
                </div>
            <?php else: ?>
                <div class="order-list">
                    <?php foreach ($orders as $o): ?>
                        <div class="order-row">
                            <div class="order-head">
                                <div>
                                    <div class="order-code">#<?= clean($o['order_code']) ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                        <?= date('d M Y, H:i', strtotime($o['created_at'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?= $o['order_status'] ?>"><?= ucfirst($o['order_status']) ?></span>
                            </div>
                            <div class="order-body">
                                <div>
                                    <span style="color: var(--text-muted);">Pembayaran:</span> <?= strtoupper($o['payment_method']) ?>
                                    <span class="status-badge status-<?= $o['payment_status'] ?>" style="margin-left: 8px;"><?= ucfirst($o['payment_status']) ?></span>
                                </div>
                                <div class="order-amount"><?= rupiah($o['final_amount']) ?></div>
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
