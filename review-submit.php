<?php
$pageTitle = 'Beri Penilaian';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/review-helpers.php';
requireLogin();

$orderCode = $_GET['order'] ?? '';
$itemId = (int)($_GET['item'] ?? 0);

// Get order + item
$stmt = $pdo->prepare("SELECT o.*, oi.id as item_id, oi.product_id, oi.product_name, oi.variant_name, p.image as product_image, p.slug as product_slug FROM orders o JOIN order_items oi ON oi.order_id = o.id LEFT JOIN products p ON oi.product_id = p.id WHERE o.order_code = ? AND o.user_id = ? AND oi.id = ?");
$stmt->execute([$orderCode, $_SESSION['user_id'], $itemId]);
$data = $stmt->fetch();

if (!$data) {
    redirect(SITE_URL . '/dashboard?tab=orders', 'Pesanan tidak ditemukan', 'error');
}

// Cek status order - hanya bisa review kalau selesai
if (!in_array($data['order_status'], ['selesai', 'completed', 'delivered'])) {
    redirect(SITE_URL . '/order/' . $orderCode, 'Pesanan harus selesai untuk diberi penilaian', 'warning');
}

// Cek apakah udah pernah review
if (hasReviewed($data['id'], $data['product_id'], $_SESSION['user_id'], $pdo)) {
    redirect(SITE_URL . '/dashboard?tab=reviews', 'Kamu sudah memberi penilaian untuk produk ini', 'warning');
}

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        redirect($_SERVER['REQUEST_URI'], 'Token tidak valid', 'error');
    }
    
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    
    if ($rating < 1 || $rating > 5) {
        redirect($_SERVER['REQUEST_URI'], 'Pilih rating bintang dulu', 'error');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert review
        $insStmt = $pdo->prepare("INSERT INTO product_reviews (order_id, order_item_id, product_id, user_id, rating, comment, variation_label) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insStmt->execute([
            $data['id'],
            $data['item_id'],
            $data['product_id'],
            $_SESSION['user_id'],
            $rating,
            $comment,
            $data['variant_name']
        ]);
        $reviewId = $pdo->lastInsertId();
        
        // Handle upload foto (max 5)
        if (!empty($_FILES['photos']) && !empty($_FILES['photos']['name'])) {
            $uploadDir = __DIR__ . '/uploads/reviews';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $count = 0;
            foreach ($_FILES['photos']['name'] as $idx => $fname) {
                if ($count >= 5) break;
                if (empty($fname) || $_FILES['photos']['error'][$idx] !== 0) continue;
                
                $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;
                
                $newName = 'rev_' . $reviewId . '_' . time() . '_' . $count . '.' . $ext;
                $dest = $uploadDir . '/' . $newName;
                
                if (move_uploaded_file($_FILES['photos']['tmp_name'][$idx], $dest)) {
                    $pdo->prepare("INSERT INTO review_images (review_id, image_path, sort_order) VALUES (?, ?, ?)")
                        ->execute([$reviewId, 'uploads/reviews/' . $newName, $count]);
                    $count++;
                }
            }
        }
        
        // Update rating produk
        updateProductRating($data['product_id'], $pdo);
        
        // Cek apakah semua item di order udah di-review
        $reviewCheck = $pdo->prepare("SELECT COUNT(*) as items, (SELECT COUNT(*) FROM product_reviews WHERE order_id = ? AND user_id = ?) as reviewed FROM order_items WHERE order_id = ?");
        $reviewCheck->execute([$data['id'], $_SESSION['user_id'], $data['id']]);
        $check = $reviewCheck->fetch();
        if ($check && $check['reviewed'] >= $check['items']) {
            $pdo->prepare("UPDATE orders SET is_reviewed = 1 WHERE id = ?")->execute([$data['id']]);
        }
        
        $pdo->commit();
        redirect(SITE_URL . '/order/' . $orderCode, '🎉 Terima kasih atas penilaianmu!');
    } catch (Exception $e) {
        $pdo->rollBack();
        redirect($_SERVER['REQUEST_URI'], 'Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.review-form-container {
    max-width: 600px;
    margin: 20px auto;
    padding: 0 12px;
}
.review-form-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 16px;
}
.product-summary {
    display: flex;
    gap: 12px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 18px;
}
.product-summary-img {
    width: 60px;
    height: 60px;
    border-radius: 6px;
    background: var(--bg-gray);
    overflow: hidden;
    flex-shrink: 0;
}
.product-summary-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.product-summary-name {
    font-size: 14px;
    font-weight: 600;
    line-height: 1.4;
}
.rating-field {
    text-align: center;
    margin: 20px 0;
}
.rating-label {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 12px;
    color: var(--text);
}
.star-input-large {
    font-size: 42px;
    color: #e5e7eb;
    cursor: pointer;
    user-select: none;
    line-height: 1;
}
.star-input-large .star {
    cursor: pointer;
    transition: color 0.15s, transform 0.15s;
    display: inline-block;
}
.star-input-large .star:hover {
    transform: scale(1.1);
}
.star-input-large .star.filled {
    color: #fbbf24;
}
.rating-text {
    margin-top: 8px;
    font-size: 13px;
    color: var(--primary);
    font-weight: 600;
    min-height: 18px;
}
.form-field-label {
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 6px;
    display: block;
}
.review-textarea {
    width: 100%;
    min-height: 100px;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
    resize: vertical;
}
.review-textarea:focus { outline: none; border-color: var(--primary); }

.btn-submit-review {
    width: 100%;
    background: linear-gradient(90deg, #ee4d2d, #d63a1d);
    color: white;
    border: none;
    padding: 14px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 16px;
}
.btn-submit-review:hover { box-shadow: 0 4px 12px rgba(238, 77, 45, 0.3); }
.btn-submit-review:disabled { opacity: 0.5; cursor: not-allowed; }
</style>

<div class="review-form-container">
    <a href="<?= SITE_URL ?>/order/<?= clean($orderCode) ?>" style="display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;color:var(--text-light);text-decoration:none;font-size:13px;">← Kembali ke Pesanan</a>
    
    <form method="POST" action="" enctype="multipart/form-data" id="reviewForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="rating" id="ratingInput" value="0">
        
        <div class="review-form-card">
            <div class="product-summary">
                <div class="product-summary-img">
                    <?php if ($data['product_image']): ?>
                        <img src="<?= SITE_URL ?>/<?= clean($data['product_image']) ?>" alt="">
                    <?php else: ?>
                        <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:24px;">📦</div>
                    <?php endif; ?>
                </div>
                <div style="flex:1;">
                    <div class="product-summary-name"><?= clean($data['product_name']) ?></div>
                    <?php if ($data['variant_name']): ?>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Variasi: <?= clean(str_replace('|', ' / ', $data['variant_name'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="rating-field">
                <div class="rating-label">Bagaimana kualitas produknya?</div>
                <div class="star-input-large" id="starInput">
                    <span class="star" data-val="1">★</span>
                    <span class="star" data-val="2">★</span>
                    <span class="star" data-val="3">★</span>
                    <span class="star" data-val="4">★</span>
                    <span class="star" data-val="5">★</span>
                </div>
                <div class="rating-text" id="ratingText">Pilih rating</div>
            </div>
            
            <div style="margin-bottom:14px;">
                <label class="form-field-label">Komentar (opsional)</label>
                <textarea name="comment" class="review-textarea" placeholder="Ceritakan pengalamanmu menggunakan produk ini..." maxlength="1000"></textarea>
            </div>
            
            <div>
                <label class="form-field-label">Foto Produk (opsional, max 5)</label>
                <div class="review-photo-grid" id="photoGrid">
                    <label class="review-photo-slot" id="addPhotoBtn">
                        <input type="file" name="photos[]" accept="image/*" multiple style="display:none;" id="photoInput" onchange="handlePhotos(this)">
                        <span style="font-size:24px;color:var(--text-muted);">+</span>
                    </label>
                </div>
                <small style="display:block;font-size:11px;color:var(--text-muted);margin-top:6px;">Bisa upload sampai 5 foto. Format: JPG, PNG, WEBP.</small>
            </div>
            
            <button type="submit" class="btn-submit-review" id="submitBtn" disabled>Kirim Penilaian</button>
        </div>
    </form>
</div>

<script>
const ratingLabels = ['', 'Sangat buruk', 'Buruk', 'Biasa', 'Bagus', 'Sangat Bagus!'];
const stars = document.querySelectorAll('#starInput .star');
const ratingInput = document.getElementById('ratingInput');
const ratingText = document.getElementById('ratingText');
const submitBtn = document.getElementById('submitBtn');

stars.forEach(s => {
    s.addEventListener('mouseenter', () => {
        const val = parseInt(s.dataset.val);
        stars.forEach(x => x.classList.toggle('filled', parseInt(x.dataset.val) <= val));
    });
    s.addEventListener('click', () => {
        const val = parseInt(s.dataset.val);
        ratingInput.value = val;
        ratingText.textContent = ratingLabels[val];
        submitBtn.disabled = false;
        stars.forEach(x => x.classList.toggle('filled', parseInt(x.dataset.val) <= val));
    });
});
document.getElementById('starInput').addEventListener('mouseleave', () => {
    const cur = parseInt(ratingInput.value);
    stars.forEach(x => x.classList.toggle('filled', parseInt(x.dataset.val) <= cur));
});

// Handle photo upload
let photoFiles = []; // store File objects
const photoGrid = document.getElementById('photoGrid');
const addBtn = document.getElementById('addPhotoBtn');
const photoInput = document.getElementById('photoInput');

function handlePhotos(input) {
    const files = Array.from(input.files);
    for (const f of files) {
        if (photoFiles.length >= 5) break;
        photoFiles.push(f);
        renderPhotoSlot(f, photoFiles.length - 1);
    }
    // Reset input agar bisa add lagi
    input.value = '';
    if (photoFiles.length >= 5) addBtn.style.display = 'none';
}

function renderPhotoSlot(file, idx) {
    const slot = document.createElement('div');
    slot.className = 'review-photo-slot';
    slot.dataset.idx = idx;
    
    const reader = new FileReader();
    reader.onload = (e) => {
        slot.innerHTML = `<img src="${e.target.result}" alt=""><button type="button" class="remove-photo" onclick="removePhoto(${idx})">×</button>`;
    };
    reader.readAsDataURL(file);
    
    photoGrid.insertBefore(slot, addBtn);
}

function removePhoto(idx) {
    photoFiles.splice(idx, 1);
    // re-render
    photoGrid.querySelectorAll('.review-photo-slot:not(#addPhotoBtn)').forEach(el => el.remove());
    photoFiles.forEach((f, i) => renderPhotoSlot(f, i));
    addBtn.style.display = '';
}

// Submit handler: rebuild FileList dari array
document.getElementById('reviewForm').addEventListener('submit', function(e) {
    if (ratingInput.value === '0') {
        e.preventDefault();
        alert('Pilih rating bintang dulu ya');
        return false;
    }
    
    // Use DataTransfer to set photos input files
    const dt = new DataTransfer();
    photoFiles.forEach(f => dt.items.add(f));
    photoInput.files = dt.files;
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
