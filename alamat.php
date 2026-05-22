<?php
$pageTitle = 'Alamat Saya';
require_once __DIR__ . '/includes/shipping-helpers.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        redirect(SITE_URL . '/alamat.php', 'Token tidak valid', 'error');
    }
    
    if (isset($_POST['save'])) {
        $data = [
            'recipient_name' => clean($_POST['recipient_name']),
            'phone' => clean($_POST['phone']),
            'address_line' => clean($_POST['address_line']),
            'village' => clean($_POST['village'] ?? ''),
            'district' => clean($_POST['district']),
            'city' => clean($_POST['city']),
            'province' => clean($_POST['province']),
            'postal_code' => clean($_POST['postal_code']),
            'label' => clean($_POST['label'] ?? 'Rumah'),
            'is_default' => isset($_POST['is_default']) ? 1 : 0,
        ];
        
        // Validation
        if (empty($data['recipient_name']) || empty($data['phone']) || empty($data['address_line']) || empty($data['district']) || empty($data['city']) || empty($data['province']) || empty($data['postal_code'])) {
            redirect($_SERVER['REQUEST_URI'], 'Semua field wajib diisi (kecuali Desa & Label)', 'error');
        }
        
        // Jika set as default, unset default lainnya
        if ($data['is_default']) {
            $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
        }
        
        if ($id > 0) {
            $sql = "UPDATE addresses SET recipient_name=?, phone=?, address_line=?, village=?, district=?, city=?, province=?, postal_code=?, label=?, is_default=? WHERE id=? AND user_id=?";
            $params = array_values($data);
            $params[] = $id;
            $params[] = $_SESSION['user_id'];
            $pdo->prepare($sql)->execute($params);
            redirect(SITE_URL . '/alamat.php', 'Alamat diperbarui');
        } else {
            $sql = "INSERT INTO addresses (user_id, recipient_name, phone, address_line, village, district, city, province, postal_code, label, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$_SESSION['user_id'], ...array_values($data)];
            $pdo->prepare($sql)->execute($params);
            redirect(SITE_URL . '/alamat.php', 'Alamat ditambahkan');
        }
    } elseif (isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?")->execute([(int)$_POST['id'], $_SESSION['user_id']]);
        redirect(SITE_URL . '/alamat.php', 'Alamat dihapus');
    } elseif (isset($_POST['set_default'])) {
        $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
        $pdo->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([(int)$_POST['id'], $_SESSION['user_id']]);
        redirect(SITE_URL . '/alamat.php', 'Alamat utama diubah');
    }
}

// Load address kalo edit
$address = ['id' => 0, 'recipient_name' => '', 'phone' => '', 'address_line' => '', 'village' => '', 'district' => '', 'city' => '', 'province' => '', 'postal_code' => '', 'label' => 'Rumah', 'is_default' => 0];
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    $address = $stmt->fetch();
    if (!$address) redirect(SITE_URL . '/alamat.php', 'Alamat tidak ditemukan', 'error');
}

// Load semua alamat
$addresses = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$addresses->execute([$_SESSION['user_id']]);
$addresses = $addresses->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<style>
.alamat-page { max-width: 800px; margin: 16px auto; padding: 0 16px; }
.alamat-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.alamat-card { background: white; border-radius: var(--radius-lg); border: 1px solid var(--border); padding: 16px; margin-bottom: 12px; }
.alamat-card.default { border-color: var(--primary); }
.alamat-card-head { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.alamat-name { font-weight: 700; font-size: 15px; }
.alamat-label { font-size: 10px; background: var(--primary-light); color: var(--primary); padding: 2px 8px; border-radius: 10px; font-weight: 600; }
.alamat-default-badge { font-size: 10px; background: #d1f4dd; color: #1d7a4c; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
.alamat-phone { color: var(--text-light); font-size: 13px; margin-bottom: 4px; }
.alamat-text { color: var(--text-light); font-size: 13px; line-height: 1.5; margin-bottom: 12px; }
.alamat-actions { display: flex; gap: 6px; flex-wrap: wrap; }
@media (max-width: 768px) {
    .alamat-page { padding: 0 12px; }
}
</style>

<div class="alamat-page">
    <?php if ($action === 'add' || $action === 'edit'): ?>
        <div class="alamat-head">
            <h1 class="dash-title"><?= $action === 'edit' ? 'Edit Alamat' : 'Tambah Alamat Baru' ?></h1>
        </div>
        
        <div class="alamat-card">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                
                <div class="form-group">
                    <label class="form-label">Label Alamat</label>
                    <select name="label" class="form-select">
                        <option value="Rumah" <?= $address['label'] === 'Rumah' ? 'selected' : '' ?>>🏠 Rumah</option>
                        <option value="Kantor" <?= $address['label'] === 'Kantor' ? 'selected' : '' ?>>🏢 Kantor</option>
                        <option value="Apartemen" <?= $address['label'] === 'Apartemen' ? 'selected' : '' ?>>🏬 Apartemen</option>
                        <option value="Lainnya" <?= $address['label'] === 'Lainnya' ? 'selected' : '' ?>>📍 Lainnya</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nama Penerima <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="recipient_name" class="form-input" required value="<?= clean($address['recipient_name']) ?>" placeholder="Nama lengkap">
                </div>
                
                <div class="form-group">
                    <label class="form-label">No. HP <span style="color:var(--danger)">*</span></label>
                    <input type="tel" name="phone" class="form-input" required value="<?= clean($address['phone']) ?>" placeholder="08xxxxxxxxxx">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Alamat Lengkap <span style="color:var(--danger)">*</span></label>
                    <textarea name="address_line" class="form-textarea" required placeholder="Jalan, RT/RW, dll"><?= clean($address['address_line']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Desa/Kelurahan</label>
                    <input type="text" name="village" class="form-input" value="<?= clean($address['village']) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Kecamatan <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="district" class="form-input" required value="<?= clean($address['district']) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Kabupaten/Kota <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="city" class="form-input" required value="<?= clean($address['city']) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Provinsi <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="province" class="form-input" required value="<?= clean($address['province']) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Kode Pos <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="postal_code" class="form-input" required value="<?= clean($address['postal_code']) ?>" maxlength="10">
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 14px;">
                        <input type="checkbox" name="is_default" value="1" <?= $address['is_default'] ? 'checked' : '' ?>>
                        Jadikan alamat utama
                    </label>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="save" class="btn btn-primary-solid">💾 Simpan</button>
                    <a href="<?= SITE_URL ?>/alamat.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
        
    <?php else: ?>
        <div class="alamat-head">
            <h1 class="dash-title">Alamat Saya</h1>
            <a href="?action=add" class="btn btn-primary-solid">+ Tambah Alamat</a>
        </div>
        
        <?php if (empty($addresses)): ?>
            <div class="alamat-card" style="text-align: center; padding: 40px 20px;">
                <div style="font-size: 48px; margin-bottom: 12px;">📍</div>
                <h3>Belum ada alamat tersimpan</h3>
                <p style="color: var(--text-muted); margin: 8px 0 16px;">Tambah alamat untuk mempercepat checkout</p>
                <a href="?action=add" class="btn btn-primary-solid">+ Tambah Alamat Pertama</a>
            </div>
        <?php else: ?>
            <?php foreach ($addresses as $a): ?>
                <div class="alamat-card <?= $a['is_default'] ? 'default' : '' ?>">
                    <div class="alamat-card-head">
                        <span class="alamat-name"><?= clean($a['recipient_name']) ?></span>
                        <span class="alamat-label"><?= clean($a['label']) ?></span>
                        <?php if ($a['is_default']): ?>
                            <span class="alamat-default-badge">✓ Utama</span>
                        <?php endif; ?>
                    </div>
                    <div class="alamat-phone">📱 <?= clean($a['phone']) ?></div>
                    <div class="alamat-text">📍 <?= clean(formatAddressShort($a)) ?>, <?= clean($a['province']) ?> <?= clean($a['postal_code']) ?></div>
                    
                    <div class="alamat-actions">
                        <a href="?action=edit&id=<?= $a['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <?php if (!$a['is_default']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <button type="submit" name="set_default" class="btn btn-sm btn-outline">Jadikan Utama</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <button type="submit" name="delete" class="btn btn-sm btn-danger" data-confirm="Hapus alamat ini?">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
