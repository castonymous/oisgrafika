<?php
$pageTitle = 'Lacak Pesanan';
require_once __DIR__ . '/includes/shipping-helpers.php';
requireLogin();

$code = $_GET['code'] ?? '';
$stmt = $pdo->prepare("SELECT o.*, sm.name AS courier_name FROM orders o LEFT JOIN shipping_methods sm ON o.shipping_courier = sm.code WHERE o.order_code = ? AND o.user_id = ?");
$stmt->execute([$code, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect(SITE_URL . '/dashboard.php', 'Pesanan tidak ditemukan', 'error');
}

// Get trackings (urut terbaru di atas)
$trackStmt = $pdo->prepare("SELECT * FROM shipment_trackings WHERE order_id = ? ORDER BY tracked_at DESC, id DESC");
$trackStmt->execute([$order['id']]);
$trackings = $trackStmt->fetchAll();

// Get items
$itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemsStmt->execute([$order['id']]);
$items = $itemsStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<style>
.tracking-page { max-width: 720px; margin: 0 auto; padding: 12px; }

.tracking-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; color: var(--text-light); font-size: 14px; text-decoration: none; }

/* Hero status */
.tracking-hero {
    background: linear-gradient(135deg, #26aa99, #1d7a4c);
    color: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    margin-bottom: 12px;
    text-align: center;
}
.tracking-hero .icon { font-size: 36px; margin-bottom: 8px; }
.tracking-hero .status-name { font-size: 18px; font-weight: 800; font-family: var(--font-display); }
.tracking-hero .status-desc { font-size: 12px; opacity: 0.9; margin-top: 4px; }
.tracking-hero .estimate { 
    background: rgba(255,255,255,0.15); 
    padding: 10px; 
    border-radius: var(--radius); 
    margin-top: 12px;
    font-size: 13px;
}

/* Courier info card */
.courier-info {
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    padding: 14px;
    margin-bottom: 12px;
}
.courier-info-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 13px;
}
.courier-info-label { color: var(--text-light); }
.courier-info-value { font-weight: 600; }
.resi-box {
    background: var(--bg-gray);
    padding: 10px 12px;
    border-radius: var(--radius);
    margin-top: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: monospace;
    font-size: 13px;
}
.resi-copy-btn {
    background: var(--primary);
    color: white;
    padding: 4px 12px;
    border-radius: 4px;
    border: none;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
}

/* Timeline */
.timeline-card {
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    padding: 18px 16px;
    margin-bottom: 12px;
}
.timeline-title {
    font-weight: 700;
    margin-bottom: 16px;
    font-size: 14px;
}

.timeline {
    list-style: none;
    padding: 0;
    margin: 0;
    position: relative;
}

.timeline-item {
    display: grid;
    grid-template-columns: 32px 1fr;
    gap: 14px;
    padding-bottom: 18px;
    position: relative;
}

.timeline-item:last-child { padding-bottom: 0; }

.timeline-item::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 28px;
    bottom: -10px;
    width: 2px;
    background: var(--border);
}

.timeline-item:last-child::before { display: none; }

.timeline-dot {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--bg-gray);
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    position: relative;
    z-index: 1;
    border: 2px solid var(--border);
}

.timeline-item.latest .timeline-dot {
    background: var(--success);
    color: white;
    border-color: var(--success);
    box-shadow: 0 0 0 4px #d1f4dd;
}

.timeline-item.latest::before {
    background: var(--success);
}

.timeline-content {
    padding-top: 4px;
}

.timeline-status {
    font-weight: 700;
    font-size: 14px;
    color: var(--text);
    margin-bottom: 2px;
}

.timeline-item.latest .timeline-status { color: var(--success); }

.timeline-desc {
    font-size: 12px;
    color: var(--text-light);
    margin-bottom: 4px;
    line-height: 1.4;
}

.timeline-time {
    font-size: 11px;
    color: var(--text-muted);
}

/* Empty state */
.timeline-empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}
.timeline-empty-icon { font-size: 36px; margin-bottom: 8px; }
</style>

<div class="tracking-page">
    <a href="<?= SITE_URL ?>/order-detail.php?code=<?= clean($order['order_code']) ?>" class="tracking-back">← Kembali ke Pesanan</a>
    
    <!-- Hero status -->
    <?php
    $statusMap = [
        'pending' => ['⏳', 'Menunggu Pembayaran', 'Selesaikan pembayaran agar pesanan diproses'],
        'waiting_payment' => ['💳', 'Menunggu Pembayaran', 'Selesaikan pembayaran agar pesanan diproses'],
        'processing' => ['📋', 'Pesanan Diproses', 'Penjual sedang memproses pesanan kamu'],
        'packed' => ['📦', 'Pesanan Dikemas', 'Pesanan sudah dikemas, siap kirim'],
        'shipped' => ['🚚', 'Dalam Pengiriman', 'Pesanan sedang dalam perjalanan'],
        'delivered' => ['🏠', 'Pesanan Tiba', 'Pesanan sudah sampai di tujuan'],
        'completed' => ['✅', 'Pesanan Selesai', 'Terima kasih sudah belanja!'],
        'cancelled' => ['❌', 'Pesanan Dibatalkan', 'Pesanan ini telah dibatalkan'],
        'returned' => ['↩️', 'Pesanan Dikembalikan', 'Pesanan dikembalikan ke penjual'],
    ];
    $current = $statusMap[$order['order_status']] ?? ['📦', ucfirst($order['order_status']), ''];
    ?>
    <div class="tracking-hero" <?= in_array($order['order_status'], ['cancelled', 'returned']) ? 'style="background:linear-gradient(135deg,#9ca3af,#6b7280)"' : '' ?>>
        <div class="icon"><?= $current[0] ?></div>
        <div class="status-name"><?= $current[1] ?></div>
        <div class="status-desc"><?= $current[2] ?></div>
        
        <?php if ($order['estimated_arrival_start'] && in_array($order['order_status'], ['shipped', 'processing', 'packed'])): ?>
            <div class="estimate">
                📅 Estimasi tiba: <strong><?= date('d M', strtotime($order['estimated_arrival_start'])) ?> - <?= date('d M Y', strtotime($order['estimated_arrival_end'])) ?></strong>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Courier info -->
    <?php if ($order['shipping_courier']): ?>
        <div class="courier-info">
            <div class="courier-info-row">
                <span class="courier-info-label">Jasa Kirim</span>
                <span class="courier-info-value"><?= clean($order['courier_name'] ?: $order['shipping_courier']) ?></span>
            </div>
            <?php if ($order['tracking_number']): ?>
                <div class="courier-info-row">
                    <span class="courier-info-label">No. Resi</span>
                </div>
                <div class="resi-box">
                    <span id="resiText"><?= clean($order['tracking_number']) ?></span>
                    <button type="button" class="resi-copy-btn" onclick="copyResi()">Salin</button>
                </div>
            <?php else: ?>
                <div class="courier-info-row" style="color: var(--text-muted); font-style: italic;">
                    <span>No. Resi belum tersedia</span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- Timeline -->
    <div class="timeline-card">
        <div class="timeline-title">📍 Riwayat Pengiriman</div>
        
        <?php if (empty($trackings)): ?>
            <div class="timeline-empty">
                <div class="timeline-empty-icon">📦</div>
                <div>Belum ada update pengiriman</div>
            </div>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($trackings as $idx => $t): ?>
                    <li class="timeline-item <?= $idx === 0 ? 'latest' : '' ?>">
                        <div class="timeline-dot">
                            <?php
                            $iconMap = [
                                'Pesanan dibuat' => '📝',
                                'Pembayaran diterima' => '💳',
                                'Pesanan diproses' => '📋',
                                'Pesanan dikemas' => '📦',
                                'Diserahkan ke kurir' => '🚚',
                                'Dalam pengiriman' => '🛵',
                                'Sampai tujuan' => '🏠',
                                'Pesanan selesai' => '✅',
                                'Pesanan dibatalkan' => '❌',
                            ];
                            echo $iconMap[$t['status']] ?? '•';
                            ?>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-status"><?= clean($t['status']) ?></div>
                            <?php if ($t['description']): ?>
                                <div class="timeline-desc"><?= clean($t['description']) ?></div>
                            <?php endif; ?>
                            <div class="timeline-time"><?= date('d M Y · H:i', strtotime($t['tracked_at'])) ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    
    <!-- Product summary -->
    <div class="timeline-card">
        <div class="timeline-title">📦 Produk Pesanan (<?= count($items) ?> item)</div>
        <?php foreach ($items as $item): ?>
            <div style="display: grid; grid-template-columns: 1fr auto; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 13px;">
                <div>
                    <div style="font-weight: 500;"><?= clean($item['product_name']) ?></div>
                    <?php if ($item['variant_name']): ?>
                        <div style="font-size: 11px; color: var(--text-muted);"><?= clean($item['variant_name']) ?></div>
                    <?php endif; ?>
                </div>
                <div style="text-align: right;">
                    <div style="font-weight: 600;"><?= rupiah($item['price']) ?></div>
                    <div style="font-size: 11px; color: var(--text-muted);">x<?= $item['quantity'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div style="height: 40px;"></div>
</div>

<script>
function copyResi() {
    const resi = document.getElementById('resiText').textContent;
    navigator.clipboard.writeText(resi).then(() => {
        const btn = event.target;
        const original = btn.textContent;
        btn.textContent = '✓ Disalin';
        setTimeout(() => btn.textContent = original, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
