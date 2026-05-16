<?php
// ============================================
// DEBUG SAVE PRODUK - tangkap apa yang dikirim form
// HAPUS file ini setelah selesai!
// ============================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();
?>
<!DOCTYPE html>
<html>
<head>
<title>Debug Save Produk</title>
<style>
body { font-family: monospace; max-width: 900px; margin: 30px auto; padding: 20px; background: #f5f5f5; font-size: 13px; }
.box { background: white; padding: 16px; margin: 10px 0; border-radius: 8px; border: 1px solid #ddd; }
.ok { color: #1d7a4c; font-weight: 600; }
.fail { color: #b91c1c; font-weight: 600; }
.warn { color: #92560f; font-weight: 600; }
h2 { color: #ee4d2d; margin-top: 0; font-family: -apple-system, sans-serif; }
pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto; font-size: 11px; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
th { background: #f5f5f5; }
.highlight { background: yellow; padding: 2px 4px; font-weight: 700; }
</style>
</head>
<body>
<h1>🔍 Debug Save Produk</h1>

<div class="box">
<h2>1. Daftar Kategori di DB</h2>
<?php
$cats = $pdo->query("SELECT * FROM categories ORDER BY id")->fetchAll();
if (empty($cats)) {
    echo "<div class='fail'>✗ KATEGORI KOSONG! Ini sebabnya error. Jalankan cek-kategori.php dulu</div>";
} else {
    echo "<table>";
    echo "<thead><tr><th>ID</th><th>Nama</th><th>Slug</th></tr></thead><tbody>";
    foreach ($cats as $c) {
        echo "<tr><td><strong>" . $c['id'] . "</strong></td><td>" . htmlspecialchars($c['name']) . "</td><td><code>" . htmlspecialchars($c['slug']) . "</code></td></tr>";
    }
    echo "</tbody></table>";
    echo "<p>✓ Ada " . count($cats) . " kategori. <strong>ID yang valid: " . implode(', ', array_column($cats, 'id')) . "</strong></p>";
}
?>
</div>

<div class="box">
<h2>2. Foreign Key Check</h2>
<?php
// Cek konstrain FK
try {
    $fk = $pdo->query("
        SELECT 
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_NAME = 'products' 
          AND CONSTRAINT_NAME = 'products_ibfk_1'
          AND TABLE_SCHEMA = DATABASE()
    ")->fetch();
    
    if ($fk) {
        echo "<p>Foreign Key: <code>products." . $fk['COLUMN_NAME'] . "</code> → <code>" . $fk['REFERENCED_TABLE_NAME'] . "." . $fk['REFERENCED_COLUMN_NAME'] . "</code></p>";
        echo "<div class='ok'>✓ FK constraint ada</div>";
    }
} catch (Exception $e) {
    echo "<div class='warn'>" . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
</div>

<div class="box">
<h2>3. Form Test - Submit ke Sini</h2>
<p>Pilih kategori, klik <b>Test Submit</b>. Tool ini akan tangkap data form & cek apakah <code>category_id</code>-nya valid.</p>

<form method="POST" style="background:#f9f9f9;padding:14px;border-radius:6px;">
    <label>Pilih Kategori:</label><br>
    <select name="category_id" style="width:100%;padding:10px;margin:8px 0;">
        <option value="">— Pilih —</option>
        <?php foreach ($cats as $c): ?>
            <option value="<?= $c['id'] ?>">[ID: <?= $c['id'] ?>] <?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <br>
    <input type="text" name="name" placeholder="Nama test product" value="Test Product" style="width:100%;padding:10px;margin:8px 0;">
    <br>
    <button type="submit" name="test_submit" style="background:#ee4d2d;color:white;padding:10px 24px;border:none;border-radius:6px;font-weight:700;cursor:pointer;">🧪 Test Submit</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_submit'])):
    echo "<hr style='margin:20px 0;'>";
    echo "<h3>📥 Data yang Diterima dari Form:</h3>";
    echo "<pre>" . htmlspecialchars(print_r($_POST, true)) . "</pre>";
    
    $catId = (int)($_POST['category_id'] ?? 0);
    echo "<p>category_id dari form (after cast int): <span class='highlight'>$catId</span></p>";
    
    if ($catId === 0) {
        echo "<div class='fail'>✗ category_id KOSONG / NOL! Form ga ngirim ID kategori dengan benar.</div>";
    } else {
        // Cek apa ID-nya valid
        $check = $pdo->prepare("SELECT id, name FROM categories WHERE id = ?");
        $check->execute([$catId]);
        $found = $check->fetch();
        
        if ($found) {
            echo "<div class='ok'>✓ category_id $catId valid (= " . htmlspecialchars($found['name']) . ")</div>";
            echo "<p>Berarti foreign key seharusnya OK. Coba langsung test INSERT...</p>";
            
            // Coba test INSERT minimal
            try {
                $testSlug = 'test-debug-' . time();
                $pdo->prepare("INSERT INTO products (name, slug, category_id, base_price, type) VALUES (?, ?, ?, 0, 'fisik')")
                    ->execute(['Test Debug ' . time(), $testSlug, $catId]);
                $newId = $pdo->lastInsertId();
                echo "<div class='ok'>✓ INSERT BERHASIL! Product ID: $newId</div>";
                
                // Cleanup
                $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$newId]);
                echo "<p><small>(Test record sudah dihapus)</small></p>";
                
                echo "<h3 class='ok'>🎉 FK constraint sebenarnya OK. Masalah ada di proses save asli.</h3>";
                echo "<p>Coba cek <code>admin/produk-save.php</code> — kemungkinan ada manipulasi data POST yang bikin category_id berubah.</p>";
            } catch (Exception $e) {
                echo "<div class='fail'>✗ INSERT GAGAL: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            echo "<div class='fail'>✗ category_id $catId TIDAK ADA di tabel categories!</div>";
            echo "<p>ID yang dikirim form: <strong>$catId</strong></p>";
            echo "<p>ID yang valid di DB: <strong>" . implode(', ', array_column($cats, 'id')) . "</strong></p>";
        }
    }
endif;
?>
</div>

<div class="box">
<h2>4. Cek Recent Error di MySQL</h2>
<?php
// Cek apakah ada produk yg ke-insert dengan category_id invalid (orphan)
try {
    $orphans = $pdo->query("
        SELECT p.id, p.name, p.category_id 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE c.id IS NULL
    ")->fetchAll();
    
    if (empty($orphans)) {
        echo "<div class='ok'>✓ Tidak ada produk yatim (orphan)</div>";
    } else {
        echo "<div class='fail'>✗ Ada " . count($orphans) . " produk yatim:</div>";
        foreach ($orphans as $o) {
            echo "<p>- Product ID {$o['id']}: <b>" . htmlspecialchars($o['name']) . "</b> punya category_id {$o['category_id']} (tidak ada di tabel)</p>";
        }
    }
} catch (Exception $e) {
    echo "<div class='warn'>" . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
</div>

<div class="box" style="background:#fff3cd;border-color:#ffe69c;">
<h2>📋 Yang harus lu kasih ke gua:</h2>
<ol>
<li>Screenshot section <strong>"Daftar Kategori di DB"</strong> (section 1) — gua mau liat ID asli</li>
<li>Pilih kategori "Jasa Desain" di section 3 → klik Test Submit → screenshot hasilnya</li>
<li>Setelah dapet info, gua bisa pinpoint exact problemnya</li>
</ol>
<p><strong>HAPUS file ini setelah selesai!</strong></p>
</div>
</body>
</html>
