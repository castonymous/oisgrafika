<?php
$pageTitle = 'Tambah Produk Baru';
require_once __DIR__ . '/../includes/admin-helpers.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;

// Default data
$product = [
    'id' => 0, 'name' => '', 'slug' => '', 'category_id' => '', 'type' => 'fisik',
    'gtin' => '', 'no_gtin' => 0, 'short_description' => '', 'description' => '',
    'base_price' => '', 'stock' => 0, 'sku' => '', 'min_purchase' => 1, 'max_purchase' => null,
    'weight' => '', 'length_cm' => '', 'width_cm' => '', 'height_cm' => '',
    'shipping_origin' => 'Jakarta', 'free_shipping' => 0, 'preorder' => 0, 'preorder_days' => null,
    'status' => 'draft', 'tags' => '', 'internal_note' => '', 'seo_title' => '', 'seo_description' => '',
    'image' => null,
];

$images = [];
$attributes = [];
$variations = [];
$variationItems = [];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) redirect(SITE_URL . '/admin/produk.php', 'Produk tidak ada', 'error');
    
    $imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_cover DESC, sort_order ASC");
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll();
    
    $attrStmt = $pdo->prepare("SELECT * FROM product_attributes WHERE product_id = ? ORDER BY sort_order ASC");
    $attrStmt->execute([$id]);
    $attributes = $attrStmt->fetchAll();
    
    $varStmt = $pdo->prepare("SELECT * FROM product_variations WHERE product_id = ? ORDER BY sort_order ASC");
    $varStmt->execute([$id]);
    $variations = $varStmt->fetchAll();
    
    $viStmt = $pdo->prepare("SELECT * FROM product_variation_items WHERE product_id = ?");
    $viStmt->execute([$id]);
    $variationItems = $viStmt->fetchAll();
}

// Restore form_data dari session kalo ada error
if (!empty($_SESSION['form_data'])) {
    $fd = $_SESSION['form_data'];
    foreach ($product as $k => $v) {
        if (isset($fd[$k])) $product[$k] = $fd[$k];
    }
    unset($_SESSION['form_data']);
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Auto-seed kalo kategori kosong (defensive)
if (empty($categories)) {
    $pdo->prepare("INSERT INTO categories (name, slug, icon, description) VALUES 
        ('Jasa Desain', 'jasa-desain', '🎨', 'Logo, banner, packaging, dan desain custom'),
        ('Percetakan', 'percetakan', '🖨️', 'Cetak banner, kartu nama, brosur, sticker'),
        ('Produk Digital', 'digital', '💾', 'Template, font, preset, mockup siap pakai'),
        ('Merchandise', 'merchandise', '👕', 'Kaos custom, mug, sticker, totebag')
    ")->execute();
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
}
$completeness = calculateProductCompleteness($product, $images, $attributes);
$defaultAttrs = getDefaultAttributes();

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/seller-form.css?v=<?= @filemtime(__DIR__ . '/../assets/css/seller-form.css') ?: '1.0' ?>">

<div class="seller-form-wrapper">
    <!-- Top breadcrumb -->
    <div class="seller-breadcrumb">
        <a href="<?= SITE_URL ?>/admin/index.php">Dashboard</a>
        <span>›</span>
        <a href="<?= SITE_URL ?>/admin/produk.php">Produk</a>
        <span>›</span>
        <strong><?= $isEdit ? 'Edit' : 'Tambah' ?> Produk</strong>
    </div>

    <?php
    // Tampilkan error dari validation
    if (!empty($_SESSION['form_errors'])):
        $formErrors = $_SESSION['form_errors'];
        unset($_SESSION['form_errors']);
    ?>
        <div style="background:#fde8e8;border:1px solid #fca5a5;border-radius:8px;padding:14px 18px;margin-bottom:16px;color:#991b1b;">
            <strong style="display:block;margin-bottom:6px;">❌ Produk tidak bisa disimpan:</strong>
            <ul style="margin:0;padding-left:20px;">
                <?php foreach ($formErrors as $err): ?>
                    <li style="margin-bottom:2px;"><?= clean($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="<?= SITE_URL ?>/admin/produk-save.php" enctype="multipart/form-data" id="productForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>
        <input type="hidden" name="action" id="formAction" value="save_draft">

        <div class="seller-grid">
            <!-- ============ SIDEBAR PROGRESS (KIRI) ============ -->
            <aside class="seller-progress">
                <div class="progress-card">
                    <h3>Kelengkapan Produk</h3>
                    <div class="progress-stat">
                        <span class="progress-percent" id="completenessPercent"><?= $completeness['score'] ?>%</span>
                        <span class="progress-detail"><?= $completeness['done'] ?>/<?= $completeness['total'] ?> selesai</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="completenessFill" style="width: <?= $completeness['score'] ?>%"></div>
                    </div>
                    
                    <ul class="checklist">
                        <?php foreach ($completeness['items'] as $key => $item): ?>
                            <li class="check-item <?= $item['done'] ? 'done' : '' ?>" data-check="<?= $key ?>">
                                <span class="check-icon">
                                    <?= $item['done'] ? '✓' : ($item['required'] ? '!' : '○') ?>
                                </span>
                                <span class="check-label"><?= $item['label'] ?></span>
                                <?php if ($item['required']): ?>
                                    <span class="check-req">wajib</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="progress-card status-card">
                    <h4>Status Saat Ini</h4>
                    <div class="status-display">
                        <span class="status-badge status-<?= $product['status'] ?: 'draft' ?>">
                            <?= ucfirst($product['status'] ?: 'draft') ?>
                        </span>
                    </div>
                </div>
            </aside>

            <!-- ============ MAIN CONTENT (TENGAH) ============ -->
            <div class="seller-main">
                <!-- Tab navigation -->
                <div class="tab-nav-sticky">
                    <button type="button" class="tab-btn active" data-tab="info">Informasi Produk</button>
                    <button type="button" class="tab-btn" data-tab="spec">Spesifikasi</button>
                    <button type="button" class="tab-btn" data-tab="desc">Deskripsi</button>
                    <button type="button" class="tab-btn" data-tab="sale">Penjualan</button>
                    <button type="button" class="tab-btn" data-tab="ship">Pengiriman</button>
                    <button type="button" class="tab-btn" data-tab="other">Lainnya</button>
                </div>

                <!-- ===== TAB: INFORMASI PRODUK ===== -->
                <div class="tab-panel active" data-panel="info">
                    <div class="form-card">
                        <h3 class="card-title">Informasi Produk</h3>
                        
                        <div class="field">
                            <label class="field-label"><span class="req">*</span> Foto Produk <small>(Maks. 8 foto, format JPG/PNG/WEBP)</small></label>
                            <div class="photo-grid" id="photoGrid">
                                <?php foreach ($images as $img): ?>
                                    <div class="photo-item <?= $img['is_cover'] ? 'is-cover' : '' ?>" data-img-id="<?= $img['id'] ?>">
                                        <img src="<?= SITE_URL ?>/<?= clean($img['image']) ?>" alt="">
                                        <?php if ($img['is_cover']): ?><span class="photo-badge">Cover</span><?php endif; ?>
                                        <div class="photo-actions">
                                            <?php if (!$img['is_cover']): ?>
                                                <button type="button" class="photo-btn" onclick="setCoverImage(<?= $img['id'] ?>)" title="Jadikan cover">★</button>
                                            <?php endif; ?>
                                            <button type="button" class="photo-btn photo-btn-danger" onclick="deleteExistingImage(<?= $img['id'] ?>, this)" title="Hapus">×</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <label class="photo-upload" id="photoUploadBtn">
                                    <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" onchange="handlePhotoUpload(event)" style="display:none">
                                    <div class="upload-icon">📷</div>
                                    <div class="upload-text">Tambah Foto<br><small>(<?= count($images) ?>/8)</small></div>
                                </label>
                            </div>
                            <input type="hidden" name="cover_image_id" id="coverImageId" value="">
                            <div id="deleteImagesContainer"></div>
                        </div>

                        <div class="field">
                            <label class="field-label"><span class="req">*</span> Nama Produk <small style="font-weight:400;color:#888;">(min 5 karakter untuk publish)</small></label>
                            <div class="input-with-counter">
                                <input type="text" name="name" id="inputName" class="form-input" maxlength="255" required value="<?= clean($product['name']) ?>" placeholder="Contoh: Kaos Custom Cotton Combed 30s">
                                <span class="counter"><span id="nameCount"><?= strlen($product['name']) ?></span>/255</span>
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label class="field-label"><span class="req">*</span> Kategori</label>
                                <select name="category_id" id="inputCategory" class="form-select" required>
                                    <option value="">Pilih kategori</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                            <?= $cat['icon'] ?? '' ?> <?= clean($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="field">
                                <label class="field-label">Tipe Produk</label>
                                <select name="type" class="form-select">
                                    <option value="fisik" <?= $product['type'] === 'fisik' ? 'selected' : '' ?>>📦 Fisik</option>
                                    <option value="digital" <?= $product['type'] === 'digital' ? 'selected' : '' ?>>💾 Digital</option>
                                    <option value="jasa" <?= $product['type'] === 'jasa' ? 'selected' : '' ?>>🎨 Jasa</option>
                                </select>
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label class="field-label">GTIN (Barcode)</label>
                                <input type="text" name="gtin" class="form-input" value="<?= clean($product['gtin']) ?>" placeholder="Masukkan GTIN" <?= $product['no_gtin'] ? 'disabled' : '' ?>>
                            </div>
                            <div class="field" style="display: flex; align-items: flex-end;">
                                <label style="display: flex; align-items: center; gap: 8px; padding-bottom: 11px;">
                                    <input type="checkbox" name="no_gtin" value="1" onchange="this.closest('.field-row').querySelector('input[name=gtin]').disabled = this.checked" <?= $product['no_gtin'] ? 'checked' : '' ?>>
                                    Produk tanpa GTIN
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== TAB: SPESIFIKASI ===== -->
                <div class="tab-panel" data-panel="spec">
                    <div class="form-card">
                        <h3 class="card-title">Spesifikasi Produk</h3>
                        <p class="card-subtitle">Lengkapi atribut produk agar lebih banyak ditemukan pembeli</p>
                        
                        <div id="attrContainer">
                            <?php 
                            // Merge default + saved
                            $savedAttrNames = array_column($attributes, 'attr_name');
                            $allAttrs = $defaultAttrs;
                            // Tambah custom yang udah disave tapi ga ada di default
                            foreach ($attributes as $sa) {
                                if (!in_array($sa['attr_name'], array_column($defaultAttrs, 'name'))) {
                                    $allAttrs[] = ['name' => $sa['attr_name'], 'required' => $sa['is_required'], 'type' => 'text'];
                                }
                            }
                            
                            $requiredAttrs = array_filter($allAttrs, fn($a) => $a['required']);
                            $optionalAttrs = array_filter($allAttrs, fn($a) => !$a['required']);
                            ?>
                            
                            <!-- Required attributes -->
                            <div class="attr-section">
                                <h4 class="attr-section-title">Atribut Utama</h4>
                                <div class="attr-grid">
                                    <?php foreach ($requiredAttrs as $idx => $attr): 
                                        $savedVal = '';
                                        foreach ($attributes as $sa) {
                                            if ($sa['attr_name'] === $attr['name']) { $savedVal = $sa['attr_value']; break; }
                                        }
                                    ?>
                                        <div class="field">
                                            <label class="field-label">
                                                <span class="req">*</span> <?= clean($attr['name']) ?>
                                            </label>
                                            <input type="hidden" name="attr_name[]" value="<?= clean($attr['name']) ?>">
                                            <input type="hidden" name="attr_required[]" value="1">
                                            <?php if ($attr['type'] === 'select'): ?>
                                                <select name="attr_value[]" class="form-select">
                                                    <option value="">Pilih</option>
                                                    <?php foreach ($attr['options'] as $opt): ?>
                                                        <option value="<?= clean($opt) ?>" <?= $savedVal === $opt ? 'selected' : '' ?>><?= clean($opt) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php else: ?>
                                                <input type="text" name="attr_value[]" class="form-input" value="<?= clean($savedVal) ?>" placeholder="<?= clean($attr['placeholder'] ?? '') ?>">
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Optional attributes (collapsible) -->
                            <div class="attr-section">
                                <h4 class="attr-section-title">Atribut Lainnya</h4>
                                <div class="attr-grid" id="optionalAttrs">
                                    <?php foreach ($optionalAttrs as $idx => $attr): 
                                        $savedVal = '';
                                        foreach ($attributes as $sa) {
                                            if ($sa['attr_name'] === $attr['name']) { $savedVal = $sa['attr_value']; break; }
                                        }
                                        $hidden = $idx >= 4 && empty($savedVal);
                                    ?>
                                        <div class="field optional-attr <?= $hidden ? 'attr-hidden' : '' ?>">
                                            <label class="field-label"><?= clean($attr['name']) ?></label>
                                            <input type="hidden" name="attr_name[]" value="<?= clean($attr['name']) ?>">
                                            <input type="hidden" name="attr_required[]" value="0">
                                            <?php if ($attr['type'] === 'select'): ?>
                                                <select name="attr_value[]" class="form-select">
                                                    <option value="">Pilih</option>
                                                    <?php foreach ($attr['options'] as $opt): ?>
                                                        <option value="<?= clean($opt) ?>" <?= $savedVal === $opt ? 'selected' : '' ?>><?= clean($opt) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php else: ?>
                                                <input type="text" name="attr_value[]" class="form-input" value="<?= clean($savedVal) ?>" placeholder="<?= clean($attr['placeholder'] ?? '') ?>">
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="link-btn" id="toggleAttrs" onclick="toggleOptionalAttrs()">+ Tampilkan lebih banyak</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== TAB: DESKRIPSI ===== -->
                <div class="tab-panel" data-panel="desc">
                    <div class="form-card">
                        <h3 class="card-title">Deskripsi Produk</h3>
                        
                        <div class="field">
                            <label class="field-label">Deskripsi Singkat <small>(akan tampil di card produk)</small></label>
                            <input type="text" name="short_description" class="form-input" maxlength="300" value="<?= clean($product['short_description']) ?>" placeholder="Highlight singkat produk">
                        </div>
                        
                        <div class="field">
                            <label class="field-label"><span class="req">*</span> Deskripsi Lengkap <small>(min 20 karakter untuk publish)</small></label>
                            <div class="input-with-counter">
                                <textarea name="description" id="inputDescription" class="form-textarea" rows="10" maxlength="5000" placeholder="Jelaskan detail produkmu...&#10;&#10;Tips:&#10;- Sebutkan keunggulan utama&#10;- Cara penggunaan&#10;- Kualitas material&#10;- Garansi & after sales"><?= clean($product['description']) ?></textarea>
                                <span class="counter"><span id="descCount"><?= strlen($product['description']) ?></span>/5000</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== TAB: PENJUALAN ===== -->
                <div class="tab-panel" data-panel="sale">
                    <div class="form-card">
                        <h3 class="card-title">Informasi Penjualan</h3>
                        
                        <div class="field-row">
                            <div class="field" id="basePriceField">
                                <label class="field-label"><span class="req">*</span> Harga (Rp)</label>
                                <div class="rp-input" id="basePriceWrap">
                                    <span>Rp</span>
                                    <input type="text" id="inputPriceDisplay" class="form-input" value="<?= $product['base_price'] ? number_format((int)$product['base_price'], 0, ',', '.') : '' ?>" placeholder="0" oninput="formatThousand(this, 'inputPrice')">
                                    <input type="hidden" name="base_price" id="inputPrice" value="<?= $product['base_price'] ?>">
                                </div>
                                <small id="basePriceHelp" style="display:none; font-size: 11px; color: var(--text-muted); margin-top: 4px;">⚠️ Harga otomatis dari variasi. Diatur per kombinasi varian.</small>
                            </div>
                            <div class="field" id="baseStockField">
                                <label class="field-label"><span class="req">*</span> Stok</label>
                                <input type="number" name="stock" id="inputStock" class="form-input" min="0" required value="<?= $product['stock'] ?>" placeholder="0">
                                <small id="baseStockHelp" style="display:none; font-size: 11px; color: var(--text-muted); margin-top: 4px;">⚠️ Stok dihitung per varian.</small>
                            </div>
                            <div class="field">
                                <label class="field-label">SKU</label>
                                <input type="text" name="sku" class="form-input" value="<?= clean($product['sku']) ?>" placeholder="Optional">
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label class="field-label">Min. Pembelian</label>
                                <input type="number" name="min_purchase" class="form-input" min="1" value="<?= $product['min_purchase'] ?: 1 ?>">
                            </div>
                            <div class="field">
                                <label class="field-label">Maks. Pembelian</label>
                                <input type="number" name="max_purchase" class="form-input" min="1" value="<?= $product['max_purchase'] ?>" placeholder="Tidak terbatas">
                            </div>
                        </div>

                        <!-- Harga Grosir / Tier Pricing -->
                        <?php
                        $tierStmt = $pdo->prepare("SELECT * FROM product_tier_prices WHERE product_id = ? ORDER BY min_qty ASC");
                        $tierStmt->execute([$id ?: 0]);
                        $tiers = $tierStmt->fetchAll();
                        ?>
                        <div class="variation-section" id="tierSection" style="margin-top: 16px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <h4 style="margin: 0; font-size: 15px;">💰 Harga Grosir</h4>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px;" id="tierToggleLabel">
                                    <input type="checkbox" name="use_tier_pricing" id="enableTier" value="1" <?= !empty($product['use_tier_pricing']) ? 'checked' : '' ?> onchange="toggleTierPricing(this.checked)">
                                    Aktifkan Harga Grosir
                                </label>
                            </div>
                            <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">Beda harga berdasarkan jumlah beli. Max 5 tier.</p>
                            
                            <!-- Warning kalo variasi beda harga -->
                            <div id="tierVariationWarning" style="display:none; padding: 10px 12px; background: #fff7e6; border: 1px solid #ffd591; border-radius: var(--radius); font-size: 12px; color: #92560f; margin-bottom: 12px;">
                                ⚠️ <strong>Harga Grosir tidak tersedia</strong> karena variasi produk memiliki harga yang berbeda-beda. Harga grosir hanya bisa dipakai kalau semua varian punya harga yang sama.
                            </div>
                            
                            <div id="tierContainer" style="display: <?= !empty($product['use_tier_pricing']) ? 'block' : 'none' ?>;">
                                <!-- Tabel Tier -->
                                <div class="tier-table-wrap">
                                    <table class="tier-table-admin">
                                        <thead>
                                            <tr>
                                                <th style="width: 15%;">No.</th>
                                                <th>Jumlah Min.</th>
                                                <th>Jumlah Maks.</th>
                                                <th>Harga Satuan</th>
                                                <th style="width: 60px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tierList">
                                            <?php if (empty($tiers)): ?>
                                                <tr class="tier-row">
                                                    <td class="tier-label">Harga Grosir 1</td>
                                                    <td><input type="number" name="tier_min[]" class="form-input" min="1" placeholder="Min."></td>
                                                    <td><input type="number" name="tier_max[]" class="form-input" min="1" placeholder="Maks."></td>
                                                    <td>
                                                        <div class="rp-input">
                                                            <span>Rp</span>
                                                            <input type="text" name="tier_price[]" class="form-input tier-price-input" placeholder="Harga Satuan">
                                                        </div>
                                                    </td>
                                                    <td><button type="button" class="tier-del" onclick="removeTier(this)" title="Hapus">🗑️</button></td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($tiers as $idx => $tier): ?>
                                                    <tr class="tier-row">
                                                        <td class="tier-label">Harga Grosir <?= $idx + 1 ?></td>
                                                        <td><input type="number" name="tier_min[]" class="form-input" min="1" value="<?= $tier['min_qty'] ?>"></td>
                                                        <td><input type="number" name="tier_max[]" class="form-input" min="1" value="<?= $tier['max_qty'] ?>" placeholder="Maks."></td>
                                                        <td>
                                                            <div class="rp-input">
                                                                <span>Rp</span>
                                                                <input type="text" name="tier_price[]" class="form-input tier-price-input" value="<?= number_format((int)$tier['price'], 0, ',', '.') ?>">
                                                            </div>
                                                        </td>
                                                        <td><button type="button" class="tier-del" onclick="removeTier(this)" title="Hapus">🗑️</button></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="5" style="text-align: center; padding: 0;">
                                                    <button type="button" class="btn-add-tier" onclick="addTier()" id="addTierBtn">+ Tambah Harga Grosir</button>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <p style="font-size: 11px; color: var(--text-muted); margin-top: 8px;">⚠️ Harga grosir akan disembunyikan ketika produk dalam promosi/diskon.</p>
                            </div>
                        </div>

                        <!-- Variasi Produk -->
                        <?php
                        // Load existing image map untuk variasi 1
                        $existingImageMaps = [];
                        if (!empty($variations)) {
                            foreach ($variations as $v) {
                                if (!empty($v['image_map'])) {
                                    $existingImageMaps[$v['id']] = json_decode($v['image_map'], true) ?: [];
                                }
                            }
                        }
                        ?>
                        <div class="variation-section">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <h4 style="margin: 0; font-size: 15px;">Variasi Produk</h4>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                                    <input type="checkbox" name="enable_variation" id="enableVariation" value="1" <?= !empty($variations) ? 'checked' : '' ?> onchange="toggleVariations(this.checked)">
                                    Aktifkan Variasi
                                </label>
                            </div>
                            
                            <div id="variationContainer" style="display: <?= !empty($variations) ? 'block' : 'none' ?>;">
                                <div id="variationList">
                                    <?php if (empty($variations)): ?>
                                        <!-- Default: 1 variasi kosong dengan slot gambar -->
                                        <div class="var-axis" data-axis="1">
                                            <div class="var-axis-head">
                                                <span class="var-axis-label">Variasi 1</span>
                                                <button type="button" class="var-axis-del" onclick="removeAxis(this)" title="Hapus">×</button>
                                            </div>
                                            <div class="var-axis-body">
                                                <div class="field">
                                                    <label class="field-label">Nama Variasi <small>(Contoh: Warna)</small></label>
                                                    <input type="text" name="variation_name[]" class="form-input var-name" placeholder="Warna" onchange="updateOptions(this)">
                                                </div>
                                                <div class="field">
                                                    <label class="field-label">Opsi <small>(klik + untuk tambah, atau ketik dipisah koma)</small></label>
                                                    <div class="var-options-grid" data-axis-options>
                                                        <!-- Options akan generate via JS -->
                                                    </div>
                                                    <input type="hidden" name="variation_options[]" class="var-options-hidden" value="">
                                                    <input type="hidden" name="variation_has_images[]" class="var-has-images" value="1">
                                                    <button type="button" class="btn-add-option" onclick="addOption(this)">+ Tambah Opsi</button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($variations as $idx => $var): 
                                            $opts = json_decode($var['options'], true) ?? [];
                                            $imgMap = $existingImageMaps[$var['id']] ?? [];
                                            $hasImg = !empty($var['has_images']) || $idx === 0;
                                        ?>
                                            <div class="var-axis" data-axis="<?= $idx + 1 ?>">
                                                <div class="var-axis-head">
                                                    <span class="var-axis-label">Variasi <?= $idx + 1 ?></span>
                                                    <button type="button" class="var-axis-del" onclick="removeAxis(this)" title="Hapus">×</button>
                                                </div>
                                                <div class="var-axis-body">
                                                    <div class="field">
                                                        <label class="field-label">Nama Variasi</label>
                                                        <input type="text" name="variation_name[]" class="form-input var-name" value="<?= clean($var['name']) ?>" placeholder="<?= $idx === 0 ? 'Warna' : 'Ukuran' ?>" onchange="updateOptions(this)">
                                                    </div>
                                                    <div class="field">
                                                        <label class="field-label">Opsi <small><?= $idx === 0 ? '(bisa upload gambar)' : '(text only)' ?></small></label>
                                                        <div class="var-options-grid" data-axis-options>
                                                            <?php foreach ($opts as $optIdx => $opt): 
                                                                $imgPath = $imgMap[$opt] ?? '';
                                                            ?>
                                                                <div class="var-option-item">
                                                                    <?php if ($hasImg): ?>
                                                                        <label class="var-option-img" data-option="<?= clean($opt) ?>">
                                                                            <input type="file" name="variation_option_image[<?= $idx ?>][<?= clean($opt) ?>]" accept="image/*" onchange="previewVarImg(this)" style="display:none;">
                                                                            <?php if ($imgPath): ?>
                                                                                <img src="<?= SITE_URL ?>/<?= clean($imgPath) ?>" alt="">
                                                                            <?php else: ?>
                                                                                <span class="var-img-placeholder">🖼️</span>
                                                                            <?php endif; ?>
                                                                        </label>
                                                                    <?php endif; ?>
                                                                    <input type="text" class="form-input var-option-text" value="<?= clean($opt) ?>" placeholder="Opsi" onchange="syncOptions(this)">
                                                                    <button type="button" class="var-option-del" onclick="removeOption(this)" title="Hapus opsi">×</button>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <input type="hidden" name="variation_options[]" class="var-options-hidden" value="<?= clean(implode(',', $opts)) ?>">
                                                        <input type="hidden" name="variation_has_images[]" class="var-has-images" value="<?= $hasImg ? 1 : 0 ?>">
                                                        <button type="button" class="btn-add-option" onclick="addOption(this)">+ Tambah Opsi</button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn-add-axis" id="addAxisBtn" onclick="addAxis()" <?= count($variations ?? []) >= 2 ? 'style="display:none;"' : '' ?>>+ Tambah Variasi 2</button>
                                
                                <!-- Tabel kombinasi (auto-generated) -->
                                <div id="combinationTable" style="margin-top: 20px;">
                                    <h4 style="font-size: 14px; margin-bottom: 10px;">Daftar Variasi</h4>
                                    
                                    <!-- Apply ke semua -->
                                    <div class="apply-all-bar">
                                        <input type="number" id="bulkPrice" class="form-input form-input-sm" placeholder="Harga" min="0">
                                        <input type="number" id="bulkStock" class="form-input form-input-sm" placeholder="Stok" min="0">
                                        <input type="text" id="bulkSku" class="form-input form-input-sm" placeholder="Kode Variasi">
                                        <button type="button" class="btn-apply-all" onclick="applyToAll()">Terapkan Ke Semua</button>
                                    </div>
                                    
                                    <div style="overflow-x: auto; margin-top: 10px;">
                                        <table class="combo-table">
                                            <thead id="comboTableHead">
                                                <!-- Headers auto-generated -->
                                            </thead>
                                            <tbody id="combinationBody">
                                                <!-- Rows auto-generated -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== TAB: PENGIRIMAN ===== -->
                <div class="tab-panel" data-panel="ship">
                    <div class="form-card">
                        <h3 class="card-title">Informasi Pengiriman</h3>
                        
                        <div class="field-row">
                            <div class="field">
                                <label class="field-label"><span class="req">*</span> Berat (gram)</label>
                                <input type="number" name="weight" class="form-input" min="0" value="<?= $product['weight'] ?>" placeholder="0">
                            </div>
                            <div class="field">
                                <label class="field-label">Asal Pengiriman</label>
                                <input type="text" name="shipping_origin" class="form-input" value="<?= clean($product['shipping_origin']) ?>" placeholder="Jakarta">
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label class="field-label">Panjang (cm)</label>
                                <input type="number" name="length_cm" class="form-input" min="0" step="0.1" value="<?= $product['length_cm'] ?>" placeholder="0">
                            </div>
                            <div class="field">
                                <label class="field-label">Lebar (cm)</label>
                                <input type="number" name="width_cm" class="form-input" min="0" step="0.1" value="<?= $product['width_cm'] ?>" placeholder="0">
                            </div>
                            <div class="field">
                                <label class="field-label">Tinggi (cm)</label>
                                <input type="number" name="height_cm" class="form-input" min="0" step="0.1" value="<?= $product['height_cm'] ?>" placeholder="0">
                            </div>
                        </div>

                        <div class="field">
                            <label style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" name="free_shipping" value="1" <?= $product['free_shipping'] ? 'checked' : '' ?>>
                                <span>🚚 Aktifkan Gratis Ongkir</span>
                            </label>
                        </div>

                        <div class="field">
                            <label style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" name="preorder" id="cbPreorder" value="1" onchange="document.getElementById('preorderDays').style.display = this.checked ? 'block' : 'none'" <?= $product['preorder'] ? 'checked' : '' ?>>
                                <span>📅 Preorder Produk</span>
                            </label>
                            <div id="preorderDays" style="margin-top: 10px; display: <?= $product['preorder'] ? 'block' : 'none' ?>;">
                                <label class="field-label">Lama Preorder (hari)</label>
                                <input type="number" name="preorder_days" class="form-input" min="1" value="<?= $product['preorder_days'] ?>" placeholder="7" style="max-width: 200px;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== TAB: LAINNYA ===== -->
                <div class="tab-panel" data-panel="other">
                    <div class="form-card">
                        <h3 class="card-title">Pengaturan Lainnya</h3>
                        
                        <div class="field">
                            <label class="field-label">Slug URL <small>(otomatis dari nama, bisa diedit)</small></label>
                            <input type="text" name="slug" class="form-input" value="<?= clean($product['slug']) ?>" placeholder="contoh-nama-produk">
                        </div>

                        <div class="field">
                            <label class="field-label">Tags <small>(pisahkan dengan koma)</small></label>
                            <input type="text" name="tags" class="form-input" value="<?= clean($product['tags']) ?>" placeholder="kaos, custom, distro">
                        </div>

                        <div class="field">
                            <label class="field-label">SEO Title</label>
                            <input type="text" name="seo_title" class="form-input" maxlength="255" value="<?= clean($product['seo_title']) ?>" placeholder="Untuk Google search">
                        </div>

                        <div class="field">
                            <label class="field-label">SEO Description</label>
                            <textarea name="seo_description" class="form-textarea" rows="3" placeholder="Meta description untuk Google"><?= clean($product['seo_description']) ?></textarea>
                        </div>

                        <div class="field">
                            <label class="field-label">Catatan Internal <small>(tidak ditampilkan ke pembeli)</small></label>
                            <textarea name="internal_note" class="form-textarea" rows="3" placeholder="Catatan untuk admin"><?= clean($product['internal_note']) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="form-actions">
                    <a href="<?= SITE_URL ?>/admin/produk.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" name="action_btn" class="btn btn-outline" onclick="document.getElementById('formAction').value='save_draft'">💾 Simpan Draft</button>
                    <button type="submit" name="action_btn" class="btn btn-primary-solid" onclick="return preparePublish()">🚀 Publish Produk</button>
                </div>
            </div>

            <!-- ============ LIVE PREVIEW (KANAN) ============ -->
            <aside class="seller-preview">
                <div class="preview-card">
                    <div class="preview-header">Preview Produk</div>
                    <div class="preview-image" id="previewImage">
                        <?php
                        $coverImg = null;
                        foreach ($images as $img) { if ($img['is_cover']) { $coverImg = $img['image']; break; } }
                        if (!$coverImg && !empty($images)) $coverImg = $images[0]['image'];
                        ?>
                        <?php if ($coverImg): ?>
                            <img src="<?= SITE_URL ?>/<?= clean($coverImg) ?>" alt="">
                        <?php else: ?>
                            <div class="preview-placeholder">📷</div>
                        <?php endif; ?>
                    </div>
                    <div class="preview-body">
                        <div class="preview-name" id="previewName"><?= clean($product['name']) ?: 'Nama Produk' ?></div>
                        <div class="preview-price" id="previewPrice"><?= $product['base_price'] ? rupiah($product['base_price']) : 'Rp 0' ?></div>
                        <div class="preview-meta">
                            <span class="preview-rating">★ 5.0</span>
                            <span>•</span>
                            <span>0 terjual</span>
                        </div>
                        <div class="preview-actions">
                            <button type="button" class="preview-btn preview-btn-outline">🛒 +Cart</button>
                            <button type="button" class="preview-btn preview-btn-primary">Beli Sekarang</button>
                        </div>
                    </div>
                    <p class="preview-note">Hanya untuk referensi. Tampilan asli mungkin sedikit berbeda.</p>
                </div>
            </aside>
        </div>
    </form>
</div>

<script>
// Existing variation items dari DB (untuk pre-fill harga/stok/SKU saat edit)
window.EXISTING_VARIATION_ITEMS = <?= json_encode($variationItems) ?>;
</script>
<script src="<?= SITE_URL ?>/assets/js/seller-form.js?v=<?= @filemtime(__DIR__ . '/../assets/js/seller-form.js') ?: '1.0' ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
