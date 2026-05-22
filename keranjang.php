<?php
$pageTitle = 'Keranjang';
require_once __DIR__ . '/includes/tier-helpers.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        if (isset($_POST['delete'])) {
            $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?")->execute([(int)$_POST['cart_id'], $_SESSION['user_id']]);
            redirect(SITE_URL . '/keranjang.php', 'Item dihapus');
        } elseif (isset($_POST['update'])) {
            foreach ($_POST['quantity'] ?? [] as $cartId => $qty) {
                $qty = max(1, (int)$qty);
                $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?")->execute([$qty, (int)$cartId, $_SESSION['user_id']]);
            }
            redirect(SITE_URL . '/keranjang.php', 'Keranjang diperbarui');
        }
    }
}

$stmt = $pdo->prepare("
    SELECT c.*, p.id AS pid, p.name AS product_name, p.slug, p.base_price, p.use_tier_pricing, p.type, p.stock, p.image,
           vi.combination AS variant_name, vi.price AS variant_price
    FROM cart c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN product_variation_items vi ON c.variant_id = vi.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll();

$subtotal = 0;
foreach ($items as &$item) {
    // Get tier price kalo aktif
    $product = ['id' => $item['pid'], 'base_price' => $item['base_price'], 'use_tier_pricing' => $item['use_tier_pricing']];
    $basePrice = getProductPrice($product, $item['quantity'], $pdo);
    
    // Kalo ada variant, pake harga variant (override base)
    if (!empty($item['variant_price']) && $item['variant_price'] > 0) {
        $item['unit_price'] = (float)$item['variant_price'];
    } else {
        $item['unit_price'] = $basePrice;
    }
    
    // Display name kombinasi dengan separator " / "
    if (!empty($item['variant_name'])) {
        $item['variant_name'] = str_replace('|', ' / ', $item['variant_name']);
    }
    
    $item['subtotal'] = $item['unit_price'] * $item['quantity'];
    $item['saving'] = !empty($item['use_tier_pricing']) && empty($item['variant_price']) ? ((int)$item['base_price'] - $basePrice) * $item['quantity'] : 0;
    $subtotal += $item['subtotal'];
}
unset($item);

$totalSaving = array_sum(array_column($items, 'saving'));
$iconMap = ['jasa' => '🎨', 'digital' => '💾', 'fisik' => '📦'];

require_once __DIR__ . '/includes/header.php';
?>

<?php if (empty($items)): ?>
    <div class="cart-page" style="grid-template-columns: 1fr;">
        <div class="cart-items">
            <div class="cart-empty">
                <div class="cart-empty-icon">🛒</div>
                <h2 style="margin-bottom: 8px;">Keranjang kosong</h2>
                <p style="margin-bottom: 24px;">Yuk mulai belanja!</p>
                <a href="<?= SITE_URL ?>/produk.php" class="btn btn-primary-solid btn-lg">Mulai Belanja</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="cart-page">
        <form method="POST" class="cart-items">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <h2 style="margin-bottom: 16px;">Keranjang (<?= count($items) ?> item)</h2>

            <?php foreach ($items as $item): ?>
                <div class="cart-item">
                    <div class="cart-item-img">
                        <?php if ($item['image']): ?>
                            <img src="<?= SITE_URL ?>/<?= clean($item['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:var(--radius);">
                        <?php else: ?>
                            <?= $iconMap[$item['type']] ?? '📦' ?>
                        <?php endif; ?>
                    </div>
                    <div class="cart-item-info">
                        <a href="<?= SITE_URL ?>/detail-produk.php?slug=<?= clean($item['slug']) ?>" class="cart-item-name"><?= clean($item['product_name']) ?></a>
                        <?php if ($item['variant_name']): ?>
                            <div class="cart-item-variant"><?= clean($item['variant_name']) ?></div>
                        <?php endif; ?>
                        <div class="cart-item-price"><?= rupiah($item['unit_price']) ?>
                            <?php if (!empty($item['use_tier_pricing']) && $item['unit_price'] < $item['base_price']): ?>
                                <span style="color: var(--success); font-size: 11px; font-weight: 600; margin-left: 4px;">💰 Harga Grosir</span>
                                <span style="color: var(--text-muted); font-size: 11px; text-decoration: line-through; margin-left: 4px;"><?= rupiah($item['base_price']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="cart-item-controls">
                        <div class="qty-control">
                            <button type="button" class="qty-minus">−</button>
                            <input type="number" name="quantity[<?= $item['id'] ?>]" value="<?= $item['quantity'] ?>" min="1">
                            <button type="button" class="qty-plus">+</button>
                        </div>
                    </div>
                    <div>
                        <button type="submit" name="delete" value="1" formnovalidate
                                onclick="document.querySelector('input[name=cart_id]').value=<?= $item['id'] ?>"
                                style="color: var(--danger); padding: 6px;" title="Hapus"
                                data-confirm="Hapus item ini?">🗑️</button>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <input type="hidden" name="cart_id" value="">
            
            <div style="display: flex; gap: 10px; margin-top: 16px;">
                <button type="submit" name="update" value="1" class="btn btn-secondary">Update Keranjang</button>
                <a href="<?= SITE_URL ?>/produk.php" class="btn btn-outline">Lanjut Belanja</a>
            </div>
        </form>

        <div class="cart-summary">
            <h3 style="margin-bottom: 16px;">Ringkasan</h3>
            
            <div class="summary-row">
                <span>Subtotal (<?= array_sum(array_column($items, 'quantity')) ?> barang)</span>
                <span><?= rupiah($subtotal) ?></span>
            </div>
            <?php if ($totalSaving > 0): ?>
                <div class="summary-row" style="color: var(--success); font-weight: 600;">
                    <span>💰 Hemat grosir</span>
                    <span>-<?= rupiah($totalSaving) ?></span>
                </div>
            <?php endif; ?>
            <div class="summary-row">
                <span>Ongkir</span>
                <span style="color: var(--text-muted);">Dihitung di checkout</span>
            </div>
            
            <div class="summary-row summary-total">
                <span>Total</span>
                <span class="total-price"><?= rupiah($subtotal) ?></span>
            </div>

            <a href="<?= SITE_URL ?>/checkout.php" class="btn btn-primary-solid btn-block btn-lg" style="margin-top: 16px;">Lanjut ke Checkout</a>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
