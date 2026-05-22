<?php
$pageTitle = 'Checkout';
require_once __DIR__ . '/includes/shipping-helpers.php';
require_once __DIR__ . '/includes/tier-helpers.php';
requireLogin();

// Ambil items keranjang
$stmt = $pdo->prepare("
    SELECT c.*, p.id AS pid, p.name AS product_name, p.base_price, p.use_tier_pricing, p.type, p.image, p.weight, p.slug,
           vi.combination AS variant_name, vi.price AS variant_price
    FROM cart c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN product_variation_items vi ON c.variant_id = vi.id
    WHERE c.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll();

if (empty($items)) {
    redirect(SITE_URL . '/keranjang.php', 'Keranjang kosong', 'warning');
}

// Hitung subtotal & berat (pake tier pricing atau harga variant)
$subtotal = 0;
$totalWeight = 0;
$hasPhysical = false;
foreach ($items as &$item) {
    $product = ['id' => $item['pid'], 'base_price' => $item['base_price'], 'use_tier_pricing' => $item['use_tier_pricing']];
    $basePrice = getProductPrice($product, $item['quantity'], $pdo);
    
    // Kalo ada variant_price, pake itu (override tier)
    if (!empty($item['variant_price']) && $item['variant_price'] > 0) {
        $item['unit_price'] = (float)$item['variant_price'];
    } else {
        $item['unit_price'] = $basePrice;
    }
    
    if (!empty($item['variant_name'])) {
        $item['variant_name'] = str_replace('|', ' / ', $item['variant_name']);
    }
    
    $item['subtotal'] = $item['unit_price'] * $item['quantity'];
    $subtotal += $item['subtotal'];
    if ($item['type'] === 'fisik') {
        $hasPhysical = true;
        $totalWeight += ($item['weight'] ?? 500) * $item['quantity'];
    }
}
unset($item);

// Get alamat default
$user = getCurrentUser();
$defaultAddress = getDefaultAddress($_SESSION['user_id'], $pdo);

// Get shipping options
$shippingOptions = $hasPhysical ? getShippingOptions($totalWeight, $pdo) : [];

// Rekomendasi "Beli Sekalian" - 4 produk termurah yang belum di cart
$cartProductIds = array_unique(array_column($items, 'product_id'));
$placeholders = !empty($cartProductIds) ? implode(',', array_fill(0, count($cartProductIds), '?')) : 'NULL';
$recoStmt = $pdo->prepare("
    SELECT id, name, slug, base_price, image, type
    FROM products
    WHERE is_active = 1 AND id NOT IN ($placeholders) AND base_price > 0
    ORDER BY base_price ASC, sold DESC
    LIMIT 4
");
$recoStmt->execute($cartProductIds);
$recommendations = $recoStmt->fetchAll();

// Handle add recommendation to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reco'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $recoPid = (int)$_POST['reco_product_id'];
        // Cek apa udah di cart (skip kalau udah ada)
        $exists = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? AND product_id = ? AND variant_id IS NULL");
        $exists->execute([$_SESSION['user_id'], $recoPid]);
        if (!$exists->fetch()) {
            $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)")
                ->execute([$_SESSION['user_id'], $recoPid]);
        }
        redirect(SITE_URL . '/checkout.php', '+ Ditambahkan ke pesanan');
    }
}

// Handle POST (buat pesanan)
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token tidak valid';
    } else {
        $addressId = (int)($_POST['address_id'] ?? 0);
        $shippingCode = clean($_POST['shipping_courier'] ?? '');
        $paymentMethod = clean($_POST['payment_method'] ?? '');
        $voucherCode = trim($_POST['voucher_code'] ?? '');
        $sellerNote = clean($_POST['seller_note'] ?? '');
        $referralCode = trim($_POST['referral_code'] ?? '');
        $isDropship = isset($_POST['is_dropshipper']) ? 1 : 0;
        $dropName = clean($_POST['dropshipper_name'] ?? '');
        $dropPhone = clean($_POST['dropshipper_phone'] ?? '');
        
        // Validasi
        if ($hasPhysical && !$addressId) $error = 'Pilih alamat pengiriman';
        elseif ($hasPhysical && !$shippingCode) $error = 'Pilih metode pengiriman';
        elseif (!$paymentMethod) $error = 'Pilih metode pembayaran';
        
        if (!$error && $hasPhysical) {
            $addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
            $addrStmt->execute([$addressId, $_SESSION['user_id']]);
            $selectedAddress = $addrStmt->fetch();
            if (!$selectedAddress) $error = 'Alamat tidak valid';
        }
        
        // Hitung ongkir
        $shippingCost = 0;
        $shippingMethod = null;
        if (!$error && $hasPhysical && $shippingCode) {
            $shipResult = calculateShipping($totalWeight, $shippingCode, $pdo);
            if ($shipResult) {
                $shippingCost = $shipResult['cost'];
                $shippingMethod = $shipResult['method'];
            }
        }
        
        // Voucher
        $voucherDiscount = 0;
        $voucherId = null;
        $shippingDiscount = 0;
        if (!$error && !empty($voucherCode)) {
            $voucherCheck = checkVoucher($voucherCode, $subtotal, $_SESSION['user_id'], $pdo);
            if (!$voucherCheck['ok']) {
                $error = $voucherCheck['msg'];
            } else {
                $voucherId = $voucherCheck['voucher']['id'];
                if ($voucherCheck['voucher']['type'] === 'shipping_discount') {
                    $shippingDiscount = min($shippingCost, $voucherCheck['discount']);
                } else {
                    $voucherDiscount = $voucherCheck['discount'];
                }
            }
        }
        
        if (!$error) {
            $serviceFee = 0; // GRATIS - web kita sengaja bikin murah
            $finalAmount = $subtotal + $shippingCost - $shippingDiscount + $serviceFee - $voucherDiscount;
            
            try {
                $pdo->beginTransaction();
                
                $orderCode = generateOrderCode();
                $shippingAddressText = $hasPhysical ? formatAddressShort($selectedAddress) . ', ' . $selectedAddress['province'] . ' ' . $selectedAddress['postal_code'] : null;
                
                $estStart = $hasPhysical && $shippingMethod ? date('Y-m-d', strtotime('+' . $shippingMethod['estimate_days_min'] . ' days')) : null;
                $estEnd = $hasPhysical && $shippingMethod ? date('Y-m-d', strtotime('+' . $shippingMethod['estimate_days_max'] . ' days')) : null;
                
                $ins = $pdo->prepare("INSERT INTO orders 
                    (order_code, user_id, address_id, recipient_name, recipient_phone, shipping_address, 
                     shipping_courier, shipping_service, shipping_cost, shipping_discount,
                     estimated_arrival_start, estimated_arrival_end,
                     total_amount, service_fee, voucher_id, voucher_discount, final_amount,
                     payment_method, payment_status, order_status,
                     buyer_note, seller_note, is_dropshipper, dropshipper_name, dropshipper_phone,
                     referral_code_used)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?, ?, ?, ?)");
                
                $ins->execute([
                    $orderCode, $_SESSION['user_id'],
                    $hasPhysical ? $addressId : null,
                    $hasPhysical ? $selectedAddress['recipient_name'] : null,
                    $hasPhysical ? $selectedAddress['phone'] : null,
                    $shippingAddressText,
                    $shippingCode ?: null, $shippingMethod ? $shippingMethod['name'] : null,
                    $shippingCost, $shippingDiscount,
                    $estStart, $estEnd,
                    $subtotal, $serviceFee, $voucherId, $voucherDiscount, $finalAmount,
                    $paymentMethod,
                    $sellerNote, $sellerNote, $isDropship, $dropName ?: null, $dropPhone ?: null,
                    $referralCode ?: null
                ]);
                
                $orderId = $pdo->lastInsertId();
                
                // Insert items
                $itemIns = $pdo->prepare("INSERT INTO order_items (order_id, product_id, variant_id, product_name, variant_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($items as $i) {
                    $itemIns->execute([$orderId, $i['product_id'], $i['variant_id'], $i['product_name'], $i['variant_name'], $i['unit_price'], $i['quantity'], $i['subtotal']]);
                }
                
                // Insert tracking awal
                $pdo->prepare("INSERT INTO shipment_trackings (order_id, status, description, tracked_at) VALUES (?, 'Pesanan dibuat', 'Menunggu pembayaran', NOW())")->execute([$orderId]);
                
                // Referral commission
                if (!empty($referralCode)) {
                    $refStmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND id != ?");
                    $refStmt->execute([$referralCode, $_SESSION['user_id']]);
                    $referrer = $refStmt->fetch();
                    if ($referrer) {
                        $commission = $subtotal * (REFERRAL_COMMISSION_PERCENT / 100);
                        $pdo->prepare("INSERT INTO referral_commissions (referrer_id, referred_user_id, order_id, commission) VALUES (?, ?, ?, ?)")
                            ->execute([$referrer['id'], $_SESSION['user_id'], $orderId, $commission]);
                    }
                }
                
                // Voucher usage
                if ($voucherId) {
                    $pdo->prepare("INSERT INTO voucher_usages (voucher_id, user_id, order_id) VALUES (?, ?, ?)")->execute([$voucherId, $_SESSION['user_id'], $orderId]);
                    $pdo->prepare("UPDATE vouchers SET used_count = used_count + 1 WHERE id = ?")->execute([$voucherId]);
                }
                
                // Clear cart
                $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$_SESSION['user_id']]);
                
                $pdo->commit();
                redirect(SITE_URL . '/order-detail.php?code=' . $orderCode, '🎉 Pesanan berhasil dibuat!');
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Gagal: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/checkout-v2.css?v=<?= @filemtime(__DIR__ . '/assets/css/checkout-v2.css') ?: '1.0' ?>">

<div class="checkout-v2">
    <?php if ($error): ?>
        <div class="flash flash-error" style="margin: 12px 16px;"><?= clean($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="checkoutForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <!-- ===== ADDRESS CARD ===== -->
        <?php if ($hasPhysical): ?>
            <div class="co-card">
                <?php if ($defaultAddress): ?>
                    <input type="hidden" name="address_id" value="<?= $defaultAddress['id'] ?>">
                    <a href="<?= SITE_URL ?>/alamat.php" class="address-card">
                        <span class="address-icon">📍</span>
                        <div class="address-content">
                            <div class="address-head">
                                <strong><?= clean($defaultAddress['recipient_name']) ?></strong>
                                <span class="address-phone">(<?= clean($defaultAddress['phone']) ?>)</span>
                            </div>
                            <div class="address-detail"><?= clean(formatAddressShort($defaultAddress)) ?>, <?= clean($defaultAddress['province']) ?> <?= clean($defaultAddress['postal_code']) ?></div>
                        </div>
                        <span class="address-arrow">›</span>
                    </a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/alamat.php?action=add" class="address-empty">
                        <span style="font-size: 24px;">📍</span>
                        <div>
                            <strong>Tambah Alamat Pengiriman</strong>
                            <div style="font-size: 12px; color: var(--text-muted);">Wajib diisi sebelum checkout</div>
                        </div>
                        <span style="font-size: 20px; color: var(--text-muted);">›</span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ===== PRODUCT LIST ===== -->
        <div class="co-card">
            <div class="co-store-head">
                <span class="store-badge">Toko</span>
                <strong>Ois Grafika</strong>
            </div>
            
            <?php foreach ($items as $item):
                $iconMap = ['jasa'=>'🎨','digital'=>'💾','fisik'=>'📦'];
            ?>
                <div class="co-product-item">
                    <div class="co-prod-img">
                        <?php if ($item['image']): ?>
                            <img src="<?= SITE_URL ?>/<?= clean($item['image']) ?>" alt="">
                        <?php else: ?>
                            <?= $iconMap[$item['type']] ?? '📦' ?>
                        <?php endif; ?>
                    </div>
                    <div class="co-prod-info">
                        <div class="co-prod-name"><?= clean($item['product_name']) ?></div>
                        <?php if ($item['variant_name']): ?>
                            <div class="co-prod-variant"><?= clean($item['variant_name']) ?></div>
                        <?php endif; ?>
                        <div class="co-prod-bottom">
                            <span class="co-prod-price"><?= rupiah($item['unit_price']) ?></span>
                            <span class="co-prod-qty">x<?= $item['quantity'] ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Pesan untuk penjual -->
            <a href="#" class="co-row" onclick="toggleNote(event)">
                <span>Pesan untuk Penjual</span>
                <span class="co-row-val text-muted">Tinggalkan pesan ›</span>
            </a>
            <div id="noteInput" style="display:none; padding: 0 14px 14px;">
                <textarea name="seller_note" class="form-input" placeholder="Contoh: Tolong dibungkus rapi" rows="2"></textarea>
            </div>
        </div>

        <!-- ===== SHIPPING OPTIONS ===== -->
        <?php if ($hasPhysical && !empty($shippingOptions)): ?>
            <div class="co-card">
                <div class="co-section-title">
                    <span>Opsi Pengiriman</span>
                    <small>Berat <?= number_format($totalWeight / 1000, 1) ?> kg</small>
                </div>
                
                <?php foreach ($shippingOptions as $idx => $opt): ?>
                    <label class="shipping-option <?= $idx === 0 ? 'selected' : '' ?>">
                        <input type="radio" name="shipping_courier" value="<?= $opt['code'] ?>" <?= $idx === 0 ? 'checked' : '' ?> 
                               data-cost="<?= $opt['cost'] ?>" onchange="updateShippingChoice(this)">
                        <div class="shipping-content">
                            <div class="shipping-head">
                                <strong><?= clean($opt['name']) ?></strong>
                                <span class="shipping-cost"><?= rupiah($opt['cost']) ?></span>
                            </div>
                            <div class="shipping-meta">
                                <span class="shipping-icon">🚚</span>
                                <span><?= clean($opt['estimate_label']) ?></span>
                                <?php if ($opt['type'] === 'cargo'): ?>
                                    <span class="cargo-badge">Cargo</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ===== BELI SEKALIAN (Rekomendasi) ===== -->
        <?php if (!empty($recommendations)): ?>
            <?php
            $recoTotal = array_sum(array_column($recommendations, 'base_price'));
            $recoSaving = (int)($recoTotal * 0.3); // dummy "savings" 30%
            ?>
            <div class="co-card reco-card">
                <div class="reco-head">
                    <span class="reco-title">🔥 Beli Sekalian</span>
                    <span class="reco-saving">Hemat <?= rupiah($recoSaving) ?></span>
                    <span class="reco-countdown" id="recoCountdown">00:59:59</span>
                </div>
                <div class="reco-list">
                    <?php $iconMap2 = ['jasa' => '🎨', 'digital' => '💾', 'fisik' => '📦']; ?>
                    <?php foreach ($recommendations as $r): ?>
                        <div class="reco-item">
                            <div class="reco-img">
                                <?php if ($r['image']): ?>
                                    <img src="<?= SITE_URL ?>/<?= clean($r['image']) ?>" alt="">
                                <?php else: ?>
                                    <?= $iconMap2[$r['type']] ?? '📦' ?>
                                <?php endif; ?>
                            </div>
                            <div class="reco-info">
                                <div class="reco-name"><?= clean($r['name']) ?></div>
                                <div style="font-size: 11px; color: var(--success); margin-bottom: 4px;">🚚 Gratis Ongkir</div>
                                <div class="reco-price"><?= rupiah($r['base_price']) ?></div>
                            </div>
                            <button type="button" class="reco-add-btn" onclick="addReco(<?= $r['id'] ?>)">+ Tambah</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Form tersembunyi buat submit add reco -->
            <form method="POST" id="recoForm" style="display:none;">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="reco_product_id" id="recoProductIdInput">
                <input type="hidden" name="add_reco" value="1">
            </form>
        <?php endif; ?>

        <!-- ===== VOUCHER ===== -->
        <div class="co-card">
            <?php
            // Check kalo ada voucher dari URL (dari halaman pilih voucher)
            $autoVoucher = '';
            $autoVoucherName = '';
            if (!empty($_GET['voucher'])) {
                $vCheck = checkVoucher($_GET['voucher'], $subtotal, $_SESSION['user_id'], $pdo);
                if ($vCheck['ok']) {
                    $autoVoucher = $_GET['voucher'];
                    $autoVoucherName = $vCheck['voucher']['name'];
                }
            }
            ?>
            <a href="<?= SITE_URL ?>/voucher.php?subtotal=<?= $subtotal ?>&current=<?= urlencode($autoVoucher) ?>" class="co-row" style="text-decoration:none;">
                <span>🎟️ Voucher</span>
                <span class="co-row-val <?= $autoVoucher ? '' : 'text-muted' ?>" id="voucherStatus">
                    <?= $autoVoucher ? '<span style="color:var(--primary);font-weight:600;">' . clean($autoVoucherName) . '</span>' : 'Pilih voucher ›' ?>
                </span>
            </a>
            <input type="hidden" name="voucher_code" id="voucherCodeInput" value="<?= clean($autoVoucher) ?>">
            
            <!-- Alternatif: input manual -->
            <div style="border-top: 1px solid var(--border); padding: 10px 14px;">
                <details>
                    <summary style="font-size: 12px; color: var(--text-light); cursor: pointer;">Atau masukkan kode manual</summary>
                    <div style="display: flex; gap: 8px; margin-top: 10px;">
                        <input type="text" id="manualVoucher" class="form-input" placeholder="Kode voucher" style="text-transform:uppercase;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="validateVoucher()">Pakai</button>
                    </div>
                    <div id="voucherMsg" style="margin-top: 8px; font-size: 12px;"></div>
                </details>
            </div>
        </div>

        <!-- ===== REFERRAL CODE ===== -->
        <div class="co-card">
            <div class="co-row" onclick="toggleReferral(event)" style="cursor:pointer;">
                <span>🎁 Kode Referral</span>
                <span class="co-row-val text-muted">Optional ›</span>
            </div>
            <div id="referralInput" style="display:none; padding: 0 14px 14px;">
                <input type="text" name="referral_code" class="form-input" placeholder="Kode referral teman" style="text-transform:uppercase;">
            </div>
        </div>

        <!-- ===== PAYMENT METHOD ===== -->
        <div class="co-card">
            <div class="co-section-title"><span>Metode Pembayaran</span></div>
            
            <?php
            $methods = [
                'cod' => ['💵', 'COD (Bayar di Tempat)', 'Bayar saat barang sampai'],
                'qris' => ['📱', 'QRIS', 'Scan QR semua bank/e-wallet'],
                'va_bca' => ['🏦', 'Transfer BCA', 'Virtual Account BCA'],
                'va_mandiri' => ['🏦', 'Transfer Mandiri', 'Virtual Account Mandiri'],
                'va_bni' => ['🏦', 'Transfer BNI', 'Virtual Account BNI'],
                'gopay' => ['💚', 'GoPay', 'E-wallet GoPay'],
                'ovo' => ['💜', 'OVO', 'E-wallet OVO'],
                'dana' => ['💙', 'DANA', 'E-wallet DANA'],
            ];
            foreach ($methods as $key => $m):
            ?>
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="<?= $key ?>" required>
                    <span class="payment-icon"><?= $m[0] ?></span>
                    <div class="payment-info">
                        <strong><?= $m[1] ?></strong>
                        <small><?= $m[2] ?></small>
                    </div>
                    <span class="payment-check"></span>
                </label>
            <?php endforeach; ?>
        </div>

        <!-- ===== DROPSHIPPER ===== -->
        <div class="co-card">
            <label class="dropshipper-toggle">
                <div>
                    <strong>📦 Kirim sebagai Dropshipper</strong>
                    <div style="font-size: 11px; color: var(--text-muted);">Aktifkan jika kirim atas nama orang lain</div>
                </div>
                <input type="checkbox" name="is_dropshipper" value="1" id="dropToggle" onchange="document.getElementById('dropFields').style.display = this.checked ? 'block' : 'none'">
            </label>
            <div id="dropFields" style="display:none; padding: 0 14px 14px;">
                <input type="text" name="dropshipper_name" class="form-input" placeholder="Nama pengirim" style="margin-bottom: 8px;">
                <input type="tel" name="dropshipper_phone" class="form-input" placeholder="No. HP pengirim">
            </div>
        </div>

        <!-- ===== PAYMENT SUMMARY ===== -->
        <div class="co-card">
            <div class="co-section-title"><span>Rincian Pembayaran</span></div>
            <div class="summary-list">
                <div class="summary-line">
                    <span>Subtotal Pesanan</span>
                    <span><?= rupiah($subtotal) ?></span>
                </div>
                <?php if ($hasPhysical): ?>
                    <div class="summary-line">
                        <span>Subtotal Pengiriman</span>
                        <span id="sumShipping"><?= rupiah($shippingOptions[0]['cost'] ?? 0) ?></span>
                    </div>
                <?php endif; ?>
                <div class="summary-line discount" id="sumVoucherRow" style="display:none;">
                    <span>Diskon Voucher</span>
                    <span id="sumVoucher">-Rp 0</span>
                </div>
                <div class="summary-line discount" id="sumShipDiscRow" style="display:none;">
                    <span>Diskon Ongkir</span>
                    <span id="sumShipDisc">-Rp 0</span>
                </div>
                <div class="summary-line total">
                    <span>Total Pembayaran</span>
                    <span class="total-amount" id="sumTotal"><?= rupiah($subtotal + ($shippingOptions[0]['cost'] ?? 0)) ?></span>
                </div>
            </div>
        </div>

        <!-- Spacer for sticky bottom -->
        <div style="height: 80px;"></div>

        <!-- ===== STICKY BOTTOM ===== -->
        <div class="co-sticky-bottom">
            <div class="sticky-info">
                <div style="font-size: 11px; color: var(--text-muted);">Total Pembayaran</div>
                <div style="font-size: 18px; font-weight: 800; color: var(--primary); font-family: var(--font-display);" id="stickyTotal"><?= rupiah($subtotal + ($shippingOptions[0]['cost'] ?? 0)) ?></div>
            </div>
            <button type="submit" name="create_order" class="btn-checkout">Buat Pesanan</button>
        </div>
    </form>
</div>

<script>
// Initial values
const initSubtotal = <?= $subtotal ?>;
const initServiceFee = 0;
let currentShipping = <?= $shippingOptions[0]['cost'] ?? 0 ?>;
let currentVoucherDisc = 0;
let currentShipDisc = 0;

function formatRp(num) {
    return 'Rp ' + Math.round(num).toLocaleString('id-ID');
}

function updateTotal() {
    const total = initSubtotal + currentShipping + initServiceFee - currentVoucherDisc - currentShipDisc;
    document.getElementById('sumTotal').textContent = formatRp(total);
    document.getElementById('stickyTotal').textContent = formatRp(total);
    
    // Update voucher rows
    document.getElementById('sumVoucherRow').style.display = currentVoucherDisc > 0 ? 'flex' : 'none';
    document.getElementById('sumVoucher').textContent = '-' + formatRp(currentVoucherDisc);
    
    document.getElementById('sumShipDiscRow').style.display = currentShipDisc > 0 ? 'flex' : 'none';
    document.getElementById('sumShipDisc').textContent = '-' + formatRp(currentShipDisc);
}

function updateShippingChoice(input) {
    // Highlight selected
    document.querySelectorAll('.shipping-option').forEach(el => el.classList.remove('selected'));
    input.closest('.shipping-option').classList.add('selected');
    
    currentShipping = parseFloat(input.dataset.cost);
    const sumShip = document.getElementById('sumShipping');
    if (sumShip) sumShip.textContent = formatRp(currentShipping);
    updateTotal();
}

function toggleNote(e) {
    e.preventDefault();
    const el = document.getElementById('noteInput');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function toggleReferral(e) {
    e.preventDefault();
    const el = document.getElementById('referralInput');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

async function validateVoucher() {
    const code = document.getElementById('manualVoucher').value.trim().toUpperCase();
    const msg = document.getElementById('voucherMsg');
    
    if (!code) {
        msg.innerHTML = '<span style="color:var(--danger)">Masukkan kode voucher</span>';
        return;
    }
    
    try {
        const res = await fetch('<?= SITE_URL ?>/api/check-voucher.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'code=' + encodeURIComponent(code) + '&subtotal=' + initSubtotal
        });
        const data = await res.json();
        
        if (data.ok) {
            msg.innerHTML = '<span style="color:#1d7a4c">✓ ' + data.voucher.name + ' diterapkan</span>';
            document.getElementById('voucherStatus').innerHTML = '<span style="color:var(--primary);font-weight:600;">' + data.voucher.name + '</span>';
            document.getElementById('voucherCodeInput').value = code;
            
            if (data.voucher.type === 'shipping_discount') {
                currentShipDisc = Math.min(currentShipping, data.discount);
                currentVoucherDisc = 0;
            } else {
                currentVoucherDisc = data.discount;
                currentShipDisc = 0;
            }
            updateTotal();
        } else {
            msg.innerHTML = '<span style="color:var(--danger)">✗ ' + data.msg + '</span>';
        }
    } catch (err) {
        msg.innerHTML = '<span style="color:var(--danger)">Error: ' + err.message + '</span>';
    }
}

// Auto-apply voucher dari URL
<?php if (!empty($autoVoucher)): ?>
const autoVoucherType = '<?= $vCheck['voucher']['type'] ?>';
const autoVoucherDiscount = <?= $vCheck['discount'] ?>;
if (autoVoucherType === 'shipping_discount') {
    currentShipDisc = Math.min(currentShipping, autoVoucherDiscount);
} else {
    currentVoucherDisc = autoVoucherDiscount;
}
updateTotal();
<?php endif; ?>

// Payment option click handler (highlight)
document.querySelectorAll('.payment-option input').forEach(input => {
    input.addEventListener('change', () => {
        document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
        input.closest('.payment-option').classList.add('selected');
    });
});

updateTotal();

// ===== Beli Sekalian =====
function addReco(productId) {
    document.getElementById('recoProductIdInput').value = productId;
    document.getElementById('recoForm').submit();
}

// Countdown dummy 1 jam
const countdownEl = document.getElementById('recoCountdown');
if (countdownEl) {
    let totalSeconds = 3599;
    setInterval(() => {
        if (totalSeconds <= 0) return;
        totalSeconds--;
        const h = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
        const m = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
        const s = String(totalSeconds % 60).padStart(2, '0');
        countdownEl.textContent = `${h}:${m}:${s}`;
    }, 1000);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
