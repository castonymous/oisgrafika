# 🎨 Ois Grafika - E-commerce PHP

Toko online untuk jasa desain, percetakan, produk digital, dan merchandise. Dibuat dengan **PHP native + MySQL**, siap deploy di **shared hosting Hostinger** atau hosting PHP lainnya.

---

## 📦 Fitur

- ✅ **Frontend responsive** — mobile-first, clean Shopee-style design
- ✅ **Multi-kategori produk** — jasa, digital, fisik dengan variant support
- ✅ **Auth system** — register, login, session-based dengan CSRF protection
- ✅ **Keranjang & Checkout** — multi-item, varian, alamat untuk produk fisik
- ✅ **Sistem Referral** — komisi 5% otomatis tercatat
- ✅ **User Dashboard** — overview, pesanan, referral, profil
- ✅ **Admin Panel** — manage produk, kategori, pesanan, user
- ✅ **Payment Gateway Ready** — struktur sudah siap, tinggal integrasi Midtrans/Tripay

---

## 🚀 Cara Install di Hostinger

### 1️⃣ Upload Files

1. Login ke **hPanel Hostinger**
2. Buka **File Manager**
3. Masuk ke folder `public_html`
4. **Upload semua file & folder** dari folder `ois-grafika/` di komputer kamu  
   (atau upload zip-nya lalu extract di sana)

### 2️⃣ Buat Database

1. Di hPanel Hostinger, buka **Databases → MySQL Databases**
2. Klik **Create New Database**:
   - Database name: `ois_grafika` (atau bebas)
   - Username: bebas
   - Password: bikin yang kuat
3. Catat 4 hal ini:
   - Database host (biasanya `localhost`)
   - Database name (lengkap dengan prefix, contoh: `u123456789_oisgrafika`)
   - Username (contoh: `u123456789_admin`)
   - Password

### 3️⃣ Import Database

1. Di hPanel → **phpMyAdmin** → pilih database yang baru dibuat
2. Klik tab **Import**
3. Pilih file `database.sql` dari folder project
4. Klik **Go**
5. Pastikan semua tabel ter-create (kategori, produk, dll)

### 4️⃣ Konfigurasi

Edit file `config/config.php`:

```php
define('DB_HOST', 'localhost');                    // biarin localhost
define('DB_NAME', 'u123456789_oisgrafika');        // ganti sesuai nama DB Hostinger
define('DB_USER', 'u123456789_admin');             // ganti sesuai username DB Hostinger
define('DB_PASS', 'PASSWORD_LU_DI_SINI');          // ganti sesuai password DB
define('SITE_URL', 'https://oisgrafika.com');      // ganti ke domain LU
```

### 5️⃣ Setup Password Admin

1. Buka browser, akses: `https://domain-lu.com/generate-admin.php`
2. Email: `admin@oisgrafika.com` (atau ganti sesuai keinginan lu)
3. Password: bikin password admin yg kuat
4. Klik "Set Password Admin"
5. ⚠️ **PENTING: HAPUS file `generate-admin.php` dari server setelah selesai!**

### 6️⃣ Set Permission Folder Upload

Di File Manager Hostinger:
- Klik kanan folder `uploads/` → Permissions → set ke `755`
- Klik kanan folder `uploads/products/` → Permissions → set ke `755`

### 7️⃣ Selesai! 🎉

Buka domain lu di browser:
- **Toko**: `https://domain-lu.com/`
- **Admin Panel**: login pakai email admin yang lu set tadi, lalu klik menu Admin

---

## 📁 Struktur File

```
ois-grafika/
├── config/
│   └── config.php              # Konfigurasi DB & site
├── includes/
│   ├── functions.php           # Helper functions
│   ├── header.php              # Navbar
│   └── footer.php              # Footer
├── assets/
│   ├── css/                    # Stylesheets
│   ├── js/                     # JavaScript
│   └── images/                 # Static images
├── admin/                      # Admin panel
│   ├── index.php               # Admin dashboard
│   ├── produk.php              # Manage produk
│   ├── pesanan.php             # Manage pesanan
│   ├── users.php               # Manage user
│   └── kategori.php            # Manage kategori
├── uploads/                    # Folder upload (gambar produk, dll)
├── index.php                   # Homepage
├── produk.php                  # Listing produk
├── detail-produk.php           # Detail produk
├── register.php                # Daftar akun
├── login.php                   # Login
├── logout.php                  # Logout
├── keranjang.php               # Keranjang belanja
├── checkout.php                # Checkout
├── order-success.php           # Halaman sukses pesanan
├── dashboard.php               # User dashboard
├── generate-admin.php          # Setup admin (HAPUS setelah pakai!)
├── database.sql                # Schema database
├── .htaccess                   # Apache config
└── README.md                   # File ini
```

---

## 🎯 Cara Pakai

### Sebagai Customer:
1. Daftar di `register.php` (bisa pake kode referral kalau ada)
2. Browse produk di `produk.php`
3. Add to cart, checkout, pilih metode pembayaran
4. Pesanan masuk status "pending payment"
5. Cek pesanan di `dashboard.php?tab=orders`

### Sebagai Admin:
1. Login pake email admin
2. Klik avatar → "Admin Panel"
3. Manage produk, kategori, lihat pesanan
4. Update status pesanan (pending → processing → shipped → completed)

### Sistem Referral:
- Setiap user dapat kode referral otomatis saat daftar
- Bagikan kode/link ke teman: `domain-lu.com/register.php?ref=KODE-LU`
- Komisi 5% otomatis tercatat saat ada order pakai kode referralnya
- Komisi jadi "paid" otomatis saat order pembayaran selesai

---

## 💳 Integrasi Payment Gateway (Phase 2)

Sekarang sistem checkout cuma simpan pesanan dengan status `pending`. Untuk live payment:

**Rekomendasi: Tripay** (paling gampang untuk perorangan)
1. Daftar di [tripay.co.id](https://tripay.co.id)
2. Verifikasi akun
3. Ambil API key dari dashboard
4. Buat file `api/payment-callback.php` untuk handle webhook
5. Update `checkout.php` untuk redirect ke Tripay
6. Update `order-success.php` untuk handle status

**Atau Midtrans** (lebih populer):
1. Daftar di [midtrans.com](https://midtrans.com)
2. Ambil Server Key & Client Key
3. Install Snap.js
4. Implement di checkout

> Nanti tinggal kasih tau kalau mau lanjut integrasi payment, gua siapin kodenya.

---

## 🛠️ Tips & Troubleshooting

**Error koneksi database?**
- Cek di `config/config.php`: DB_NAME, DB_USER, DB_PASS udah bener
- Pastikan database sudah dibuat di Hostinger
- Pastikan SQL sudah di-import

**Halaman blank putih?**
- Tambah ini di awal `index.php` untuk lihat error:
  ```php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  ```

**Login admin gagal?**
- Pastikan udah jalanin `generate-admin.php` dulu
- Cek tabel `users` di phpMyAdmin, role harus `admin`

**Upload gambar gagal?**
- Cek permission folder `uploads/` (harus 755 atau 775)

**Mau ganti warna primary?**
- Edit `assets/css/style.css`, ubah variable `--primary` (default: `#ee4d2d`)

---

## 📝 Default Login

Setelah jalanin `generate-admin.php`:
- **Email**: `admin@oisgrafika.com` (atau yg lu set)
- **Password**: yang lu input di generate-admin.php

---

## 🔒 Security Checklist Setelah Deploy

- [ ] Hapus file `generate-admin.php` dari server
- [ ] Ganti password admin dengan yang kuat
- [ ] Aktifkan HTTPS/SSL (gratis di Hostinger)
- [ ] Backup database secara berkala
- [ ] Update password database setelah testing
- [ ] Set `display_errors` ke `0` di production

---

## 📞 Phase 2 (Nanti)

Yang bisa ditambahin nanti:
- 🔄 Integrasi Midtrans/Tripay (QRIS, VA, e-wallet)
- 📸 Upload gambar produk via admin
- 📧 Notifikasi email (order confirmation, dll)
- 🎟️ Sistem voucher/promo code
- ⭐ Review & rating produk
- 💬 Live chat WhatsApp button
- 📊 Analytics dashboard
- 🔍 Search advanced + filter harga
- 📱 PWA support
- 💸 Withdraw komisi referral

---

**Built with ❤️ for Ois Grafika**
