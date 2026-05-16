-- ============================================
-- OIS GRAFIKA - Database Schema
-- Import file ini di phpMyAdmin Hostinger
-- ============================================

CREATE DATABASE IF NOT EXISTS ois_grafika CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ois_grafika;

-- Tabel kategori produk
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) UNIQUE NOT NULL,
    icon VARCHAR(50) DEFAULT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel user
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    referral_code VARCHAR(20) UNIQUE NOT NULL,
    referred_by INT DEFAULT NULL,
    balance DECIMAL(12,2) DEFAULT 0,
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Tabel produk
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(220) UNIQUE NOT NULL,
    description TEXT,
    short_description VARCHAR(300),
    base_price DECIMAL(12,2) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    type ENUM('jasa', 'digital', 'fisik') NOT NULL,
    stock INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    sold INT DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Tabel varian produk (warna, ukuran, paket, dll)
CREATE TABLE product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    price_modifier DECIMAL(12,2) DEFAULT 0,
    stock INT DEFAULT 0,
    sku VARCHAR(50) DEFAULT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Galeri foto produk
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Tabel keranjang
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
);

-- Tabel pesanan
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(30) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    shipping_cost DECIMAL(12,2) DEFAULT 0,
    discount DECIMAL(12,2) DEFAULT 0,
    final_amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'expired') DEFAULT 'pending',
    order_status ENUM('pending', 'processing', 'shipped', 'completed', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT,
    notes TEXT,
    payment_token VARCHAR(255) DEFAULT NULL,
    referral_code_used VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Item pesanan
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT DEFAULT NULL,
    product_name VARCHAR(200) NOT NULL,
    variant_name VARCHAR(150) DEFAULT NULL,
    price DECIMAL(12,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
);

-- Komisi referral
CREATE TABLE referral_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    referred_user_id INT NOT NULL,
    order_id INT NOT NULL,
    commission DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ============================================
-- SEED DATA
-- ============================================

-- Admin default (password: admin123)
INSERT INTO users (name, email, password, role, referral_code) VALUES
('Admin Ois', 'admin@oisgrafika.com', '$2y$10$kFsxJ2WMx2lQwgTQy.eABuW8tIBHZGSGYqAfL6FN6FbpDLM4HpfQq', 'admin', 'OIS-ADMIN');
-- NOTE: hash di atas dummy. Setelah deploy, login dengan email admin@oisgrafika.com, password apa aja DULU GANTI.
-- Cara aman: jalankan generate-admin.php (akan dibuat) untuk regenerate hash.

-- Kategori
INSERT INTO categories (name, slug, icon, description) VALUES
('Jasa Desain', 'jasa-desain', '🎨', 'Logo, banner, packaging, dan desain custom lainnya'),
('Percetakan', 'percetakan', '🖨️', 'Cetak banner, kartu nama, brosur, sticker'),
('Produk Digital', 'digital', '💾', 'Template, font, preset, mockup siap pakai'),
('Merchandise', 'merchandise', '👕', 'Kaos custom, mug, sticker, totebag');

-- Produk dummy
INSERT INTO products (category_id, name, slug, description, short_description, base_price, type, stock, sold, rating) VALUES
(1, 'Desain Logo Premium', 'desain-logo-premium', 'Jasa pembuatan logo profesional dengan unlimited revisi sampai puas. Hasil dalam format AI, PNG, SVG, PDF.', 'Logo profesional + unlimited revisi + semua format file', 350000, 'jasa', 999, 124, 4.9),
(1, 'Desain Banner Sosmed', 'desain-banner-sosmed', 'Banner Instagram, Facebook, Twitter custom sesuai brand kamu.', 'Banner sosmed custom siap upload', 50000, 'jasa', 999, 287, 4.8),
(2, 'Cetak Banner Outdoor', 'cetak-banner-outdoor', 'Cetak banner flexi outdoor tahan cuaca, kualitas tajam.', 'Banner flexi outdoor per meter', 25000, 'fisik', 100, 543, 4.7),
(2, 'Kartu Nama Premium', 'kartu-nama-premium', 'Kartu nama art carton 260gr, doff/glossy, finishing rapi.', '100pcs kartu nama premium', 75000, 'fisik', 50, 198, 4.9),
(3, 'Template Feed Instagram', 'template-feed-ig', 'Bundle 30 template feed Instagram editable Canva/PSD.', '30 template feed siap edit', 45000, 'digital', 999, 421, 5.0),
(3, 'Mockup Bundle Premium', 'mockup-bundle-premium', '50 mockup PSD high-res untuk presentasi desain.', '50 mockup PSD high-res', 80000, 'digital', 999, 156, 4.8),
(4, 'Kaos Custom Cotton 30s', 'kaos-custom-cotton', 'Kaos cotton combed 30s sablon DTF/DTG kualitas premium.', 'Kaos custom kualitas distro', 95000, 'fisik', 80, 312, 4.9),
(4, 'Mug Custom Sublim', 'mug-custom-sublim', 'Mug keramik putih cetak sublim, gambar tajam tahan lama.', 'Mug custom sublim full color', 35000, 'fisik', 60, 245, 4.8);

-- Varian produk
INSERT INTO product_variants (product_id, name, price_modifier, stock) VALUES
-- Logo
(1, 'Basic (1 konsep)', 0, 999),
(1, 'Standard (3 konsep)', 150000, 999),
(1, 'Premium (5 konsep + brand guideline)', 350000, 999),
-- Banner sosmed
(2, '1 Desain', 0, 999),
(2, 'Paket 5 Desain', 200000, 999),
(2, 'Paket 10 Desain', 400000, 999),
-- Banner outdoor
(3, 'Per Meter Persegi', 0, 100),
-- Kartu nama
(4, '100 pcs', 0, 50),
(4, '200 pcs', 50000, 50),
(4, '500 pcs', 150000, 50),
-- Kaos
(7, 'Ukuran S', 0, 20),
(7, 'Ukuran M', 0, 20),
(7, 'Ukuran L', 5000, 20),
(7, 'Ukuran XL', 10000, 20),
(7, 'Ukuran XXL', 20000, 20),
-- Mug
(8, 'Mug Standard 11oz', 0, 30),
(8, 'Mug Jumbo 15oz', 15000, 30);
