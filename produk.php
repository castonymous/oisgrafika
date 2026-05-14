<?php
$pageTitle = 'Semua Produk';
require_once __DIR__ . '/includes/header.php';

$catSlug = $_GET['cat'] ?? '';
$search = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'popular';

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

<div class="produk-page">
    <div class="produk-header">
        <h1 class="produk-title">
            <?php if ($catSlug): ?>
                <?php $currentCat = array_filter($categories, fn($c) => $c['slug'] === $catSlug); $currentCat = reset($currentCat); ?>
                <?= clean($currentCat['name'] ?? 'Produk') ?>
            <?php elseif ($search): ?>
                Hasil: "<?= clean($search) ?>"
            <?php else: ?>
                Semua Produk
            <?php endif; ?>
        </h1>
        <p class="produk-count"><?= count($products) ?> produk ditemukan</p>
    </div>

    <div class="produk-layout">
        <aside class="produk-filter">
            <div class="filter-section">
                <h3 class="filter-title">Kategori</h3>
                <ul class="filter-list">
                    <li>
                        <a href="<?= SITE_URL ?>/produk.php" class="filter-chip <?= !$catSlug ? 'active' : '' ?>">
                            Semua
                        </a>
                    </li>
                    <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="<?= SITE_URL ?>/produk.php?cat=<?= clean($cat['slug']) ?>" class="filter-chip <?= $catSlug === $cat['slug'] ? 'active' : '' ?>">
                                <?= $cat['icon'] ?> <?= clean($cat['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="filter-section">
                <h3 class="filter-title">Urutkan</h3>
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
            </div>
        </aside>

        <div class="produk-content">
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔍</div>
                    <h3>Produk tidak ditemukan</h3>
                    <p>Coba kata kunci atau kategori lain</p>
                </div>
            <?php else: ?>
                <!-- PAKE class product-grid yg SAMA persis dengan index.php -->
                <div class="product-grid">
                    <?php foreach ($products as $p): ?>
                        <a href="<?= SITE_URL ?>/detail-produk.php?slug=<?= clean($p['slug']) ?>" class="product-card">
                            <div class="product-image">
                                <?php
                                $iconMap = ['jasa' => '🎨', 'digital' => '💾', 'fisik' => '📦'];
                                if (!empty($p['image'])) {
                                    echo '<img src="' . SITE_URL . '/' . clean($p['image']) . '" alt="' . clean($p['name']) . '" style="width:100%;height:100%;object-fit:cover;">';
                                } else {
                                    echo $iconMap[$p['type']] ?? '📦';
                                }
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
