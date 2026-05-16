<?php
// ============================================
// DIAGNOSTIC UPLOAD - HAPUS setelah selesai!
// ============================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$uploadDir = __DIR__ . '/uploads/products';
$msg = '';
$msgType = '';

// Handle fix permission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
    if (!is_dir($uploadDir)) {
        if (mkdir($uploadDir, 0755, true)) {
            $msg = "✓ Folder dibuat: $uploadDir";
            $msgType = 'success';
        } else {
            $msg = "✗ Gagal buat folder. Hostinger File Manager → buat folder uploads/products manual";
            $msgType = 'error';
        }
    } else {
        if (chmod($uploadDir, 0755)) {
            $msg = "✓ Permission folder uploads/products diset ke 0755";
            $msgType = 'success';
        }
    }
}

// Handle test upload
$testResult = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_upload']) && !empty($_FILES['test_img']['name'])) {
    $f = $_FILES['test_img'];
    $testResult .= "<h4>Test Upload Result:</h4>";
    $testResult .= "<pre>" . print_r($f, true) . "</pre>";
    
    if ($f['error'] === 0) {
        $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
        $dest = $uploadDir . '/test_' . time() . '.' . $ext;
        if (move_uploaded_file($f['tmp_name'], $dest)) {
            $testResult .= "<p style='color:green'>✓ Upload BERHASIL ke: " . basename($dest) . "</p>";
            $testResult .= "<img src='" . SITE_URL . "/uploads/products/" . basename($dest) . "' style='max-width:200px;border:1px solid #ddd;'>";
        } else {
            $testResult .= "<p style='color:red'>✗ move_uploaded_file GAGAL. Cek folder permission!</p>";
            $err = error_get_last();
            if ($err) $testResult .= "<p style='color:red'>Last error: " . htmlspecialchars($err['message']) . "</p>";
        }
    } else {
        $errMap = [
            1 => 'UPLOAD_ERR_INI_SIZE - File melebihi upload_max_filesize',
            2 => 'UPLOAD_ERR_FORM_SIZE - File melebihi MAX_FILE_SIZE form',
            3 => 'UPLOAD_ERR_PARTIAL - File ke-upload sebagian',
            4 => 'UPLOAD_ERR_NO_FILE - Ga ada file',
            6 => 'UPLOAD_ERR_NO_TMP_DIR - Tmp folder ga ada',
            7 => 'UPLOAD_ERR_CANT_WRITE - Ga bisa write ke disk',
            8 => 'UPLOAD_ERR_EXTENSION - Extension blok',
        ];
        $testResult .= "<p style='color:red'>✗ Error code " . $f['error'] . ": " . ($errMap[$f['error']] ?? 'unknown') . "</p>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Upload Diagnostic</title>
<style>
body { font-family: -apple-system, monospace; max-width: 900px; margin: 30px auto; padding: 20px; background: #f5f5f5; font-size: 13px; }
.box { background: white; padding: 16px; margin: 10px 0; border-radius: 8px; border: 1px solid #ddd; }
.ok { color: #1d7a4c; font-weight: 600; }
.fail { color: #b91c1c; font-weight: 600; }
.warn { color: #92560f; font-weight: 600; }
h2 { color: #ee4d2d; margin-top: 0; font-family: -apple-system, sans-serif; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
th, td { padding: 6px 10px; border-bottom: 1px solid #eee; text-align: left; }
th { background: #fafafa; font-weight: 700; }
pre { background: #f5f5f5; padding: 8px; border-radius: 4px; font-size: 11px; overflow: auto; max-height: 200px; }
button { background: #ee4d2d; color: white; padding: 10px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
.msg { padding: 12px; border-radius: 6px; margin: 10px 0; }
.msg.success { background: #d1f4dd; color: #1d7a4c; }
.msg.error { background: #fde8e8; color: #b91c1c; }
</style>
</head>
<body>
<h1>🔍 Upload Diagnostic</h1>

<?php if ($msg): ?>
<div class="msg <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="box">
<h2>1. Folder uploads/products</h2>
<table>
<tr><td>Path</td><td><code><?= htmlspecialchars($uploadDir) ?></code></td></tr>
<tr><td>Exists</td><td><?= is_dir($uploadDir) ? '<span class="ok">✓ Ada</span>' : '<span class="fail">✗ TIDAK ADA</span>' ?></td></tr>
<tr><td>Writable</td><td><?= is_writable($uploadDir) ? '<span class="ok">✓ Bisa ditulis</span>' : '<span class="fail">✗ TIDAK BISA DITULIS</span>' ?></td></tr>
<tr><td>Permission</td><td><?= is_dir($uploadDir) ? substr(sprintf('%o', fileperms($uploadDir)), -4) : 'N/A' ?></td></tr>
</table>

<?php if (!is_dir($uploadDir) || !is_writable($uploadDir)): ?>
<form method="POST" style="margin-top: 12px;">
    <button type="submit" name="fix">🔧 Auto-fix Folder</button>
</form>
<?php endif; ?>
</div>

<div class="box">
<h2>2. PHP Upload Config</h2>
<table>
<?php
$limits = [
    'file_uploads' => ini_get('file_uploads'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'max_input_vars' => ini_get('max_input_vars'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
];
foreach ($limits as $k => $v) {
    echo "<tr><td>$k</td><td><strong>$v</strong></td></tr>";
}
?>
</table>
<p style="font-size:11px;color:#888;margin-top:8px;">⚠️ Kalo <code>upload_max_filesize</code> < 2M, gambar variasi besar bakal gagal upload</p>
</div>

<div class="box">
<h2>3. Test Upload Manual</h2>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="test_img" accept="image/*" required style="margin-bottom:10px;">
    <br>
    <button type="submit" name="test_upload">📤 Test Upload</button>
</form>
<?= $testResult ?>
</div>

<div class="box">
<h2>4. Cek debug-variasi.log (kalau ada)</h2>
<?php
$logPath = __DIR__ . '/debug-variasi.log';
if (file_exists($logPath)) {
    $log = file_get_contents($logPath);
    // Ambil 2 entries terakhir aja biar ga overload
    $entries = explode("===== ", $log);
    $lastEntries = array_slice(array_filter($entries), -2);
    echo "<p class='ok'>✓ Log file ada (" . number_format(filesize($logPath)) . " bytes)</p>";
    foreach ($lastEntries as $entry) {
        echo "<pre>===== " . htmlspecialchars($entry) . "</pre>";
    }
    echo '<form method="POST" onsubmit="return confirm(\'Hapus log file?\')"><button type="submit" name="clear_log" style="background:#999">Clear log</button></form>';
    if (isset($_POST['clear_log'])) {
        unlink($logPath);
        echo '<script>location.reload()</script>';
    }
} else {
    echo "<p class='warn'>⚠️ debug-variasi.log belum ada. Coba save produk variasi dengan upload gambar dulu, lalu reload halaman ini.</p>";
}
?>
</div>

<div class="box" style="background:#fff3cd;border-color:#ffe69c;">
<h3>📋 Langkah debug:</h3>
<ol>
<li>Cek section 1 — folder ada & writable?</li>
<li>Cek section 2 — upload_max_filesize cukup?</li>
<li>Section 3 — coba upload manual, harus berhasil & gambar tampil</li>
<li>Section 4 — kalau ada log, screenshot ke claude</li>
<li><strong>HAPUS file ini setelah selesai!</strong></li>
</ol>
</div>
</body>
</html>
