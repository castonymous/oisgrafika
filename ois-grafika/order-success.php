<?php
$pageTitle = 'Pesanan Berhasil';
require_once __DIR__ . '/includes/header.php';
requireLogin();

$code = $_GET['code'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ? AND user_id = ?");
$stmt->execute([$code, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect(SITE_URL . '/dashboard.php');
}
?>

<div style="max-width: 600px; margin: 60px auto; padding: 0 16px; text-align: center;">
    <div style="background: white; border-radius: var(--radius-lg); padding: 48px 32px; border: 1px solid var(--border);">
        <div style="font-size: 64px; margin-bottom: 16px;">🎉</div>
        <h1 style="font-family: var(--font-display); font-size: 24px; margin-bottom: 12px;">Pesanan Berhasil Dibuat!</h1>
        <p style="color: var(--text-light); margin-bottom: 24px;">Terima kasih sudah belanja di Ois Grafika. Pesanan kamu segera kami proses.</p>
        
        <div style="background: var(--bg-gray); border-radius: var(--radius); padding: 16px; margin-bottom: 24px;">
            <div style="color: var(--text-light); font-size: 12px; margin-bottom: 4px;">Nomor Pesanan</div>
            <div style="font-family: monospace; font-size: 18px; font-weight: 700; letter-spacing: 1px;"><?= clean($order['order_code']) ?></div>
        </div>
        
        <div style="background: #fff3cd; border-radius: var(--radius); padding: 16px; margin-bottom: 24px; text-align: left; border: 1px solid #ffe69c;">
            <strong style="color: #92560f;">⏳ Menunggu Pembayaran</strong>
            <p style="font-size: 13px; margin-top: 6px; color: #5d3a05;">
                Total bayar: <strong><?= rupiah($order['final_amount']) ?></strong> via <?= strtoupper($order['payment_method']) ?>.<br>
                <small style="color: #7d4e0a;">Setelah payment gateway aktif, kamu akan diarahkan ke halaman pembayaran otomatis.</small>
            </p>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
            <a href="<?= SITE_URL ?>/dashboard.php?tab=orders" class="btn btn-primary-solid">Lihat Pesanan</a>
            <a href="<?= SITE_URL ?>/produk.php" class="btn btn-outline">Lanjut Belanja</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
