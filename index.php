<?php
$pageTitle = 'Beranda';
require_once __DIR__ . '/includes/header.php';

$stmt = $pdo->query("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY p.sold DESC LIMIT 10");
$populerProducts = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

// Hero slides (defensive - tabel mungkin belum ada)
$slides = [];
try {
    $slides = $pdo->query("SELECT * FROM hero_slides WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
} catch (Exception $e) {
    $slides = [];
}
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
        <div class="hero-visual">
            <?php if (!empty($slides)): ?>
                <div class="hero-slideshow" id="heroSlideshow">
                    <?php foreach ($slides as $idx => $slide): ?>
                        <div class="hero-slide <?= $idx === 0 ? 'active' : '' ?>" data-idx="<?= $idx ?>">
                            <?php if (!empty($slide['link_url'])): ?>
                                <a href="<?= clean($slide['link_url']) ?>" style="display:block;width:100%;height:100%;">
                            <?php endif; ?>
                            <img src="<?= SITE_URL ?>/<?= clean($slide['image_path']) ?>" alt="<?= clean($slide['title'] ?: 'Slide') ?>">
                            <?php if (!empty($slide['title']) || !empty($slide['subtitle'])): ?>
                                <div class="hero-slide-caption">
                                    <?php if (!empty($slide['title'])): ?>
                                        <h3><?= clean($slide['title']) ?></h3>
                                    <?php endif; ?>
                                    <?php if (!empty($slide['subtitle'])): ?>
                                        <p><?= clean($slide['subtitle']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($slide['link_url'])): ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($slides) > 1): ?>
                        <button type="button" class="hero-nav hero-prev" onclick="heroSlide(-1)">‹</button>
                        <button type="button" class="hero-nav hero-next" onclick="heroSlide(1)">›</button>
                        <div class="hero-dots">
                            <?php foreach ($slides as $idx => $_): ?>
                                <button type="button" class="hero-dot <?= $idx === 0 ? 'active' : '' ?>" onclick="heroGoTo(<?= $idx ?>)"></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="hero-visual-empty">🎨</div>
            <?php endif; ?>
        </div>
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
            <a href="<?= SITE_URL ?>/kategori/<?= clean($cat['slug']) ?>" class="category-card">
                <div class="category-icon">
                    <?php if (!empty($cat['image_path'])): ?>
                        <img src="<?= SITE_URL ?>/<?= clean($cat['image_path']) ?>" alt="<?= clean($cat['name']) ?>">
                    <?php else: ?>
                        <span class="category-icon-emoji"><?= $cat['icon'] ?></span>
                    <?php endif; ?>
                </div>
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
            <a href="<?= url('produk', $p['slug']) ?>" class="product-card">
                <div class="product-image">
                    <?php
                    $iconMap = ['jasa' => '🎨', 'digital' => '💾', 'fisik' => '📦'];
                    ?>
                    <?php if (!empty($p['image'])): ?>
                        <img src="<?= SITE_URL ?>/<?= clean($p['image']) ?>" alt="<?= clean($p['name']) ?>" style="width:100%;height:100%;object-fit:cover;" loading="lazy">
                    <?php else: ?>
                        <?= $iconMap[$p['type']] ?? '📦' ?>
                    <?php endif; ?>
                    <?php if ($p['sold'] > 200): ?>
                        <span class="product-badge">Terlaris</span>
                    <?php endif; ?>
                </div>
                <div class="product-body">
                    <div class="product-name"><?= clean($p['name']) ?></div>
                    <div class="product-price"><?= rupiah((int)$p['base_price']) ?></div>
                    <div class="product-meta">
                        <span class="product-rating">★ <?= number_format($p['rating'], 1) ?></span>
                        <span class="product-sold">Terjual <?= $p['sold'] ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="section why-section">
    <div class="section-head" style="justify-content: center; text-align: center;">
        <div>
            <h2 class="section-title">Kenapa Pilih Ois Grafika?</h2>
            <p class="section-subtitle">Komitmen kami untuk kepuasan pelanggan</p>
        </div>
    </div>
    <div class="why-grid">
        <div class="why-card">
            <div class="why-icon">⚡</div>
            <h3>Pengerjaan Cepat</h3>
            <p>Order diproses 1x24 jam, hasil tepat waktu.</p>
        </div>
        <div class="why-card">
            <div class="why-icon">💯</div>
            <h3>Kualitas Premium</h3>
            <p>Material terbaik, hasil profesional.</p>
        </div>
        <div class="why-card">
            <div class="why-icon">🔒</div>
            <h3>Pembayaran Aman</h3>
            <p>Support QRIS, e-wallet, transfer bank.</p>
        </div>
        <div class="why-card">
            <div class="why-icon">🎁</div>
            <h3>Program Referral</h3>
            <p>Ajak teman, dapat komisi <?= REFERRAL_COMMISSION_PERCENT ?>%.</p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
