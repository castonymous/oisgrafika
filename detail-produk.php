<?php
require_once __DIR__ . '/includes/tier-helpers.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) redirect(SITE_URL . '/produk.php');

$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon FROM products p JOIN categories c ON p.category_id = c.id WHERE p.slug = ? AND p.is_active = 1");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) redirect(SITE_URL . '/produk.php', 'Produk tidak ditemukan', 'error');

// Get variations (Variasi 1 & 2 — axes) + image map
$varStmt = $pdo->prepare("SELECT * FROM product_variations WHERE product_id = ? ORDER BY sort_order ASC");
$varStmt->execute([$product['id']]);
$variations = $varStmt->fetchAll();

$variationAxes = [];
foreach ($variations as $v) {
    $opts = json_decode($v['options'], true) ?? [];
    $imgMapRaw = !empty($v['image_map']) ? (json_decode($v['image_map'], true) ?: []) : [];
    
    // Normalize image_map keys (trim whitespace, biar match dengan $opt nanti)
    $imgMap = [];
    foreach ($imgMapRaw as $k => $val) {
        $imgMap[trim($k)] = $val;
    }
    
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

// Pre-build COMBO_MAP JSON untuk JS injection (lebih reliable daripada inline encode)
$comboMapJs = [];
foreach ($comboMap as $key => $vi) {
    $comboMapJs[$key] = [
        'id' => (int)$vi['id'],
        'price' => (int)$vi['price'],
        'stock' => (int)$vi['stock'],
        'sku' => (string)($vi['sku'] ?? ''),
    ];
}
$comboMapJson = !empty($comboMapJs) ? json_encode($comboMapJs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '{}';
if ($comboMapJson === false) $comboMapJson = '{}';

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
        <div class="detail-main-image" id="mainImage">
            <?php if (!empty($images)): ?>
                <img src="<?= SITE_URL ?>/<?= clean($images[0]['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:var(--radius);cursor:zoom-in;" onclick="openMainImageLightbox()">
            <?php elseif ($product['image']): ?>
                <img src="<?= SITE_URL ?>/<?= clean($product['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:var(--radius);cursor:zoom-in;" onclick="openMainImageLightbox()">
            <?php else: ?>
                <?= $iconMap[$product['type']] ?? '📦' ?>
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
                                $optTrim = trim($opt);
                                $imgPath = '';
                                if ($axis['has_images'] && !empty($axis['image_map'])) {
                                    // Lookup direct
                                    if (isset($axis['image_map'][$optTrim])) {
                                        $imgPath = $axis['image_map'][$optTrim];
                                    } else {
                                        // Fallback: case-insensitive lookup
                                        foreach ($axis['image_map'] as $mapKey => $mapVal) {
                                            if (strcasecmp(trim($mapKey), $optTrim) === 0) {
                                                $imgPath = $mapVal;
                                                break;
                                            }
                                        }
                                    }
                                }
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
            <?php endif; ?>

            <div class="detail-section">
                <div class="detail-section-label">Jumlah:</div>
                <div class="qty-control">
                    <button type="button" class="qty-minus" onclick="changeQty(-1)">−</button>
                    <input type="number" name="quantity" id="qtyInput" value="<?= $product['min_purchase'] ?: 1 ?>" min="<?= $product['min_purchase'] ?: 1 ?>" max="<?= !empty($variationAxes) ? 99999 : ($product['stock'] ?: 999) ?>" oninput="updateTierPrice()">
                    <button type="button" class="qty-plus" onclick="changeQty(1)">+</button>
                </div>
                <?php if (!empty($product['min_purchase']) && $product['min_purchase'] > 1): ?>
                    <small style="color: var(--text-muted); margin-top: 6px; display: block;">Min. pembelian: <?= $product['min_purchase'] ?> unit</small>
                <?php endif; ?>
            </div>

            <!-- Subtotal display - selalu show -->
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

<?php
// === Reviews Section ===
require_once __DIR__ . '/includes/review-helpers.php';
$ratingStats = getProductRatingStats($product['id'], $pdo);
$filterStars = isset($_GET['stars']) ? (int)$_GET['stars'] : null;
if ($filterStars && ($filterStars < 1 || $filterStars > 5)) $filterStars = null;
$reviews = getProductReviews($product['id'], $pdo, 20, 0, $filterStars);
?>

<div class="product-reviews-section" id="reviews">
    <h3 style="font-size:18px;margin:0 0 16px;">⭐ Penilaian Produk</h3>
    
    <?php if ($ratingStats['total_reviews'] > 0): ?>
        <div class="review-summary">
            <div class="review-summary-left">
                <div class="review-avg-rating"><?= number_format($ratingStats['avg_rating'], 1) ?></div>
                <div><?= renderStars($ratingStats['avg_rating'], 18) ?></div>
                <div class="review-total"><?= $ratingStats['total_reviews'] ?> penilaian</div>
            </div>
            <div class="review-stars-bar">
                <?php for ($s = 5; $s >= 1; $s--): 
                    $count = (int)$ratingStats['r' . $s];
                    $pct = $ratingStats['total_reviews'] > 0 ? ($count / $ratingStats['total_reviews']) * 100 : 0;
                ?>
                    <div class="bar-row">
                        <span><?= $s ?>★</span>
                        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
                        <span style="color:var(--text-muted);"><?= $count ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="review-filters">
            <a href="?slug=<?= clean($product['slug']) ?>#reviews" class="review-filter-btn <?= !$filterStars ? 'active' : '' ?>">Semua (<?= $ratingStats['total_reviews'] ?>)</a>
            <?php for ($s = 5; $s >= 1; $s--): if ($ratingStats['r' . $s] > 0): ?>
                <a href="?slug=<?= clean($product['slug']) ?>&stars=<?= $s ?>#reviews" class="review-filter-btn <?= $filterStars == $s ? 'active' : '' ?>"><?= $s ?>★ (<?= $ratingStats['r' . $s] ?>)</a>
            <?php endif; endfor; ?>
        </div>
        
        <div class="reviews-list">
            <?php foreach ($reviews as $rev): 
                $initials = strtoupper(substr($rev['user_name'] ?: $rev['user_email'], 0, 2));
            ?>
                <div class="review-item">
                    <div class="review-user">
                        <div class="review-avatar"><?= clean($initials) ?></div>
                        <div class="review-user-name"><?= clean(substr($rev['user_name'] ?: 'User', 0, 3)) ?>***<?= clean(substr($rev['user_name'] ?: 'User', -2)) ?></div>
                        <div class="review-date"><?= date('d M Y', strtotime($rev['created_at'])) ?></div>
                    </div>
                    <div class="review-body">
                        <div><?= renderStars($rev['rating'], 14) ?></div>
                        <?php if (!empty($rev['variation_label'])): ?>
                            <div class="review-variation">Varian: <?= clean(str_replace('|', ' / ', $rev['variation_label'])) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($rev['comment'])): ?>
                            <div class="review-text"><?= clean($rev['comment']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($rev['images'])): ?>
                            <div class="review-images">
                                <?php foreach ($rev['images'] as $img): ?>
                                    <div class="review-image" onclick="window.open('<?= SITE_URL ?>/<?= clean($img['image_path']) ?>', '_blank')">
                                        <img src="<?= SITE_URL ?>/<?= clean($img['image_path']) ?>" alt="" loading="lazy">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($rev['reply'])): ?>
                            <div class="review-reply">
                                <div class="review-reply-header">🏪 Balasan dari Ois Grafika</div>
                                <div class="review-reply-text"><?= clean($rev['reply']['reply']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align:center;padding:30px 20px;color:var(--text-muted);">
            <div style="font-size:36px;margin-bottom:10px;">⭐</div>
            <p>Belum ada penilaian untuk produk ini</p>
        </div>
    <?php endif; ?>
</div>

<script>
// === Global vars ===
const basePrice = <?= (int)$product['base_price'] ?>;
const useTier = <?= !empty($tiers) ? 'true' : 'false' ?>;
const hasVariations = <?= !empty($variationAxes) ? 'true' : 'false' ?>;
const axesCount = <?= count($variationAxes) ?>;

// Map kombinasi → {price, stock, sku, id}
const COMBO_MAP = <?= $comboMapJson ?>;

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

function changeQty(delta) {
    const inp = document.getElementById('qtyInput');
    let cur = parseInt(inp.value) || 1;
    const min = parseInt(inp.min) || 1;
    const max = parseInt(inp.max) || 999;
    cur = cur + delta;
    if (cur < min) cur = min;
    if (cur > max) cur = max;
    inp.value = cur;
    updateTierPrice();
}

// Lightbox untuk gambar produk
function openMainImageLightbox() {
    const mainImg = document.querySelector('#mainImage img');
    if (mainImg && mainImg.src && typeof openLightbox === 'function') {
        openLightbox(mainImg.src);
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