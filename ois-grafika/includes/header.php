<?php
require_once __DIR__ . '/functions.php';
$currentUser = getCurrentUser();
$cartCount = $currentUser ? getCartCount($currentUser['id']) : 0;
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
    
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/responsive.css">
</head>
<body>
<header class="navbar">
    <div class="nav-container">
        <a href="<?= SITE_URL ?>/index.php" class="nav-logo">
            Ois<span class="accent">Grafika</span>
        </a>

        <button class="nav-toggle" id="navToggle" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>

        <nav class="nav-menu" id="navMenu">
            <form action="<?= SITE_URL ?>/produk.php" method="GET" class="nav-search">
                <input type="text" name="q" placeholder="Cari produk..." value="<?= clean($_GET['q'] ?? '') ?>">
                <button type="submit" aria-label="Cari">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </button>
            </form>

            <ul class="nav-links">
                <li><a href="<?= SITE_URL ?>/index.php">Beranda</a></li>
                <li><a href="<?= SITE_URL ?>/produk.php">Produk</a></li>
                <?php if ($currentUser): ?>
                    <li>
                        <a href="<?= SITE_URL ?>/keranjang.php" class="nav-cart">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                            <?php if ($cartCount > 0): ?>
                                <span class="cart-badge"><?= $cartCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-user">
                        <a href="#" class="nav-user-toggle">
                            <span class="user-avatar"><?= strtoupper(substr($currentUser['name'], 0, 1)) ?></span>
                            <span class="user-name"><?= clean(explode(' ', $currentUser['name'])[0]) ?></span>
                        </a>
                        <ul class="user-dropdown">
                            <li><a href="<?= SITE_URL ?>/dashboard.php">Dashboard</a></li>
                            <li><a href="<?= SITE_URL ?>/dashboard.php?tab=orders">Pesanan Saya</a></li>
                            <li><a href="<?= SITE_URL ?>/dashboard.php?tab=referral">Referral</a></li>
                            <?php if (isAdmin()): ?>
                                <li><a href="<?= SITE_URL ?>/admin/index.php">Admin Panel</a></li>
                            <?php endif; ?>
                            <li><a href="<?= SITE_URL ?>/logout.php">Keluar</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li><a href="<?= SITE_URL ?>/login.php" class="btn-text">Masuk</a></li>
                    <li><a href="<?= SITE_URL ?>/register.php" class="btn-primary">Daftar</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content">
<?= showFlash() ?>
