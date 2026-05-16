<?php
require __DIR__ . '/config.php';
require_login();

$inventory = load_json(INVENTORY_FILE);

// ── AJAX endpoint ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $body = json_decode(file_get_contents('php://input'), true);
    $inventory = load_json(INVENTORY_FILE);

    if ($_GET['action'] === 'add') {
        $maxId = empty($inventory) ? 0 : max(array_column($inventory, 'id'));
        $new = [
            'id'       => $maxId + 1,
            'nama'     => trim($body['nama'] ?? ''),
            'harga'    => intval($body['harga'] ?? 0),
            'satuan'   => trim($body['satuan'] ?? 'pcs'),
            'kategori' => trim($body['kategori'] ?? 'Lainnya'),
        ];
        if (!$new['nama']) { echo json_encode(['ok'=>false,'msg'=>'Nama wajib diisi']); exit; }
        $inventory[] = $new;
        save_json(INVENTORY_FILE, $inventory);
        echo json_encode(['ok'=>true,'item'=>$new]);
        exit;
    }

    if ($_GET['action'] === 'edit') {
        foreach ($inventory as &$item) {
            if ($item['id'] == intval($body['id'])) {
                $item['nama']     = trim($body['nama'] ?? $item['nama']);
                $item['harga']    = intval($body['harga'] ?? $item['harga']);
                $item['satuan']   = trim($body['satuan'] ?? $item['satuan']);
                $item['kategori'] = trim($body['kategori'] ?? $item['kategori']);
                save_json(INVENTORY_FILE, $inventory);
                echo json_encode(['ok'=>true,'item'=>$item]);
                exit;
            }
        }
        echo json_encode(['ok'=>false,'msg'=>'Item tidak ditemukan']); exit;
    }

    if ($_GET['action'] === 'delete') {
        $id = intval($body['id']);
        $inventory = array_values(array_filter($inventory, fn($i) => $i['id'] !== $id));
        save_json(INVENTORY_FILE, $inventory);
        echo json_encode(['ok'=>true]); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory – OIS Grafika</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<?php include __DIR__ . '/_topbar.php'; ?>

<div class="app">
  <div class="page-header">
    <div>
      <h1>Inventory</h1>
      <p class="muted">Daftar produk &amp; layanan untuk autocomplete nota</p>
    </div>
    <button class="btn btn-primary" onclick="openAdd()">+ Tambah Item</button>
  </div>

  <div class="card">
    <div class="card-header"><h2>Daftar Produk (<span id="count">0</span>)</h2></div>
    <div class="filter-bar">
      <input type="text" id="search" class="form-control" placeholder="🔍 Cari nama atau kategori..." oninput="filterTable()">
    </div>
    <div style="overflow-x:auto;">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Nama Barang / Layanan</th>
            <th>Kategori</th>
            <th>Satuan</th>
            <th class="num">Harga</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody id="tbl-body"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="modal">
  <div class="modal">
    <h3 id="modal-title">Tambah Item</h3>
    <input type="hidden" id="m-id">
    <div class="form-group">
      <label>Nama Barang / Layanan *</label>
      <input class="form-control" id="m-nama" placeholder="cth: Banner 60x160cm">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Kategori</label>
        <input class="form-control" id="m-kategori" placeholder="cth: Banner">
      </div>
      <div class="form-group">
        <label>Satuan</label>
        <input class="form-control" id="m-satuan" placeholder="pcs">
      </div>
    </div>
    <div class="form-group">
      <label>Harga (Rp)</label>
      <input class="form-control mono" id="m-harga" type="number" min="0" placeholder="0">
    </div>
    <div class="modal-actions">
      <button class="btn btn-outline" onclick="closeModal()">Batal</button>
      <button class="btn btn-primary" onclick="saveItem()">Simpan</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
let inventory = <?= json_encode(array_values($inventory), JSON_UNESCAPED_UNICODE) ?>;

function renderTable(data) {
  document.getElementById('count').textContent = data.length;
  const tbody = document.getElementById('tbl-body');
  if (!data.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="empty">Belum ada data</td></tr>';
    return;
  }
  tbody.innerHTML = data.map((item, i) => `
    <tr>
      <td class="muted small">${i+1}</td>
      <td><b>${escHtml(item.nama)}</b></td>
      <td><span class="badge pink">${escHtml(item.kategori || '-')}</span></td>
      <td class="muted">${escHtml(item.satuan || 'pcs')}</td>
      <td class="num mono"><b>${formatRp(item.harga)}</b></td>
      <td>
        <div class="actions">
          <button class="btn btn-outline btn-sm" onclick="openEdit(${item.id})">✏</button>
          <button class="btn btn-danger-outline btn-sm" onclick="deleteItem(${item.id})">🗑</button>
        </div>
      </td>
    </tr>
  `).join('');
}

function filterTable() {
  const q = document.getElementById('search').value.toLowerCase();
  renderTable(inventory.filter(i =>
    i.nama.toLowerCase().includes(q) || (i.kategori||'').toLowerCase().includes(q)
  ));
}

function openAdd() {
  document.getElementById('modal-title').textContent = 'Tambah Item';
  ['m-id','m-nama','m-kategori','m-harga'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('m-satuan').value = 'pcs';
  document.getElementById('modal').classList.add('open');
  setTimeout(() => document.getElementById('m-nama').focus(), 50);
}

function openEdit(id) {
  const item = inventory.find(i => i.id === id);
  if (!item) return;
  document.getElementById('modal-title').textContent = 'Edit Item';
  document.getElementById('m-id').value = item.id;
  document.getElementById('m-nama').value = item.nama;
  document.getElementById('m-kategori').value = item.kategori || '';
  document.getElementById('m-satuan').value = item.satuan || 'pcs';
  document.getElementById('m-harga').value = item.harga;
  document.getElementById('modal').classList.add('open');
}

function closeModal() { document.getElementById('modal').classList.remove('open'); }

async function saveItem() {
  const id = document.getElementById('m-id').value;
  const body = {
    id: id ? parseInt(id) : undefined,
    nama: document.getElementById('m-nama').value.trim(),
    harga: parseInt(document.getElementById('m-harga').value) || 0,
    satuan: document.getElementById('m-satuan').value.trim() || 'pcs',
    kategori: document.getElementById('m-kategori').value.trim() || 'Lainnya',
  };
  if (!body.nama) { alert('Nama wajib diisi'); return; }

  const action = id ? 'edit' : 'add';
  const res = await fetch(`inventory.php?action=${action}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  const data = await res.json();
  if (!data.ok) { alert(data.msg || 'Gagal'); return; }

  if (action === 'add') inventory.push(data.item);
  else {
    const idx = inventory.findIndex(i => i.id === data.item.id);
    if (idx !== -1) inventory[idx] = data.item;
  }
  closeModal();
  filterTable();
  showToast(action === 'add' ? '✅ Item ditambahkan!' : '✅ Item diperbarui!');
}

async function deleteItem(id) {
  const item = inventory.find(i => i.id === id);
  if (!confirm(`Hapus "${item?.nama}"?`)) return;
  const res = await fetch('inventory.php?action=delete', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id }),
  });
  const data = await res.json();
  if (!data.ok) { alert('Gagal menghapus'); return; }
  inventory = inventory.filter(i => i.id !== id);
  filterTable();
  showToast('🗑 Item dihapus');
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}

function formatRp(n) { return 'Rp ' + (n || 0).toLocaleString('id-ID'); }
function escHtml(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

renderTable(inventory);
</script>
</body>
</html>
