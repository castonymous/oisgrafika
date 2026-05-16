<?php
require __DIR__ . '/../config.php';
require_login();
$size = ($_GET['size'] ?? 'a4') === 'a6' ? 'a6' : 'a4';
$settings = load_json(SETTINGS_FILE);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Preview</title>
<link rel="stylesheet" href="../assets/nota.css">
<style>
body { margin: 0; background: #d8d9dc; padding: 8mm; }
.nota-wrapper { box-shadow: 0 4px 20px rgba(0,0,0,.1); margin: 0 auto; }
</style>
</head>
<body>
<div id="preview-root"></div>

<script>
const SETTINGS = <?= json_encode($settings, JSON_UNESCAPED_UNICODE) ?>;
const SIZE = <?= json_encode($size) ?>;

window.addEventListener('message', (e) => {
  if (e.data && e.data.type === 'render') {
    renderNota(e.data.data);
  }
});

function renderNota(d) {
  const items = d.items || [];
  const minRows = SIZE === 'a4' ? 12 : 8;
  const emptyRows = Math.max(0, minRows - items.length);
  const total = items.reduce((s, r) => s + (r.qty * r.harga), 0);
  const dp = d.down_payment || 0;
  const sisa = Math.max(0, total - dp);

  const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  let dateFmt = '';
  if (d.invoice_date) {
    const dt = new Date(d.invoice_date);
    dateFmt = String(dt.getDate()).padStart(2,'0') + ' ' + months[dt.getMonth()] + ' ' + dt.getFullYear();
  }

  const services = SETTINGS.services || [];
  const half = Math.ceil(services.length / 2);
  const col1 = services.slice(0, half);
  const col2 = services.slice(half);

  let itemsHtml = items.map(it => `
    <tr>
      <td class="col-qty mono">${esc(it.qty)}</td>
      <td class="col-nama">${esc(it.nama)}</td>
      <td class="col-harga mono">${rupiah(it.harga)}</td>
      <td class="col-jumlah mono">${rupiah(it.qty * it.harga)}</td>
    </tr>
  `).join('');
  for (let i = 0; i < emptyRows; i++) {
    itemsHtml += `<tr><td class="col-qty">&nbsp;</td><td></td><td></td><td></td></tr>`;
  }

  const phoneUrl = 'https://wa.me/' + encodeURIComponent(SETTINGS.phone || '');
  const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(phoneUrl)}`;

  document.getElementById('preview-root').innerHTML = `
  <div class="nota-wrapper nota-${SIZE}" style="--accent: ${esc(SETTINGS.accent_color || '#ff7aa8')};">
    <div class="nota-grid">
      <aside class="nota-left">
        <div class="nota-brand">
          <div class="nota-logo">
            <svg viewBox="0 0 40 40" width="38" height="38">
              <circle cx="20" cy="20" r="18" fill="${esc(SETTINGS.accent_color)}" opacity="0.15"/>
              <circle cx="20" cy="20" r="14" fill="none" stroke="${esc(SETTINGS.accent_color)}" stroke-width="2.5"/>
              <text x="20" y="26" text-anchor="middle" font-family="Arial Black" font-size="13" fill="${esc(SETTINGS.accent_color)}" font-weight="900">OG</text>
            </svg>
          </div>
          <div class="nota-brand-text">
            <div class="nota-brand-name">${esc(SETTINGS.business_name || '')}</div>
            <div class="nota-tagline">${esc(SETTINGS.tagline || '')}</div>
          </div>
        </div>
        <div class="nota-address">${esc(SETTINGS.address || '')}</div>

        <div class="nota-contact-box">
          <div class="nota-qr">
            <img src="${qrUrl}" alt="QR">
            <div class="nota-phone">${esc(SETTINGS.phone || '')}</div>
          </div>
          <div class="nota-socials">
            <div><span class="soc-ico">f</span>${esc(SETTINGS.facebook || '')}</div>
            <div><span class="soc-ico">▶</span>${esc(SETTINGS.youtube || '')}</div>
            <div><span class="soc-ico">◉</span>${esc(SETTINGS.instagram || '')}</div>
            <div><span class="soc-ico">♪</span>${esc(SETTINGS.tiktok || '')}</div>
            <div><span class="soc-ico">𝕏</span>${esc(SETTINGS.twitter || '')}</div>
          </div>
        </div>

        <div class="nota-services">
          <ul>${col1.map(s => `<li># ${esc(s)}</li>`).join('')}</ul>
          <ul>${col2.map(s => `<li># ${esc(s)}</li>`).join('')}</ul>
        </div>

        <div class="nota-warning">
          <div class="nota-warning-title">PERHATIAN</div>
          <div class="nota-warning-text">${esc(SETTINGS.attention_text || '')}</div>
        </div>
      </aside>

      <section class="nota-right">
        <div class="nota-header">
          <div class="nota-field">
            <span class="lbl">No:</span>
            <div class="box mono">${esc(d.invoice_number || '')}</div>
          </div>
          <div class="nota-field">
            <span class="lbl">Tanggal</span>
            <div class="box">${esc(dateFmt)}</div>
          </div>
          <div class="nota-field full">
            <span class="lbl">Yth.</span>
            <div class="box">${esc(d.customer_name || '')}</div>
          </div>
        </div>

        <table class="nota-table">
          <thead>
            <tr>
              <th class="col-qty">QTY</th>
              <th class="col-nama">NAMA BARANG</th>
              <th class="col-harga">HARGA SATUAN</th>
              <th class="col-jumlah">JUMLAH</th>
            </tr>
          </thead>
          <tbody>${itemsHtml}</tbody>
        </table>

        <div class="nota-footer">
          <div class="nota-ttd">
            <div class="lbl">Tanda Terima</div>
            <div class="dots">..................</div>
          </div>
          <div class="nota-ttd">
            <div class="lbl">Hormat kami,</div>
            <div class="dots">..................</div>
          </div>
          <div class="nota-totals">
            <div class="row"><span class="t-lbl">TOTAL</span><span class="t-box mono">${rupiah(total)}</span></div>
            <div class="row"><span class="t-lbl">UANG MUKA</span><span class="t-box mono">${dp > 0 ? rupiah(dp) : ''}</span></div>
            <div class="row"><span class="t-lbl">SISA</span><span class="t-box mono">${(dp > 0 || d.payment_status === 'Belum Lunas') ? rupiah(sisa) : ''}</span></div>
            <div class="pay-row">
              <span class="pay-item"><span class="cb ${d.payment_method==='QRIS'?'on':''}"></span> QRIS</span>
              <span class="pay-item"><span class="cb ${d.payment_method==='BCA'?'on':''}"></span> BCA</span>
              <span class="pay-item"><span class="cb ${d.payment_method==='Tunai'?'on':''}"></span> TUNAI</span>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>`;
}

function esc(s) { return (s==null?'':String(s)).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function rupiah(n) { return 'Rp ' + (n || 0).toLocaleString('id-ID'); }

// Initial render placeholder
renderNota({
  invoice_number: '<?= e(next_invoice_number(load_json(INVOICES_FILE))) ?>',
  invoice_date: new Date().toISOString().slice(0,10),
  customer_name: '',
  items: [],
  payment_method: 'Tunai',
  payment_status: 'Lunas',
  down_payment: 0,
});
</script>
</body>
</html>
