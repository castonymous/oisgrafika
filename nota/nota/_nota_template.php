<?php
// _nota_template.php
// Variabel yang dibutuhkan (di-pass dari caller):
//   $invoice (array), $settings (array), $size ('a4'|'a6')

function service_columns($services, $cols = 2) {
    $out = array_fill(0, $cols, []);
    foreach ($services as $i => $s) $out[$i % $cols][] = $s;
    return $out;
}

$items = $invoice['items'] ?? [];
$minRows = $size === 'a4' ? 12 : 8;
$emptyRows = max(0, $minRows - count($items));

$total = (int)($invoice['total'] ?? 0);
$dp    = (int)($invoice['down_payment'] ?? 0);
$sisa  = (int)($invoice['remaining'] ?? max(0, $total - $dp));

$method = $invoice['payment_method'] ?? 'Tunai';
$serviceCols = service_columns($settings['services'] ?? [], 2);

$dateFmt = '';
if (!empty($invoice['invoice_date'])) {
    $ts = strtotime($invoice['invoice_date']);
    $months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $dateFmt = date('d', $ts) . ' ' . $months[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

$accent = $settings['accent_color'] ?? '#ff7aa8';
?>
<div class="nota-wrapper nota-<?= e($size) ?>" style="--accent: <?= e($accent) ?>;">

  <div class="nota-grid">
    <!-- ═══ KIRI ═══ -->
    <aside class="nota-left">

      <div class="nota-brand">
        <div class="nota-logo">
          <svg viewBox="0 0 40 40" width="38" height="38">
            <circle cx="20" cy="20" r="18" fill="var(--accent)" opacity="0.15"/>
            <circle cx="20" cy="20" r="14" fill="none" stroke="var(--accent)" stroke-width="2.5"/>
            <text x="20" y="26" text-anchor="middle" font-family="Arial Black" font-size="13" fill="var(--accent)" font-weight="900">OG</text>
          </svg>
        </div>
        <div class="nota-brand-text">
          <div class="nota-brand-name"><?= e($settings['business_name'] ?? 'OIS GRAFIKA') ?></div>
          <div class="nota-tagline"><?= e($settings['tagline'] ?? '') ?></div>
        </div>
      </div>
      <div class="nota-address"><?= e($settings['address'] ?? '') ?></div>

      <div class="nota-contact-box">
        <div class="nota-qr">
          <!-- QR placeholder pake API gratis, no auth, server-side encode -->
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?= e(urlencode('https://wa.me/' . ($settings['phone'] ?? ''))) ?>" alt="QR">
          <div class="nota-phone"><?= e($settings['phone'] ?? '') ?></div>
        </div>
        <div class="nota-socials">
          <div><span class="soc-ico">f</span><?= e($settings['facebook'] ?? '') ?></div>
          <div><span class="soc-ico">▶</span><?= e($settings['youtube'] ?? '') ?></div>
          <div><span class="soc-ico">◉</span><?= e($settings['instagram'] ?? '') ?></div>
          <div><span class="soc-ico">♪</span><?= e($settings['tiktok'] ?? '') ?></div>
          <div><span class="soc-ico">𝕏</span><?= e($settings['twitter'] ?? '') ?></div>
        </div>
      </div>

      <div class="nota-services">
        <?php foreach ($serviceCols as $col): ?>
          <ul>
            <?php foreach ($col as $s): ?>
              <li># <?= e($s) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>
      </div>

      <div class="nota-warning">
        <div class="nota-warning-title">PERHATIAN</div>
        <div class="nota-warning-text"><?= e($settings['attention_text'] ?? '') ?></div>
      </div>

    </aside>

    <!-- ═══ KANAN ═══ -->
    <section class="nota-right">

      <div class="nota-header">
        <div class="nota-field">
          <span class="lbl">No:</span>
          <div class="box mono"><?= e($invoice['invoice_number'] ?? '') ?></div>
        </div>
        <div class="nota-field">
          <span class="lbl">Tanggal</span>
          <div class="box"><?= e($dateFmt) ?></div>
        </div>
        <div class="nota-field full">
          <span class="lbl">Yth.</span>
          <div class="box"><?= e($invoice['customer_name'] ?? '') ?></div>
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
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td class="col-qty mono"><?= e($it['qty']) ?></td>
            <td class="col-nama"><?= e($it['item_name']) ?></td>
            <td class="col-harga mono"><?= rupiah($it['unit_price']) ?></td>
            <td class="col-jumlah mono"><?= rupiah($it['subtotal']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php for ($i = 0; $i < $emptyRows; $i++): ?>
          <tr><td class="col-qty">&nbsp;</td><td></td><td></td><td></td></tr>
        <?php endfor; ?>
        </tbody>
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
          <div class="row">
            <span class="t-lbl">TOTAL</span>
            <span class="t-box mono"><?= rupiah($total) ?></span>
          </div>
          <div class="row">
            <span class="t-lbl">UANG MUKA</span>
            <span class="t-box mono"><?= $dp > 0 ? rupiah($dp) : '' ?></span>
          </div>
          <div class="row">
            <span class="t-lbl">SISA</span>
            <span class="t-box mono"><?= ($dp > 0 || ($invoice['payment_status'] ?? '') === 'Belum Lunas') ? rupiah($sisa) : '' ?></span>
          </div>
          <div class="pay-row">
            <span class="pay-item"><span class="cb <?= $method==='QRIS'?'on':'' ?>"></span> QRIS</span>
            <span class="pay-item"><span class="cb <?= $method==='BCA'?'on':'' ?>"></span> BCA</span>
            <span class="pay-item"><span class="cb <?= $method==='Tunai'?'on':'' ?>"></span> TUNAI</span>
          </div>
        </div>
      </div>

    </section>
  </div>
</div>
