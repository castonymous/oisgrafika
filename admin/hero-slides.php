<?php
$pageTitle = 'Kelola Hero Slideshow';
require_once __DIR__ . '/../includes/admin-helpers.php';
requireAdmin();

// Check tabel ada
try {
    $pdo->query("SELECT 1 FROM hero_slides LIMIT 1");
} catch (Exception $e) {
    die('<div style="padding:40px;text-align:center;font-family:sans-serif;">❌ Tabel hero_slides belum ada. Import dulu database-upgrade-v7.sql via phpMyAdmin.</div>');
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        redirect($_SERVER['REQUEST_URI'], 'Token tidak valid', 'error');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $title = clean(trim($_POST['title'] ?? ''));
        $subtitle = clean(trim($_POST['subtitle'] ?? ''));
        $linkUrl = clean(trim($_POST['link_url'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        
        // Upload gambar
        if (empty($_FILES['image']['name']) || $_FILES['image']['error'] !== 0) {
            redirect($_SERVER['REQUEST_URI'], 'Upload gambar wajib', 'error');
        }
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            redirect($_SERVER['REQUEST_URI'], 'Format harus JPG/PNG/WEBP', 'error');
        }
        $uploadDir = __DIR__ . '/../uploads/slides';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $newName = 'slide_' . time() . '_' . rand(100,999) . '.' . $ext;
        $dest = $uploadDir . '/' . $newName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            $pdo->prepare("INSERT INTO hero_slides (image_path, title, subtitle, link_url, sort_order) VALUES (?, ?, ?, ?, ?)")
                ->execute(['uploads/slides/' . $newName, $title, $subtitle, $linkUrl, $sortOrder]);
            redirect(SITE_URL . '/admin/hero-slides', '✓ Slide ditambahkan');
        } else {
            redirect($_SERVER['REQUEST_URI'], 'Gagal upload gambar', 'error');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $s = $pdo->prepare("SELECT image_path FROM hero_slides WHERE id = ?");
        $s->execute([$id]);
        $slide = $s->fetch();
        if ($slide) {
            @unlink(__DIR__ . '/../' . $slide['image_path']);
            $pdo->prepare("DELETE FROM hero_slides WHERE id = ?")->execute([$id]);
        }
        redirect(SITE_URL . '/admin/hero-slides', '✓ Slide dihapus');
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE hero_slides SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        redirect(SITE_URL . '/admin/hero-slides', '✓ Status diubah');
    }
}

$slides = $pdo->query("SELECT * FROM hero_slides ORDER BY sort_order ASC, id DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:1000px;margin:0 auto;padding:20px;">
    <h1 style="font-size:22px;margin-bottom:6px;">🎬 Kelola Hero Slideshow</h1>
    <p style="color:var(--text-muted);margin-bottom:20px;">Slideshow yang muncul di homepage. Rekomendasi rasio gambar 16:10 (1280×800px).</p>
    
    <!-- Form Add -->
    <div style="background:white;border:1px solid var(--border);border-radius:8px;padding:20px;margin-bottom:24px;">
        <h3 style="margin:0 0 14px;font-size:16px;">➕ Tambah Slide Baru</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="add">
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:12px;">
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Gambar (1280×800px rekomen)</label>
                    <input type="file" name="image" accept="image/*" required style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Urutan (kecil = duluan)</label>
                    <input type="number" name="sort_order" value="0" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;">
                </div>
            </div>
            
            <div style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Judul (opsional)</label>
                <input type="text" name="title" maxlength="255" placeholder="Mis: Diskon Lebaran 30%" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;">
            </div>
            
            <div style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Subtitle (opsional)</label>
                <input type="text" name="subtitle" maxlength="500" placeholder="Mis: Hingga akhir bulan untuk semua produk" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;">
            </div>
            
            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Link URL (opsional)</label>
                <input type="text" name="link_url" placeholder="Mis: /produk/lanyard-ut" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;">
            </div>
            
            <button type="submit" style="background:var(--primary);color:white;border:none;padding:10px 20px;border-radius:6px;font-weight:600;cursor:pointer;">Tambah Slide</button>
        </form>
    </div>
    
    <!-- List Slides -->
    <h3 style="margin:0 0 14px;font-size:16px;">📋 Slides Aktif (<?= count($slides) ?>)</h3>
    <?php if (empty($slides)): ?>
        <div style="text-align:center;padding:40px;background:white;border:1px solid var(--border);border-radius:8px;color:var(--text-muted);">Belum ada slide. Tambah slide pertama di atas ☝️</div>
    <?php else: ?>
        <div style="display:grid;gap:12px;">
            <?php foreach ($slides as $s): ?>
                <div style="background:white;border:1px solid var(--border);border-radius:8px;padding:14px;display:flex;gap:14px;align-items:center;<?= !$s['is_active'] ? 'opacity:0.5;' : '' ?>">
                    <img src="<?= SITE_URL ?>/<?= clean($s['image_path']) ?>" style="width:120px;height:75px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:14px;"><?= clean($s['title'] ?: '(no title)') ?></div>
                        <div style="font-size:12px;color:var(--text-muted);"><?= clean($s['subtitle']) ?: '<em>(no subtitle)</em>' ?></div>
                        <?php if ($s['link_url']): ?>
                            <div style="font-size:11px;color:var(--primary);margin-top:2px;">🔗 <?= clean($s['link_url']) ?></div>
                        <?php endif; ?>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Urutan: <?= $s['sort_order'] ?> • <?= $s['is_active'] ? '✓ Aktif' : '🚫 Non-aktif' ?></div>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" style="background:none;border:1px solid var(--border);padding:6px 10px;border-radius:6px;font-size:11px;cursor:pointer;"><?= $s['is_active'] ? '🚫 Off' : '✓ On' ?></button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus slide ini?')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" style="background:#fde8e8;color:#b91c1c;border:1px solid #fca5a5;padding:6px 10px;border-radius:6px;font-size:11px;cursor:pointer;">🗑️</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
