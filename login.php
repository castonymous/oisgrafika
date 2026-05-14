<?php
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect(SITE_URL . '/index.php');
}

$error = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid';
    } else {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Email dan password wajib diisi';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];
                
                $redirectUrl = $redirect ?: ($user['role'] === 'admin' ? SITE_URL . '/admin/index.php' : SITE_URL . '/dashboard.php');
                redirect($redirectUrl, 'Selamat datang kembali, ' . explode(' ', $user['name'])[0] . '!');
            } else {
                $error = 'Email atau password salah';
            }
        }
    }
}

$pageTitle = 'Masuk';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <h1 class="auth-title">Selamat Datang Kembali</h1>
        <p class="auth-subtitle">Masuk ke akun Ois Grafika kamu</p>

        <?php if ($error): ?>
            <div class="flash flash-error" style="margin-bottom: 16px;"><?= clean($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" placeholder="email@contoh.com" required value="<?= clean($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" placeholder="Password kamu" required>
            </div>

            <button type="submit" class="btn btn-primary-solid btn-block btn-lg" style="margin-top: 8px;">
                Masuk
            </button>
        </form>

        <div class="auth-divider">─ belum punya akun? ─</div>

        <a href="<?= SITE_URL ?>/register.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>" class="btn btn-outline btn-block">
            Daftar Sekarang
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
