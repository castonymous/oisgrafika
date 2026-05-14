<?php
$pageTitle = 'Kelola Pesanan';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);

// Handle update status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        redirect(SITE_URL . '/admin/pesanan.php', 'Token tidak valid', 'error');
    }
    
    if (isset($_POST['update_status']) && $id > 0) {
        $orderStatus = $_POST['order_status'];
        $paymentStatus = $_POST['payment_status'];
        
        $pdo->prepare("UPDATE orders SET order_status = ?, payment_status = ? WHERE id = ?")
            ->execute([$orderStatus, $paymentStatus, $id]);
        
        // Kalau payment paid, set komisi referral jadi paid juga
        if ($paymentStatus === 'paid') {
            $pdo->prepare("UPDATE referral_commissions SET status = 'paid' WHERE order_id = ?")
                ->execute([$id]);
            
            // Update sold count untuk produk di order ini
            $pdo->prepare("UPDATE products p
                JOIN order_items oi ON oi.product_id = p.id
                SET p.sold = p.sold + oi.quantity
                WHERE oi.order_id = ?")
                ->execute([$id]);
        }
        
        redirect(SITE_URL . '/admin/pesanan.php?id=' . $id, 'Status pesanan diperbarui');
    }
}

// Filter
$filterStatus = $_GET['status'] ?? '';
?>

<div class="dashboard">
    <aside class="dash-sidebar">
        <div style="text-align: center; padding-bottom: 16px; border-bottom: 1px solid var(--border); margin-bottom: 16px;">
            <div style="font-family: var(--font-display); font-weight: 800; color: var(--primary);">🛠️ ADMIN PANEL</div>
        </div>
        <ul class="dash-menu">
            <li><a href="<?= SITE_URL ?>/admin/index.php">📊 Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/admin/produk.php">🛍️ Kelola Produk</a></li>
            <li><a href="<?= SITE_URL ?>/admin/pesanan.php" class="active">📦 Pesanan</a></li>
            <li><a href="<?= SITE_URL ?>/admin/users.php">👥 Pengguna</a></li>
            <li><a href="<?= SITE_URL ?>/admin/kategori.php">🏷️ Kategori</a></li>
            <li><a href="<?= SITE_URL ?>/dashboard.php">👤 User Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/index.php">🏠 Beranda</a></li>
        </ul>
    </aside>

    <div class="dash-content">
        <?php if ($id > 0):
            // DETAIL ORDER
            $stmt = $pdo->prepare("SELECT o.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            
            if (!$order) redirect(SITE_URL . '/admin/pesanan.php', 'Pesanan tidak ditemukan', 'error');
            
            $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $itemsStmt->execute([$id]);
            $orderItems = $itemsStmt->fetchAll();
        ?>
            <a href="<?= SITE_URL ?>/admin/pesanan.php" style="color: var(--text-muted); font-size: 13px;">← Kembali ke daftar</a>
            <h1 class="dash-title" style="margin-top: 8px;">Detail Pesanan #<?= clean($order['order_code']) ?></h1>
            <p class="dash-subtitle"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></p>

            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px; margin-top: 20px;">
                <div>
                    <h3 style="font-size: 15px; margin-bottom: 12px;">📋 Item Pesanan</h3>
                    <div style="background: var(--bg-gray); border-radius: var(--radius); padding: 16px;">
                        <?php foreach ($orderItems as $it): ?>
                            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed var(--border); font-size: 13px;">
                                <div>
                                    <strong><?= clean($it['product_name']) ?></strong>
                                    <?php if ($it['variant_name']): ?>
                                        <div style="color: var(--text-muted); font-size: 12px;"><?= clean($it['variant_name']) ?></div>
                                    <?php endif; ?>
                                    <div style="color: var(--text-muted); font-size: 12px;"><?= $it['quantity'] ?> × <?= rupiah($it['price']) ?></div>
                                </div>
                                <div style="font-weight: 700; color: var(--primary);"><?= rupiah($it['subtotal']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <div style="margin-top: 12px; padding-top: 12px; border-top: 2px solid var(--border);">
                            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                                <span>Subtotal:</span>
                                <span><?= rupiah($order['total_amount']) ?></span>
                            </div>
                            <?php if ($order['shipping_cost'] > 0): ?>
                                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                                    <span>Ongkir:</span>
                                    <span><?= rupiah($order['shipping_cost']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 16px; margin-top: 8px;">
                                <span>TOTAL:</span>
                                <span style="color: var(--primary);"><?= rupiah($order['final_amount']) ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($order['shipping_address']): ?>
                        <h3 style="font-size: 15px; margin: 20px 0 12px;">📍 Alamat Pengiriman</h3>
                        <div style="background: var(--bg-gray); border-radius: var(--radius); padding: 14px; font-size: 13px; line-height: 1.6;">
                            <?= nl2br(clean($order['shipping_address'])) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($order['notes']): ?>
                        <h3 style="font-size: 15px; margin: 20px 0 12px;">📝 Catatan</h3>
                        <div style="background: var(--bg-gray); border-radius: var(--radius); padding: 14px; font-size: 13px;">
                            <?= nl2br(clean($order['notes'])) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <h3 style="font-size: 15px; margin-bottom: 12px;">👤 Pelanggan</h3>
                    <div style="background: var(--bg-gray); border-radius: var(--radius); padding: 14px; font-size: 13px; line-height: 1.7;">
                        <div><strong><?= clean($order['user_name']) ?></strong></div>
                        <div style="color: var(--text-light);">📧 <?= clean($order['user_email']) ?></div>
                        <?php if ($order['user_phone']): ?>
                            <div style="color: var(--text-light);">📱 <?= clean($order['user_phone']) ?></div>
                        <?php endif; ?>
                    </div>

                    <h3 style="font-size: 15px; margin: 20px 0 12px;">💳 Pembayaran</h3>
                    <div style="background: var(--bg-gray); border-radius: var(--radius); padding: 14px; font-size: 13px; line-height: 1.7;">
                        <div>Metode: <strong><?= strtoupper($order['payment_method']) ?></strong></div>
                        <?php if ($order['referral_code_used']): ?>
                            <div>Referral: <code><?= clean($order['referral_code_used']) ?></code></div>
                        <?php endif; ?>
                    </div>

                    <h3 style="font-size: 15px; margin: 20px 0 12px;">⚙️ Update Status</h3>
                    <form method="POST" style="background: var(--bg-gray); border-radius: var(--radius); padding: 16px;">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 12px;">Status Pembayaran</label>
                            <select name="payment_status" class="form-select">
                                <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="failed" <?= $order['payment_status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                                <option value="expired" <?= $order['payment_status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 12px;">Status Pesanan</label>
                            <select name="order_status" class="form-select">
                                <option value="pending" <?= $order['order_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="processing" <?= $order['order_status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="shipped" <?= $order['order_status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                <option value="completed" <?= $order['order_status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= $order['order_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>

                        <button type="submit" name="update_status" class="btn btn-primary-solid btn-block">Simpan</button>
                    </form>
                </div>
            </div>

        <?php else:
            // LIST ORDERS
            $where = [];
            $params = [];
            if ($filterStatus) {
                $where[] = 'o.order_status = ?';
                $params[] = $filterStatus;
            }
            $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $stmt = $pdo->prepare("SELECT o.*, u.name AS user_name FROM orders o JOIN users u ON o.user_id = u.id $whereSQL ORDER BY o.created_at DESC");
            $stmt->execute($params);
            $orders = $stmt->fetchAll();
        ?>
            <h1 class="dash-title">Kelola Pesanan</h1>
            <p class="dash-subtitle">Total: <?= count($orders) ?> pesanan</p>

            <div style="display: flex; gap: 6px; margin: 16px 0 20px; flex-wrap: wrap;">
                <a href="?" class="btn btn-sm <?= !$filterStatus ? 'btn-primary-solid' : 'btn-secondary' ?>">Semua</a>
                <a href="?status=pending" class="btn btn-sm <?= $filterStatus === 'pending' ? 'btn-primary-solid' : 'btn-secondary' ?>">Pending</a>
                <a href="?status=processing" class="btn btn-sm <?= $filterStatus === 'processing' ? 'btn-primary-solid' : 'btn-secondary' ?>">Processing</a>
                <a href="?status=shipped" class="btn btn-sm <?= $filterStatus === 'shipped' ? 'btn-primary-solid' : 'btn-secondary' ?>">Shipped</a>
                <a href="?status=completed" class="btn btn-sm <?= $filterStatus === 'completed' ? 'btn-primary-solid' : 'btn-secondary' ?>">Completed</a>
                <a href="?status=cancelled" class="btn btn-sm <?= $filterStatus === 'cancelled' ? 'btn-primary-solid' : 'btn-secondary' ?>">Cancelled</a>
            </div>

            <?php if (empty($orders)): ?>
                <p style="text-align: center; color: var(--text-muted); padding: 40px;">Belum ada pesanan.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="background: var(--bg-gray); border-bottom: 2px solid var(--border);">
                                <th style="text-align: left; padding: 10px;">Kode</th>
                                <th style="text-align: left; padding: 10px;">User</th>
                                <th style="text-align: left; padding: 10px;">Total</th>
                                <th style="text-align: left; padding: 10px;">Payment</th>
                                <th style="text-align: left; padding: 10px;">Status</th>
                                <th style="text-align: left; padding: 10px;">Tanggal</th>
                                <th style="text-align: left; padding: 10px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $o): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 10px; font-family: monospace; font-size: 12px;"><?= clean($o['order_code']) ?></td>
                                    <td style="padding: 10px;"><?= clean($o['user_name']) ?></td>
                                    <td style="padding: 10px; color: var(--primary); font-weight: 700;"><?= rupiah($o['final_amount']) ?></td>
                                    <td style="padding: 10px;"><span class="status-badge status-<?= $o['payment_status'] ?>"><?= ucfirst($o['payment_status']) ?></span></td>
                                    <td style="padding: 10px;"><span class="status-badge status-<?= $o['order_status'] ?>"><?= ucfirst($o['order_status']) ?></span></td>
                                    <td style="padding: 10px; color: var(--text-muted); font-size: 12px;"><?= date('d M, H:i', strtotime($o['created_at'])) ?></td>
                                    <td style="padding: 10px;">
                                        <a href="?id=<?= $o['id'] ?>" class="btn btn-sm btn-secondary">Detail</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
