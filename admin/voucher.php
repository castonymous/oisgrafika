<?php
$pageTitle = 'Pilih Voucher';
require_once __DIR__ . '/includes/shipping-helpers.php';
requireLogin();

// Subtotal dari URL (dari checkout)
$subtotal = (float)($_GET['subtotal'] ?? 0);
$currentCode = trim($_GET['current'] ?? '');

// Get semua voucher aktif
$now = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("
    SELECT v.*,
           (v.quota IS NOT NULL AND v.used_count >= v.quota) AS quota_habis,
           (SELECT COUNT(*) FROM voucher_usages WHERE voucher_id = v.id AND user_id = ?) AS used_by_me
    FROM vouchers v
    WHERE v.is_active = 1 AND ? BETWEEN v.start_date AND v.end_date
    ORDER BY 
        CASE v.type WHEN 'shipping_discount' THEN 1 ELSE 2 END,
        v.discount_value DESC
");
$stmt->execute([$_SESSION['user_id'], $now]);
$allVouchers = $stmt->fetchAll();

// Group berdasarkan tipe
$shippingVouchers = array_filter($allVouchers, fn($v) => $v['type'] === 'shipping_discount');
$discountVouchers = array_filter($allVouchers, fn($v) => $v['type'] !== 'shipping_discount');

// Handle apply code manual
$flash = '';
$flashType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_code'])) {
    $code = strtoupper(trim($_POST['code']));
    $check = checkVoucher($code, $subtotal, $_SESSION['user_id'], $pdo);
    if ($check['ok']) {
        // Redirect kembali ke checkout dengan kode
        redirect(SITE_URL . '/checkout.php?voucher=' . urlencode($code), '✓ Voucher diterapkan');
    } else {
        $flash = $check['msg'];
        $flashType = 'error';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/voucher-page.css?v=<?= @filemtime(__DIR__ . '/assets/css/voucher-page.css') ?: '1.0' ?>">

<div class="voucher-page">
    <!-- Header -->
    <div class="voucher-header">
        <a href="<?= SITE_URL ?>/checkout.php" class="back-btn">←</a>
        <h1>Pilih Voucher</h1>
        <span style="width: 32px;"></span>
    </div>

    <!-- Input kode manual -->
    <form method="POST" class="voucher-input-card">
        <input type="text" name="code" class="form-input" placeholder="Masukkan kode voucher" style="text-transform:uppercase;" required>
        <button type="submit" name="apply_code" class="voucher-input-btn">Pakai</button>
    </form>

    <?php if ($flash): ?>
        <div class="flash flash-<?= $flashType ?>" style="margin: 8px 16px;"><?= clean($flash) ?></div>
    <?php endif; ?>

    <!-- VOUCHER GRATIS ONGKIR -->
    <?php if (!empty($shippingVouchers)): ?>
        <div class="voucher-section">
            <h2 class="voucher-section-title">Voucher Gratis Ongkir</h2>
            <?php foreach ($shippingVouchers as $v):
                $belowMin = $subtotal < $v['min_purchase'];
                $kurang = $v['min_purchase'] - $subtotal;
                $disabled = $v['quota_habis'] || $v['used_by_me'] > 0;
            ?>
                <label class="voucher-card voucher-card-shipping <?= $disabled ? 'disabled' : '' ?>">
                    <div class="voucher-card-left">
                        <div class="voucher-icon">🚚</div>
                        <div class="voucher-stamp">GRATIS<br>ONGKIR</div>
                    </div>
                    <div class="voucher-card-body">
                        <div class="voucher-title"><?= clean($v['name']) ?></div>
                        <div class="voucher-min">Min. Belanja <?= $v['min_purchase'] > 0 ? rupiah($v['min_purchase']) : 'Tanpa minimum' ?></div>
                        <?php if ($v['quota']): ?>
                            <div class="voucher-quota">Sisa: <?= $v['quota'] - $v['used_count'] ?>/<?= $v['quota'] ?></div>
                        <?php endif; ?>
                        <div class="voucher-expire">Berlaku s/d <?= date('d M Y', strtotime($v['end_date'])) ?></div>
                        <?php if ($belowMin && !$disabled): ?>
                            <div class="voucher-warning">⚠️ Tambah <?= rupiah($kurang) ?> untuk pakai voucher ini</div>
                        <?php elseif ($v['used_by_me'] > 0): ?>
                            <div class="voucher-warning">✗ Voucher ini sudah pernah kamu pakai</div>
                        <?php elseif ($v['quota_habis']): ?>
                            <div class="voucher-warning">✗ Kuota habis</div>
                        <?php endif; ?>
                    </div>
                    <div class="voucher-card-right">
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="code" value="<?= clean($v['code']) ?>">
                            <button type="submit" name="apply_code" class="voucher-use-btn" <?= ($belowMin || $disabled) ? 'disabled' : '' ?>>
                                <?= $currentCode === $v['code'] ? '✓ Dipilih' : 'Pakai' ?>
                            </button>
                        </form>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- VOUCHER DISKON / CASHBACK -->
    <?php if (!empty($discountVouchers)): ?>
        <div class="voucher-section">
            <h2 class="voucher-section-title">Diskon / Cashback</h2>
            <?php foreach ($discountVouchers as $v):
                $belowMin = $subtotal < $v['min_purchase'];
                $kurang = $v['min_purchase'] - $subtotal;
                $disabled = $v['quota_habis'] || $v['used_by_me'] > 0;
                
                // Hitung label diskon
                if ($v['discount_type'] === 'percentage') {
                    $label = 'Diskon ' . $v['discount_value'] . '%';
                    if ($v['max_discount']) $label .= ' s/d ' . rupiah($v['max_discount']);
                } else {
                    $label = 'Diskon ' . rupiah($v['discount_value']);
                }
            ?>
                <label class="voucher-card voucher-card-discount <?= $disabled ? 'disabled' : '' ?>">
                    <div class="voucher-card-left">
                        <div class="voucher-icon">🛍️</div>
                        <div class="voucher-stamp" style="background: rgba(255,255,255,0.25);">VOUCHER</div>
                    </div>
                    <div class="voucher-card-body">
                        <div class="voucher-title"><?= $label ?></div>
                        <div class="voucher-min">Min. Belanja <?= $v['min_purchase'] > 0 ? rupiah($v['min_purchase']) : 'Tanpa minimum' ?></div>
                        <?php if ($v['quota']): ?>
                            <div class="voucher-quota">Sisa: <?= $v['quota'] - $v['used_count'] ?>/<?= $v['quota'] ?></div>
                        <?php endif; ?>
                        <div class="voucher-expire">Berlaku s/d <?= date('d M Y', strtotime($v['end_date'])) ?></div>
                        <?php if ($belowMin && !$disabled): ?>
                            <div class="voucher-warning">⚠️ Tambah <?= rupiah($kurang) ?> untuk pakai voucher ini</div>
                        <?php elseif ($v['used_by_me'] > 0): ?>
                            <div class="voucher-warning">✗ Voucher ini sudah pernah kamu pakai</div>
                        <?php elseif ($v['quota_habis']): ?>
                            <div class="voucher-warning">✗ Kuota habis</div>
                        <?php endif; ?>
                    </div>
                    <div class="voucher-card-right">
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="code" value="<?= clean($v['code']) ?>">
                            <button type="submit" name="apply_code" class="voucher-use-btn" <?= ($belowMin || $disabled) ? 'disabled' : '' ?>>
                                <?= $currentCode === $v['code'] ? '✓ Dipilih' : 'Pakai' ?>
                            </button>
                        </form>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($allVouchers)): ?>
        <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
            <div style="font-size: 48px; margin-bottom: 16px;">🎟️</div>
            <h3>Belum ada voucher tersedia</h3>
            <p style="font-size: 13px; margin-top: 6px;">Coba lagi nanti ya!</p>
        </div>
    <?php endif; ?>
    
    <div style="height: 40px;"></div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
