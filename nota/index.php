<?php
require __DIR__ . '/config.php';
require_login();

$invoices = load_json(INVOICES_FILE);
// Newest first
usort($invoices, fn($a, $b) => strcmp($b['created'] ?? '', $a['created'] ?? ''));

// Stats
$today = date('Y-m-d');
$totalToday = 0; $countToday = 0;
$totalLunas = 0; $totalBelumLunas = 0; $totalDP = 0;
foreach ($invoices as $inv) {
    if (($inv['invoice_date'] ?? '') === $today) {
        $countToday++;
        $totalToday += (int)($inv['total'] ?? 0);
    }
    $st = $inv['payment_status'] ?? 'Belum Lunas';
    if ($st === 'Lunas') $totalLunas++;
    elseif ($st === 'DP') $totalDP++;
    else $totalBelumLunas++;
}

// Search & filter
$q = trim($_GET['q'] ?? '');
$fStatus = $_GET['status'] ?? '';
$fMethod = $_GET['method'] ?? '';
$filtered = array_filter($invoices, function($inv) use ($q, $fStatus, $fMethod) {
    if ($q !== '') {
        $hay = strtolower(($inv['invoice_number'] ?? '') . ' ' . ($inv['customer_name'] ?? '') . ' ' . ($inv['customer_phone'] ?? '') . ' ' . ($inv['invoice_date'] ?? ''));
        if (strpos($hay, strtolower($q)) === false) return false;
    }
    if ($fStatus !== '' && ($inv['payment_status'] ?? '') !== $fStatus) return false;
    if ($fMethod !== '' && ($inv['payment_method'] ?? '') !== $fMethod) return false;
    return true;
});

$user = current_user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – OIS Grafika</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<?php include __DIR__ . '/_topbar.php'; ?>

<div class="app">

  <div class="page-header">
    <div>
      <h1>Dashboard</h1>
      <p class="muted">Hai <?= e($user['name']) ?>, selamat datang kembali</p>
    </div>
    <a href="nota/create.php" class="btn btn-primary">+ Buat Nota Baru</a>
  </div>

  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-label">Nota Hari Ini</div>
      <div class="stat-value"><?= $countToday ?></div>
      <div class="stat-sub mono"><?= rupiah($totalToday) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Nota</div>
      <div class="stat-value"><?= count($invoices) ?></div>
      <div class="stat-sub">semua transaksi</div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Lunas</div>
      <div class="stat-value"><?= $totalLunas ?></div>
      <div class="stat-sub">selesai</div>
    </div>
    <div class="stat-card yellow">
      <div class="stat-label">DP</div>
      <div class="stat-value"><?= $totalDP ?></div>
      <div class="stat-sub">setengah bayar</div>
    </div>
    <div class="stat-card red">
      <div class="stat-label">Belum Lunas</div>
      <div class="stat-value"><?= $totalBelumLunas ?></div>
      <div class="stat-sub">perlu ditagih</div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Daftar Nota</h2>
      <div style="display:flex;gap:8px;">
        <a href="nota/export_csv.php" class="btn btn-outline btn-sm">⬇ Export CSV</a>
        <a href="inventory.php" class="btn btn-outline btn-sm">📦 Inventory</a>
      </div>
    </div>
    <form class="filter-bar" method="GET">
      <input class="form-control" type="text" name="q" placeholder="🔍 Cari nomor nota, nama, HP, tanggal..." value="<?= e($q) ?>">
      <select class="form-control" name="status" onchange="this.form.submit()">
        <option value="">Semua Status</option>
        <?php foreach (['Lunas','DP','Belum Lunas'] as $s): ?>
          <option value="<?= $s ?>" <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-control" name="method" onchange="this.form.submit()">
        <option value="">Semua Metode</option>
        <?php foreach (['QRIS','BCA','Tunai'] as $m): ?>
          <option value="<?= $m ?>" <?= $fMethod===$m?'selected':'' ?>><?= $m ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary btn-sm" type="submit">Cari</button>
      <?php if ($q || $fStatus || $fMethod): ?>
        <a href="index.php" class="btn btn-outline btn-sm">× Reset</a>
      <?php endif; ?>
    </form>

    <div style="overflow-x:auto;">
      <table class="table">
        <thead>
          <tr>
            <th>No. Nota</th>
            <th>Tanggal</th>
            <th>Pelanggan</th>
            <th class="num">Total</th>
            <th>Metode</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($filtered)): ?>
          <tr><td colspan="7" class="empty">Belum ada nota.<br><a href="nota/create.php">Buat nota pertama →</a></td></tr>
        <?php else: foreach ($filtered as $inv): ?>
          <tr>
            <td class="mono"><b><?= e($inv['invoice_number']) ?></b></td>
            <td><?= e(date('d M Y', strtotime($inv['invoice_date']))) ?></td>
            <td>
              <div><?= e($inv['customer_name']) ?></div>
              <?php if (!empty($inv['customer_phone'])): ?>
                <div class="muted small"><?= e($inv['customer_phone']) ?></div>
              <?php endif; ?>
            </td>
            <td class="num mono"><b><?= rupiah($inv['total']) ?></b></td>
            <td><span class="badge gray"><?= e($inv['payment_method']) ?></span></td>
            <td>
              <?php
                $st = $inv['payment_status'];
                $cls = $st==='Lunas'?'green':($st==='DP'?'yellow':'red');
              ?>
              <span class="badge <?= $cls ?>"><?= e($st) ?></span>
            </td>
            <td>
              <div class="actions">
                <a href="nota/print.php?id=<?= e($inv['id']) ?>" target="_blank" class="btn btn-outline btn-sm" title="Print">🖨</a>
                <a href="nota/edit.php?id=<?= e($inv['id']) ?>" class="btn btn-outline btn-sm" title="Edit">✏</a>
                <a href="nota/delete.php?id=<?= e($inv['id']) ?>" class="btn btn-danger-outline btn-sm" title="Hapus" onclick="return confirm('Hapus nota <?= e($inv['invoice_number']) ?>?')">🗑</a>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
