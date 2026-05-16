-- ============================================
-- OIS GRAFIKA - DATABASE UPGRADE v4
-- Untuk: Harga Grosir (Tier Pricing)
-- 
-- CARA IMPORT:
-- phpMyAdmin > pilih DB ois_grafika > tab SQL > paste > Go
-- ============================================

-- Tabel harga grosir per produk (max 5 tier)
CREATE TABLE IF NOT EXISTS product_tier_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    min_qty INT NOT NULL COMMENT 'Pesan minimal sekian unit',
    max_qty INT DEFAULT NULL COMMENT 'NULL = ke atas (tidak terbatas)',
    price DECIMAL(12,2) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_qty (product_id, min_qty)
);

-- Aktifkan flag pakai tier di product
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS use_tier_pricing TINYINT(1) DEFAULT 0 AFTER base_price;
