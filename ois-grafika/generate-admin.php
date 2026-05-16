<?php
// ============================================
// GENERATE ADMIN PASSWORD
// HAPUS FILE INI SETELAH SELESAI SETUP!
// ============================================

require_once __DIR__ . '/config/config.php';

$success = false;
$error = '';
$generatedHash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? 'admin@oisgrafika.com';
    
    if (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } else {
        $generatedHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Update di database
        try {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'admin'");
            $stmt->execute([$generatedHash, $email]);
            
            if ($stmt->rowCount() > 0) {
                $success = true;
            } else {
                $error = "User dengan email $email dan role admin tidak ditemukan di database. Cek SQL sudah diimport belum.";
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Generate Admin Password</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #f5f5f5; padding: 40px; max-width: 600px; margin: 0 auto; }
        .card { background: white; padding: 32px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        h1 { margin: 0 0 8px; color: #ee4d2d; }
        .warning { background: #fff3cd; padding: 14px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ffe69c; color: #92560f; font-size: 14px; }
        .success { background: #d1f4dd; padding: 14px; border-radius: 8px; margin-bottom: 20px; color: #1d7a4c; font-size: 14px; }
        .error { background: #fde8e8; padding: 14px; border-radius: 8px; margin-bottom: 20px; color: #b91c1c; font-size: 14px; }
        label { display: block; font-weight: 600; margin: 12px 0 6px; font-size: 13px; }
        input { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        button { background: #ee4d2d; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 16px; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 4px; font-size: 12px; word-break: break-all; }
        .hash-box { background: #f5f5f5; padding: 12px; border-radius: 8px; font-family: monospace; font-size: 12px; word-break: break-all; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔐 Generate Admin Password</h1>
        <p style="color: #666; margin-bottom: 20px;">Set password admin untuk login ke panel admin.</p>
        
        <div class="warning">
            ⚠️ <strong>PENTING:</strong> HAPUS file ini (<code>generate-admin.php</code>) dari server setelah selesai setup biar ga ada orang lain yang bisa reset password admin!
        </div>

        <?php if ($success): ?>
            <div class="success">
                ✓ Password admin berhasil di-set!<br>
                Login pakai: <strong><?= htmlspecialchars($_POST['email']) ?></strong> + password yang lu masukin.<br><br>
                <strong>Sekarang HAPUS file ini dari server (via File Manager Hostinger)!</strong>
            </div>
            <a href="login.php" style="display: inline-block; background: #ee4d2d; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600;">→ Login Sekarang</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <label>Email Admin</label>
                <input type="email" name="email" value="admin@oisgrafika.com" required>
                
                <label>Password Baru (min 6 karakter)</label>
                <input type="password" name="password" required minlength="6" placeholder="Password yang aman ya...">
                
                <button type="submit">🔐 Set Password Admin</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
