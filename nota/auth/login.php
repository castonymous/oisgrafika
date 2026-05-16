<?php
require __DIR__ . '/../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $users = load_json(USERS_FILE);
    $found = null;
    foreach ($users as $u) {
        if (strcasecmp($u['username'], $username) === 0) { $found = $u; break; }
    }

    if ($found && password_verify($password, $found['password'])) {
        $_SESSION['user'] = [
            'username' => $found['username'],
            'name'     => $found['name'] ?? $found['username'],
        ];
        header('Location: ' . app_url('index.php'));
        exit;
    } else {
        $error = 'Username atau password salah';
    }
}

if (is_logged_in()) {
    header('Location: ' . app_url('index.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login – OIS Grafika</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root { --pink: #ff7aa8; --pink-dark: #e85a8a; --bg: #f5f3f0; --text: #1a1d23; --muted: #6b7280; --border: #e0e3e8; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: var(--bg);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background-image:
    radial-gradient(circle at 20% 10%, rgba(255,122,168,.15), transparent 40%),
    radial-gradient(circle at 80% 90%, rgba(255,122,168,.12), transparent 40%);
}
.login-card {
  width: 100%;
  max-width: 400px;
  background: #fff;
  border-radius: 18px;
  padding: 36px 32px;
  box-shadow: 0 12px 40px rgba(0,0,0,.08), 0 2px 6px rgba(0,0,0,.04);
  border: 1.5px solid var(--border);
}
.brand {
  text-align: center;
  margin-bottom: 28px;
}
.brand-logo {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 56px; height: 56px;
  background: var(--pink);
  color: #fff;
  border-radius: 14px;
  font-size: 26px;
  font-weight: 800;
  margin-bottom: 12px;
  box-shadow: 0 6px 16px rgba(255,122,168,.4);
}
.brand h1 { font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
.brand p { font-size: 13px; color: var(--muted); margin-top: 4px; }
.form-group { margin-bottom: 14px; }
.form-group label {
  display: block;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  color: var(--muted);
  margin-bottom: 6px;
}
.form-control {
  width: 100%;
  padding: 11px 14px;
  border-radius: 10px;
  border: 1.5px solid var(--border);
  background: #fafafa;
  font-family: inherit;
  font-size: 14px;
  transition: all .15s;
}
.form-control:focus {
  outline: none;
  border-color: var(--pink);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(255,122,168,.15);
}
.btn-submit {
  width: 100%;
  padding: 12px;
  margin-top: 8px;
  border: none;
  border-radius: 10px;
  background: var(--pink);
  color: #fff;
  font-family: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all .15s;
}
.btn-submit:hover { background: var(--pink-dark); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(255,122,168,.4); }
.alert {
  padding: 10px 14px;
  border-radius: 8px;
  background: #fff0f0;
  border: 1.5px solid #ffc8c8;
  color: #c41010;
  font-size: 13px;
  margin-bottom: 16px;
}
.hint {
  margin-top: 18px;
  padding: 12px;
  background: #fff8fa;
  border: 1px dashed var(--pink);
  border-radius: 10px;
  font-size: 12px;
  color: var(--muted);
  text-align: center;
}
.hint b { color: var(--text); }
</style>
</head>
<body>
<div class="login-card">
  <div class="brand">
    <div class="brand-logo">OG</div>
    <h1>OIS GRAFIKA</h1>
    <p>solusi desain &amp; cetak anda</p>
  </div>

  <?php if ($error): ?>
    <div class="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Username</label>
      <input class="form-control" name="username" autofocus required value="<?= e($_POST['username'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input class="form-control" name="password" type="password" required>
    </div>
    <button class="btn-submit" type="submit">Masuk</button>
  </form>

  <div class="hint">
    Default: <b>admin</b> / <b>admin123</b><br>
    <small>ganti setelah login pertama</small>
  </div>
</div>
</body>
</html>
