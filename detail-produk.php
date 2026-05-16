<?php
require_once __DIR__ . '/includes/tier-helpers.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) redirect(SITE_URL . '/produk.php');

$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon FROM products p JOIN categories c ON p.category_id = c.id WHERE p.slug = ? AND p.is_active = 1");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) redirect(SITE_URL . '/produk.php', 'Produk tidak ditemukan', 'error');

<<<<<<< ours
// Get variations (Variasi 1 & 2 — axes) + image map
$varStmt = $pdo->prepare("SELECT * FROM product_variations WHERE product_id = ? ORDER BY sort_order ASC");
=======
// Get variants (schema v2: product_variation_items)
$varStmt = $pdo->prepare("SELECT * FROM product_variation_items WHERE product_id = ? ORDER BY id ASC");
>>>>>>> theirs
$varStmt->execute([$product['id']]);
$variations = $varStmt->fetchAll();

$variationAxes = [];
foreach ($variations as $v) {
    $opts = json_decode($v['options'], true) ?? [];
    $imgMap = !empty($v['image_map']) ? (json_decode($v['image_map'], true) ?: []) : [];
    $variationAxes[] = [
        'id' => $v['id'],
        'name' => $v['name'],
        'options' => $opts,
        'image_map' => $imgMap,
        'has_images' => !empty($v['has_images']),
    ];
}

// Get variation items (kombinasi: Merah|S, Merah|M, dll)
$viStmt = $pdo->prepare("SELECT * FROM product_variation_items WHERE product_id = ?");
$viStmt->execute([$product['id']]);
$variationItems = $viStmt->fetchAll();

// Build map kombinasi → {price, stock, sku}
$comboMap = [];
foreach ($variationItems as $vi) {
    $comboMap[$vi['combination']] = $vi;
}

// Get tier prices
$tiers = !empty($product['use_tier_pricing']) ? getProductTiers($product['id'], $pdo) : [];

// Get images
$imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_cover DESC, sort_order ASC");
$imgStmt->execute([$product['id']]);
$images = $imgStmt->fetchAll();

$pageTitle = $product['name'];
$iconMap = ['jasa' => '🎨', 'digital' => '💾', 'fisik' => '📦'];

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']), 'Login dulu untuk add to cart', 'warning');
    }
    
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        redirect($_SERVER['REQUEST_URI'], 'Token tidak valid', 'error');
    }
    
    $variationItemId = !empty($_POST['variation_item_id']) ? (int)$_POST['variation_item_id'] : null;
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    
    if (!empty($variationAxes) && !$variationItemId) {
        redirect($_SERVER['REQUEST_URI'], 'Pilih semua varian dulu', 'warning');
    }
    
    // Cek apakah cart sudah ada untuk variation_item_id sama
    $checkStmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND (variant_id = ? OR (variant_id IS NULL AND ? IS NULL))");
    $checkStmt->execute([$_SESSION['user_id'], $product['id'], $variationItemId, $variationItemId]);
    $existing = $checkStmt->fetch();
    
    if ($existing) {
        $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?")->execute([$qty, $existing['id']]);
    } else {
        $pdo->prepare("INSERT INTO cart (user_id, product_id, variant_id, quantity) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $product['id'], $variationItemId, $qty]);
    }
    
    if (isset($_POST['buy_now'])) {
        redirect(SITE_URL . '/checkout.php');
    } else {
        redirect($_SERVER['REQUEST_URI'], 'Berhasil ditambahkan ke keranjang!', 'success');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.tier-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
.tier-table th, .tier-table td { padding: 8px 10px; border: 1px solid var(--border); text-align: center; }
.tier-table th { background: var(--bg-gray); font-weight: 600; }
.tier-table tbody tr.active { background: var(--primary-light); }
.tier-table tbody tr.active td { color: var(--primary); font-weight: 700; }
.tier-info { display: inline-block; background: linear-gradient(90deg, #fff5f3, #ffe5dd); padding: 8px 12px; border-radius: var(--radius); font-size: 12px; color: var(--primary); font-weight: 600; margin-top: 8px; }

/* Variasi 2-level di detail */
.var-axis-buyer { margin-bottom: 16px; }
.var-axis-buyer-label { font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text); }
.var-axis-buyer-label .selected-value { color: var(--text-muted); font-weight: 400; margin-left: 4px; }
.var-options-buyer { display: flex; flex-wrap: wrap; gap: 8px; }

.var-opt-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: white;
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    color: var(--text);
    transition: all 0.2s;
    position: relative;
    min-height: 38px;
}

/* Style untuk yang ada thumbnail - lebih chip-like Shopee */
.var-opt-btn.has-thumb {
    padding: 4px 10px 4px 4px;
}

.var-opt-btn:hover { border-color: var(--primary); color: var(--primary); }
.var-opt-btn.active {
    border-color: var(--primary);
    background: var(--primary-light);
    color: var(--primary);
    font-weight: 600;
}
.var-opt-btn.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    right: 0;
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 0 0 14px 14px;
    border-color: transparent transparent var(--primary) transparent;
}
.var-opt-btn.active::before {
    content: '✓';
    position: absolute;
    bottom: -2px;
    right: 0px;
    color: white;
    font-size: 9px;
    font-weight: 700;
    z-index: 1;
    line-height: 1;
}
.var-opt-btn.disabled {
    opacity: 0.4;
    cursor: not-allowed;
    background: var(--bg-gray);
}
.var-opt-img {
    width: 30px;
    height: 30px;
    border-radius: 4px;
    object-fit: cover;
    flex-shrink: 0;
}
</style>

<div class="product-detail">
    <div class="detail-gallery">
<<<<<<< ours
        <div class="detail-main-image" id="mainImage">
            <?php if (!empty($images)): ?>
                <img src="<?= SITE_URL ?>/<?= clean($images[0]['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:var(--radius);">
            <?php elseif ($product['image']): ?>
                <img src="<?= SITE_URL ?>/<?= clean($product['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:var(--radius);">
            <?php else: ?>
                <?= $iconMap[$product['type']] ?? '📦' ?>
=======
        <div class="detail-main-image">
            <?php if (!empty($product['image'])): ?>
                <img id="detailMainImage" src="<?= SITE_URL ?>/<?= clean($product['image']) ?>" alt="<?= clean($product['name']) ?>" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
                <span id="detailMainIcon"><?= $iconMap[$product['type']] ?? '📦' ?></span>
>>>>>>> theirs
            <?php endif; ?>
        </div>
        
        <?php if (count($images) > 1): ?>
            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 10px;">
                <?php foreach (array_slice($images, 0, 5) as $idx => $img): ?>
                    <div onclick="changeMainImg(this, '<?= SITE_URL ?>/<?= clean($img['image']) ?>')" style="aspect-ratio: 1; border-radius: var(--radius); overflow: hidden; cursor: pointer; border: 2px solid <?= $idx === 0 ? 'var(--primary)' : 'transparent' ?>;">
                        <img src="<?= SITE_URL ?>/<?= clean($img['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="detail-info">
        <a href="<?= SITE_URL ?>/produk.php?cat=<?= clean($product['category_slug']) ?>" class="detail-category">
            <?= $product['category_icon'] ?> <?= clean($product['category_name']) ?>
        </a>
        <h1 class="detail-title"><?= clean($product['name']) ?></h1>
        
        <div class="detail-stats">
            <div class="detail-stat"><strong>★ <?= number_format($product['rating'], 1) ?></strong> Rating</div>
            <div class="detail-stat"><strong><?= $product['sold'] ?></strong> Terjual</div>
            <?php if ($product['type'] === 'fisik'): ?>
                <div class="detail-stat"><strong id="stockDisplay"><?= $product['stock'] ?></strong> Stok</div>
            <?php endif; ?>
        </div>

        <div class="detail-price">
            <div class="detail-section-label">Harga</div>
            <div class="detail-price-num" id="priceDisplay"><?= rupiah((int)$product['base_price']) ?></div>
            <?php if (!empty($tiers)): ?>
                <div class="tier-info">💰 Hemat lebih banyak dengan beli grosir!</div>
            <?php endif; ?>
        </div>

        <?php if (!empty($tiers)): ?>
            <div class="detail-section">
                <div class="detail-section-label">📊 Harga Grosir</div>
                <table class="tier-table">
                    <thead>
                        <tr><th>Jumlah Pesanan</th><th>Harga per Unit</th></tr>
                    </thead>
                    <tbody id="tierTbody">
                        <?php foreach ($tiers as $tier): ?>
                            <tr data-min="<?= $tier['min_qty'] ?>" data-max="<?= $tier['max_qty'] ?: 999999 ?>" data-price="<?= (int)$tier['price'] ?>">
                                <td><?= formatTierRange($tier) ?></td>
                                <td><?= rupiah((int)$tier['price']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <p style="color: var(--text-light); line-height: 1.7; margin-bottom: 22px;">
            <?= clean($product['short_description']) ?>
        </p>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="variation_item_id" id="variationItemId" value="">
            
<<<<<<< ours
            <?php if (!empty($variationAxes)): ?>
                <!-- Variasi axes -->
                <?php foreach ($variationAxes as $axisIdx => $axis): ?>
                    <div class="var-axis-buyer" data-axis-idx="<?= $axisIdx ?>">
                        <div class="var-axis-buyer-label">
                            <?= clean($axis['name']) ?>:
                            <span class="selected-value" id="selectedAxis<?= $axisIdx ?>">Belum dipilih</span>
                        </div>
                        <div class="var-options-buyer">
                            <?php foreach ($axis['options'] as $opt): 
                                $imgPath = $axis['has_images'] ? ($axis['image_map'][$opt] ?? '') : '';
                            ?>
                                <button type="button" 
                                    class="var-opt-btn <?= $imgPath ? 'has-thumb' : '' ?>" 
                                    data-axis="<?= $axisIdx ?>" 
                                    data-value="<?= clean($opt) ?>"
                                    <?= $imgPath ? 'data-image="' . clean($imgPath) . '"' : '' ?>
                                    onclick="selectVarOption(this)">
                                    <?php if ($imgPath): ?>
                                        <img src="<?= SITE_URL ?>/<?= clean($imgPath) ?>" alt="<?= clean($opt) ?>" class="var-opt-img">
                                    <?php endif; ?>
                                    <span><?= clean($opt) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
=======
            <?php if (!empty($variants)): ?>
                <div class="detail-section">
                    <div class="detail-section-label">Pilih Varian:</div>
                    <div class="variant-options">
                        <?php foreach ($variants as $idx => $v): 
                            $totalPrice = !empty($v['price']) ? (float)$v['price'] : (float)$product['base_price'];
                        ?>
                            <button type="button" class="variant-btn <?= $idx === 0 ? 'active' : '' ?>" 
                                    data-variant-id="<?= $v['id'] ?>"
                                    data-total-price="<?= rupiah($totalPrice) ?>"
                                    data-image="<?= clean($v['image'] ?? '') ?>">
                                <?= clean(str_replace('|', ' / ', $v['combination'])) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <script>
                    // Set default variant
                    document.querySelector('input[name="variant_id"]').value = '<?= $variants[0]['id'] ?>';
                    document.querySelectorAll('.variant-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            document.querySelectorAll('.variant-btn').forEach(x => x.classList.remove('active'));
                            this.classList.add('active');
                            document.querySelector('input[name="variant_id"]').value = this.dataset.variantId;
                            const image = this.dataset.image;
                            const mainImage = document.getElementById('detailMainImage');
                            if (image && mainImage) {
                                mainImage.src = '<?= SITE_URL ?>/' + image;
                            }
                        });
                    });
                </script>
>>>>>>> theirs
            <?php endif; ?>

            <div class="detail-section">
                <div class="detail-section-label">Jumlah:</div>
                <div class="qty-control">
                    <button type="button" class="qty-minus" onclick="updateTierPrice()">−</button>
                    <input type="number" name="quantity" id="qtyInput" value="<?= $product['min_purchase'] ?: 1 ?>" min="<?= $product['min_purchase'] ?: 1 ?>" max="<?= $product['stock'] ?: 999 ?>" oninput="updateTierPrice()">
                    <button type="button" class="qty-plus" onclick="updateTierPrice()">+</button>
                </div>
                <?php if (!empty($product['min_purchase']) && $product['min_purchase'] > 1): ?>
                    <small style="color: var(--text-muted); margin-top: 6px; display: block;">Min. pembelian: <?= $product['min_purchase'] ?> unit</small>
                <?php endif; ?>
            </div>

            <!-- Subtotal display -->
            <?php if (!empty($tiers) || !empty($variationAxes)): ?>
                <div style="background: var(--bg-gray); border-radius: var(--radius); padding: 14px; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 12px; color: var(--text-muted);">Subtotal (<span id="qtyDisplay"><?= $product['min_purchase'] ?: 1 ?></span> unit)</div>
                            <div id="subtotalDisplay" style="font-family: var(--font-display); font-size: 22px; font-weight: 800; color: var(--primary);"><?= rupiah((int)$product['base_price'] * ($product['min_purchase'] ?: 1)) ?></div>
                        </div>
                        <div id="savingDisplay" style="text-align: right; display: none;">
                            <div style="font-size: 11px; color: var(--success); font-weight: 600;">Hemat</div>
                            <div id="savingAmount" style="color: var(--success); font-weight: 700;"></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="detail-actions">
                <button type="submit" name="add_cart" class="btn btn-outline btn-lg" style="flex: 1;">🛒 + Keranjang</button>
                <button type="submit" name="buy_now" value="1" class="btn btn-primary-solid btn-lg" style="flex: 1;">Beli Sekarang</button>
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

<script>
const basePrice = <?= (int)$product['base_price'] ?>;
const useTier = <?= !empty($tiers) ? 'true' : 'false' ?>;
const hasVariations = <?= !empty($variationAxes) ? 'true' : 'false' ?>;
const axesCount = <?= count($variationAxes) ?>;

// Map kombinasi → {price, stock, sku, id}
const COMBO_MAP = <?= json_encode(array_map(function($vi) {
    return [
        'id' => (int)$vi['id'],
        'price' => (int)$vi['price'],
        'stock' => (int)$vi['stock'],
        'sku' => $vi['sku'],
    ];
}, $comboMap)) ?>;

const selectedOptions = new Array(axesCount).fill(null);

function changeMainImg(thumb, url) {
    document.querySelectorAll('.detail-gallery > div:nth-child(2) > div').forEach(el => el.style.border = '2px solid transparent');
    thumb.style.borderColor = 'var(--primary)';
    document.getElementById('mainImage').innerHTML = `<img src="${url}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:var(--radius);">`;
}

function formatRupiah(num) {
    return 'Rp ' + Math.round(num).toLocaleString('id-ID');
}

// Simpan original gallery untuk restore
let originalMainImageHtml = '';
window.addEventListener('DOMContentLoaded', () => {
    const mainImg = document.getElementById('mainImage');
    if (mainImg) originalMainImageHtml = mainImg.innerHTML;
});

function selectVarOption(btn) {
    const axisIdx = parseInt(btn.dataset.axis);
    const value = btn.dataset.value;
    const imageUrl = btn.dataset.image; // path relatif kalo ada
    
    // Toggle dalam axis yang sama
    document.querySelectorAll(`.var-opt-btn[data-axis="${axisIdx}"]`).forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    selectedOptions[axisIdx] = value;
    document.getElementById('selectedAxis' + axisIdx).textContent = value;
    
    // SHOPEE-STYLE: Kalau opsi yang dipilih punya gambar (variasi pertama), swap gallery
    if (imageUrl) {
        const fullUrl = '<?= SITE_URL ?>/' + imageUrl;
        const mainImg = document.getElementById('mainImage');
        if (mainImg) {
            mainImg.innerHTML = `<img src="${fullUrl}" alt="${value}" style="width:100%;height:100%;object-fit:cover;border-radius:var(--radius);">`;
        }
        // Reset border thumbnail di gallery (kalo ada)
        document.querySelectorAll('.detail-gallery > div:nth-child(2) > div').forEach(el => el.style.border = '2px solid transparent');
    }
    
    // Check kalau semua axis udah dipilih
    if (selectedOptions.every(v => v !== null)) {
        const combo = selectedOptions.join('|');
        const data = COMBO_MAP[combo];
        if (data) {
            document.getElementById('variationItemId').value = data.id;
            document.getElementById('priceDisplay').textContent = formatRupiah(data.price);
            const stockEl = document.getElementById('stockDisplay');
            if (stockEl) stockEl.textContent = data.stock;
            document.getElementById('qtyInput').max = data.stock;
            updateTierPrice();
        }
    }
}

function updateTierPrice() {
    const qty = parseInt(document.getElementById('qtyInput').value) || 1;
    let currentPrice = basePrice;
    
    // Kalau ada variasi yang dipilih, pakai harga variasi
    if (hasVariations && selectedOptions.every(v => v !== null)) {
        const combo = selectedOptions.join('|');
        const data = COMBO_MAP[combo];
        if (data) currentPrice = data.price;
    }
    
    // Apply tier kalo aktif
    if (useTier) {
        const tbody = document.getElementById('tierTbody');
        // Reset highlight
        tbody.querySelectorAll('tr').forEach(tr => tr.classList.remove('active'));
        
        // Find matching tier
        tbody.querySelectorAll('tr').forEach(tr => {
            const min = parseInt(tr.dataset.min);
            const max = parseInt(tr.dataset.max);
            if (qty >= min && qty <= max) {
                currentPrice = parseInt(tr.dataset.price);
                tr.classList.add('active');
            }
        });
    }
    
    document.getElementById('priceDisplay').textContent = formatRupiah(currentPrice);
    const qtyDisplay = document.getElementById('qtyDisplay');
    if (qtyDisplay) qtyDisplay.textContent = qty;
    const subtotalDisplay = document.getElementById('subtotalDisplay');
    if (subtotalDisplay) subtotalDisplay.textContent = formatRupiah(currentPrice * qty);
    
    // Saving display
    const savingEl = document.getElementById('savingDisplay');
    if (savingEl) {
        const saving = (basePrice - currentPrice) * qty;
        if (saving > 0) {
            savingEl.style.display = 'block';
            document.getElementById('savingAmount').textContent = formatRupiah(saving);
        } else {
            savingEl.style.display = 'none';
        }
    }
}

updateTierPrice();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
