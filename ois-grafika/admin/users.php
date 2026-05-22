<?php
$pageTitle = 'Kelola Pengguna';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        redirect(SITE_URL . '/admin/users.php', 'Token tidak valid', 'error');
    }
    
    $id = (int)$_POST['id'];
    
    if (isset($_POST['toggle_role'])) {
        $newRole = $_POST['current_role'] === 'admin' ? 'user' : 'admin';
        if ($id != $_SESSION['user_id']) {
            $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $id]);
            redirect(SITE_URL . '/admin/users.php', "Role diubah jadi $newRole");
        }
    } elseif (isset($_POST['delete'])) {
        if ($id != $_SESSION['user_id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            redirect(SITE_URL . '/admin/users.php', 'User dihapus');
        }
    }
}

$users = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count, (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as referral_count FROM users u ORDER BY u.created_at DESC")->fetchAll();
?>

<div class="dashboard">
    <aside class="dash-sidebar">
        <div style="text-align: center; padding-bottom: 16px; border-bottom: 1px solid var(--border); margin-bottom: 16px;">
            <div style="font-family: var(--font-display); font-weight: 800; color: var(--primary);">🛠️ ADMIN PANEL</div>
        </div>
        <ul class="dash-menu">
            <li><a href="<?= SITE_URL ?>/admin/index.php">📊 Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/admin/produk.php">🛍️ Kelola Produk</a></li>
            <li><a href="<?= SITE_URL ?>/admin/pesanan.php">📦 Pesanan</a></li>
            <li><a href="<?= SITE_URL ?>/admin/users.php" class="active">👥 Pengguna</a></li>
            <li><a href="<?= SITE_URL ?>/admin/kategori.php">🏷️ Kategori</a></li>
            <li><a href="<?= SITE_URL ?>/dashboard.php">👤 User Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/index.php">🏠 Beranda</a></li>
        </ul>
    </aside>

    <div class="dash-content">
        <h1 class="dash-title">Kelola Pengguna</h1>
        <p class="dash-subtitle">Total: <?= count($users) ?> pengguna</p>

        <div style="overflow-x: auto; margin-top: 20px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: var(--bg-gray); border-bottom: 2px solid var(--border);">
                        <th style="text-align: left; padding: 10px;">Nama</th>
                        <th style="text-align: left; padding: 10px;">Email</th>
                        <th style="text-align: left; padding: 10px;">Kode Ref</th>
                        <th style="text-align: left; padding: 10px;">Pesanan</th>
                        <th style="text-align: left; padding: 10px;">Referral</th>
                        <th style="text-align: left; padding: 10px;">Role</th>
                        <th style="text-align: left; padding: 10px;">Daftar</th>
                        <th style="text-align: left; padding: 10px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 10px;">
                                <strong><?= clean($u['name']) ?></strong>
                                <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                    <span style="font-size: 10px; background: var(--primary-light); color: var(--primary); padding: 1px 6px; border-radius: 8px; margin-left: 4px;">YOU</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px; color: var(--text-light);"><?= clean($u['email']) ?></td>
                            <td style="padding: 10px; font-family: monospace; font-size: 11px;"><?= clean($u['referral_code']) ?></td>
                            <td style="padding: 10px;"><?= $u['order_count'] ?></td>
                            <td style="padding: 10px;"><?= $u['referral_count'] ?></td>
                            <td style="padding: 10px;">
                                <span class="status-badge <?= $u['role'] === 'admin' ? 'status-paid' : 'status-completed' ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td style="padding: 10px; color: var(--text-muted); font-size: 12px;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                            <td style="padding: 10px;">
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <div style="display: flex; gap: 6px;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="current_role" value="<?= $u['role'] ?>">
                                            <button type="submit" name="toggle_role" class="btn btn-sm btn-secondary" data-confirm="Ubah role user ini?">
                                                <?= $u['role'] === 'admin' ? '↓ User' : '↑ Admin' ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <button type="submit" name="delete" class="btn btn-sm btn-danger" data-confirm="Hapus user ini? Semua data terkait akan hilang.">Hapus</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 11px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
