<?php
// ============================================
// DEBUG: Cek image_map produk variasi
// HAPUS setelah selesai!
// ============================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pid = (int)($_GET['pid'] ?? 0);
?>
<!DOCTYPE html>
<html>
<head>
<title>Debug Variation Image</title>
<style>
body { font-family: monospace; max-width: 1000px; margin: 30px auto; padding: 20px; background: #f5f5f5; font-size: 13px; }
.box { background: white; padding: 16px; margin: 10px 0; border-radius: 8px; border: 1px solid #ddd; }
h2 { color: #ee4d2d; font-family: -apple-system, sans-serif; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 8px 12px; border-bottom: 1px solid #eee; text-align: left; vertical-align: top; }
th { background: #fafafa; }
.ok { color: green; font-weight: 600; }
.fail { color: red; font-weight: 600; }
img { max-width: 60px; max-height: 60px; border: 1px solid #ddd; border-radius: 4px; }
pre { background: #f5f5f5; padding: 8px; border-radius: 4px; font-size: 11px; overflow: auto; }
</style>
</head>
<body>
<h1>🔍 Debug Variation Image</h1>

<div class="box">
<h2>Pilih Produk</h2>
<?php
$products = $pdo->query("SELECT id, name, slug FROM products ORDER BY id DESC LIMIT 20")->fetchAll();
?>
<form method="GET">
    <select name="pid" onchange="this.form.submit()" style="padding:8px; font-size:13px;">
        <option value="">-- Pilih Produk --</option>
        <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $pid === (int)$p['id'] ? 'selected' : '' ?>>
                #<?= $p['id'] ?> - <?= htmlspecialchars($p['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>
</div>

<?php if ($pid > 0): 
    $stmt = $pdo->prepare("SELECT * FROM product_variations WHERE product_id = ? ORDER BY sort_order");
    $stmt->execute([$pid]);
    $vars = $stmt->fetchAll();
?>
<div class="box">
<h2>Variasi untuk Produk ID #<?= $pid ?></h2>

<?php if (empty($vars)): ?>
    <p class="fail">❌ Belum ada variasi tersimpan untuk produk ini</p>
<?php else: ?>
    <?php foreach ($vars as $v): 
        $opts = json_decode($v['options'], true) ?? [];
        $imgMap = !empty($v['image_map']) ? (json_decode($v['image_map'], true) ?: []) : [];
    ?>
    <h3>Variasi: <?= htmlspecialchars($v['name']) ?> (sort_order=<?= $v['sort_order'] ?>)</h3>
    <table>
        <tr><th>Field</th><th>Value</th></tr>
        <tr><td>has_images</td><td><?= $v['has_images'] ? '<span class="ok">1 (YES)</span>' : '<span class="fail">0 (NO)</span>' ?></td></tr>
        <tr><td>options (raw)</td><td><code><?= htmlspecialchars($v['options']) ?></code></td></tr>
        <tr><td>options (decoded)</td><td><pre><?= htmlspecialchars(print_r($opts, true)) ?></pre></td></tr>
        <tr><td>image_map (raw)</td><td><code><?= htmlspecialchars($v['image_map'] ?? 'NULL') ?></code></td></tr>
        <tr><td>image_map (decoded)</td><td><pre><?= htmlspecialchars(print_r($imgMap, true)) ?></pre></td></tr>
    </table>
    
    <h4>Cek match: setiap opsi → image_map</h4>
    <table>
        <tr><th>Opsi</th><th>Lookup key</th><th>Image path</th><th>File ada?</th><th>Preview</th></tr>
        <?php foreach ($opts as $opt): 
            $optTrim = trim($opt);
            $path = $imgMap[$optTrim] ?? '';
            // Fallback case-insensitive
            if (!$path) {
                foreach ($imgMap as $k => $val) {
                    if (strcasecmp(trim($k), $optTrim) === 0) { $path = $val; break; }
                }
            }
            $fullPath = __DIR__ . '/' . $path;
            $exists = $path && file_exists($fullPath);
        ?>
            <tr>
                <td><?= htmlspecialchars($opt) ?></td>
                <td><code>"<?= htmlspecialchars($optTrim) ?>"</code></td>
                <td><code><?= htmlspecialchars($path ?: '(empty)') ?></code></td>
                <td>
                    <?php if (!$path): ?>
                        <span class="fail">⚠️ NO PATH</span>
                    <?php elseif ($exists): ?>
                        <span class="ok">✓ File ada (<?= number_format(filesize($fullPath)) ?> bytes)</span>
                    <?php else: ?>
                        <span class="fail">❌ File GA ADA di <?= htmlspecialchars($fullPath) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($exists): ?>
                        <img src="<?= SITE_URL ?>/<?= htmlspecialchars($path) ?>" alt="">
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <hr>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<div class="box">
<h2>Cek folder uploads/products/</h2>
<?php
$uploadDir = __DIR__ . '/uploads/products';
if (is_dir($uploadDir)) {
    $files = glob($uploadDir . '/var_*');
    echo "<p>Found " . count($files) . " variation image files:</p>";
    echo "<ul>";
    foreach (array_slice($files, -10) as $f) {
        $fname = basename($f);
        echo "<li><code>$fname</code> (" . number_format(filesize($f)) . " bytes)</li>";
    }
    echo "</ul>";
} else {
    echo "<p class='fail'>Folder uploads/products GA ADA!</p>";
}
?>
</div>
<?php endif; ?>

<p style="text-align:center;color:#888;font-size:11px;margin-top:30px;">⚠️ HAPUS file ini setelah selesai debug!</p>
</body>
</html>
