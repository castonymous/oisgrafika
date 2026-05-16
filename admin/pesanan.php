<?php
$pageTitle = 'Kelola Pesanan';
require_once __DIR__ . '/../includes/admin-helpers.php';
requireAdmin();

// Handle POST: update status / add tracking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        redirect($_SERVER['REQUEST_URI'], 'Token tidak valid', 'error');
    }
    
    $orderId = (int)($_POST['order_id'] ?? 0);
    
    if (isset($_POST['update_basic'])) {
        $orderStatus = clean($_POST['order_status']);
        $paymentStatus = clean($_POST['payment_status']);
        $pdo->prepare("UPDATE orders SET order_status = ?, payment_status = ? WHERE id = ?")
            ->execute([$orderStatus, $paymentStatus, $orderId]);
        redirect($_SERVER['REQUEST_URI'], '✓ Status diperbarui');
    }
    
    if (isset($_POST['update_shipping'])) {
        $courier = clean($_POST['shipping_courier']);
        $resi = clean($_POST['tracking_number']);
        $pdo->prepare("UPDATE orders SET shipping_courier = ?, tracking_number = ? WHERE id = ?")
            ->execute([$courier, $resi, $orderId]);
        redirect($_SERVER['REQUEST_URI'], '✓ Info pengiriman diperbarui');
    }
    
    if (isset($_POST['add_tracking'])) {
        $status = clean($_POST['tracking_status']);
        $desc = clean($_POST['tracking_desc'] ?? '');
        $pdo->prepare("INSERT INTO shipment_trackings (order_id, status, description, tracked_at) VALUES (?, ?, ?, NOW())")
            ->execute([$orderId, $status, $desc]);
        
        // Auto-update order_status sesuai tracking
        $statusMap = [
            'Pembayaran diterima' => 'processing',
            'Pesanan diproses' => 'processing',
            'Pesanan dikemas' => 'packed',
            'Diserahkan ke kurir' => 'shipped',
            'Dalam pengiriman' => 'shipped',
            'Sampai tujuan' => 'delivered',
            'Pesanan selesai' => 'completed',
        ];
        if (isset($statusMap[$status])) {
            $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?")
                ->execute([$statusMap[$status], $orderId]);
        }
        redirect($_SERVER['REQUEST_URI'], '✓ Update tracking ditambahkan');
    }
    
    if (isset($_POST['delete_tracking'])) {
        $tid = (int)$_POST['tracking_id'];
        $pdo->prepare("DELETE FROM shipment_trackings WHERE id = ? AND order_id = ?")->execute([$tid, $orderId]);
        redirect($_SERVER['REQUEST_URI'], 'Tracking dihapus');
    }
}

// Filter
$filterStatus = $_GET['status'] ?? '';
$search = $_GET['q'] ?? '';
$viewId = (int)($_GET['view'] ?? 0);

// Single order view
if ($viewId > 0) {
    $stmt = $pdo->prepare("SELECT o.*, u.name AS user_name, u.email AS user_email, sm.name AS courier_name 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        LEFT JOIN shipping_methods sm ON o.shipping_courier = sm.code 
        WHERE o.id = ?");
    $stmt->execute([$viewId]);
    $order = $stmt->fetch();
    
    if (!$order) redirect(SITE_URL . '/admin/pesanan.php', 'Pesanan tidak ditemukan', 'error');
    
    $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $items->execute([$viewId]);
    $items = $items->fetchAll();
    
    $trackings = $pdo->prepare("SELECT * FROM shipment_trackings WHERE order_id = ? ORDER BY tracked_at DESC, id DESC");
    $trackings->execute([$viewId]);
    $trackings = $trackings->fetchAll();
    
    $shippingMethods = $pdo->query("SELECT * FROM shipping_methods WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.admin-page { max-width: 1100px; margin: 16px auto; padding: 0 16px; }
.admin-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
.admin-title { font-size: 22px; font-weight: 800; font-family: var(--font-display); }

.admin-tabs { display: flex; gap: 6px; margin-bottom: 16px; overflow-x: auto; padding-bottom: 4px; }
.admin-tabs::-webkit-scrollbar { display: none; }
.admin-tab { padding: 8px 14px; border-radius: 20px; background: white; border: 1px solid var(--border); font-size: 13px; font-weight: 600; color: var(--text); text-decoration: none; white-space: nowrap; }
.admin-tab.active { background: var(--primary); color: white; border-color: var(--primary); }

.order-table { background: white; border-radius: var(--radius-lg); border: 1px solid var(--border); overflow: hidden; }
.order-row { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr 100px; gap: 10px; padding: 12px 16px; align-items: center; border-bottom: 1px solid var(--border); font-size: 13px; }
.order-row.head { background: var(--bg-gray); font-weight: 700; font-size: 11px; text-transform: uppercase; color: var(--text-muted); }
.order-row:last-child { border-bottom: none; }
.order-code { font-family: monospace; font-weight: 700; }
.order-customer { color: var(--text-light); font-size: 12px; }

.status-badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
.status-pending, .status-waiting_payment { background: #fff7e6; color: #92560f; }
.status-processing { background: #dbeafe; color: #1e40af; }
.status-packed { background: #e0e7ff; color: #4338ca; }
.status-shipped { background: #cce7ff; color: #1e40af; }
.status-delivered { background: #ddffec; color: #1d7a4c; }
.status-completed { background: #d1f4dd; color: #1d7a4c; }
.status-cancelled { background: #fde8e8; color: #b91c1c; }

.btn-view { padding: 6px 12px; background: var(--primary); color: white; border-radius: var(--radius); font-size: 11px; font-weight: 600; text-decoration: none; }

@media (max-width: 768px) {
    .order-row { grid-template-columns: 1fr 80px; gap: 4px; }
    .order-row.head { display: none; }
    .order-row > div:nth-child(2), .order-row > div:nth-child(3), .order-row > div:nth-child(4) {
        grid-column: 1; font-size: 11px;
    }
    .order-row > div:last-child { grid-row: 1 / 4; grid-column: 2; }
}

/* Detail view */
.detail-grid { display: grid; grid-template-columns: 1fr 380px; gap: 14px; align-items: start; }
@media (max-width: 900px) { .detail-grid { grid-template-columns: 1fr; } }

.detail-card { background: white; border-radius: var(--radius-lg); border: 1px solid var(--border); padding: 16px; margin-bottom: 12px; }
.detail-card h3 { font-size: 14px; font-weight: 700; margin-bottom: 12px; }
.detail-card .form-label { font-size: 12px; }

.detail-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 13px; }
.detail-row .label { color: var(--text-light); }
.detail-row .val { font-weight: 600; }

.btn-track-add { background: var(--success); color: white; padding: 10px 14px; border: none; border-radius: var(--radius); font-weight: 600; cursor: pointer; font-size: 13px; }

/* Mini timeline in admin */
.mini-timeline { list-style: none; padding: 0; }
.mini-timeline li { display: grid; grid-template-columns: 24px 1fr auto; gap: 10px; padding: 8px 0; border-bottom: 1px dashed var(--border); }
.mini-timeline li:last-child { border-bottom: none; }
.mini-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--text-muted); margin-top: 5px; }
.mini-timeline li:first-child .mini-dot { background: var(--success); box-shadow: 0 0 0 3px #d1f4dd; }
.mini-content strong { font-size: 13px; display: block; }
.mini-content small { font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px; }
</style>

<div class="admin-page">
    <?php if (!$viewId): ?>
        <!-- ============ LIST VIEW ============ -->
        <div class="admin-head">
            <h1 class="admin-title">📦 Kelola Pesanan</h1>
            <form method="GET" style="display: flex; gap: 6px;">
                <input type="hidden" name="status" value="<?= clean($filterStatus) ?>">
                <input type="text" name="q" class="form-input" placeholder="Cari order code..." value="<?= clean($search) ?>" style="font-size: 13px; padding: 8px 12px;">
                <button class="btn btn-secondary btn-sm">Cari</button>
            </form>
        </div>
        
        <div class="admin-tabs">
            <?php
            $statuses = [
                '' => 'Semua',
                'pending' => 'Menunggu Bayar',
                'processing' => 'Diproses',
                'packed' => 'Dikemas',
                'shipped' => 'Dikirim',
                'delivered' => 'Tiba',
                'completed' => 'Selesai',
                'cancelled' => 'Batal',
            ];
            foreach ($statuses as $st => $lbl):
                $url = SITE_URL . '/admin/pesanan.php?status=' . urlencode($st);
                if ($search) $url .= '&q=' . urlencode($search);
            ?>
                <a href="<?= $url ?>" class="admin-tab <?= $filterStatus === $st ? 'active' : '' ?>"><?= $lbl ?></a>
            <?php endforeach; ?>
        </div>
        
        <?php
        // Build query
        $where = [];
        $params = [];
        if ($filterStatus) { $where[] = "o.order_status = ?"; $params[] = $filterStatus; }
        if ($search) { $where[] = "(o.order_code LIKE ? OR u.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $orders = $pdo->prepare("SELECT o.*, u.name AS user_name FROM orders o LEFT JOIN users u ON o.user_id = u.id $whereClause ORDER BY o.created_at DESC LIMIT 100");
        $orders->execute($params);
        $orders = $orders->fetchAll();
        ?>
        
        <?php if (empty($orders)): ?>
            <div class="detail-card" style="text-align: center; padding: 40px;">
                <div style="font-size: 48px; margin-bottom: 12px;">📭</div>
                <h3>Belum ada pesanan</h3>
            </div>
        <?php else: ?>
            <div class="order-table">
                <div class="order-row head">
                    <div>Order Code</div>
                    <div>Customer</div>
                    <div>Total</div>
                    <div>Status</div>
                    <div></div>
                </div>
                <?php foreach ($orders as $o): ?>
                    <div class="order-row">
                        <div>
                            <div class="order-code">#<?= clean($o['order_code']) ?></div>
                            <div class="order-customer"><?= date('d M Y · H:i', strtotime($o['created_at'])) ?></div>
                        </div>
                        <div>
                            <div><?= clean($o['user_name'] ?: 'Guest') ?></div>
                            <div class="order-customer"><?= clean($o['recipient_name']) ?></div>
                        </div>
                        <div>
                            <strong><?= rupiah($o['final_amount']) ?></strong>
                            <div class="order-customer"><?= strtoupper($o['payment_method']) ?></div>
                        </div>
                        <div>
                            <span class="status-badge status-<?= $o['order_status'] ?>"><?= clean($o['order_status']) ?></span>
                            <?php if ($o['tracking_number']): ?>
                                <div class="order-customer">📦 <?= clean($o['tracking_number']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <a href="?view=<?= $o['id'] ?>" class="btn-view">Detail</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- ============ DETAIL VIEW ============ -->
        <a href="<?= SITE_URL ?>/admin/pesanan.php" style="display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;color:var(--text-light);">← Kembali</a>
        
        <div class="admin-head">
            <div>
                <h1 class="admin-title">#<?= clean($order['order_code']) ?></h1>
                <div style="color: var(--text-light); font-size: 13px;"><?= date('d M Y · H:i', strtotime($order['created_at'])) ?></div>
            </div>
            <div>
                <span class="status-badge status-<?= $order['order_status'] ?>" style="font-size:13px;padding:6px 14px;"><?= clean($order['order_status']) ?></span>
            </div>
        </div>
        
        <div class="detail-grid">
            <!-- Left column -->
            <div>
                <!-- Customer info -->
                <div class="detail-card">
                    <h3>👤 Customer</h3>
                    <div class="detail-row"><span class="label">Nama</span><span class="val"><?= clean($order['user_name']) ?></span></div>
                    <div class="detail-row"><span class="label">Email</span><span class="val"><?= clean($order['user_email']) ?></span></div>
                    <?php if ($order['recipient_name']): ?>
                        <div class="detail-row"><span class="label">Penerima</span><span class="val"><?= clean($order['recipient_name']) ?> (<?= clean($order['recipient_phone']) ?>)</span></div>
                        <div style="font-size: 12px; color: var(--text-light); margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--border);">📍 <?= clean($order['shipping_address']) ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- Items -->
                <div class="detail-card">
                    <h3>📦 Produk (<?= count($items) ?> item)</h3>
                    <?php foreach ($items as $i): ?>
                        <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border);">
                            <div>
                                <div style="font-weight: 500; font-size: 13px;"><?= clean($i['product_name']) ?></div>
                                <?php if ($i['variant_name']): ?>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= clean($i['variant_name']) ?></div>
                                <?php endif; ?>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= rupiah($i['price']) ?> × <?= $i['quantity'] ?></div>
                            </div>
                            <div style="font-weight: 700; color: var(--primary);"><?= rupiah($i['subtotal']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--border);">
                        <div class="detail-row"><span class="label">Subtotal</span><span class="val"><?= rupiah($order['total_amount']) ?></span></div>
                        <div class="detail-row"><span class="label">Ongkir</span><span class="val"><?= rupiah($order['shipping_cost']) ?></span></div>
                        <?php if ($order['shipping_discount'] > 0): ?>
                            <div class="detail-row" style="color: var(--success);"><span class="label">- Diskon Ongkir</span><span class="val">-<?= rupiah($order['shipping_discount']) ?></span></div>
                        <?php endif; ?>
                        <?php if ($order['voucher_discount'] > 0): ?>
                            <div class="detail-row" style="color: var(--success);"><span class="label">- Diskon Voucher</span><span class="val">-<?= rupiah($order['voucher_discount']) ?></span></div>
                        <?php endif; ?>
                        <div class="detail-row" style="border-top: 1px solid var(--border); padding-top: 8px; margin-top: 4px;">
                            <span class="label" style="font-weight: 700;">Total</span>
                            <span class="val" style="color: var(--primary); font-size: 16px;"><?= rupiah($order['final_amount']) ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Tracking history -->
                <div class="detail-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h3 style="margin:0;">📍 Riwayat Tracking</h3>
                        <span style="font-size: 11px; color: var(--text-muted);"><?= count($trackings) ?> update</span>
                    </div>
                    
                    <?php if (empty($trackings)): ?>
                        <p style="color: var(--text-muted); font-size: 13px; text-align: center; padding: 20px 0;">Belum ada update tracking</p>
                    <?php else: ?>
                        <ul class="mini-timeline">
                            <?php foreach ($trackings as $t): ?>
                                <li>
                                    <div class="mini-dot"></div>
                                    <div class="mini-content">
                                        <strong><?= clean($t['status']) ?></strong>
                                        <?php if ($t['description']): ?>
                                            <small><?= clean($t['description']) ?></small>
                                        <?php endif; ?>
                                        <small><?= date('d M Y · H:i', strtotime($t['tracked_at'])) ?></small>
                                    </div>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <input type="hidden" name="tracking_id" value="<?= $t['id'] ?>">
                                        <button type="submit" name="delete_tracking" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:14px;" title="Hapus" data-confirm="Hapus update ini?">🗑️</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right column - Actions -->
            <div>
                <!-- Update Status -->
                <div class="detail-card">
                    <h3>⚙️ Status Pesanan</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Status Pesanan</label>
                            <select name="order_status" class="form-select">
                                <?php foreach (['pending', 'waiting_payment', 'processing', 'packed', 'shipped', 'delivered', 'completed', 'cancelled', 'returned'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $order['order_status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status Pembayaran</label>
                            <select name="payment_status" class="form-select">
                                <?php foreach (['pending', 'paid', 'failed', 'expired', 'refunded'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $order['payment_status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" name="update_basic" class="btn btn-primary-solid btn-block">Update Status</button>
                    </form>
                </div>
                
                <!-- Update Shipping (resi & courier) -->
                <div class="detail-card">
                    <h3>🚚 Info Pengiriman</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Kurir</label>
                            <select name="shipping_courier" class="form-select">
                                <option value="">— Pilih Kurir —</option>
                                <?php foreach ($shippingMethods as $m): ?>
                                    <option value="<?= $m['code'] ?>" <?= $order['shipping_courier'] === $m['code'] ? 'selected' : '' ?>><?= clean($m['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">No. Resi</label>
                            <input type="text" name="tracking_number" class="form-input" value="<?= clean($order['tracking_number']) ?>" placeholder="JNT12345..." style="font-family: monospace;">
                        </div>
                        
                        <button type="submit" name="update_shipping" class="btn btn-primary-solid btn-block">Update Pengiriman</button>
                    </form>
                </div>
                
                <!-- Add Tracking Update -->
                <div class="detail-card" style="background: #f0fdf4; border-color: #86efac;">
                    <h3>➕ Tambah Update Tracking</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="tracking_status" class="form-select" required>
                                <option value="Pembayaran diterima">💳 Pembayaran diterima</option>
                                <option value="Pesanan diproses">📋 Pesanan diproses</option>
                                <option value="Pesanan dikemas">📦 Pesanan dikemas</option>
                                <option value="Diserahkan ke kurir">🚚 Diserahkan ke kurir</option>
                                <option value="Dalam pengiriman">🛵 Dalam pengiriman</option>
                                <option value="Sampai tujuan">🏠 Sampai tujuan</option>
                                <option value="Pesanan selesai">✅ Pesanan selesai</option>
                                <option value="Pesanan dibatalkan">❌ Pesanan dibatalkan</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Keterangan (Opsional)</label>
                            <textarea name="tracking_desc" class="form-textarea" rows="2" placeholder="Mis: Tiba di gudang Jakarta Pusat"></textarea>
                        </div>
                        
                        <button type="submit" name="add_tracking" class="btn-track-add" style="width: 100%;">+ Tambah Update</button>
                        <small style="display: block; margin-top: 8px; color: var(--text-muted); font-size: 11px;">⚠️ Status pesanan akan auto-update sesuai pilihan</small>
                    </form>
                </div>
            </div>
        </div>
        
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
