<?php
$pageTitle = 'Kelola Kategori';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        redirect(SITE_URL . '/admin/kategori.php', 'Token tidak valid', 'error');
    }
    
    if (isset($_POST['save'])) {
        $name = clean($_POST['name']);
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $icon = clean($_POST['icon']);
        $desc = clean($_POST['description']);
        
        if ($id > 0) {
            $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, description = ? WHERE id = ?")
                ->execute([$name, $slug, $icon, $desc, $id]);
            redirect(SITE_URL . '/admin/kategori.php', 'Kategori diperbarui');
        } else {
            $pdo->prepare("INSERT INTO categories (name, slug, icon, description) VALUES (?, ?, ?, ?)")
                ->execute([$name, $slug, $icon, $desc]);
            redirect(SITE_URL . '/admin/kategori.php', 'Kategori ditambahkan');
        }
    } elseif (isset($_POST['delete'])) {
        try {
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([(int)$_POST['id']]);
            redirect(SITE_URL . '/admin/kategori.php', 'Kategori dihapus');
        } catch (Exception $e) {
            redirect(SITE_URL . '/admin/kategori.php', 'Gagal hapus, masih ada produk di kategori ini', 'error');
        }
    }
}

$action = $_GET['action'] ?? 'list';
$category = ['id' => 0, 'name' => '', 'icon' => '', 'description' => ''];
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    if (!$category) redirect(SITE_URL . '/admin/kategori.php');
}

$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS product_count FROM categories c ORDER BY c.name")->fetchAll();
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
            <li><a href="<?= SITE_URL ?>/admin/users.php">👥 Pengguna</a></li>
            <li><a href="<?= SITE_URL ?>/admin/kategori.php" class="active">🏷️ Kategori</a></li>
            <li><a href="<?= SITE_URL ?>/dashboard.php">👤 User Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/index.php">🏠 Beranda</a></li>
        </ul>
    </aside>

    <div class="dash-content">
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <h1 class="dash-title"><?= $action === 'edit' ? 'Edit Kategori' : 'Tambah Kategori' ?></h1>

            <form method="POST" style="max-width: 500px; margin-top: 20px;">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                
                <div class="form-group">
                    <label class="form-label">Nama Kategori</label>
                    <input type="text" name="name" class="form-input" value="<?= clean($category['name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Icon (emoji)</label>
                    <input type="text" name="icon" class="form-input" value="<?= clean($category['icon']) ?>" placeholder="🎨" maxlength="4">
                    <div class="form-help">Contoh: 🎨 🖨️ 💾 👕 📦</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" class="form-textarea" rows="3"><?= clean($category['description']) ?></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="save" class="btn btn-primary-solid">💾 Simpan</button>
                    <a href="<?= SITE_URL ?>/admin/kategori.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>

        <?php else: ?>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                <div>
                    <h1 class="dash-title">Kelola Kategori</h1>
                    <p class="dash-subtitle">Total: <?= count($categories) ?> kategori</p>
                </div>
                <a href="?action=add" class="btn btn-primary-solid">+ Tambah Kategori</a>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px;">
                <?php foreach ($categories as $cat): ?>
                    <div style="background: var(--bg-gray); border-radius: var(--radius); padding: 16px; border: 1px solid var(--border);">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 8px;">
                            <div style="font-size: 32px;"><?= $cat['icon'] ?: '📦' ?></div>
                            <span style="font-size: 11px; background: var(--bg-white); padding: 2px 8px; border-radius: 10px; color: var(--text-light);">
                                <?= $cat['product_count'] ?> produk
                            </span>
                        </div>
                        <h3 style="font-size: 14px; margin-bottom: 4px;"><?= clean($cat['name']) ?></h3>
                        <p style="font-size: 12px; color: var(--text-light); margin-bottom: 12px; min-height: 32px;"><?= clean($cat['description']) ?></p>
                        <div style="display: flex; gap: 6px;">
                            <a href="?action=edit&id=<?= $cat['id'] ?>" class="btn btn-sm btn-secondary" style="flex: 1; text-align: center;">Edit</a>
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                <button type="submit" name="delete" class="btn btn-sm btn-danger btn-block" data-confirm="Hapus kategori? Hanya bisa kalau tidak ada produk di dalamnya.">Hapus</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
