<?php
require_once __DIR__ . '/includes/functions.php';

// Kalau sudah login, redirect
if (isLoggedIn()) {
    redirect(SITE_URL . '/index.php');
}

$error = '';
$prefillRef = $_GET['ref'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid';
    } else {
        $name = clean($_POST['name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $phone = clean($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $refCode = trim($_POST['referral_code'] ?? '');
        
        // Validation
        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Semua field wajib diisi';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter';
        } elseif ($password !== $confirm) {
            $error = 'Konfirmasi password tidak cocok';
        } else {
            // Cek email duplikat
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email sudah terdaftar';
            } else {
                // Cek kode referral kalau ada
                $referrerId = null;
                if (!empty($refCode)) {
                    $refStmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                    $refStmt->execute([$refCode]);
                    $referrer = $refStmt->fetch();
                    if ($referrer) {
                        $referrerId = $referrer['id'];
                    } else {
                        $error = 'Kode referral tidak ditemukan';
                    }
                }
                
                if (empty($error)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $myRef = generateReferralCode($name);
                    
                    // Pastikan unique
                    while (true) {
                        $check = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                        $check->execute([$myRef]);
                        if (!$check->fetch()) break;
                        $myRef = generateReferralCode($name);
                    }
                    
                    $insert = $pdo->prepare("INSERT INTO users (name, email, phone, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $insert->execute([$name, $email, $phone, $hash, $myRef, $referrerId]);
                    
                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    $_SESSION['role'] = 'user';
                    redirect(SITE_URL . '/dashboard.php', 'Pendaftaran berhasil! Selamat datang di Ois Grafika 🎉');
                }
            }
        }
    }
}

$pageTitle = 'Daftar Akun';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <h1 class="auth-title">Daftar Akun Baru</h1>
        <p class="auth-subtitle">Buat akun gratis dan dapatkan kode referral pribadi</p>

        <?php if ($error): ?>
            <div class="flash flash-error" style="margin-bottom: 16px;"><?= clean($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            
            <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="name" class="form-input" placeholder="John Doe" required value="<?= clean($_POST['name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" placeholder="email@contoh.com" required value="<?= clean($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">No. HP (opsional)</label>
                <input type="tel" name="phone" class="form-input" placeholder="08xxxxxxxxxx" value="<?= clean($_POST['phone'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" placeholder="Minimal 6 karakter" required>
            </div>

            <div class="form-group">
                <label class="form-label">Konfirmasi Password</label>
                <input type="password" name="confirm_password" class="form-input" placeholder="Ulangi password" required>
            </div>

            <div class="form-group">
                <label class="form-label">Kode Referral (opsional)</label>
                <input type="text" name="referral_code" class="form-input" placeholder="Contoh: OIS-ABC123" value="<?= clean($prefillRef) ?>" style="text-transform: uppercase;">
                <div class="form-help">Punya kode referral dari teman? Masukkan disini biar dia dapat komisi 🎁</div>
            </div>

            <button type="submit" class="btn btn-primary-solid btn-block btn-lg" style="margin-top: 8px;">
                Buat Akun
            </button>
        </form>

        <div class="auth-divider">─ atau ─</div>

        <p style="text-align: center; color: var(--text-light); font-size: 14px;">
            Sudah punya akun? <a href="<?= SITE_URL ?>/login.php" class="auth-link">Masuk disini</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
