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

// ===== VARIATIONS =====
function toggleVariations(enabled) {
    document.getElementById('variationContainer').style.display = enabled ? 'block' : 'none';
    if (enabled && document.querySelectorAll('.variation-row').length === 0) {
        addVariation();
    }
}

function addVariation() {
    const list = document.getElementById('variationList');
    const count = list.children.length;
    if (count >= 2) {
        alert('Maksimal 2 variasi');
        return;
    }
    const row = document.createElement('div');
    row.className = 'variation-row';
    row.innerHTML = `
        <button type="button" class="variation-del" onclick="removeVariation(this)">×</button>
        <div class="field">
            <label class="field-label">Nama Variasi (Contoh: ${count === 0 ? 'Warna' : 'Ukuran'})</label>
            <input type="text" name="variation_name[]" class="form-input var-name" placeholder="${count === 0 ? 'Warna' : 'Ukuran'}" onchange="generateCombinations()">
        </div>
        <div class="field">
            <label class="field-label">Opsi <small>(pisahkan dengan koma)</small></label>
            <input type="text" name="variation_options[]" class="form-input var-options" placeholder="${count === 0 ? 'Merah, Biru, Hijau' : 'S, M, L, XL'}" onchange="generateCombinations()">
        </div>
    `;
    list.appendChild(row);
}

function removeVariation(btn) {
    btn.closest('.variation-row').remove();
    generateCombinations();
}

function generateCombinations() {
    const rows = document.querySelectorAll('.variation-row');
    const variations = [];
    
    rows.forEach(row => {
        const name = row.querySelector('.var-name').value.trim();
        const optsRaw = row.querySelector('.var-options').value;
        const opts = optsRaw.split(',').map(o => o.trim()).filter(Boolean);
        if (name && opts.length > 0) {
            variations.push({ name, opts });
        }
    });
    
    const table = document.getElementById('combinationTable');
    const body = document.getElementById('combinationBody');
    
    if (variations.length === 0) {
        table.style.display = 'none';
        return;
    }
    
    // Cartesian product
    const combine = (arr) => arr.reduce((acc, curr) => acc.flatMap(a => curr.opts.map(c => a ? `${a}|${c}` : c)), ['']);
    const combos = combine(variations).filter(c => c);
    
    // Preserve existing values
    const existing = {};
    body.querySelectorAll('tr').forEach(tr => {
        const combo = tr.querySelector('input[name="vi_combination[]"]')?.value;
        if (combo) {
            existing[combo] = {
                price: tr.querySelector('input[name="vi_price[]"]')?.value || 0,
                stock: tr.querySelector('input[name="vi_stock[]"]')?.value || 0,
                sku: tr.querySelector('input[name="vi_sku[]"]')?.value || '',
            };
        }
    });
    
    body.innerHTML = '';
    combos.forEach(combo => {
        const ex = existing[combo] || { price: '', stock: '', sku: '' };
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${combo.replace(/\|/g, ' / ')}<input type="hidden" name="vi_combination[]" value="${combo}"></td>
            <td><input type="number" name="vi_price[]" class="form-input form-input-sm" min="0" value="${ex.price}" placeholder="0"></td>
            <td><input type="number" name="vi_stock[]" class="form-input form-input-sm" min="0" value="${ex.stock}" placeholder="0"></td>
            <td><input type="text" name="vi_sku[]" class="form-input form-input-sm" value="${ex.sku}" placeholder="SKU"></td>
        `;
        body.appendChild(tr);
    });
    
    table.style.display = combos.length > 0 ? 'block' : 'none';
}

// Init
updateCompleteness();
