<?php
require __DIR__ . '/config.php';
require_login();

$settings = load_json(SETTINGS_FILE);
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servicesRaw = trim($_POST['services'] ?? '');
    $services = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $servicesRaw)));

    $settings = array_merge($settings, [
        'business_name'  => trim($_POST['business_name'] ?? ''),
        'tagline'        => trim($_POST['tagline'] ?? ''),
        'address'        => trim($_POST['address'] ?? ''),
        'phone'          => trim($_POST['phone'] ?? ''),
        'facebook'       => trim($_POST['facebook'] ?? ''),
        'youtube'        => trim($_POST['youtube'] ?? ''),
        'instagram'      => trim($_POST['instagram'] ?? ''),
        'tiktok'         => trim($_POST['tiktok'] ?? ''),
        'twitter'        => trim($_POST['twitter'] ?? ''),
        'accent_color'   => trim($_POST['accent_color'] ?? '#ff7aa8'),
        'services'       => array_values($services),
        'attention_text' => trim($_POST['attention_text'] ?? ''),
        'updated'        => date('Y-m-d H:i:s'),
    ]);
    save_json(SETTINGS_FILE, $settings);
    $saved = true;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengaturan – OIS Grafika</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<?php include __DIR__ . '/_topbar.php'; ?>

<div class="app">
  <div class="page-header">
    <div>
      <h1>Pengaturan Toko</h1>
      <p class="muted">Data ini muncul di setiap nota yang dicetak</p>
    </div>
  </div>

  <?php if ($saved): ?>
    <div class="alert alert-success">✅ Pengaturan berhasil disimpan!</div>
  <?php endif; ?>

  <form method="POST">
    <div class="card">
      <div class="card-header"><h2>Identitas Usaha</h2></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group">
            <label>Nama Usaha</label>
            <input class="form-control" name="business_name" value="<?= e($settings['business_name'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Tagline</label>
            <input class="form-control" name="tagline" value="<?= e($settings['tagline'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Alamat</label>
          <textarea class="form-control" name="address" rows="2"><?= e($settings['address'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>No. HP / WhatsApp</label>
            <input class="form-control mono" name="phone" value="<?= e($settings['phone'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Warna Aksen</label>
            <input class="form-control" name="accent_color" type="color" value="<?= e($settings['accent_color'] ?? '#ff7aa8') ?>" style="height:42px;">
          </div>
        </div>
      </div>

      <div class="card-header"><h2>Media Sosial</h2></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group">
            <label>Facebook</label>
            <input class="form-control" name="facebook" value="<?= e($settings['facebook'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>YouTube</label>
            <input class="form-control" name="youtube" value="<?= e($settings['youtube'] ?? '') ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Instagram</label>
            <input class="form-control" name="instagram" value="<?= e($settings['instagram'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>TikTok</label>
            <input class="form-control" name="tiktok" value="<?= e($settings['tiktok'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>X / Twitter</label>
          <input class="form-control" name="twitter" value="<?= e($settings['twitter'] ?? '') ?>">
        </div>
      </div>

      <div class="card-header"><h2>Daftar Layanan</h2></div>
      <div class="card-body">
        <div class="form-group">
          <label>Layanan (pisahkan dengan baris baru atau koma)</label>
          <textarea class="form-control mono" name="services" rows="10" style="font-size:13px;"><?= e(implode("\n", $settings['services'] ?? [])) ?></textarea>
          <small class="muted">Akan ditampilkan di bagian kiri nota dalam 2 kolom.</small>
        </div>
      </div>

      <div class="card-header"><h2>Box Perhatian</h2></div>
      <div class="card-body">
        <div class="form-group">
          <label>Teks Perhatian</label>
          <textarea class="form-control" name="attention_text" rows="3"><?= e($settings['attention_text'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="card-body" style="display:flex;justify-content:flex-end;gap:10px;">
        <a href="index.php" class="btn btn-outline">Batal</a>
        <button type="submit" class="btn btn-primary btn-lg">💾 Simpan Pengaturan</button>
      </div>
    </div>
  </form>
</div>
</body>
</html>
