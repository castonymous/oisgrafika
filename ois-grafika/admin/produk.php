<?php
$pageTitle = 'Kelola Produk';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        redirect(SITE_URL . '/admin/produk.php', 'Token tidak valid', 'error');
    }
    
    if (isset($_POST['save'])) {
        $name = clean($_POST['name']);
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $catId = (int)$_POST['category_id'];
        $price = (float)$_POST['base_price'];
        $type = $_POST['type'];
        $stock = (int)$_POST['stock'];
        $desc = $_POST['description'] ?? '';
        $shortDesc = clean($_POST['short_description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if ($id > 0) {
            // Update
            $pdo->prepare("UPDATE products SET category_id = ?, name = ?, slug = ?, base_price = ?, type = ?, stock = ?, description = ?, short_description = ?, is_active = ? WHERE id = ?")
                ->execute([$catId, $name, $slug, $price, $type, $stock, $desc, $shortDesc, $isActive, $id]);
            redirect(SITE_URL . '/admin/produk.php', 'Produk diperbarui');
        } else {
            // Insert
            $pdo->prepare("INSERT INTO products (category_id, name, slug, base_price, type, stock, description, short_description, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$catId, $name, $slug, $price, $type, $stock, $desc, $shortDesc, $isActive]);
            redirect(SITE_URL . '/admin/produk.php', 'Produk ditambahkan');
        }
    } elseif (isset($_POST['delete']) && $id > 0) {
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        redirect(SITE_URL . '/admin/produk.php', 'Produk dihapus');
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Load product if edit
$product = ['id' => 0, 'name' => '', 'category_id' => '', 'base_price' => '', 'type' => 'fisik', 'stock' => 0, 'description' => '', 'short_description' => '', 'is_active' => 1];
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) redirect(SITE_URL . '/admin/produk.php', 'Produk tidak ada', 'error');
}
?>

<div class="dashboard">
    <aside class="dash-sidebar">
        <div style="text-align: center; padding-bottom: 16px; border-bottom: 1px solid var(--border); margin-bottom: 16px;">
            <div style="font-family: var(--font-display); font-weight: 800; color: var(--primary);">🛠️ ADMIN PANEL</div>
        </div>
        <ul class="dash-menu">
            <li><a href="<?= SITE_URL ?>/admin/index.php">📊 Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/admin/produk.php" class="active">🛍️ Kelola Produk</a></li>
            <li><a href="<?= SITE_URL ?>/admin/pesanan.php">📦 Pesanan</a></li>
            <li><a href="<?= SITE_URL ?>/admin/users.php">👥 Pengguna</a></li>
            <li><a href="<?= SITE_URL ?>/admin/kategori.php">🏷️ Kategori</a></li>
            <li><a href="<?= SITE_URL ?>/dashboard.php">👤 User Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/index.php">🏠 Beranda</a></li>
        </ul>
    </aside>

    <div class="dash-content">
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <h1 class="dash-title"><?= $action === 'edit' ? 'Edit Produk' : 'Tambah Produk Baru' ?></h1>
            <p class="dash-subtitle"><?= $action === 'edit' ? 'Update info produk' : 'Isi data produk baru' ?></p>

            <form method="POST" style="max-width: 720px;">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Nama Produk</label>
                        <input type="text" name="name" class="form-input" value="<?= clean($product['name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Pilih kategori</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= clean($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Harga Dasar (Rp)</label>
                        <input type="number" name="base_price" class="form-input" value="<?= $product['base_price'] ?>" required min="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipe</label>
                        <select name="type" class="form-select" required>
                            <option value="jasa" <?= $product['type'] === 'jasa' ? 'selected' : '' ?>>Jasa</option>
                            <option value="digital" <?= $product['type'] === 'digital' ? 'selected' : '' ?>>Digital</option>
                            <option value="fisik" <?= $product['type'] === 'fisik' ? 'selected' : '' ?>>Fisik</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Stok</label>
                        <input type="number" name="stock" class="form-input" value="<?= $product['stock'] ?>" min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Deskripsi Singkat</label>
                    <input type="text" name="short_description" class="form-input" value="<?= clean($product['short_description']) ?>" maxlength="300">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Deskripsi Lengkap</label>
                    <textarea name="description" class="form-textarea" rows="6"><?= clean($product['description']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" name="is_active" value="1" <?= $product['is_active'] ? 'checked' : '' ?> style="accent-color: var(--primary);">
                        Produk aktif (tampil di toko)
                    </label>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="save" class="btn btn-primary-solid">💾 Simpan</button>
                    <a href="<?= SITE_URL ?>/admin/produk.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>

        <?php else: ?>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                <div>
                    <h1 class="dash-title">Kelola Produk</h1>
                    <p class="dash-subtitle">Daftar semua produk di toko</p>
                </div>
                <a href="?action=add" class="btn btn-primary-solid">+ Tambah Produk</a>
            </div>

            <?php
            $products = $pdo->query("SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC")->fetchAll();
            ?>

            <?php if (empty($products)): ?>
                <p style="text-align: center; color: var(--text-muted); padding: 40px;">Belum ada produk.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="background: var(--bg-gray); border-bottom: 2px solid var(--border);">
                                <th style="text-align: left; padding: 10px;">Nama</th>
                                <th style="text-align: left; padding: 10px;">Kategori</th>
                                <th style="text-align: left; padding: 10px;">Harga</th>
                                <th style="text-align: left; padding: 10px;">Tipe</th>
                                <th style="text-align: left; padding: 10px;">Stok</th>
                                <th style="text-align: left; padding: 10px;">Terjual</th>
                                <th style="text-align: left; padding: 10px;">Status</th>
                                <th style="text-align: left; padding: 10px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 10px;"><strong><?= clean($p['name']) ?></strong></td>
                                    <td style="padding: 10px;"><?= clean($p['cat_name']) ?></td>
                                    <td style="padding: 10px; color: var(--primary); font-weight: 600;"><?= rupiah($p['base_price']) ?></td>
                                    <td style="padding: 10px;"><?= ucfirst($p['type']) ?></td>
                                    <td style="padding: 10px;"><?= $p['stock'] ?></td>
                                    <td style="padding: 10px;"><?= $p['sold'] ?></td>
                                    <td style="padding: 10px;">
                                        <span class="status-badge <?= $p['is_active'] ? 'status-completed' : 'status-cancelled' ?>">
                                            <?= $p['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td style="padding: 10px; display: flex; gap: 6px;">
                                        <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" name="delete" formaction="?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Hapus produk ini?">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
