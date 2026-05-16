<?php
$pageTitle = 'Semua Produk';
require_once __DIR__ . '/includes/header.php';

// Filter params
$catSlugs = !empty($_GET['cats']) ? explode(',', $_GET['cats']) : [];
$search = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'popular';
$minPrice = (int)($_GET['min_price'] ?? 0);
$maxPrice = (int)($_GET['max_price'] ?? 0);
$minRating = (float)($_GET['rating'] ?? 0);
$promos = !empty($_GET['promos']) ? explode(',', $_GET['promos']) : [];

$where = ['p.is_active = 1'];
$params = [];

if (!empty($catSlugs)) {
    $placeholders = implode(',', array_fill(0, count($catSlugs), '?'));
    $where[] = "c.slug IN ($placeholders)";
    foreach ($catSlugs as $cs) $params[] = $cs;
}

if ($search) {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($minPrice > 0) {
    $where[] = 'p.base_price >= ?';
    $params[] = $minPrice;
}

if ($maxPrice > 0) {
    $where[] = 'p.base_price <= ?';
    $params[] = $maxPrice;
}

if ($minRating > 0) {
    $where[] = 'p.rating >= ?';
    $params[] = $minRating;
}

if (in_array('discount', $promos)) {
    // Filter produk yang pakai tier pricing (= ada diskon grosir)
    $where[] = 'p.use_tier_pricing = 1';
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

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Count active filters
$activeFilterCount = count($catSlugs) + count($promos) + ($minPrice > 0 ? 1 : 0) + ($maxPrice > 0 ? 1 : 0) + ($minRating > 0 ? 1 : 0);
?>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/filter-modal.css?v=<?= @filemtime(__DIR__ . '/assets/css/filter-modal.css') ?: '1.0' ?>">

<div class="produk-page">
    <div class="produk-header">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap;">
            <div>
                <h1 class="produk-title">
                    <?php if ($search): ?>
                        Hasil: "<?= clean($search) ?>"
                    <?php elseif (!empty($catSlugs)): ?>
                        <?php
                        $catNames = array_filter($categories, fn($c) => in_array($c['slug'], $catSlugs));
                        echo count($catNames) === 1 ? clean(reset($catNames)['name']) : count($catSlugs) . ' Kategori';
                        ?>
                    <?php else: ?>
                        Semua Produk
                    <?php endif; ?>
                </h1>
                <p class="produk-count"><?= count($products) ?> produk ditemukan</p>
            </div>
            
            <!-- Filter button -->
            <button type="button" class="btn-filter" onclick="openFilterModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line>
                    <line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line>
                    <line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line>
                </svg>
                Filter
                <?php if ($activeFilterCount > 0): ?>
                    <span class="filter-count-badge"><?= $activeFilterCount ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <!-- Sort dropdown (compact) -->
    <div style="background: white; border-radius: var(--radius); border: 1px solid var(--border); padding: 10px 14px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
        <form method="GET" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <?php foreach ($_GET as $k => $v): if ($k !== 'sort'): ?>
                <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= clean($v) ?>">
            <?php endif; endforeach; ?>
            <label style="font-size: 13px; color: var(--text-light);">Urutkan:</label>
            <select name="sort" onchange="this.form.submit()" class="form-select" style="width: auto; padding: 6px 10px; font-size: 13px;">
                <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Terlaris</option>
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option>
                <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Harga Terendah</option>
                <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Harga Tertinggi</option>
                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Rating Tertinggi</option>
            </select>
        </form>
        <?php if ($activeFilterCount > 0): ?>
            <a href="<?= SITE_URL ?>/produk.php" style="font-size: 12px; color: var(--primary); font-weight: 600;">✕ Hapus filter</a>
        <?php endif; ?>
    </div>

    <?php if (empty($products)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <h3>Produk tidak ditemukan</h3>
            <p>Coba ubah filter atau kata kunci</p>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $p):
                $iconMap = ['jasa' => '🎨', 'digital' => '💾', 'fisik' => '📦'];
            ?>
                <a href="<?= SITE_URL ?>/detail-produk.php?slug=<?= clean($p['slug']) ?>" class="product-card">
                    <div class="product-image">
                        <?php if (!empty($p['image'])): ?>
                            <img src="<?= SITE_URL ?>/<?= clean($p['image']) ?>" alt="<?= clean($p['name']) ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <?= $iconMap[$p['type']] ?? '📦' ?>
                        <?php endif; ?>
                        <?php if ($p['sold'] > 200): ?>
                            <span class="product-badge">Terlaris</span>
                        <?php elseif (!empty($p['use_tier_pricing'])): ?>
                            <span class="product-badge" style="background: var(--success);">Grosir</span>
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

<!-- ============ FILTER MODAL ============ -->
<div class="filter-overlay" id="filterOverlay" onclick="closeFilterModal()"></div>
<div class="filter-modal" id="filterModal">
    <div class="filter-head">
        <h3>Pilih Preferensi</h3>
        <button type="button" class="filter-close" onclick="closeFilterModal()">✕</button>
    </div>
    
    <form method="GET" action="<?= SITE_URL ?>/produk.php" id="filterForm">
        <?php if ($search): ?>
            <input type="hidden" name="q" value="<?= clean($search) ?>">
        <?php endif; ?>
        <input type="hidden" name="sort" value="<?= clean($sort) ?>">
        
        <div class="filter-body">
            <!-- ===== PROGRAM PROMO ===== -->
            <div class="filter-section">
                <h4>Program Promo</h4>
                <div class="chip-grid">
                    <?php
                    $promoOptions = [
                        'discount' => '💰 Dengan Diskon',
                        'best_seller' => '🔥 Terlaris',
                        'new' => '✨ Produk Baru',
                        'rated' => '⭐ Rating Tinggi',
                    ];
                    foreach ($promoOptions as $val => $label):
                    ?>
                        <label class="chip-filter">
                            <input type="checkbox" name="promos[]" value="<?= $val ?>" <?= in_array($val, $promos) ? 'checked' : '' ?>>
                            <span><?= $label ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ===== BATAS HARGA ===== -->
            <div class="filter-section">
                <h4>Batas Harga</h4>
                <div class="price-input-row">
                    <input type="number" name="min_price" id="minPrice" class="form-input" placeholder="MIN" value="<?= $minPrice ?: '' ?>" min="0">
                    <span style="color: var(--text-muted);">─</span>
                    <input type="number" name="max_price" id="maxPrice" class="form-input" placeholder="MAX" value="<?= $maxPrice ?: '' ?>" min="0">
                </div>
                <div class="quick-price-grid">
                    <button type="button" class="quick-price" onclick="setPriceRange(0, 75000)">0 - 75RB</button>
                    <button type="button" class="quick-price" onclick="setPriceRange(75000, 150000)">75RB - 150RB</button>
                    <button type="button" class="quick-price" onclick="setPriceRange(150000, 300000)">150RB - 300RB</button>
                    <button type="button" class="quick-price" onclick="setPriceRange(300000, 0)">300RB+</button>
                </div>
            </div>

            <!-- ===== PENILAIAN ===== -->
            <div class="filter-section">
                <h4>Penilaian</h4>
                <div class="rating-grid">
                    <?php $ratings = [5, 4, 3, 2, 1]; foreach ($ratings as $r): ?>
                        <label class="rating-chip">
                            <input type="radio" name="rating" value="<?= $r ?>" <?= $minRating == $r ? 'checked' : '' ?>>
                            <span>
                                <?= $r === 5 ? '5' : '≥' . $r ?>
                                <span style="color: var(--accent);">★</span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ===== KATEGORI ===== -->
            <div class="filter-section">
                <h4>Berdasarkan Kategori</h4>
                <div class="chip-grid">
                    <?php foreach ($categories as $cat): ?>
                        <label class="chip-filter">
                            <input type="checkbox" name="cats[]" value="<?= clean($cat['slug']) ?>" <?= in_array($cat['slug'], $catSlugs) ? 'checked' : '' ?>>
                            <span><?= $cat['icon'] ?> <?= clean($cat['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="height: 16px;"></div>
        </div>
        
        <!-- Sticky bottom buttons -->
        <div class="filter-actions">
            <a href="<?= SITE_URL ?>/produk.php" class="btn-reset">Atur Ulang</a>
            <button type="submit" class="btn-apply">Terapkan</button>
        </div>
    </form>
</div>

<script>
function openFilterModal() {
    document.getElementById('filterOverlay').classList.add('active');
    document.getElementById('filterModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeFilterModal() {
    document.getElementById('filterOverlay').classList.remove('active');
    document.getElementById('filterModal').classList.remove('active');
    document.body.style.overflow = '';
}

function setPriceRange(min, max) {
    document.getElementById('minPrice').value = min || '';
    document.getElementById('maxPrice').value = max || '';
}

// Convert checkbox values ke comma-separated string sebelum submit
document.getElementById('filterForm').addEventListener('submit', function(e) {
    // Combine cats[] ke single string cats=a,b,c
    const cats = Array.from(this.querySelectorAll('input[name="cats[]"]:checked')).map(i => i.value);
    const promos = Array.from(this.querySelectorAll('input[name="promos[]"]:checked')).map(i => i.value);
    
    // Remove all cats[] and promos[]
    this.querySelectorAll('input[name="cats[]"], input[name="promos[]"]').forEach(i => i.disabled = true);
    
    // Add combined
    if (cats.length) {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'cats';
        inp.value = cats.join(',');
        this.appendChild(inp);
    }
    if (promos.length) {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'promos';
        inp.value = promos.join(',');
        this.appendChild(inp);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
