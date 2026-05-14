-- ============================================
-- OIS GRAFIKA - UPGRADE DATABASE
-- Untuk fitur admin produk Shopee-style
-- 
-- CARA IMPORT:
-- 1. Buka phpMyAdmin > pilih database ois_grafika lu
-- 2. Tab SQL > paste isi file ini > Go
-- ============================================

-- 1. Tambah kolom baru di tabel products
ALTER TABLE products 
    ADD COLUMN IF NOT EXISTS sku VARCHAR(100) DEFAULT NULL AFTER stock,
    ADD COLUMN IF NOT EXISTS gtin VARCHAR(50) DEFAULT NULL AFTER sku,
    ADD COLUMN IF NOT EXISTS no_gtin TINYINT(1) DEFAULT 0 AFTER gtin,
    ADD COLUMN IF NOT EXISTS min_purchase INT DEFAULT 1,
    ADD COLUMN IF NOT EXISTS max_purchase INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS weight INT DEFAULT NULL COMMENT 'gram',
    ADD COLUMN IF NOT EXISTS length_cm DECIMAL(8,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS width_cm DECIMAL(8,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS height_cm DECIMAL(8,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS shipping_origin VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS free_shipping TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS preorder TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS preorder_days INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS status ENUM('draft','active','inactive') DEFAULT 'draft',
    ADD COLUMN IF NOT EXISTS tags VARCHAR(500) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS internal_note TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS seo_title VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS seo_description TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS completeness_score INT DEFAULT 0;

-- 2. Tabel atribut/spesifikasi produk
CREATE TABLE IF NOT EXISTS product_attributes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    attr_name VARCHAR(100) NOT NULL,
    attr_value VARCHAR(255) DEFAULT NULL,
    is_required TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 3. Tabel variasi produk (Warna, Ukuran, dll)
CREATE TABLE IF NOT EXISTS product_variations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    name VARCHAR(100) NOT NULL COMMENT 'Contoh: Warna, Ukuran',
    options TEXT NOT NULL COMMENT 'JSON array',
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 4. Tabel kombinasi varian (Merah-S, Merah-M, Biru-S, dll)
CREATE TABLE IF NOT EXISTS product_variation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    combination VARCHAR(255) NOT NULL COMMENT 'Format: Merah|S',
    price DECIMAL(12,2) DEFAULT 0,
    stock INT DEFAULT 0,
    sku VARCHAR(100) DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 5. Tabel video produk
CREATE TABLE IF NOT EXISTS product_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 6. Tambah kolom is_cover di product_images (kalo belum ada)
ALTER TABLE product_images 
    ADD COLUMN IF NOT EXISTS is_cover TINYINT(1) DEFAULT 0;

-- 7. Subcategory support
ALTER TABLE categories 
    ADD COLUMN IF NOT EXISTS parent_id INT DEFAULT NULL,
    ADD CONSTRAINT fk_parent_cat FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL;
