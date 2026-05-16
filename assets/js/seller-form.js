// ============================================
// SELLER FORM - JavaScript
// ============================================

// ===== TAB NAVIGATION =====
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.querySelector(`.tab-panel[data-panel="${tab}"]`).classList.add('active');
        // Scroll ke atas form area
        document.querySelector('.tab-nav-sticky').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

// ===== CHARACTER COUNTER =====
const nameInput = document.getElementById('inputName');
const nameCount = document.getElementById('nameCount');
if (nameInput && nameCount) {
    nameInput.addEventListener('input', () => {
        nameCount.textContent = nameInput.value.length;
        updatePreview();
        updateCompleteness();
    });
}

const descInput = document.getElementById('inputDescription');
const descCount = document.getElementById('descCount');
if (descInput && descCount) {
    descInput.addEventListener('input', () => {
        descCount.textContent = descInput.value.length;
        updateCompleteness();
    });
}

// ===== LIVE PREVIEW =====
function updatePreview() {
    const name = document.getElementById('inputName')?.value || 'Nama Produk';
    const price = document.getElementById('inputPrice')?.value || 0;
    
    const previewName = document.getElementById('previewName');
    const previewPrice = document.getElementById('previewPrice');
    
    if (previewName) previewName.textContent = name;
    if (previewPrice) previewPrice.textContent = 'Rp ' + parseInt(price || 0).toLocaleString('id-ID');
}

document.getElementById('inputPrice')?.addEventListener('input', updatePreview);

// ===== COMPLETENESS UPDATE (live) =====
function updateCompleteness() {
    const checks = {
        name: (document.getElementById('inputName')?.value.length >= 10),
        category: !!document.getElementById('inputCategory')?.value,
        description: (document.getElementById('inputDescription')?.value.length >= 50),
        price: ((parseFloat(document.getElementById('inputPrice')?.value) || 0) > 0),
    };
    
    document.querySelectorAll('.check-item').forEach(item => {
        const key = item.dataset.check;
        if (key in checks) {
            const isDone = checks[key];
            item.classList.toggle('done', isDone);
            const icon = item.querySelector('.check-icon');
            if (icon) {
                const required = item.querySelector('.check-req');
                icon.textContent = isDone ? '✓' : (required ? '!' : '○');
            }
        }
    });
    
    // Update progress bar
    const allItems = document.querySelectorAll('.check-item');
    const doneItems = document.querySelectorAll('.check-item.done');
    const percent = allItems.length > 0 ? Math.round((doneItems.length / allItems.length) * 100) : 0;
    
    const fill = document.getElementById('completenessFill');
    const pct = document.getElementById('completenessPercent');
    const detail = document.querySelector('.progress-detail');
    
    if (fill) fill.style.width = percent + '%';
    if (pct) pct.textContent = percent + '%';
    if (detail) detail.textContent = `${doneItems.length}/${allItems.length} selesai`;
}

document.getElementById('inputCategory')?.addEventListener('change', updateCompleteness);

// ===== PHOTO UPLOAD PREVIEW =====
function handlePhotoUpload(event) {
    const files = event.target.files;
    const grid = document.getElementById('photoGrid');
    const uploadBtn = document.getElementById('photoUploadBtn');
    
    Array.from(files).forEach((file, idx) => {
        if (!file.type.startsWith('image/')) return;
        if (file.size > 5 * 1024 * 1024) {
            alert(`File ${file.name} terlalu besar (max 5MB)`);
            return;
        }
        
        const reader = new FileReader();
        reader.onload = e => {
            const item = document.createElement('div');
            item.className = 'photo-item';
            item.innerHTML = `
                <img src="${e.target.result}" alt="">
                <span class="photo-badge" style="background: rgba(38, 170, 153, 0.9);">Baru</span>
                <div class="photo-actions">
                    <button type="button" class="photo-btn photo-btn-danger" onclick="this.closest('.photo-item').remove()" title="Hapus">×</button>
                </div>
            `;
            grid.insertBefore(item, uploadBtn);
            updatePhotoCount();
        };
        reader.readAsDataURL(file);
    });
}

function updatePhotoCount() {
    const total = document.querySelectorAll('.photo-item').length;
    const counter = document.querySelector('.upload-text small');
    if (counter) counter.textContent = `(${total}/8)`;
    
    // Update preview image kalo belum ada
    if (total > 0) {
        const firstImg = document.querySelector('.photo-item img');
        const previewImg = document.querySelector('.preview-image img');
        const placeholder = document.querySelector('.preview-placeholder');
        if (firstImg && (placeholder || !previewImg)) {
            document.getElementById('previewImage').innerHTML = `<img src="${firstImg.src}" alt="">`;
        }
    }
    
    // Update completeness
    const photoCheck = document.querySelector('.check-item[data-check="photo"]');
    if (photoCheck) {
        photoCheck.classList.toggle('done', total >= 1);
        const icon = photoCheck.querySelector('.check-icon');
        if (icon) icon.textContent = total >= 1 ? '✓' : '!';
    }
    const photo3Check = document.querySelector('.check-item[data-check="photos_3"]');
    if (photo3Check) {
        photo3Check.classList.toggle('done', total >= 3);
        const icon = photo3Check.querySelector('.check-icon');
        if (icon) icon.textContent = total >= 3 ? '✓' : '○';
    }
    updateCompleteness();
}

function setCoverImage(imageId) {
    document.querySelectorAll('.photo-item').forEach(item => {
        item.classList.remove('is-cover');
        const badge = item.querySelector('.photo-badge');
        if (badge && badge.textContent === 'Cover') badge.remove();
    });
    const targetItem = document.querySelector(`.photo-item[data-img-id="${imageId}"]`);
    if (targetItem) {
        targetItem.classList.add('is-cover');
        if (!targetItem.querySelector('.photo-badge')) {
            const badge = document.createElement('span');
            badge.className = 'photo-badge';
            badge.textContent = 'Cover';
            targetItem.appendChild(badge);
        }
    }
    document.getElementById('coverImageId').value = imageId;
}

function deleteExistingImage(imageId, btn) {
    if (!confirm('Hapus foto ini?')) return;
    const container = document.getElementById('deleteImagesContainer');
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'delete_images[]';
    input.value = imageId;
    container.appendChild(input);
    btn.closest('.photo-item').style.display = 'none';
    updatePhotoCount();
}

// ===== TOGGLE OPTIONAL ATTRIBUTES =====
function toggleOptionalAttrs() {
    const btn = document.getElementById('toggleAttrs');
    const hidden = document.querySelectorAll('.optional-attr.attr-hidden');
    const allOptional = document.querySelectorAll('.optional-attr');
    
    if (hidden.length > 0) {
        // Show all
        allOptional.forEach(el => el.classList.remove('attr-hidden'));
        btn.textContent = '- Tampilkan lebih sedikit';
    } else {
        // Hide ones after index 4
        allOptional.forEach((el, idx) => {
            const input = el.querySelector('input[type="text"], select');
            const isEmpty = !input?.value;
            if (idx >= 4 && isEmpty) el.classList.add('attr-hidden');
        });
        btn.textContent = '+ Tampilkan lebih banyak';
    }
}

// ===== VARIATIONS (Shopee 2-level style) =====
function toggleVariations(enabled) {
    document.getElementById('variationContainer').style.display = enabled ? 'block' : 'none';
    if (enabled && document.querySelectorAll('.var-axis').length === 0) {
        // Generate variasi pertama (dengan gambar)
        const list = document.getElementById('variationList');
        list.innerHTML = `
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
                        <label class="field-label">Opsi <small>(bisa upload gambar)</small></label>
                        <div class="var-options-grid" data-axis-options></div>
                        <input type="hidden" name="variation_options[]" class="var-options-hidden" value="">
                        <input type="hidden" name="variation_has_images[]" class="var-has-images" value="1">
                        <button type="button" class="btn-add-option" onclick="addOption(this)">+ Tambah Opsi</button>
                    </div>
                </div>
            </div>
        `;
        // Auto-add 1 empty option
        const newAxis = list.querySelector('.var-axis');
        addOption(newAxis.querySelector('.btn-add-option'));
    }
    generateCombinations();
}

function addAxis() {
    const list = document.getElementById('variationList');
    if (list.children.length >= 2) {
        alert('Maksimal 2 variasi');
        return;
    }
    const div = document.createElement('div');
    div.className = 'var-axis';
    div.dataset.axis = '2';
    div.innerHTML = `
        <div class="var-axis-head">
            <span class="var-axis-label">Variasi 2</span>
            <button type="button" class="var-axis-del" onclick="removeAxis(this)" title="Hapus">×</button>
        </div>
        <div class="var-axis-body">
            <div class="field">
                <label class="field-label">Nama Variasi <small>(Contoh: Ukuran)</small></label>
                <input type="text" name="variation_name[]" class="form-input var-name" placeholder="Ukuran" onchange="updateOptions(this)">
            </div>
            <div class="field">
                <label class="field-label">Opsi <small>(text only)</small></label>
                <div class="var-options-grid" data-axis-options></div>
                <input type="hidden" name="variation_options[]" class="var-options-hidden" value="">
                <input type="hidden" name="variation_has_images[]" class="var-has-images" value="0">
                <button type="button" class="btn-add-option" onclick="addOption(this)">+ Tambah Opsi</button>
            </div>
        </div>
    `;
    list.appendChild(div);
    addOption(div.querySelector('.btn-add-option'));
    document.getElementById('addAxisBtn').style.display = 'none';
}

function removeAxis(btn) {
    const axis = btn.closest('.var-axis');
    const list = document.getElementById('variationList');
    if (list.children.length <= 1) {
        // Hapus variasi pertama = disable
        document.getElementById('enableVariation').checked = false;
        toggleVariations(false);
        return;
    }
    axis.remove();
    document.getElementById('addAxisBtn').style.display = 'inline-block';
    relabelAxes();
    generateCombinations();
}

function relabelAxes() {
    document.querySelectorAll('.var-axis').forEach((axis, i) => {
        axis.dataset.axis = (i + 1);
        const label = axis.querySelector('.var-axis-label');
        if (label) label.textContent = 'Variasi ' + (i + 1);
        // Update has_images: variasi 1 = bisa gambar, variasi 2+ = text only
        const hasImagesInput = axis.querySelector('.var-has-images');
        if (hasImagesInput) hasImagesInput.value = (i === 0) ? '1' : '0';
    });
}

function addOption(btn) {
    const axis = btn.closest('.var-axis');
    const grid = axis.querySelector('[data-axis-options]');
    const axisIdx = Array.from(document.querySelectorAll('.var-axis')).indexOf(axis);
    const hasImages = axis.querySelector('.var-has-images').value === '1';
    
    const item = document.createElement('div');
    item.className = 'var-option-item';
    item.innerHTML = `
        ${hasImages ? `
            <label class="var-option-img">
                <input type="file" name="variation_option_image[${axisIdx}][]" accept="image/*" onchange="previewVarImg(this)" style="display:none;">
                <span class="var-img-placeholder">🖼️</span>
            </label>
        ` : ''}
        <input type="text" class="form-input var-option-text" placeholder="Opsi" onchange="syncOptions(this)" oninput="syncOptions(this)">
        <button type="button" class="var-option-del" onclick="removeOption(this)" title="Hapus opsi">×</button>
    `;
    grid.appendChild(item);
}

function removeOption(btn) {
    const item = btn.closest('.var-option-item');
    const axis = btn.closest('.var-axis');
    item.remove();
    syncOptions(axis.querySelector('.var-option-text'));
}

function syncOptions(input) {
    // Sync semua option text ke hidden var-options-hidden, dan rename file inputs sesuai option name
    const axis = input.closest('.var-axis');
    const items = axis.querySelectorAll('.var-option-item');
    const opts = [];
    const axisIdx = Array.from(document.querySelectorAll('.var-axis')).indexOf(axis);
    
    items.forEach(item => {
        const text = item.querySelector('.var-option-text').value.trim();
        if (text) {
            opts.push(text);
            // Rename file input to use option name as key
            const fileInput = item.querySelector('input[type="file"]');
            if (fileInput) fileInput.name = `variation_option_image[${axisIdx}][${text}]`;
        }
    });
    
    axis.querySelector('.var-options-hidden').value = opts.join(',');
    
    // FAILSAFE: Pastikan enable_variation checkbox tetep checked
    // (kalau user isi data variasi, otomatis variasi dianggap aktif)
    if (opts.length > 0) {
        const enableVar = document.getElementById('enableVariation');
        if (enableVar && !enableVar.checked) {
            enableVar.checked = true;
        }
    }
    
    generateCombinations();
}

function updateOptions(input) {
    // Trigger generate ketika nama variasi berubah
    // Plus failsafe: auto-check enable_variation kalau ada nama variasi
    if (input.value.trim()) {
        const enableVar = document.getElementById('enableVariation');
        if (enableVar && !enableVar.checked) {
            enableVar.checked = true;
        }
    }
    generateCombinations();
}

function previewVarImg(input) {
    const file = input.files[0];
    if (!file) return;
    
    // PENTING: snapshot nama opsi saat user pilih file
    // Ini bikin upload tetep ke-track meski user belum trigger syncOptions
    const item = input.closest('.var-option-item');
    const axis = input.closest('.var-axis');
    const textInput = item.querySelector('.var-option-text');
    const optName = (textInput?.value || '').trim();
    const axisIdx = Array.from(document.querySelectorAll('.var-axis')).indexOf(axis);
    
    if (optName) {
        input.name = `variation_option_image[${axisIdx}][${optName}]`;
    }
    
    const label = input.closest('.var-option-img');
    const reader = new FileReader();
    reader.onload = (e) => {
        label.innerHTML = `<input type="file" name="${input.name}" accept="image/*" onchange="previewVarImg(this)" style="display:none;"><img src="${e.target.result}" alt="">`;
    };
    reader.readAsDataURL(file);
}

function generateCombinations() {
    const axes = document.querySelectorAll('.var-axis');
    const variations = [];
    
    axes.forEach(axis => {
        const name = axis.querySelector('.var-name').value.trim();
        const opts = axis.querySelector('.var-options-hidden').value.split(',').map(o => o.trim()).filter(Boolean);
        if (name && opts.length > 0) {
            variations.push({ name, opts });
        }
    });
    
    const table = document.getElementById('combinationTable');
    const head = document.getElementById('comboTableHead');
    const body = document.getElementById('combinationBody');
    
    if (variations.length === 0) {
        table.style.display = 'none';
        return;
    }
    
    // Generate headers
    let headerHtml = '<tr>';
    variations.forEach(v => headerHtml += `<th>${v.name}</th>`);
    headerHtml += '<th>Harga</th><th>Stok</th><th>Kode Variasi</th>';
    headerHtml += '</tr>';
    head.innerHTML = headerHtml;
    
    // Cartesian product
    const combine = (arr) => arr.reduce((acc, curr) => acc.flatMap(a => curr.opts.map(c => a ? `${a}|${c}` : c)), ['']);
    const combos = combine(variations).filter(c => c);
    
    // Preserve existing values (from current form + loaded from DB)
    const existing = {};
    body.querySelectorAll('tr').forEach(tr => {
        const combo = tr.querySelector('input[name="vi_combination[]"]')?.value;
        if (combo) {
            existing[combo] = {
                price: tr.querySelector('input[name="vi_price[]"]')?.value || '',
                stock: tr.querySelector('input[name="vi_stock[]"]')?.value || '',
                sku: tr.querySelector('input[name="vi_sku[]"]')?.value || '',
                image: tr.querySelector('input[name="vi_image_existing[]"]')?.value || '',
            };
        }
    });
    
    // Also merge from DB (only on first load)
    if (window.EXISTING_VARIATION_ITEMS && !window._variationsLoaded) {
        window.EXISTING_VARIATION_ITEMS.forEach(vi => {
            if (!existing[vi.combination]) {
                existing[vi.combination] = {
                    price: vi.price,
                    stock: vi.stock,
                    sku: vi.sku || '',
                };
            }
        });
        window._variationsLoaded = true;
    }
    
    body.innerHTML = '';
    combos.forEach(combo => {
<<<<<<< ours
        const ex = existing[combo] || { price: '', stock: '', sku: '' };
        // Format harga ke ribuan — FIX: split di titik dulu biar decimal "25000.00" ga jadi "2500000"
        let rawPrice = '';
        if (ex.price !== '' && ex.price !== null && ex.price !== undefined) {
            const priceStr = String(ex.price);
            // Kalau ada titik decimal (mis "25000.00"), ambil bagian sebelum titik
            const intPart = priceStr.split('.')[0].replace(/[^0-9]/g, '');
            rawPrice = intPart;
        }
        const priceFormatted = rawPrice ? parseInt(rawPrice, 10).toLocaleString('id-ID') : '';
        const parts = combo.split('|');
        let cellsHtml = '';
        parts.forEach(part => {
            cellsHtml += `<td class="combo-label">${part}</td>`;
        });
        const tr = document.createElement('tr');
        tr.innerHTML = `
            ${cellsHtml}
            <td>
                <div class="rp-input"><span>Rp</span><input type="text" name="vi_price[]" class="form-input form-input-sm" value="${priceFormatted}" placeholder="Input"></div>
                <input type="hidden" name="vi_combination[]" value="${combo}">
            </td>
            <td><input type="number" name="vi_stock[]" class="form-input form-input-sm" min="0" value="${ex.stock}" placeholder="Stok"></td>
            <td><input type="text" name="vi_sku[]" class="form-input form-input-sm" value="${ex.sku}" placeholder="Mohon masukkan"></td>
=======
        const ex = existing[combo] || { price: '', stock: '', sku: '', image: '' };
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${combo.replace(/\|/g, ' / ')}<input type="hidden" name="vi_combination[]" value="${combo}"></td>
            <td>
                ${ex.image ? `<img src="${window.location.origin}/${ex.image}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:6px;margin-bottom:6px;">` : ''}
                <input type="hidden" name="vi_image_existing[]" value="${ex.image}">
                <input type="file" name="vi_image[]" class="form-input form-input-sm" accept="image/jpeg,image/png,image/webp">
            </td>
            <td><input type="number" name="vi_price[]" class="form-input form-input-sm" min="0" value="${ex.price}" placeholder="0"></td>
            <td><input type="number" name="vi_stock[]" class="form-input form-input-sm" min="0" value="${ex.stock}" placeholder="0"></td>
            <td><input type="text" name="vi_sku[]" class="form-input form-input-sm" value="${ex.sku}" placeholder="SKU"></td>
>>>>>>> theirs
        `;
        body.appendChild(tr);
    });
    
    table.style.display = 'block';
    updatePricingState();
}

function applyToAll() {
    const price = document.getElementById('bulkPrice').value.replace(/[^0-9]/g, '');
    const stock = document.getElementById('bulkStock').value;
    const sku = document.getElementById('bulkSku').value;
    
    document.querySelectorAll('#combinationBody tr').forEach(tr => {
        if (price !== '') {
            const pIn = tr.querySelector('input[name="vi_price[]"]');
            if (pIn) pIn.value = parseInt(price, 10).toLocaleString('id-ID');
        }
        if (stock !== '') {
            const sIn = tr.querySelector('input[name="vi_stock[]"]');
            if (sIn) sIn.value = stock;
        }
        if (sku !== '') {
            const kIn = tr.querySelector('input[name="vi_sku[]"]');
            if (kIn) kIn.value = sku;
        }
    });
    
    // Trigger pricing state update
    updatePricingState();
}

// Auto-load existing data saat halaman dibuka
document.addEventListener('DOMContentLoaded', () => {
    // Convert hidden var-options-hidden ke option items (untuk edit existing produk)
    document.querySelectorAll('.var-axis').forEach(axis => {
        const grid = axis.querySelector('[data-axis-options]');
        if (grid.children.length === 0) {
            const optsHidden = axis.querySelector('.var-options-hidden').value;
            const opts = optsHidden.split(',').map(s => s.trim()).filter(Boolean);
            const hasImages = axis.querySelector('.var-has-images').value === '1';
            const axisIdx = Array.from(document.querySelectorAll('.var-axis')).indexOf(axis);
            
            opts.forEach(opt => {
                const item = document.createElement('div');
                item.className = 'var-option-item';
                item.innerHTML = `
                    ${hasImages ? `
                        <label class="var-option-img">
                            <input type="file" name="variation_option_image[${axisIdx}][${opt}]" accept="image/*" onchange="previewVarImg(this)" style="display:none;">
                            <span class="var-img-placeholder">🖼️</span>
                        </label>
                    ` : ''}
                    <input type="text" class="form-input var-option-text" value="${opt}" placeholder="Opsi" onchange="syncOptions(this)" oninput="syncOptions(this)">
                    <button type="button" class="var-option-del" onclick="removeOption(this)" title="Hapus opsi">×</button>
                `;
                grid.appendChild(item);
            });
        }
    });
    generateCombinations();
});

// ===== PRE-PUBLISH VALIDATION =====
function preparePublish() {
    const errors = [];
    
    const name = (document.getElementById('inputName')?.value || '').trim();
    if (name.length < 5) errors.push('Nama produk min 5 karakter');
    
    const desc = (document.getElementById('inputDescription')?.value || '').trim();
    if (desc.length < 20) errors.push('Deskripsi min 20 karakter');
    
    const catSelect = document.querySelector('select[name="category_id"]');
    if (catSelect && !catSelect.value) errors.push('Pilih kategori');
    
    // Cek harga: detect variasi by DATA (failsafe kalo checkbox enable_variation ke-uncheck)
    let hasVariation = false;
    const varNames = document.querySelectorAll('input[name="variation_name[]"]');
    varNames.forEach((vn, i) => {
        const name = vn.value.trim();
        const optsInput = document.querySelectorAll('input[name="variation_options[]"]')[i];
        const opts = optsInput ? optsInput.value.trim() : '';
        if (name && opts) hasVariation = true;
    });
    
    if (!hasVariation) {
        const price = (document.getElementById('inputPrice')?.value || '').replace(/[^0-9]/g, '');
        if (!price || parseInt(price) <= 0) errors.push('Harga produk wajib diisi > 0');
    } else {
        // Variasi aktif, cek minimal 1 varian punya harga
        const varPrices = document.querySelectorAll('input[name="vi_price[]"]');
        let hasPrice = false;
        varPrices.forEach(inp => {
            const v = inp.value.replace(/[^0-9]/g, '');
            if (v && parseInt(v) > 0) hasPrice = true;
        });
        if (!hasPrice) errors.push('Minimal 1 varian harus punya harga > 0');
        
        // Pastikan enable_variation tetep checked
        const enableVar = document.getElementById('enableVariation');
        if (enableVar && !enableVar.checked) enableVar.checked = true;
    }
    
    // Berat (untuk fisik)
    const typeSelect = document.querySelector('select[name="type"]');
    if (typeSelect && typeSelect.value === 'fisik') {
        const weight = document.querySelector('input[name="weight"]')?.value;
        if (!weight || parseInt(weight) <= 0) errors.push('Berat produk wajib diisi (produk fisik)');
    }
    
    if (errors.length > 0) {
        alert('Produk belum bisa di-publish:\n\n• ' + errors.join('\n• '));
        return false;
    }
    
    document.getElementById('formAction').value = 'publish';
    return true;
}

// ===== FORMAT RIBUAN (25000 → 25.000) =====
function formatThousand(input, hiddenId) {
    // Hapus selain digit
    let raw = input.value.replace(/[^0-9]/g, '');
    if (raw === '') {
        input.value = '';
        if (hiddenId) document.getElementById(hiddenId).value = '';
        return;
    }
    // Format dengan titik ribuan
    const formatted = parseInt(raw, 10).toLocaleString('id-ID');
    input.value = formatted;
    if (hiddenId) document.getElementById(hiddenId).value = raw;
}

// ===== CEK APAKAH VARIASI PUNYA HARGA SAMA =====
// Return: 'no_variation' | 'same_price' | 'mixed_price'
function getVariationPricingMode() {
    const enableVar = document.getElementById('enableVariation');
    if (!enableVar || !enableVar.checked) return 'no_variation';
    
    const priceInputs = document.querySelectorAll('#combinationBody input[name="vi_price[]"]');
    if (priceInputs.length === 0) return 'no_variation';
    
    const prices = Array.from(priceInputs).map(i => i.value.trim()).filter(v => v !== '');
    if (prices.length === 0) return 'no_variation'; // belum ada harga diisi
    if (prices.length < priceInputs.length) return 'mixed_price'; // sebagian kosong = mixed
    
    // Cek semua sama
    const unique = [...new Set(prices)];
    return unique.length === 1 ? 'same_price' : 'mixed_price';
}

// ===== UPDATE STATE HARGA & TIER BERDASAR VARIASI =====
function updatePricingState() {
    const mode = getVariationPricingMode();
    const baseInput = document.getElementById('inputPriceDisplay');
    const baseHidden = document.getElementById('inputPrice');
    const baseHelp = document.getElementById('basePriceHelp');
    const stockInput = document.getElementById('inputStock');
    const stockHelp = document.getElementById('baseStockHelp');
    const tierEnable = document.getElementById('enableTier');
    const tierWarning = document.getElementById('tierVariationWarning');
    const tierContainer = document.getElementById('tierContainer');
    const tierToggleLabel = document.getElementById('tierToggleLabel');
    const tierSection = document.getElementById('tierSection');
    
    if (mode === 'no_variation') {
        // Variasi off → harga aktif normal, tier section visible
        if (baseInput) {
            baseInput.disabled = false;
            baseInput.style.background = '';
        }
        if (stockInput) {
            stockInput.disabled = false;
            stockInput.style.background = '';
        }
        if (baseHelp) baseHelp.style.display = 'none';
        if (stockHelp) stockHelp.style.display = 'none';
        if (tierWarning) tierWarning.style.display = 'none';
        if (tierSection) tierSection.style.display = '';
        if (tierEnable) {
            tierEnable.disabled = false;
            if (tierToggleLabel) tierToggleLabel.style.opacity = '1';
        }
    } else {
        // Variasi aktif → harga & stok base disable, tier section HIDE
        if (baseInput) {
            baseInput.disabled = true;
            baseInput.style.background = '#f5f5f5';
            baseInput.value = '';
            if (baseHidden) baseHidden.value = '';
        }
        if (stockInput) {
            stockInput.disabled = true;
            stockInput.style.background = '#f5f5f5';
            stockInput.value = '';
        }
        if (baseHelp) baseHelp.style.display = 'block';
        if (stockHelp) stockHelp.style.display = 'block';
        
        // Hide tier section sepenuhnya karena variasi & tier ga compatible
        if (tierSection) tierSection.style.display = 'none';
        // Pastikan tier checkbox uncheck (biar ga ke-submit)
        if (tierEnable) tierEnable.checked = false;
        if (tierContainer) tierContainer.style.display = 'none';
    }
}

// Listen ke perubahan harga di tabel variasi
document.addEventListener('input', function(e) {
    if (e.target.matches('input[name="vi_price[]"]') || e.target.matches('#bulkPrice')) {
        // Auto-format ribuan untuk harga variasi juga
        if (e.target.matches('input[name="vi_price[]"]')) {
            const raw = e.target.value.replace(/[^0-9]/g, '');
            if (raw !== '') {
                const formatted = parseInt(raw, 10).toLocaleString('id-ID');
                if (formatted !== e.target.value) {
                    const pos = e.target.selectionStart;
                    e.target.value = formatted;
                    try { e.target.setSelectionRange(pos, pos); } catch(_) {}
                }
            }
        }
        updatePricingState();
    }
    
    // Format ribuan untuk tier price inputs
    if (e.target.matches('.tier-price-input')) {
        const raw = e.target.value.replace(/[^0-9]/g, '');
        if (raw !== '') {
            const formatted = parseInt(raw, 10).toLocaleString('id-ID');
            if (formatted !== e.target.value) {
                const pos = e.target.selectionStart;
                e.target.value = formatted;
                try { e.target.setSelectionRange(pos, pos); } catch(_) {}
            }
        }
    }
});

// Listen ke toggle variasi
document.addEventListener('change', function(e) {
    if (e.target.id === 'enableVariation') {
        updatePricingState();
    }
});

// Initial call
setTimeout(updatePricingState, 200);

// Init format ribuan untuk input harga utama
const inputPriceDisplay = document.getElementById('inputPriceDisplay');
if (inputPriceDisplay) {
    // Format ulang value awal
    const v = inputPriceDisplay.value.replace(/[^0-9]/g, '');
    if (v) inputPriceDisplay.value = parseInt(v, 10).toLocaleString('id-ID');
}

// ===== PRE-SUBMIT: Force sync semua file input names =====
// Memastikan file uploads ke-receive dengan nama option yang benar di server
const productForm = document.getElementById('productForm');
if (productForm) {
    productForm.addEventListener('submit', function(e) {
        // Force sync semua axis sebelum kirim
        document.querySelectorAll('.var-axis').forEach((axis, axisIdx) => {
            // Update var-options-hidden dari text inputs
            const items = axis.querySelectorAll('.var-option-item');
            const opts = [];
            items.forEach(item => {
                const text = (item.querySelector('.var-option-text')?.value || '').trim();
                if (text) {
                    opts.push(text);
                    // Rename file input name ke option name terkini
                    const fileInput = item.querySelector('input[type="file"]');
                    if (fileInput) {
                        fileInput.name = `variation_option_image[${axisIdx}][${text}]`;
                    }
                }
            });
            const hidden = axis.querySelector('.var-options-hidden');
            if (hidden) hidden.value = opts.join(',');
        });
    });
}

// ===== TIER PRICING (HARGA GROSIR) =====
function toggleTierPricing(enabled) {
    const c = document.getElementById('tierContainer');
    if (c) c.style.display = enabled ? 'block' : 'none';
}

function renumberTiers() {
    document.querySelectorAll('#tierList .tier-row').forEach((row, i) => {
        const label = row.querySelector('.tier-label');
        if (label) label.textContent = 'Harga Grosir ' + (i + 1);
    });
}

function addTier() {
    const list = document.getElementById('tierList');
    const count = list.children.length;
    if (count >= 5) {
        alert('Maksimal 5 tier harga grosir');
        return;
    }
    const row = document.createElement('tr');
    row.className = 'tier-row';
    row.innerHTML = `
        <td class="tier-label">Harga Grosir ${count + 1}</td>
        <td><input type="number" name="tier_min[]" class="form-input" min="1" placeholder="Min."></td>
        <td><input type="number" name="tier_max[]" class="form-input" min="1" placeholder="Maks."></td>
        <td>
            <div class="rp-input">
                <span>Rp</span>
                <input type="text" name="tier_price[]" class="form-input tier-price-input" placeholder="Harga Satuan">
            </div>
        </td>
        <td><button type="button" class="tier-del" onclick="removeTier(this)" title="Hapus">🗑️</button></td>
    `;
    list.appendChild(row);
    
    if (list.children.length >= 5) {
        document.getElementById('addTierBtn').style.display = 'none';
    }
}

function removeTier(btn) {
    const row = btn.closest('.tier-row');
    const list = document.getElementById('tierList');
    if (list.children.length <= 1) {
        alert('Minimal harus ada 1 tier');
        return;
    }
    row.remove();
    renumberTiers();
    document.getElementById('addTierBtn').style.display = 'inline-block';
}
