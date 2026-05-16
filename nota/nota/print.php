<?php
require __DIR__ . '/../config.php';
require_login();

$id = $_GET['id'] ?? '';
$size = ($_GET['size'] ?? 'a4') === 'a6' ? 'a6' : 'a4';
$autoprint = isset($_GET['autoprint']);

$invoices = load_json(INVOICES_FILE);
$settings = load_json(SETTINGS_FILE);

$invoice = null;
foreach ($invoices as $inv) {
    if ($inv['id'] === $id) { $invoice = $inv; break; }
}
if (!$invoice) {
    http_response_code(404);
    echo "Nota tidak ditemukan. <a href='../index.php'>Kembali</a>";
    exit;
}

$pageSize = $size === 'a6' ? 'A6 landscape' : 'A4 landscape';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nota <?= e($invoice['invoice_number']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/nota.css">
<style>
/* Override @page secara dinamis berdasarkan size */
@page { size: <?= $pageSize ?>; margin: <?= $size === 'a6' ? '3mm' : '6mm' ?>; }

body { margin: 0; background: #d8d9dc; }
.toolbar {
  position: fixed; top: 0; left: 0; right: 0;
  background: #1a1d23; color: #fff;
  padding: 10px 20px;
  display: flex; align-items: center; justify-content: space-between;
  z-index: 100;
  box-shadow: 0 2px 12px rgba(0,0,0,.3);
  font-family: 'Plus Jakarta Sans', sans-serif;
}
.toolbar h1 { font-size: 15px; font-weight: 700; }
.toolbar .actions { display: flex; gap: 8px; }
.toolbar a, .toolbar button {
  padding: 7px 14px; border-radius: 7px;
  font-size: 13px; font-weight: 600; cursor: pointer;
  text-decoration: none; border: none;
  font-family: inherit;
}
.toolbar .btn-primary { background: <?= e($settings['accent_color'] ?? '#ff7aa8') ?>; color: #fff; }
.toolbar .btn-primary:hover { filter: brightness(1.1); }
.toolbar .btn-outline { background: rgba(255,255,255,.1); color: #fff; }
.toolbar .btn-outline:hover { background: rgba(255,255,255,.2); }
.toolbar select {
  padding: 7px 10px; border-radius: 7px;
  background: rgba(255,255,255,.1); color: #fff;
  border: 1px solid rgba(255,255,255,.2);
  font-family: inherit; font-size: 13px; font-weight: 600;
}

.print-area { padding-top: 60px; padding-bottom: 30px; }

@media print {
  .toolbar { display: none !important; }
  .print-area { padding: 0; }
  body { background: #fff !important; }
  .nota-wrapper { margin: 0 auto !important; box-shadow: none !important; }
}

.nota-wrapper {
  box-shadow: 0 4px 24px rgba(0,0,0,.12);
}
</style>
</head>
<body class="print-<?= e($size) ?>">

<div class="toolbar no-print">
  <h1>📄 <?= e($invoice['invoice_number']) ?> – <?= e($invoice['customer_name']) ?></h1>
  <div class="actions">
    <select onchange="changeSize(this.value)">
      <option value="a4" <?= $size==='a4'?'selected':'' ?>>📄 A4 Landscape</option>
      <option value="a6" <?= $size==='a6'?'selected':'' ?>>🧾 A6 Landscape</option>
    </select>
    <button class="btn-primary" onclick="window.print()">🖨 Cetak / Save PDF</button>
    <a href="../index.php" class="btn-outline">← Kembali</a>
  </div>
</div>

<div class="print-area">
  <?php include __DIR__ . '/_nota_template.php'; ?>
</div>

<script>
function changeSize(s) {
  const url = new URL(window.location.href);
  url.searchParams.set('size', s);
  url.searchParams.delete('autoprint');
  window.location.href = url.toString();
}
<?php if ($autoprint): ?>
window.addEventListener('load', () => {
  // Tunggu QR & font load
  setTimeout(() => window.print(), 600);
});
<?php endif; ?>
</script>
</body>
</html>
