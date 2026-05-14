<?php
// ============================================
// DIAGNOSTIC - Cek CSS & PHP version
// HAPUS file ini setelah cek!
// ============================================
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html>
<head>
<title>Diagnostic - Ois Grafika</title>
<style>
body { font-family: monospace; max-width: 800px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
.box { background: white; padding: 16px; margin: 10px 0; border-radius: 8px; border: 1px solid #ddd; }
.ok { color: green; }
.fail { color: red; }
h2 { color: #ee4d2d; }
pre { background: #f8f8f8; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
</style>
</head>
<body>
<h1>🔍 Diagnostic Ois Grafika</h1>

<div class="box">
<h2>1. Cek File CSS</h2>
<?php
$files = [
    'style.css' => __DIR__ . '/assets/css/style.css',
    'components.css' => __DIR__ . '/assets/css/components.css',
    'responsive.css' => __DIR__ . '/assets/css/responsive.css',
];
foreach ($files as $name => $path) {
    if (file_exists($path)) {
        $size = filesize($path);
        $modified = date('Y-m-d H:i:s', filemtime($path));
        echo "<div class='ok'>✓ <b>$name</b> ada ({$size} bytes, modified: {$modified})</div>";
    } else {
        echo "<div class='fail'>✗ <b>$name</b> TIDAK ADA di: $path</div>";
    }
}
?>
</div>

<div class="box">
<h2>2. Cek Versi CSS (cari kata kunci)</h2>
<?php
$styleContent = @file_get_contents(__DIR__ . '/assets/css/style.css');
$responsiveContent = @file_get_contents(__DIR__ . '/assets/css/responsive.css');

if (strpos($styleContent, 'nav-desktop-menu') !== false) {
    echo "<div class='ok'>✓ style.css adalah versi BARU (mengandung .nav-desktop-menu)</div>";
} else {
    echo "<div class='fail'>✗ style.css adalah versi LAMA! Harus upload yang baru.</div>";
}

if (strpos($responsiveContent, 'mobile-drawer') !== false) {
    echo "<div class='ok'>✓ responsive.css adalah versi BARU (mengandung .mobile-drawer)</div>";
} else {
    echo "<div class='fail'>✗ responsive.css adalah versi LAMA! Harus upload yang baru.</div>";
}
?>
</div>

<div class="box">
<h2>3. Test URL CSS</h2>
<p>Klik link berikut untuk cek CSS ke-load atau ga:</p>
<ul>
<li><a href="<?= SITE_URL ?>/assets/css/style.css" target="_blank">style.css</a></li>
<li><a href="<?= SITE_URL ?>/assets/css/components.css" target="_blank">components.css</a></li>
<li><a href="<?= SITE_URL ?>/assets/css/responsive.css" target="_blank">responsive.css</a></li>
</ul>
</div>

<div class="box">
<h2>4. SITE_URL Setting</h2>
<pre>SITE_URL = <?= SITE_URL ?></pre>
<p>Pastikan ini sesuai dengan domain lu. Kalo masih localhost padahal udah di production = error.</p>
</div>

<div class="box">
<h2>5. PHP Info</h2>
<pre>PHP Version: <?= phpversion() ?>
Server: <?= $_SERVER['SERVER_SOFTWARE'] ?? 'unknown' ?></pre>
</div>

<div class="box" style="background: #fff3cd; border-color: #ffe69c;">
<h2 style="color: #92560f;">⚠️ Setelah Selesai</h2>
<p><strong>HAPUS file diagnostic.php dari server!</strong></p>
</div>
</body>
</html>
