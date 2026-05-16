# OIS GRAFIKA — Aplikasi Nota Online

PHP native + JSON storage. Jalan di Hostinger shared hosting tanpa setup MySQL.

## 🚀 Cara Install di Hostinger

1. **Login ke hPanel Hostinger** → buka **File Manager**.
2. Masuk ke folder **`public_html`** (atau subdomain folder kalau mau pasang di subdomain).
3. Upload semua file dari folder ini, lalu **extract** kalau dalam bentuk zip.
   Pastikan struktur akhirnya seperti ini:
   ```
   public_html/
   ├── index.php
   ├── inventory.php
   ├── settings.php
   ├── config.php
   ├── _topbar.php
   ├── .htaccess
   ├── auth/
   │   ├── login.php
   │   └── logout.php
   ├── nota/
   │   ├── create.php
   │   ├── edit.php
   │   ├── delete.php
   │   ├── print.php
   │   ├── preview.php
   │   ├── export_csv.php
   │   └── _nota_template.php
   ├── assets/
   │   ├── app.css
   │   └── nota.css
   └── data/        ← auto-generated saat pertama buka
   ```
4. **Permission folder `data/`**: kalau auto-generate gagal, buat manual lewat File Manager dan set permission **755** (atau **775**). File `users.json`, `inventory.json`, `settings.json`, `invoices.json` akan auto-dibuat saat pertama akses.
5. Buka domain lo di browser → otomatis redirect ke halaman login.
6. **Login default:**
   ```
   username: admin
   password: admin123
   ```
7. Setelah login, masuk **Pengaturan** → ganti nama toko, alamat, sosmed, daftar layanan, warna aksen.
8. Buka **Inventory** → tambah/edit barang yang nanti muncul di autocomplete nota.
9. **Buat Nota Baru** → preview live di kanan, klik **Simpan & Print**.

## 🖨 Cetak / Save sebagai PDF

Di halaman print:
1. Pilih ukuran: **A4 Landscape** (default) atau **A6 Landscape**.
2. Klik **🖨 Cetak / Save PDF**.
3. Di dialog print browser:
   - **Destination**: pilih **"Save as PDF"** atau printer fisik.
   - **Paper size**: pastikan sesuai pilihan (A4 atau A6).
   - **Layout**: Landscape.
   - **Margins**: Default atau None.
   - **Options**: centang **"Background graphics"** biar warna aksen ikut tercetak.
   - **More settings** → matikan **"Headers and footers"** biar URL & tanggal browser gak muncul.
4. PDF yang dihasilkan **bisa di-copy text-nya** — bukan gambar.

## 🔐 Ganti Password Admin

Edit `data/users.json` di File Manager, atau lewat PHP one-liner:
```php
echo password_hash('passwordbaru', PASSWORD_DEFAULT);
```
Copy hash hasilnya ke field `password` di `users.json`.

## 📂 Backup Data

Semua data ada di folder `data/`. Backup = download folder itu. Restore = upload balik.

## 🛠 Troubleshooting

- **"Folder data tidak bisa ditulis"** → chmod 755 di File Manager Hostinger.
- **QR code tidak muncul saat print** → pastikan **"Background graphics"** dicentang di dialog print.
- **Layout kepotong saat print** → set zoom printer ke **100%**, jangan "Fit to page".
- **Login looping** → pastikan PHP session bisa write (cek `php.ini` Hostinger atau hubungi support).
- **File `.htaccess` dianggap salah** → buka File Manager, klik kanan → Permissions = 644.

## ⚙ Tech

- PHP 7.4+ (native, no framework)
- JSON file storage
- Session-based auth dengan `password_hash`
- Print: native `window.print()` → text PDF (bukan rasterize)
- Inventory autocomplete: client-side JS
- QR code: free API qrserver.com (no auth needed)

## 📌 Tips Pakai

- **Buat nota cepat**: ketik di kolom nama → autocomplete dari inventory → harga otomatis terisi.
- **Edit nota lama**: klik ✏ di dashboard, ubah, simpan ulang. Nomor nota tetap.
- **Export Excel/CSV**: tombol di pojok kanan card "Daftar Nota" → file CSV bisa dibuka di Excel.
- **Ubah warna aksen**: Settings → Warna Aksen → pilih warna baru → otomatis update di semua nota.
