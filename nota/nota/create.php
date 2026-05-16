<?php
require __DIR__ . '/../config.php';
require_login();

$inventory = load_json(INVENTORY_FILE);
$invoices  = load_json(INVOICES_FILE);
$settings  = load_json(SETTINGS_FILE);

// Mode: create atau edit
$editId = $_GET['id'] ?? null;
$editing = null;
if ($editId) {
    foreach ($invoices as $inv) {
        if ($inv['id'] === $editId) { $editing = $inv; break; }
    }
}

// AJAX: simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) { echo json_encode(['ok'=>false,'msg'=>'Invalid payload']); exit; }

    $items = [];
    foreach ($body['items'] ?? [] as $it) {
        $nama = trim($it['nama'] ?? '');
        if ($nama === '') continue;
        $qty = max(1, intval($it['qty'] ?? 1));
        $price = max(0, intval($it['harga'] ?? 0));
        $items[] = [
            'qty'        => $qty,
            'item_name'  => $nama,
            'unit_price' => $price,
            'subtotal'   => $qty * $price,
        ];
    }
    if (empty($items)) { echo json_encode(['ok'=>false,'msg'=>'Minimal 1 item']); exit; }

    $total = array_sum(array_column($items, 'subtotal'));
    $dp = max(0, intval($body['down_payment'] ?? 0));
    $sisa = max(0, $total - $dp);

    $now = date('Y-m-d H:i:s');
    $id = $body['id'] ?? null;

    if ($id) {
        // Edit
        $found = false;
        foreach ($invoices as &$inv) {
            if ($inv['id'] === $id) {
                $inv = array_merge($inv, [
                    'customer_name'   => trim($body['customer_name'] ?? ''),
                    'customer_phone'  => trim($body['customer_phone'] ?? ''),
                    'invoice_date'    => $body['invoice_date'] ?? date('Y-m-d'),
                    'payment_method'  => $body['payment_method'] ?? 'Tunai',
                    'payment_status'  => $body['payment_status'] ?? 'Lunas',
                    'notes'           => trim($body['notes'] ?? ''),
                    'items'           => $items,
                    'total'           => $total,
                    'down_payment'    => $dp,
                    'remaining'       => $sisa,
                    'updated'         => $now,
                ]);
                $found = true; break;
            }
        }
        unset($inv);
        if (!$found) { echo json_encode(['ok'=>false,'msg'=>'Nota tidak ditemukan']); exit; }
    } else {
        // Create
        $newId = uniqid('inv_');
        $invoices[] = [
            'id'             => $newId,
            'invoice_number' => next_invoice_number($invoices),
            'customer_name'  => trim($body['customer_name'] ?? ''),
            'customer_phone' => trim($body['customer_phone'] ?? ''),
            'invoice_date'   => $body['invoice_date'] ?? date('Y-m-d'),
            'payment_method' => $body['payment_method'] ?? 'Tunai',
            'payment_status' => $body['payment_status'] ?? 'Lunas',
            'notes'          => trim($body['notes'] ?? ''),
            'items'          => $items,
            'total'          => $total,
            'down_payment'   => $dp,
            'remaining'      => $sisa,
            'created'        => $now,
            'updated'        => $now,
        ];
        $id = $newId;
    }

    save_json(INVOICES_FILE, $invoices);
    echo json_encode(['ok'=>true, 'id'=>$id]);
    exit;
}

// Preview nomor (utk display saja, baru fix saat simpan)
$previewNumber = $editing['invoice_number'] ?? next_invoice_number($invoices);

// Encode untuk JS
$jsInventory = json_encode(array_values($inventory), JSON_UNESCAPED_UNICODE);
$jsEditing   = $editing ? json_encode($editing, JSON_UNESCAPED_UNICODE) : 'null';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $editing ? 'Edit' : 'Buat' ?> Nota – OIS Grafika</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/app.css">
</head>
<body>
<?php include __DIR__ . '/../_topbar.php'; ?>

<div class="app wide">
  <div class="page-header">
    <div>
      <h1><?= $editing ? 'Edit Nota' : 'Buat Nota Baru' ?></h1>
      <p class="muted">Nomor: <b class="mono"><?= e($previewNumber) ?></b></p>
    </div>
    <a href="../index.php" class="btn btn-outline">← Kembali</a>
  </div>

  <div class="grid-form">
    <!-- LEFT: Form -->
    <div class="card">
      <div class="card-header"><h2>Data Nota</h2></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group">
            <label>Tanggal</label>
            <input class="form-control" type="date" id="f-tanggal" value="<?= e($editing['invoice_date'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="form-group">
            <label>Pelanggan / Yth.</label>
            <input class="form-control" type="text" id="f-nama" placeholder="Nama pelanggan" value="<?= e($editing['customer_name'] ?? '') ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>No. HP</label>
            <input class="form-control" type="text" id="f-hp" placeholder="08xxx" value="<?= e($editing['customer_phone'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Catatan</label>
            <input class="form-control" type="text" id="f-catatan" placeholder="Catatan tambahan" value="<?= e($editing['notes'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="card-header"><h2>Item Barang</h2></div>
      <div class="card-body">
        <div class="items-header">
          <span>QTY</span>
          <span>NAMA BARANG / LAYANAN</span>
          <span class="num">HARGA</span>
          <span class="num">JUMLAH</span>
          <span></span>
        </div>
        <div id="items-list"></div>
        <button class="btn-add-row" type="button" onclick="addRow()">+ Tambah Baris</button>
      </div>

      <div class="card-header"><h2>Pembayaran</h2></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group">
            <label>Metode</label>
            <div class="pay-toggle" id="pay-toggle">
              <button type="button" data-val="QRIS">QRIS</button>
              <button type="button" data-val="BCA">BCA</button>
              <button type="button" data-val="Tunai">Tunai</button>
            </div>
          </div>
          <div class="form-group">
            <label>Status</label>
            <div class="pay-toggle" id="status-toggle">
              <button type="button" data-val="Lunas">Lunas</button>
              <button type="button" data-val="DP">DP</button>
              <button type="button" data-val="Belum Lunas">Belum</button>
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Uang Muka / DP (Rp)</label>
            <input class="form-control mono" type="number" min="0" id="f-dp" value="<?= e($editing['down_payment'] ?? 0) ?>" oninput="updateSummary()">
          </div>
          <div class="form-group">
            <label>Total &amp; Sisa</label>
            <div class="summary-mini">
              <div><span class="muted small">Total</span><b class="mono" id="s-total">Rp 0</b></div>
              <div><span class="muted small">Sisa</span><b class="mono" id="s-sisa">Rp 0</b></div>
            </div>
          </div>
        </div>

        <div class="save-bar">
          <button class="btn btn-primary btn-lg" onclick="saveNota(false)"><?= $editing ? '💾 Simpan Perubahan' : '💾 Simpan Nota' ?></button>
          <button class="btn btn-outline btn-lg" onclick="saveNota(true)">💾 Simpan &amp; Print</button>
        </div>
      </div>
    </div>

    <!-- RIGHT: Live Preview Nota -->
    <div class="preview-wrap">
      <div class="preview-toolbar">
        <span class="muted small">Preview Nota</span>
        <div class="size-toggle">
          <button class="active" data-size="a4" onclick="setSize('a4')">A4 Landscape</button>
          <button data-size="a6" onclick="setSize('a6')">A6</button>
        </div>
      </div>
      <div class="preview-scaler">
        <iframe id="preview-frame" src="about:blank"></iframe>
      </div>
    </div>
  </div>
</div>

<script>
const INVENTORY = <?= $jsInventory ?>;
const EDITING = <?= $jsEditing ?>;
const PREVIEW_NUMBER = <?= json_encode($previewNumber) ?>;

let items = [];
let rowCounter = 0;
let paymentMethod = 'Tunai';
let paymentStatus = 'Lunas';
let previewSize = 'a4';

// ── INIT ──
function init() {
  if (EDITING) {
    paymentMethod = EDITING.payment_method || 'Tunai';
    paymentStatus = EDITING.payment_status || 'Lunas';
    items = (EDITING.items || []).map(it => ({
      id: ++rowCounter,
      qty: it.qty,
      nama: it.item_name,
      harga: it.unit_price,
    }));
  }
  if (items.length === 0) {
    addRow(); addRow(); addRow();
  } else {
    renderRows();
  }
  setActive('pay-toggle', paymentMethod);
  setActive('status-toggle', paymentStatus);
  document.querySelectorAll('#pay-toggle button').forEach(b => {
    b.onclick = () => { paymentMethod = b.dataset.val; setActive('pay-toggle', paymentMethod); updateSummary(); };
  });
  document.querySelectorAll('#status-toggle button').forEach(b => {
    b.onclick = () => { paymentStatus = b.dataset.val; setActive('status-toggle', paymentStatus); };
  });
  ['f-tanggal','f-nama','f-hp','f-catatan'].forEach(id => {
    document.getElementById(id).addEventListener('input', updateSummary);
  });
  updateSummary();
}

function setActive(groupId, val) {
  document.querySelectorAll('#' + groupId + ' button').forEach(b => {
    b.classList.toggle('active', b.dataset.val === val);
  });
}

// ── ITEMS ──
function addRow() {
  items.push({ id: ++rowCounter, qty: 1, nama: '', harga: 0 });
  renderRows();
}

function deleteRow(id) {
  items = items.filter(r => r.id !== id);
  if (items.length === 0) addRow();
  else renderRows();
  updateSummary();
}

function renderRows() {
  const list = document.getElementById('items-list');
  list.innerHTML = items.map(r => `
    <div class="item-row" id="row-${r.id}">
      <input class="form-control mono qty-input" type="number" min="1" value="${r.qty}"
             oninput="updateRow(${r.id}, 'qty', this.value)">
      <div class="inv-search-wrap">
        <input class="form-control" type="text" placeholder="Cari atau ketik nama..." value="${escAttr(r.nama)}"
               oninput="onNamaInput(${r.id}, this)" onblur="closeDropdown(${r.id})"
               onkeydown="if(event.key==='Escape')closeDropdown(${r.id})">
        <div class="inv-dropdown" id="dd-${r.id}"></div>
      </div>
      <input class="form-control mono num-input" type="number" min="0" value="${r.harga}"
             oninput="updateRow(${r.id}, 'harga', this.value)">
      <div class="price-display" id="jumlah-${r.id}">${formatRp(r.qty * r.harga)}</div>
      <button class="btn-del" type="button" onclick="deleteRow(${r.id})" title="Hapus">×</button>
    </div>
  `).join('');
}

function updateRow(id, field, val) {
  const r = items.find(x => x.id === id);
  if (!r) return;
  if (field === 'qty')   r.qty   = Math.max(1, parseInt(val) || 1);
  if (field === 'harga') r.harga = Math.max(0, parseInt(val) || 0);
  if (field === 'nama')  r.nama  = val;
  const j = document.getElementById('jumlah-' + id);
  if (j) j.textContent = formatRp(r.qty * r.harga);
  updateSummary();
}

// ── INVENTORY AUTOCOMPLETE ──
function onNamaInput(id, input) {
  updateRow(id, 'nama', input.value);
  const q = input.value.toLowerCase().trim();
  const dd = document.getElementById('dd-' + id);
  if (!q) { dd.classList.remove('open'); return; }
  const matches = INVENTORY.filter(i =>
    i.nama.toLowerCase().includes(q) || (i.kategori||'').toLowerCase().includes(q)
  ).slice(0, 6);
  if (!matches.length) { dd.classList.remove('open'); return; }
  dd.innerHTML = matches.map(i => `
    <div class="inv-item" onmousedown="pickInventory(${id}, ${i.id})">
      <div>
        <div>${escHtml(i.nama)}</div>
        <div class="muted small">${escHtml(i.kategori || '')}</div>
      </div>
      <span class="inv-item-price mono">${formatRp(i.harga)}</span>
    </div>
  `).join('');
  dd.classList.add('open');
}

function pickInventory(rowId, invId) {
  const inv = INVENTORY.find(i => i.id === invId);
  if (!inv) return;
  const r = items.find(x => x.id === rowId);
  if (!r) return;
  r.nama = inv.nama;
  r.harga = inv.harga;
  renderRows();
  updateSummary();
}

function closeDropdown(id) {
  setTimeout(() => {
    const dd = document.getElementById('dd-' + id);
    if (dd) dd.classList.remove('open');
  }, 180);
}

// ── SUMMARY ──
function updateSummary() {
  const total = items.reduce((s, r) => s + (r.qty * r.harga), 0);
  const dp = parseInt(document.getElementById('f-dp').value) || 0;
  const sisa = Math.max(0, total - dp);
  document.getElementById('s-total').textContent = formatRp(total);
  document.getElementById('s-sisa').textContent = formatRp(sisa);
  schedulePreview();
}

// ── PREVIEW (live, debounced) ──
let previewTimer = null;
function schedulePreview() {
  clearTimeout(previewTimer);
  previewTimer = setTimeout(refreshPreview, 250);
}

function getPayload() {
  return {
    id: EDITING ? EDITING.id : null,
    invoice_number: EDITING ? EDITING.invoice_number : PREVIEW_NUMBER,
    customer_name: document.getElementById('f-nama').value,
    customer_phone: document.getElementById('f-hp').value,
    invoice_date: document.getElementById('f-tanggal').value,
    payment_method: paymentMethod,
    payment_status: paymentStatus,
    notes: document.getElementById('f-catatan').value,
    items: items.filter(r => r.nama.trim()),
    down_payment: parseInt(document.getElementById('f-dp').value) || 0,
  };
}

function refreshPreview() {
  const data = getPayload();
  // Render preview lewat URL fragment supaya gak butuh server roundtrip
  const previewUrl = 'preview.php?size=' + previewSize;
  const frame = document.getElementById('preview-frame');
  // Kirim data via postMessage setelah iframe ready
  if (frame.src.split('?')[0].endsWith('preview.php') === false) {
    frame.src = previewUrl;
    frame.onload = () => frame.contentWindow.postMessage({ type:'render', data }, '*');
  } else {
    // Update size kalau berubah
    if (!frame.src.includes('size=' + previewSize)) {
      frame.src = previewUrl;
      frame.onload = () => frame.contentWindow.postMessage({ type:'render', data }, '*');
    } else {
      frame.contentWindow.postMessage({ type:'render', data }, '*');
    }
  }
}

function setSize(s) {
  previewSize = s;
  document.querySelectorAll('.size-toggle button').forEach(b => b.classList.toggle('active', b.dataset.size === s));
  document.querySelector('.preview-scaler').dataset.size = s;
  refreshPreview();
}

// ── SAVE ──
async function saveNota(thenPrint) {
  const payload = getPayload();
  if (!payload.customer_name.trim()) { alert('Nama pelanggan wajib diisi'); return; }
  if (payload.items.length === 0)    { alert('Minimal 1 item dengan nama barang'); return; }

  const res = await fetch('create.php?action=save', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const out = await res.json();
  if (!out.ok) { alert(out.msg || 'Gagal menyimpan'); return; }

  if (thenPrint) {
    window.location.href = 'print.php?id=' + encodeURIComponent(out.id) + '&autoprint=1&size=' + previewSize;
  } else {
    window.location.href = '../index.php?saved=1';
  }
}

// ── HELPERS ──
function formatRp(n) { return 'Rp ' + (n || 0).toLocaleString('id-ID'); }
function escHtml(s) { return (s||'').toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escAttr(s) { return escHtml(s).replace(/"/g,'&quot;'); }

init();
</script>
</body>
</html>
