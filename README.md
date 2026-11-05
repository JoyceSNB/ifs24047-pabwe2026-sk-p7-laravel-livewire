# Joyce Stephanie Naibaho (ifs24047) - PABWE 2026 Praktikum 7 (Laravel 13 & Livewire)

Aplikasi **Stock Barang** (inventaris) dengan arsitektur **Feature-First**, dibangun menggunakan **Laravel 13**, **Livewire 4**, **MySQL/MariaDB**, **PhpSpreadsheet**, dan integrasi **OpenAI**.

## Fitur
- Autentikasi (login dan registrasi) dengan role `admin` dan `member`
- Member: CRUD stok miliknya sendiri, adjust quantity (+/−), cover image, deskripsi Markdown
- Import/export Excel
- Generate item stok dengan AI
- Profil pengguna
- Admin: melihat daftar pengguna dan stok mereka (read-only)

## Instalasi
```bash
composer install
npm install --ignore-scripts
npm run build
cp .env.example .env
php artisan key:generate
```

Buat database `pabwe_p7_stock`, atur `DB_*` di `.env`, lalu:
```bash
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve
```

Akun bawaan seeder: `admin` / `admin` dan `member` / `member`.

## Pengujian
```bash
composer test
composer audit
```