-- ============================================
-- OIS GRAFIKA - DATABASE UPGRADE v3
-- Untuk fitur Checkout V2 + Address Book + Voucher
-- 
-- CARA IMPORT:
-- 1. Buka phpMyAdmin > pilih database ois_grafika
-- 2. Tab SQL > paste isi file ini > Go
-- ============================================

-- 1. ADDRESSES (alamat pengiriman user)
CREATE TABLE IF NOT EXISTS addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recipient_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address_line TEXT NOT NULL COMMENT 'Jalan, RT/RW, dll',
    village VARCHAR(100) DEFAULT NULL COMMENT 'Desa/Kelurahan',
    district VARCHAR(100) NOT NULL COMMENT 'Kecamatan',
    city VARCHAR(100) NOT NULL COMMENT 'Kabupaten/Kota',
    province VARCHAR(100) NOT NULL,
    postal_code VARCHAR(10) NOT NULL,
    label VARCHAR(50) DEFAULT 'Rumah' COMMENT 'Label: Rumah, Kantor, dll',
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_default (user_id, is_default)
);

-- 2. SHIPPING METHODS (4 jasa kirim)
CREATE TABLE IF NOT EXISTS shipping_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('regular', 'cargo') DEFAULT 'regular',
    base_cost DECIMAL(10,2) DEFAULT 0,
    cost_per_kg DECIMAL(10,2) DEFAULT 0,
    min_weight_kg INT DEFAULT 1,
    estimate_days_min INT DEFAULT 2,
    estimate_days_max INT DEFAULT 4,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0
);

-- Seed shipping methods
INSERT INTO shipping_methods (code, name, type, base_cost, cost_per_kg, min_weight_kg, estimate_days_min, estimate_days_max, sort_order) VALUES
('jnt', 'J&T Reguler', 'regular', 10000, 10000, 1, 2, 4, 1),
('jne', 'JNE Reguler', 'regular', 12000, 12000, 1, 2, 4, 2),
('jnt_cargo', 'J&T Cargo', 'cargo', 25000, 5000, 5, 4, 7, 3),
('jne_cargo', 'JNE Cargo', 'cargo', 30000, 6000, 5, 4, 7, 4);

-- 3. VOUCHERS
CREATE TABLE IF NOT EXISTS vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    type ENUM('shipping_discount', 'product_discount', 'cashback') DEFAULT 'product_discount',
    discount_type ENUM('fixed', 'percentage') DEFAULT 'fixed',
    discount_value DECIMAL(12,2) NOT NULL,
    max_discount DECIMAL(12,2) DEFAULT NULL,
    min_purchase DECIMAL(12,2) DEFAULT 0,
    quota INT DEFAULT NULL COMMENT 'NULL = unlimited',
    used_count INT DEFAULT 0,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    description TEXT,
    terms TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed dummy vouchers
INSERT INTO vouchers (code, name, type, discount_type, discount_value, max_discount, min_purchase, quota, start_date, end_date, description) VALUES
('GRATISONGKIR', 'Gratis Ongkir', 'shipping_discount', 'fixed', 15000, NULL, 0, 100, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'Gratis ongkir untuk semua pengiriman'),
('DISKON20', 'Diskon 20% s.d. Rp4.000', 'product_discount', 'percentage', 20, 4000, 10000, 50, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'Diskon 20% maks Rp4.000 min belanja Rp10rb'),
('HEMAT5RB', 'Hemat Rp5.000', 'product_discount', 'fixed', 5000, NULL, 50000, 100, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'Potongan langsung Rp5.000 min belanja Rp50rb');

-- 4. VOUCHER USAGES (track pemakaian)
CREATE TABLE IF NOT EXISTS voucher_usages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT DEFAULT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. SHIPMENT TRACKINGS (history status pengiriman)
CREATE TABLE IF NOT EXISTS shipment_trackings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    description TEXT,
    tracked_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_time (order_id, tracked_at)
);

-- 6. UPGRADE TABEL ORDERS (tambah kolom baru)
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS address_id INT DEFAULT NULL AFTER user_id,
    ADD COLUMN IF NOT EXISTS recipient_name VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS recipient_phone VARCHAR(20) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS shipping_courier VARCHAR(20) DEFAULT NULL COMMENT 'jnt, jne, jnt_cargo, jne_cargo',
    ADD COLUMN IF NOT EXISTS shipping_service VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS shipping_discount DECIMAL(12,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS estimated_arrival_start DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS estimated_arrival_end DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS service_fee DECIMAL(12,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS voucher_id INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS voucher_discount DECIMAL(12,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS buyer_note TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS seller_note TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS is_dropshipper TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS dropshipper_name VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS dropshipper_phone VARCHAR(20) DEFAULT NULL;
