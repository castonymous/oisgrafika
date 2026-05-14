<?php
$pageTitle = 'Beranda';
require_once __DIR__ . '/includes/header.php';

// Ambil produk terlaris
$stmt = $pdo->query("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY p.sold DESC LIMIT 10");
$populerProducts = $stmt->fetchAll();

// Ambil kategori
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
?>

<section class="hero">
    <div class="hero-grid">
        <div class="hero-text">
            <span class="hero-badge">⚡ Solusi Lengkap Kreatif</span>
            <h1>Jasa Desain, Cetak & <span class="highlight">Merchandise</span> Untuk Bisnismu</h1>
            <p class="hero-desc">Dari logo, banner, kaos custom, sampai template digital — semua kebutuhan kreatif dalam satu tempat. Pengerjaan cepat, kualitas terjamin, harga bersahabat.</p>
            <div class="hero-cta">
                <a href="<?= SITE_URL ?>/produk.php" class="btn btn-primary-solid btn-lg">Mulai Pesan</a>
                <a href="#kategori" class="btn btn-outline btn-lg">Lihat Kategori</a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-num">2.5K+</div>
                    <div class="hero-stat-label">Pesanan Selesai</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-num">4.9★</div>
                    <div class="hero-stat-label">Rating Kepuasan</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-num">24/7</div>
                    <div class="hero-stat-label">Customer Support</div>
                </div>
            </div>
        </div>
        <div class="hero-visual">🎨</div>
    </div>
</section>

<section class="section" id="kategori">
    <div class="section-head">
        <div>
            <h2 class="section-title">Kategori Produk</h2>
            <p class="section-subtitle">Pilih kategori yang sesuai kebutuhanmu</p>
        </div>
    </div>
    <div class="category-grid">
        <?php foreach ($categories as $cat): ?>
            <a href="<?= SITE_URL ?>/produk.php?cat=<?= clean($cat['slug']) ?>" class="category-card">
                <div class="category-icon"><?= $cat['icon'] ?></div>
                <div class="category-name"><?= clean($cat['name']) ?></div>
                <div class="category-desc"><?= clean($cat['description']) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h2 class="section-title">Produk Terlaris</h2>
            <p class="section-subtitle">Pilihan favorit pelanggan kami</p>
        </div>
        <a href="<?= SITE_URL ?>/produk.php" class="section-link">
            Lihat Semua →
        </a>
    </div>
    <div class="product-grid">
        <?php foreach ($populerProducts as $p): ?>
            <a href="<?= SITE_URL ?>/detail-produk.php?slug=<?= clean($p['slug']) ?>" class="product-card">
                <div class="product-image">
                    <?php
                    $iconMap = [
                        'jasa' => '🎨',
                        'digital' => '💾',
                        'fisik' => '📦'
                    ];
                    echo $iconMap[$p['type']] ?? '📦';
                    ?>
                    <?php if ($p['sold'] > 200): ?>
                        <span class="product-badge">Terlaris</span>
                    <?php endif; ?>
                </div>
                <div class="product-body">
                    <div class="product-name"><?= clean($p['name']) ?></div>
                    <div class="product-price"><?= rupiah($p['base_price']) ?></div>
                    <div class="product-meta">
                        <span class="product-rating">★ <?= number_format($p['rating'], 1) ?></span>
                        <span class="product-sold">Terjual <?= $p['sold'] ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="section" style="background: white; margin-top: 32px;">
    <div class="container">
        <div class="section-head" style="margin-bottom: 24px;">
            <div style="text-align: center; margin: 0 auto;">
                <h2 class="section-title">Kenapa Pilih Ois Grafika?</h2>
                <p class="section-subtitle">Komitmen kami untuk kepuasan pelanggan</p>
            </div>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; max-width: var(--container-max); margin: 0 auto;">
            <div style="text-align: center; padding: 24px;">
                <div style="font-size: 40px; margin-bottom: 12px;">⚡</div>
                <h3 style="font-size: 16px; margin-bottom: 8px;">Pengerjaan Cepat</h3>
                <p style="color: var(--text-light); font-size: 13px;">Order diproses 1x24 jam, hasil tepat waktu.</p>
            </div>
            <div style="text-align: center; padding: 24px;">
                <div style="font-size: 40px; margin-bottom: 12px;">💯</div>
                <h3 style="font-size: 16px; margin-bottom: 8px;">Kualitas Premium</h3>
                <p style="color: var(--text-light); font-size: 13px;">Material terbaik, hasil profesional.</p>
            </div>
            <div style="text-align: center; padding: 24px;">
                <div style="font-size: 40px; margin-bottom: 12px;">🔒</div>
                <h3 style="font-size: 16px; margin-bottom: 8px;">Pembayaran Aman</h3>
                <p style="color: var(--text-light); font-size: 13px;">Support QRIS, e-wallet, transfer bank.</p>
            </div>
            <div style="text-align: center; padding: 24px;">
                <div style="font-size: 40px; margin-bottom: 12px;">🎁</div>
                <h3 style="font-size: 16px; margin-bottom: 8px;">Program Referral</h3>
                <p style="color: var(--text-light); font-size: 13px;">Ajak teman, dapat komisi <?= REFERRAL_COMMISSION_PERCENT ?>%.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
