<?php
$pageTitle = 'Checkout';
require_once __DIR__ . '/includes/header.php';
requireLogin();

// Ambil items
$stmt = $pdo->prepare("
    SELECT c.*, p.name AS product_name, p.base_price, p.type,
           v.name AS variant_name, v.price_modifier
    FROM cart c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN product_variants v ON c.variant_id = v.id
    WHERE c.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll();

if (empty($items)) {
    redirect(SITE_URL . '/keranjang.php', 'Keranjang kosong, tambah produk dulu', 'warning');
}

$subtotal = 0;
foreach ($items as &$item) {
    $item['unit_price'] = $item['base_price'] + ($item['price_modifier'] ?? 0);
    $item['subtotal'] = $item['unit_price'] * $item['quantity'];
    $subtotal += $item['subtotal'];
}
unset($item);

$user = getCurrentUser();
$error = '';

// Handle submit order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token tidak valid';
    } else {
        $address = clean($_POST['address'] ?? '');
        $notes = clean($_POST['notes'] ?? '');
        $payment = clean($_POST['payment_method'] ?? '');
        $refCode = trim($_POST['referral_code'] ?? '');
        
        // Cek apakah ada produk fisik yang butuh alamat
        $needAddress = false;
        foreach ($items as $i) {
            if ($i['type'] === 'fisik') { $needAddress = true; break; }
        }
        
        if ($needAddress && empty($address)) {
            $error = 'Alamat pengiriman wajib diisi untuk produk fisik';
        } elseif (empty($payment)) {
            $error = 'Pilih metode pembayaran';
        } else {
            // Validasi referral kalau ada
            $refOk = true;
            if (!empty($refCode)) {
                $rs = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND id != ?");
                $rs->execute([$refCode, $user['id']]);
                if (!$rs->fetch()) {
                    $error = 'Kode referral tidak valid';
                    $refOk = false;
                }
            }
            
            if ($refOk) {
                $shipping = $needAddress ? 15000 : 0;
                $final = $subtotal + $shipping;
                
                try {
                    $pdo->beginTransaction();
                    
                    // Buat order
                    $orderCode = generateOrderCode();
                    $ins = $pdo->prepare("INSERT INTO orders (order_code, user_id, total_amount, shipping_cost, final_amount, payment_method, payment_status, order_status, shipping_address, notes, referral_code_used) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?)");
                    $ins->execute([$orderCode, $user['id'], $subtotal, $shipping, $final, $payment, $address, $notes, $refCode ?: null]);
                    $orderId = $pdo->lastInsertId();
                    
                    // Insert items
                    $itemIns = $pdo->prepare("INSERT INTO order_items (order_id, product_id, variant_id, product_name, variant_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($items as $i) {
                        $itemIns->execute([$orderId, $i['product_id'], $i['variant_id'], $i['product_name'], $i['variant_name'], $i['unit_price'], $i['quantity'], $i['subtotal']]);
                    }
                    
                    // Catat komisi referral (status pending sampai order paid)
                    if (!empty($refCode)) {
                        $refStmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                        $refStmt->execute([$refCode]);
                        $referrer = $refStmt->fetch();
                        if ($referrer) {
                            $commission = $subtotal * (REFERRAL_COMMISSION_PERCENT / 100);
                            $pdo->prepare("INSERT INTO referral_commissions (referrer_id, referred_user_id, order_id, commission) VALUES (?, ?, ?, ?)")
                                ->execute([$referrer['id'], $user['id'], $orderId, $commission]);
                        }
                    }
                    
                    // Kosongkan keranjang
                    $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user['id']]);
                    
                    $pdo->commit();
                    
                    redirect(SITE_URL . '/order-success.php?code=' . $orderCode, 'Pesanan berhasil dibuat!');
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Gagal memproses pesanan: ' . $e->getMessage();
                }
            }
        }
    }
}

// Default ongkir
$needAddress = false;
foreach ($items as $i) {
    if ($i['type'] === 'fisik') { $needAddress = true; break; }
}
$shipping = $needAddress ? 15000 : 0;
$total = $subtotal + $shipping;
?>

<div class="cart-page">
    <form method="POST" class="cart-items">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        
        <h2 style="margin-bottom: 16px;">Checkout</h2>

        <?php if ($error): ?>
            <div class="flash flash-error" style="margin-bottom: 16px;"><?= clean($error) ?></div>
        <?php endif; ?>

        <h3 style="font-size: 15px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">📋 Detail Pesanan</h3>

        <?php foreach ($items as $item): ?>
            <div style="display: flex; justify-content: space-between; padding: 10px 0; font-size: 14px; border-bottom: 1px dashed var(--border);">
                <div>
                    <strong><?= clean($item['product_name']) ?></strong>
                    <?php if ($item['variant_name']): ?>
                        <div style="color: var(--text-muted); font-size: 12px;"><?= clean($item['variant_name']) ?></div>
                    <?php endif; ?>
                    <div style="color: var(--text-muted); font-size: 12px;"><?= $item['quantity'] ?> × <?= rupiah($item['unit_price']) ?></div>
                </div>
                <div style="font-weight: 700; color: var(--primary);"><?= rupiah($item['subtotal']) ?></div>
            </div>
        <?php endforeach; ?>

        <?php if ($needAddress): ?>
            <h3 style="font-size: 15px; margin: 20px 0 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">📍 Alamat Pengiriman</h3>
            
            <div class="form-group">
                <label class="form-label">Alamat Lengkap <span style="color: var(--danger);">*</span></label>
                <textarea name="address" class="form-textarea" placeholder="Jl. Contoh No. 123, RT/RW, Kelurahan, Kecamatan, Kota, Provinsi, Kode Pos" required><?= clean($_POST['address'] ?? '') ?></textarea>
            </div>
        <?php endif; ?>

        <h3 style="font-size: 15px; margin: 20px 0 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">💳 Metode Pembayaran</h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px;">
            <?php
            $methods = [
                'qris' => ['QRIS', '📱'],
                'va_bca' => ['VA BCA', '🏦'],
                'va_mandiri' => ['VA Mandiri', '🏦'],
                'va_bni' => ['VA BNI', '🏦'],
                'gopay' => ['GoPay', '💚'],
                'ovo' => ['OVO', '💜'],
                'dana' => ['DANA', '💙'],
                'shopeepay' => ['ShopeePay', '🧡'],
            ];
            foreach ($methods as $key => $m):
            ?>
                <label style="display: flex; align-items: center; gap: 10px; padding: 12px; border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; transition: all 0.2s;">
                    <input type="radio" name="payment_method" value="<?= $key ?>" required style="accent-color: var(--primary);">
                    <span style="font-size: 22px;"><?= $m[1] ?></span>
                    <span style="font-weight: 600; font-size: 13px;"><?= $m[0] ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <h3 style="font-size: 15px; margin: 20px 0 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">🎁 Kode Referral (Opsional)</h3>

        <div class="form-group">
            <input type="text" name="referral_code" class="form-input" placeholder="Masukkan kode referral teman" style="text-transform: uppercase;" value="<?= clean($_POST['referral_code'] ?? '') ?>">
            <div class="form-help">Kasih kredit ke teman yang merekomendasikan kamu (bukan kode milikmu sendiri ya)</div>
        </div>

        <h3 style="font-size: 15px; margin: 20px 0 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">📝 Catatan</h3>

        <div class="form-group">
            <textarea name="notes" class="form-textarea" placeholder="Catatan khusus untuk pesanan (opsional)"><?= clean($_POST['notes'] ?? '') ?></textarea>
        </div>
    </form>

    <div class="cart-summary">
        <h3 style="margin-bottom: 16px;">Ringkasan</h3>
        
        <div class="summary-row">
            <span>Subtotal</span>
            <span><?= rupiah($subtotal) ?></span>
        </div>
        <?php if ($shipping > 0): ?>
            <div class="summary-row">
                <span>Ongkir</span>
                <span><?= rupiah($shipping) ?></span>
            </div>
        <?php endif; ?>
        
        <div class="summary-row summary-total">
            <span>Total Bayar</span>
            <span class="total-price"><?= rupiah($total) ?></span>
        </div>

        <button type="submit" form="" class="btn btn-primary-solid btn-block btn-lg" style="margin-top: 16px;" onclick="document.querySelector('.cart-items').requestSubmit()">
            Buat Pesanan
        </button>
        
        <p style="font-size: 11px; color: var(--text-muted); text-align: center; margin-top: 12px; line-height: 1.5;">
            Dengan klik tombol di atas kamu menyetujui syarat & ketentuan Ois Grafika
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
