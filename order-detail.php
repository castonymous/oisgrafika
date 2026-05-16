<?php
$pageTitle = 'Detail Pesanan';
require_once __DIR__ . '/includes/shipping-helpers.php';
requireLogin();

$code = $_GET['code'] ?? '';
$stmt = $pdo->prepare("SELECT o.*, sm.name AS courier_name FROM orders o LEFT JOIN shipping_methods sm ON o.shipping_courier = sm.code WHERE o.order_code = ? AND o.user_id = ?");
$stmt->execute([$code, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect(SITE_URL . '/dashboard.php', 'Pesanan tidak ditemukan', 'error');
}

// Get items
$itemsStmt = $pdo->prepare("SELECT oi.*, p.image, p.slug FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$itemsStmt->execute([$order['id']]);
$orderItems = $itemsStmt->fetchAll();

// Get tracking
$trackStmt = $pdo->prepare("SELECT * FROM shipment_trackings WHERE order_id = ? ORDER BY tracked_at DESC");
$trackStmt->execute([$order['id']]);
$trackings = $trackStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/checkout-v2.css?v=<?= @filemtime(__DIR__ . '/assets/css/checkout-v2.css') ?: '1.0' ?>">

<style>
.order-detail { max-width: 720px; margin: 0 auto; padding: 12px; }
.estimate-card { background: linear-gradient(135deg, #26aa99, #1d7a4c); color: white; border-radius: var(--radius-lg); padding: 16px; margin-bottom: 10px; }
.estimate-card .label { font-size: 12px; opacity: 0.9; }
.estimate-card .date { font-size: 18px; font-weight: 800; font-family: var(--font-display); }
.shipping-info-card { background: white; border-radius: var(--radius-lg); border: 1px solid var(--border); padding: 14px; margin-bottom: 10px; }
.shipping-info-card .courier { font-size: 14px; font-weight: 600; margin-bottom: 4px; }
.shipping-info-card .resi { font-size: 12px; color: var(--text-muted); margin-bottom: 8px; font-family: monospace; }
.shipping-info-card .status { display: flex; align-items: center; gap: 8px; padding: 10px; background: #f0fdf4; border-radius: var(--radius); }
.shipping-info-card .status-icon { font-size: 18px; }
.shipping-info-card .status-text { color: var(--success); font-weight: 600; font-size: 13px; }
.shipping-info-card .status-time { font-size: 11px; color: var(--text-muted); }

.tracking-timeline { list-style: none; padding: 8px 0 0; }
.tracking-timeline li { display: grid; grid-template-columns: 24px 1fr; gap: 10px; padding: 8px 0; position: relative; }
.tracking-timeline li::before { content: ''; position: absolute; left: 11px; top: 24px; bottom: -8px; width: 2px; background: var(--border); }
.tracking-timeline li:last-child::before { display: none; }
.track-dot { width: 12px; height: 12px; border-radius: 50%; background: var(--border-dark); margin-top: 6px; position: relative; z-index: 1; }
.tracking-timeline li:first-child .track-dot { background: var(--success); box-shadow: 0 0 0 3px #d1f4dd; }
.track-content strong { font-size: 13px; }
.track-content small { display: block; color: var(--text-muted); font-size: 11px; margin-top: 2px; }
</style>

<div class="order-detail">
    <a href="<?= SITE_URL ?>/dashboard.php?tab=orders" style="display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;color:var(--text-light);">← Kembali</a>
    
    <h1 style="font-size: 18px; margin-bottom: 12px;">Rincian Pesanan</h1>
    
    <!-- Order code -->
    <div class="co-card" style="padding: 12px 14px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: 11px; color: var(--text-muted);">Nomor Pesanan</div>
                <div style="font-family: monospace; font-weight: 700; font-size: 14px;"><?= clean($order['order_code']) ?></div>
            </div>
            <span class="status-badge status-<?= $order['order_status'] ?>"><?= ucfirst($order['order_status']) ?></span>
        </div>
    </div>
    
    <!-- Estimate -->
    <?php if ($order['estimated_arrival_start']): ?>
        <div class="estimate-card">
            <div class="label">📦 Estimasi Tiba</div>
            <div class="date">
                <?= date('d M', strtotime($order['estimated_arrival_start'])) ?> - <?= date('d M Y', strtotime($order['estimated_arrival_end'])) ?>
            </div>
            <div style="font-size: 11px; margin-top: 6px; opacity: 0.9;">
                Garansi tiba: dapatkan voucher Rp10.000 jika pesanan belum tiba pada <?= date('d M Y', strtotime($order['estimated_arrival_end'])) ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Shipping info -->
    <?php if ($order['shipping_courier']): ?>
        <div class="shipping-info-card">
            <div class="courier"><?= clean($order['courier_name'] ?: $order['shipping_courier']) ?></div>
            <?php if ($order['tracking_number']): ?>
                <div class="resi">No. Resi: <?= clean($order['tracking_number']) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($trackings)): ?>
                <div class="status">
                    <span class="status-icon">🚚</span>
                    <div>
                        <div class="status-text"><?= clean($trackings[0]['status']) ?></div>
                        <div class="status-time"><?= date('d-m-Y H:i', strtotime($trackings[0]['tracked_at'])) ?></div>
                    </div>
                </div>
                
                <details style="margin-top: 12px;">
                    <summary style="cursor:pointer; font-size:13px; color:var(--primary); font-weight:600;">Lihat riwayat pengiriman</summary>
                    <ul class="tracking-timeline">
                        <?php foreach ($trackings as $t): ?>
                            <li>
                                <div class="track-dot"></div>
                                <div class="track-content">
                                    <strong><?= clean($t['status']) ?></strong>
                                    <small><?= clean($t['description']) ?> · <?= date('d M Y H:i', strtotime($t['tracked_at'])) ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- Address -->
    <?php if ($order['shipping_address']): ?>
        <div class="co-card" style="padding: 14px;">
            <div style="font-size: 13px; font-weight: 700; margin-bottom: 8px;">Alamat Pengiriman</div>
            <div style="font-size: 13px;"><strong><?= clean($order['recipient_name']) ?></strong> <span style="color:var(--text-light)">(<?= clean($order['recipient_phone']) ?>)</span></div>
            <div style="font-size: 12px; color: var(--text-light); line-height: 1.5;"><?= clean($order['shipping_address']) ?></div>
        </div>
    <?php endif; ?>
    
    <!-- Products -->
    <div class="co-card">
        <div class="co-store-head">
            <span class="store-badge">Toko</span>
            <strong>Ois Grafika</strong>
        </div>
        <?php foreach ($orderItems as $item): ?>
            <div class="co-product-item">
                <div class="co-prod-img">
                    <?php if ($item['image']): ?>
                        <img src="<?= SITE_URL ?>/<?= clean($item['image']) ?>" alt="">
                    <?php else: ?>📦<?php endif; ?>
                </div>
                <div class="co-prod-info">
                    <div class="co-prod-name"><?= clean($item['product_name']) ?></div>
                    <?php if ($item['variant_name']): ?>
                        <div class="co-prod-variant"><?= clean($item['variant_name']) ?></div>
                    <?php endif; ?>
                    <div class="co-prod-bottom">
                        <span class="co-prod-price"><?= rupiah($item['price']) ?></span>
                        <span class="co-prod-qty">x<?= $item['quantity'] ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Summary -->
    <div class="co-card">
        <div class="co-section-title"><span>Rincian Pembayaran</span></div>
        <div class="summary-list">
            <div class="summary-line">
                <span>Subtotal Pesanan</span>
                <span><?= rupiah($order['total_amount']) ?></span>
            </div>
            <?php if ($order['shipping_cost'] > 0): ?>
                <div class="summary-line">
                    <span>Subtotal Pengiriman</span>
                    <span><?= rupiah($order['shipping_cost']) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($order['service_fee'] > 0): ?>
                <div class="summary-line">
                    <span>Biaya Layanan</span>
                    <span><?= rupiah($order['service_fee']) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($order['shipping_discount'] > 0): ?>
                <div class="summary-line discount">
                    <span>Diskon Pengiriman</span>
                    <span>-<?= rupiah($order['shipping_discount']) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($order['voucher_discount'] > 0): ?>
                <div class="summary-line discount">
                    <span>Diskon Voucher</span>
                    <span>-<?= rupiah($order['voucher_discount']) ?></span>
                </div>
            <?php endif; ?>
            <div class="summary-line total">
                <span>Total Pembayaran</span>
                <span class="total-amount"><?= rupiah($order['final_amount']) ?></span>
            </div>
            <div class="summary-line" style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--border);">
                <span>Metode Pembayaran</span>
                <span style="font-weight:600;color:var(--text);"><?= strtoupper($order['payment_method']) ?></span>
            </div>
        </div>
    </div>
    
<?php
// Handle action: confirm order completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        if ($order['order_status'] === 'delivered' || $order['order_status'] === 'shipped') {
            $pdo->prepare("UPDATE orders SET order_status = 'completed' WHERE id = ?")->execute([$order['id']]);
            $pdo->prepare("INSERT INTO shipment_trackings (order_id, status, description, tracked_at) VALUES (?, 'Pesanan selesai', 'Pesanan telah dikonfirmasi diterima oleh pembeli', NOW())")->execute([$order['id']]);
            redirect(SITE_URL . '/order-detail.php?code=' . $order['order_code'], '🎉 Terima kasih! Pesanan ditandai selesai');
        }
    }
}
?>

<style>
.sticky-action {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    border-top: 1px solid var(--border);
    padding: 10px 14px;
    display: flex;
    gap: 10px;
    box-shadow: 0 -4px 12px rgba(0,0,0,0.08);
    z-index: 50;
}
.sticky-action .btn-sticky {
    flex: 1;
    padding: 12px;
    border-radius: var(--radius);
    font-weight: 700;
    font-size: 13px;
    text-align: center;
    border: 1px solid var(--primary);
    cursor: pointer;
    background: white;
    color: var(--primary);
    text-decoration: none;
}
.sticky-action .btn-sticky.primary {
    background: var(--primary);
    color: white;
}
.sticky-action .btn-sticky.disabled {
    background: var(--bg-gray);
    color: var(--text-muted);
    border-color: var(--border);
    cursor: not-allowed;
    pointer-events: none;
}
</style>

<!-- Help section + Sticky bottom -->
<div style="height: 80px;"></div>

<div class="sticky-action">
    <?php if (in_array($order['order_status'], ['delivered', 'shipped'])): ?>
        <form method="POST" style="flex: 1; margin: 0;" onsubmit="return confirm('Konfirmasi pesanan sudah diterima?')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <button type="submit" name="mark_completed" class="btn-sticky primary" style="width:100%;">✓ Pesanan Selesai</button>
        </form>
    <?php else: ?>
        <button type="button" class="btn-sticky disabled" style="flex:1;" disabled>
            <?= $order['order_status'] === 'completed' ? '✓ Selesai' : 'Belum sampai' ?>
        </button>
    <?php endif; ?>
    
    <?php if ($order['shipping_courier']): ?>
        <a href="<?= SITE_URL ?>/tracking.php?code=<?= clean($order['order_code']) ?>" class="btn-sticky">🚚 Lacak</a>
    <?php endif; ?>
</div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
