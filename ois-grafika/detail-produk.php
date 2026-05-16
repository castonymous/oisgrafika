<?php
require_once __DIR__ . '/includes/header.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    redirect(SITE_URL . '/produk.php');
}

$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon FROM products p JOIN categories c ON p.category_id = c.id WHERE p.slug = ? AND p.is_active = 1");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    redirect(SITE_URL . '/produk.php', 'Produk tidak ditemukan', 'error');
}

// Get variants
$varStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id ASC");
$varStmt->execute([$product['id']]);
$variants = $varStmt->fetchAll();

$pageTitle = $product['name'];
$iconMap = ['jasa' => '🎨', 'digital' => '💾', 'fisik' => '📦'];

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']), 'Silakan login dulu untuk menambahkan ke keranjang', 'warning');
    }
    
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        redirect($_SERVER['REQUEST_URI'], 'Token tidak valid', 'error');
    }
    
    $variantId = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    
    // Cek apakah varian wajib
    if (!empty($variants) && !$variantId) {
        redirect($_SERVER['REQUEST_URI'], 'Pilih varian terlebih dahulu', 'warning');
    }
    
    // Cek apakah sudah ada di keranjang
    $checkStmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND (variant_id = ? OR (variant_id IS NULL AND ? IS NULL))");
    $checkStmt->execute([$_SESSION['user_id'], $product['id'], $variantId, $variantId]);
    $existing = $checkStmt->fetch();
    
    if ($existing) {
        $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?")
            ->execute([$qty, $existing['id']]);
    } else {
        $pdo->prepare("INSERT INTO cart (user_id, product_id, variant_id, quantity) VALUES (?, ?, ?, ?)")
            ->execute([$_SESSION['user_id'], $product['id'], $variantId, $qty]);
    }
    
    if (isset($_POST['buy_now'])) {
        redirect(SITE_URL . '/checkout.php');
    } else {
        redirect($_SERVER['REQUEST_URI'], 'Berhasil ditambahkan ke keranjang!', 'success');
    }
}
?>

<div class="product-detail">
    <div class="detail-gallery">
        <div class="detail-main-image">
            <?= $iconMap[$product['type']] ?? '📦' ?>
        </div>
    </div>

    <div class="detail-info">
        <a href="<?= SITE_URL ?>/produk.php?cat=<?= clean($product['category_slug']) ?>" class="detail-category">
            <?= $product['category_icon'] ?> <?= clean($product['category_name']) ?>
        </a>
        <h1 class="detail-title"><?= clean($product['name']) ?></h1>
        
        <div class="detail-stats">
            <div class="detail-stat">
                <strong>★ <?= number_format($product['rating'], 1) ?></strong> Rating
            </div>
            <div class="detail-stat">
                <strong><?= $product['sold'] ?></strong> Terjual
            </div>
            <?php if ($product['type'] === 'fisik'): ?>
                <div class="detail-stat">
                    <strong><?= $product['stock'] ?></strong> Stok tersedia
                </div>
            <?php endif; ?>
        </div>

        <div class="detail-price">
            <div class="detail-section-label">Harga Mulai</div>
            <div class="detail-price-num"><?= rupiah($product['base_price']) ?></div>
        </div>

        <p style="color: var(--text-light); line-height: 1.7; margin-bottom: 22px;">
            <?= clean($product['short_description']) ?>
        </p>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="variant_id" value="">
            
            <?php if (!empty($variants)): ?>
                <div class="detail-section">
                    <div class="detail-section-label">Pilih Varian:</div>
                    <div class="variant-options">
                        <?php foreach ($variants as $idx => $v): 
                            $totalPrice = $product['base_price'] + $v['price_modifier'];
                        ?>
                            <button type="button" class="variant-btn <?= $idx === 0 ? 'active' : '' ?>" 
                                    data-variant-id="<?= $v['id'] ?>"
                                    data-total-price="<?= rupiah($totalPrice) ?>">
                                <?= clean($v['name']) ?>
                                <?php if ($v['price_modifier'] > 0): ?>
                                    <span style="color: var(--text-muted); font-weight: 400; font-size: 11px;">(+<?= rupiah($v['price_modifier']) ?>)</span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <script>
                    // Set default variant
                    document.querySelector('input[name="variant_id"]').value = '<?= $variants[0]['id'] ?>';
                </script>
            <?php endif; ?>

            <div class="detail-section">
                <div class="detail-section-label">Jumlah:</div>
                <div class="qty-control">
                    <button type="button" class="qty-minus">−</button>
                    <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock'] ?: 999 ?>">
                    <button type="button" class="qty-plus">+</button>
                </div>
            </div>

            <div class="detail-actions">
                <button type="submit" name="add_cart" class="btn btn-outline btn-lg" style="flex: 1;">
                    🛒 + Keranjang
                </button>
                <button type="submit" name="buy_now" value="1" class="btn btn-primary-solid btn-lg" style="flex: 1;">
                    Beli Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<div class="detail-description">
    <div class="desc-card">
        <h3>Deskripsi Produk</h3>
        <p><?= nl2br(clean($product['description'])) ?></p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
