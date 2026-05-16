<?php
require_once __DIR__ . '/functions.php';
$currentUser = getCurrentUser();
$cartCount = $currentUser ? getCartCount($currentUser['id']) : 0;
<<<<<<< ours
// Cache busting: pake filemtime, auto update saat CSS diubah
$cssVersion = @filemtime(__DIR__ . '/../assets/css/style.css') ?: '4.2';
=======
// Cache busting: ubah angka ini setiap update CSS
$cssVersion = '3.4';
>>>>>>> theirs
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME ?></title>
    <meta name="description" content="<?= SITE_NAME ?> - Jasa desain, percetakan, produk digital, dan merchandise berkualitas">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/components.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/responsive.css?v=<?= $cssVersion ?>">
</head>
<body>
<header class="navbar">
    <div class="nav-container">
        <div class="nav-top">
            <a href="<?= SITE_URL ?>/" class="nav-logo">
                Ois<span class="accent">Grafika</span>
            </a>

            <form action="<?= SITE_URL ?>/produk" method="GET" class="nav-search nav-search-desktop">
                <input type="text" name="q" placeholder="Cari produk..." value="<?= clean($_GET['q'] ?? '') ?>">
                <button type="submit" aria-label="Cari">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </button>
            </form>

            <div class="nav-desktop-menu">
                <a href="<?= SITE_URL ?>/" class="nav-link">Beranda</a>
                <a href="<?= SITE_URL ?>/produk" class="nav-link">Produk</a>
                <?php if ($currentUser): ?>
                    <a href="<?= SITE_URL ?>/keranjang" class="nav-icon-btn" aria-label="Keranjang">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-badge"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="nav-user-dropdown">
                        <button class="nav-user-btn">
                            <span class="user-avatar"><?= strtoupper(substr($currentUser['name'], 0, 1)) ?></span>
                            <span class="user-name"><?= clean(explode(' ', $currentUser['name'])[0]) ?></span>
                        </button>
                        <ul class="user-dropdown">
                            <li><a href="<?= SITE_URL ?>/dashboard">Dashboard</a></li>
                            <li><a href="<?= SITE_URL ?>/dashboard?tab=orders">Pesanan Saya</a></li>
                            <li><a href="<?= SITE_URL ?>/dashboard?tab=referral">Referral</a></li>
                            <?php if (isAdmin()): ?>
                                <li><a href="<?= SITE_URL ?>/admin/index.php">Admin Panel</a></li>
                            <?php endif; ?>
                            <li><a href="<?= SITE_URL ?>/logout" style="color: var(--danger);">Keluar</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/login" class="nav-link">Masuk</a>
                    <a href="<?= SITE_URL ?>/register" class="btn-nav-primary">Daftar</a>
                <?php endif; ?>
            </div>

            <div class="nav-mobile-actions">
                <?php if ($currentUser): ?>
                    <a href="<?= SITE_URL ?>/keranjang" class="nav-icon-btn" aria-label="Keranjang">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-badge"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <button class="nav-toggle" id="navToggle" aria-label="Menu">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>

        <form action="<?= SITE_URL ?>/produk" method="GET" class="nav-search nav-search-mobile">
            <input type="text" name="q" placeholder="Cari produk..." value="<?= clean($_GET['q'] ?? '') ?>">
            <button type="submit" aria-label="Cari">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </button>
        </form>
    </div>

    <div class="nav-overlay" id="navOverlay"></div>

    <aside class="mobile-drawer" id="navMenu">
        <?php if ($currentUser): ?>
            <div class="drawer-user">
                <div class="user-avatar"><?= strtoupper(substr($currentUser['name'], 0, 1)) ?></div>
                <div>
                    <div class="drawer-user-name"><?= clean($currentUser['name']) ?></div>
                    <div class="drawer-user-email"><?= clean($currentUser['email']) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <ul class="drawer-links">
            <li><a href="<?= SITE_URL ?>/"><span class="ico">🏠</span> Beranda</a></li>
            <li><a href="<?= SITE_URL ?>/produk"><span class="ico">🛍️</span> Produk</a></li>
            <?php if ($currentUser): ?>
                <li><a href="<?= SITE_URL ?>/dashboard"><span class="ico">📊</span> Dashboard</a></li>
                <li><a href="<?= SITE_URL ?>/dashboard?tab=orders"><span class="ico">📦</span> Pesanan Saya</a></li>
                <li><a href="<?= SITE_URL ?>/dashboard?tab=referral"><span class="ico">🎁</span> Program Referral</a></li>
                <li><a href="<?= SITE_URL ?>/alamat"><span class="ico">📍</span> Alamat Saya</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="<?= SITE_URL ?>/admin/index.php" style="color: var(--primary); font-weight: 600;"><span class="ico">🛠️</span> Admin Panel</a></li>
                <?php endif; ?>
                <li class="drawer-divider"></li>
                <li><a href="<?= SITE_URL ?>/logout" class="link-danger"><span class="ico">🚪</span> Keluar</a></li>
            <?php else: ?>
                <li class="drawer-divider"></li>
                <li><a href="<?= SITE_URL ?>/login" class="drawer-btn drawer-btn-outline">Masuk</a></li>
                <li><a href="<?= SITE_URL ?>/register" class="drawer-btn drawer-btn-primary">Daftar Sekarang</a></li>
            <?php endif; ?>
        </ul>
    </aside>
</header>

<main class="main-content">
<?= showFlash() ?>
