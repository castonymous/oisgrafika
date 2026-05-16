<?php
$pageTitle = 'Semua Produk';
require_once __DIR__ . '/includes/header.php';

// Filter
$catSlug = $_GET['cat'] ?? '';
$search = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'popular';

// Build query
$where = ['p.is_active = 1'];
$params = [];

if ($catSlug) {
    $where[] = 'c.slug = ?';
    $params[] = $catSlug;
}

if ($search) {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$orderBy = match($sort) {
    'newest' => 'p.created_at DESC',
    'price_low' => 'p.base_price ASC',
    'price_high' => 'p.base_price DESC',
    'rating' => 'p.rating DESC',
    default => 'p.sold DESC'
};

$sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $orderBy";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>

<div style="max-width: var(--container-max); margin: 24px auto; padding: 0 16px;">
    <h1 class="section-title" style="margin-bottom: 8px;">
        <?php if ($catSlug): ?>
            <?php $currentCat = array_filter($categories, fn($c) => $c['slug'] === $catSlug); $currentCat = reset($currentCat); ?>
            <?= clean($currentCat['name'] ?? 'Produk') ?>
        <?php elseif ($search): ?>
            Hasil pencarian: "<?= clean($search) ?>"
        <?php else: ?>
            Semua Produk
        <?php endif; ?>
    </h1>
    <p class="section-subtitle" style="margin-bottom: 24px;"><?= count($products) ?> produk ditemukan</p>

    <div style="display: grid; grid-template-columns: 220px 1fr; gap: 20px;" class="product-list-layout">
        <aside style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border); padding: 20px; height: fit-content;" class="filter-sidebar">
            <h3 style="font-size: 14px; margin-bottom: 14px; font-weight: 700;">Kategori</h3>
            <ul style="list-style: none; margin-bottom: 20px;">
                <li style="margin-bottom: 6px;">
                    <a href="<?= SITE_URL ?>/produk.php" style="display:block; padding: 8px 12px; border-radius: var(--radius); font-size: 13px; <?= !$catSlug ? 'background: var(--primary-light); color: var(--primary); font-weight: 600;' : 'color: var(--text-light);' ?>">
                        Semua
                    </a>
                </li>
                <?php foreach ($categories as $cat): ?>
                    <li style="margin-bottom: 6px;">
                        <a href="<?= SITE_URL ?>/produk.php?cat=<?= clean($cat['slug']) ?>" style="display:block; padding: 8px 12px; border-radius: var(--radius); font-size: 13px; <?= $catSlug === $cat['slug'] ? 'background: var(--primary-light); color: var(--primary); font-weight: 600;' : 'color: var(--text-light);' ?>">
                            <?= $cat['icon'] ?> <?= clean($cat['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <h3 style="font-size: 14px; margin-bottom: 14px; font-weight: 700;">Urutkan</h3>
            <form method="GET">
                <?php if ($catSlug): ?><input type="hidden" name="cat" value="<?= clean($catSlug) ?>"><?php endif; ?>
                <?php if ($search): ?><input type="hidden" name="q" value="<?= clean($search) ?>"><?php endif; ?>
                <select name="sort" class="form-select" onchange="this.form.submit()">
                    <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Terlaris</option>
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option>
                    <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Harga Terendah</option>
                    <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Harga Tertinggi</option>
                    <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Rating Tertinggi</option>
                </select>
            </form>
        </aside>

        <div>
            <?php if (empty($products)): ?>
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: var(--radius-lg); border: 1px solid var(--border);">
                    <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                    <h3>Produk tidak ditemukan</h3>
                    <p style="color: var(--text-muted); margin-top: 8px;">Coba kata kunci atau kategori lain</p>
                </div>
            <?php else: ?>
                <div class="product-grid" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); padding: 0;">
                    <?php foreach ($products as $p): ?>
                        <a href="<?= SITE_URL ?>/detail-produk.php?slug=<?= clean($p['slug']) ?>" class="product-card">
                            <div class="product-image">
                                <?php
                                $iconMap = ['jasa' => '🎨', 'digital' => '💾', 'fisik' => '📦'];
                                echo $iconMap[$p['type']] ?? '📦';
                                ?>
                                <?php if ($p['sold'] > 200): ?>
                                    <span class="product-badge">Terlaris</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-body">
                                <div class="product-name"><?= clean($p['name']) ?></div>
                                <div class="product-price"><?= rupiah($p['base_price']) ?></div>
                                <div class="product-meta">
                                    <span class="product-rating">★ <?= number_format($p['rating'], 1) ?></span>
                                    <span class="product-sold">Terjual <?= $p['sold'] ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@media (max-width: 900px) {
    .product-list-layout { grid-template-columns: 1fr !important; }
    .filter-sidebar { order: -1; }
    .filter-sidebar ul { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 4px; }
    .filter-sidebar ul li { margin-bottom: 0 !important; white-space: nowrap; }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
