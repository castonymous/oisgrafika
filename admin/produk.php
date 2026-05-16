<?php
$pageTitle = 'Kelola Produk';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $delId = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$delId]);
        redirect(SITE_URL . '/admin/produk.php', 'Produk dihapus');
    }
}

// Filters
$search = $_GET['q'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterCat = $_GET['cat'] ?? '';

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterStatus) {
    $where[] = 'p.status = ?';
    $params[] = $filterStatus;
}
if ($filterCat) {
    $where[] = 'p.category_id = ?';
    $params[] = $filterCat;
}

$sql = "SELECT p.*, c.name AS cat_name FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Stats
$counts = [
    'all' => (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'active' => (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn(),
    'draft' => (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='draft'")->fetchColumn(),
    'inactive' => (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='inactive'")->fetchColumn(),
];
?>

<style>
.admin-prod-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
.admin-prod-stat { background: white; padding: 16px; border-radius: var(--radius); border: 1px solid var(--border); }
.admin-prod-stat .label { font-size: 12px; color: var(--text-light); margin-bottom: 4px; }
.admin-prod-stat .value { font-family: var(--font-display); font-size: 22px; font-weight: 800; }
.admin-prod-stat.active .value { color: #1d7a4c; }
.admin-prod-stat.draft .value { color: var(--accent); }
.admin-prod-stat.inactive .value { color: var(--danger); }
.admin-filters { background: white; padding: 14px; border-radius: var(--radius); border: 1px solid var(--border); margin-bottom: 16px; display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; }
.admin-tbl { width: 100%; background: white; border-radius: var(--radius); border: 1px solid var(--border); overflow: hidden; }
.admin-tbl table { width: 100%; border-collapse: collapse; font-size: 13px; }
.admin-tbl th { background: var(--bg-gray); padding: 12px; text-align: left; font-weight: 600; border-bottom: 1px solid var(--border); }
.admin-tbl td { padding: 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.admin-tbl tr:hover td { background: #fafafa; }
.prod-info { display: flex; align-items: center; gap: 10px; }
.prod-thumb { width: 50px; height: 50px; border-radius: var(--radius); background: linear-gradient(135deg, #fff5f3, #ffe5dd); display: flex; align-items: center; justify-content: center; font-size: 22px; overflow: hidden; flex-shrink: 0; }
.prod-thumb img { width: 100%; height: 100%; object-fit: cover; }
.prod-name { font-weight: 600; margin-bottom: 2px; }
.prod-sku { font-size: 11px; color: var(--text-muted); font-family: monospace; }
.completeness-mini { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; color: var(--text-light); }
.completeness-bar { width: 50px; height: 4px; background: var(--bg-gray); border-radius: 2px; overflow: hidden; }
.completeness-bar-fill { height: 100%; background: var(--primary); }
@media (max-width: 768px) {
    .admin-prod-stats { grid-template-columns: 1fr 1fr; }
    .admin-filters { grid-template-columns: 1fr; }
    .admin-tbl { overflow-x: auto; }
    .admin-tbl table { min-width: 700px; }
}
</style>

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
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; gap: 12px; flex-wrap: wrap;">
            <div>
                <h1 class="dash-title">Kelola Produk</h1>
                <p class="dash-subtitle">Daftar semua produk di toko</p>
            </div>
            <a href="<?= SITE_URL ?>/admin/produk-form.php" class="btn btn-primary-solid">+ Tambah Produk Baru</a>
        </div>

        <!-- Stats -->
        <div class="admin-prod-stats">
            <a href="?" class="admin-prod-stat">
                <div class="label">Total Produk</div>
                <div class="value"><?= $counts['all'] ?></div>
            </a>
            <a href="?status=active" class="admin-prod-stat active">
                <div class="label">Aktif</div>
                <div class="value"><?= $counts['active'] ?></div>
            </a>
            <a href="?status=draft" class="admin-prod-stat draft">
                <div class="label">Draft</div>
                <div class="value"><?= $counts['draft'] ?></div>
            </a>
            <a href="?status=inactive" class="admin-prod-stat inactive">
                <div class="label">Nonaktif</div>
                <div class="value"><?= $counts['inactive'] ?></div>
            </a>
        </div>

        <!-- Filters -->
        <form method="GET" class="admin-filters">
            <input type="text" name="q" class="form-input" placeholder="🔍 Cari nama produk / SKU..." value="<?= clean($search) ?>">
            <select name="status" class="form-select">
                <option value="">Semua Status</option>
                <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Aktif</option>
                <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
            <select name="cat" class="form-select">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $filterCat == $cat['id'] ? 'selected' : '' ?>><?= clean($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary-solid">Filter</button>
        </form>

        <!-- Table -->
        <?php if (empty($products)): ?>
            <div style="background: white; border-radius: var(--radius); border: 1px solid var(--border); padding: 60px 20px; text-align: center; color: var(--text-muted);">
                <div style="font-size: 48px; margin-bottom: 16px;">📦</div>
                <h3>Belum ada produk</h3>
                <p style="margin-bottom: 20px;">Mulai jualan dengan menambahkan produk pertamamu</p>
                <a href="<?= SITE_URL ?>/admin/produk-form.php" class="btn btn-primary-solid">+ Tambah Produk Baru</a>
            </div>
        <?php else: ?>
            <div class="admin-tbl">
                <table>
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Terjual</th>
                            <th>Status</th>
                            <th>Kelengkapan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <div class="prod-info">
                                        <div class="prod-thumb">
                                            <?php if ($p['image']): ?>
                                                <img src="<?= SITE_URL ?>/<?= clean($p['image']) ?>" alt="">
                                            <?php else: ?>
                                                <?php $icons = ['jasa'=>'🎨','digital'=>'💾','fisik'=>'📦']; echo $icons[$p['type']] ?? '📦'; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="prod-name"><?= clean($p['name']) ?></div>
                                            <?php if ($p['sku']): ?>
                                                <div class="prod-sku">SKU: <?= clean($p['sku']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= clean($p['cat_name']) ?></td>
                                <td style="color: var(--primary); font-weight: 700;"><?= rupiah($p['base_price']) ?></td>
                                <td><?= $p['stock'] ?></td>
                                <td><?= $p['sold'] ?></td>
                                <td>
                                    <span class="status-badge status-<?= ($p['status'] === 'active' ? 'completed' : ($p['status'] === 'draft' ? 'pending' : 'cancelled')) ?>">
                                        <?= ucfirst($p['status'] ?: 'draft') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="completeness-mini">
                                        <div class="completeness-bar"><div class="completeness-bar-fill" style="width: <?= $p['completeness_score'] ?? 0 ?>%"></div></div>
                                        <span><?= $p['completeness_score'] ?? 0 ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 4px;">
                                        <a href="<?= SITE_URL ?>/admin/produk-form.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                                        <a href="<?= SITE_URL ?>/detail-produk.php?slug=<?= clean($p['slug']) ?>" target="_blank" class="btn btn-sm btn-outline" title="Preview">👁</a>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" name="delete" class="btn btn-sm btn-danger" data-confirm="Hapus produk ini?">×</button>
                                        </form>
                                    </div>
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
