# An Nur Smart System — Panduan Instalasi

## Stack
- Laravel 12
- PHP 8.2+
- MySQL 8.0+
- Laravel Sanctum (API auth Flutter)

---

## Step 1 — Buat Project Laravel

```bash
composer create-project laravel/laravel annur-smart-system
cd annur-smart-system
```

---

## Step 2 — Install Dependencies

```bash
# API Auth (untuk Flutter)
composer require laravel/sanctum

# PDF slip gaji
composer require barryvdh/laravel-dompdf

# Export Excel
composer require maatwebsite/excel

# Image upload (foto absensi)
composer require intervention/image
```

---

## Step 3 — Konfigurasi .env

```env
APP_NAME="An Nur Smart System"
APP_ENV=local
APP_KEY=           # akan di-generate
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=annur_smart_system
DB_USERNAME=root
DB_PASSWORD=your_password

# Untuk storage foto absensi
FILESYSTEM_DISK=public
```

---

## Step 4 — Setup Database

```bash
# Buat database di MySQL
mysql -u root -p -e "CREATE DATABASE annur_smart_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Generate app key
php artisan key:generate

# Publish Sanctum config
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

---

## Step 5 — Salin File Migrations

Salin semua file dari folder `migrations-output/` ke `database/migrations/`:

```bash
cp migrations-output/*.php database/migrations/
```

---

## Step 6 — Salin Models

Salin file dari `models-output/` ke `app/Models/`:

```bash
cp models-output/*.php app/Models/
```

---

## Step 7 — Salin Services

Buat folder `app/Services/` lalu salin:

```bash
mkdir -p app/Services
cp services-output/*.php app/Services/
```

---

## Step 8 — Salin Controllers

```bash
mkdir -p app/Http/Controllers/Superadmin
mkdir -p app/Http/Controllers/Api
cp controllers-output/PenggajianController.php app/Http/Controllers/Superadmin/
cp controllers-output/KoreksiAbsensiController.php app/Http/Controllers/Superadmin/
cp controllers-output/HariLiburController.php app/Http/Controllers/Superadmin/
```

---

## Step 9 — Daftarkan Middleware

Edit `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role'     => \App\Http\Middleware\RoleMiddleware::class,
        'role.api' => \App\Http\Middleware\RoleApiMiddleware::class,
    ]);
})
```

Buat file:
- `app/Http/Middleware/RoleMiddleware.php`
- `app/Http/Middleware/RoleApiMiddleware.php`

(Salin dari `controllers-output/Middleware.php`)

---

## Step 10 — Salin Routes

```bash
cp routes-output/web.php routes/web.php
cp routes-output/api.php routes/api.php
```

---

## Step 11 — Salin Seeders

```bash
# Pisahkan file AllSeeders.php menjadi file terpisah per kelas
# atau salin langsung ke database/seeders/
cp seeders-output/DatabaseSeeder.php database/seeders/
# Buat file seeder terpisah sesuai isi seeders-output/AllSeeders.php
```

---

## Step 12 — Jalankan Migration & Seeder

```bash
php artisan migrate
php artisan db:seed
```

---

## Step 13 — Setup Storage

```bash
php artisan storage:link
```

---

## Step 14 — Jalankan Server

```bash
php artisan serve
```

Akses: `http://localhost:8000/admin`

**Login superadmin:**
- Email: `superadmin@annur.sch.id`
- Password: `annur@2025`

---

## Catatan Penting

### Untuk API Flutter
Base URL: `http://your-domain/api/v1`

Login Flutter:
```json
POST /api/v1/auth/login
{
  "email": "ahmad@annur.sch.id",
  "password": "password"
}
```
Response: `{ "token": "...", "user": {...} }`

Gunakan token di header: `Authorization: Bearer {token}`

### Exception Handling Mendadak
- **Libur mendadak**: POST `/admin/smart-payroll/hari-libur/darurat` → otomatis update semua absensi
- **Koreksi absensi**: POST `/admin/smart-payroll/absensi/koreksi/harian/{id}` → jika ada penggajian draft, akan ada flag `needs_recalc`
- **Recalculate gaji**: POST `/admin/smart-payroll/penggajian/{id}/recalculate` → hitung ulang tanpa hapus override manual

### Package yang Diperlukan Selanjutnya
```bash
# Untuk views (Blade UI)
composer require livewire/livewire    # jika pakai Livewire
# ATAU
npm install                           # jika pakai Vite + Alpine.js
```
