<?php
$pageTitle = 'Keranjang';
require_once __DIR__ . '/includes/header.php';
requireLogin();

// Handle update qty / hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        if (isset($_POST['delete'])) {
            $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?")
                ->execute([(int)$_POST['cart_id'], $_SESSION['user_id']]);
            redirect(SITE_URL . '/keranjang.php', 'Item dihapus dari keranjang');
        } elseif (isset($_POST['update'])) {
            foreach ($_POST['quantity'] ?? [] as $cartId => $qty) {
                $qty = max(1, (int)$qty);
                $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?")
                    ->execute([$qty, (int)$cartId, $_SESSION['user_id']]);
            }
            redirect(SITE_URL . '/keranjang.php', 'Keranjang diperbarui');
        }
    }
}

// Ambil item keranjang
$stmt = $pdo->prepare("
    SELECT c.*, p.name AS product_name, p.slug, p.base_price, p.type, p.stock,
           v.name AS variant_name, v.price_modifier
    FROM cart c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN product_variants v ON c.variant_id = v.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll();

$subtotal = 0;
foreach ($items as &$item) {
    $item['unit_price'] = $item['base_price'] + ($item['price_modifier'] ?? 0);
    $item['subtotal'] = $item['unit_price'] * $item['quantity'];
    $subtotal += $item['subtotal'];
}
unset($item);

$iconMap = ['jasa' => '🎨', 'digital' => '💾', 'fisik' => '📦'];
?>

<?php if (empty($items)): ?>
    <div class="cart-page" style="grid-template-columns: 1fr;">
        <div class="cart-items">
            <div class="cart-empty">
                <div class="cart-empty-icon">🛒</div>
                <h2 style="margin-bottom: 8px;">Keranjang kosong</h2>
                <p style="margin-bottom: 24px;">Yuk mulai belanja kebutuhan kreatifmu!</p>
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
                        <?= $iconMap[$item['type']] ?? '📦' ?>
                    </div>
                    <div class="cart-item-info">
                        <a href="<?= SITE_URL ?>/detail-produk.php?slug=<?= clean($item['slug']) ?>" class="cart-item-name"><?= clean($item['product_name']) ?></a>
                        <?php if ($item['variant_name']): ?>
                            <div class="cart-item-variant">Varian: <?= clean($item['variant_name']) ?></div>
                        <?php endif; ?>
                        <div class="cart-item-price"><?= rupiah($item['unit_price']) ?></div>
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
                                data-confirm="Hapus item ini dari keranjang?">
                            🗑️
                        </button>
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
            <h3 style="margin-bottom: 16px;">Ringkasan Belanja</h3>
            
            <div class="summary-row">
                <span>Subtotal (<?= array_sum(array_column($items, 'quantity')) ?> barang)</span>
                <span><?= rupiah($subtotal) ?></span>
            </div>
            <div class="summary-row">
                <span>Ongkir</span>
                <span style="color: var(--text-muted);">Dihitung di checkout</span>
            </div>
            
            <div class="summary-row summary-total">
                <span>Total</span>
                <span class="total-price"><?= rupiah($subtotal) ?></span>
            </div>

            <a href="<?= SITE_URL ?>/checkout.php" class="btn btn-primary-solid btn-block btn-lg" style="margin-top: 16px;">
                Lanjut ke Checkout
            </a>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
