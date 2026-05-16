<?php
// Include via require __DIR__ . '/_topbar.php'; from root pages
// Or relative for subfolder pages
if (!function_exists('app_url')) {
    // safety
    return;
}
$_settings = load_json(SETTINGS_FILE);
$_currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$_currentDir    = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
?>
<div class="topbar">
  <a href="<?= app_url('index.php') ?>" class="topbar-brand">
    <span class="brand-mark">OG</span>
    <span class="brand-text"><?= e($_settings['business_name'] ?? 'OIS GRAFIKA') ?></span>
  </a>
  <nav class="topbar-nav">
    <a href="<?= app_url('index.php') ?>" class="<?= $_currentScript==='index.php' && $_currentDir!=='nota' ? 'active':'' ?>">Dashboard</a>
    <a href="<?= app_url('nota/create.php') ?>" class="<?= $_currentScript==='create.php' ? 'active':'' ?>">+ Nota Baru</a>
    <a href="<?= app_url('inventory.php') ?>" class="<?= $_currentScript==='inventory.php' ? 'active':'' ?>">Inventory</a>
    <a href="<?= app_url('settings.php') ?>" class="<?= $_currentScript==='settings.php' ? 'active':'' ?>">Pengaturan</a>
  </nav>
  <div class="topbar-user">
    <span class="muted small">👤 <?= e(current_user()['name'] ?? 'admin') ?></span>
    <a href="<?= app_url('auth/logout.php') ?>" class="btn btn-ghost btn-sm">Logout</a>
  </div>
</div>
