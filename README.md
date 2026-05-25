# Backend Absensi

Backend Absensi adalah aplikasi Laravel untuk manajemen absensi, cuti, lembur,
approval, payroll, pajak/BPJS, perjalanan dinas, dan reimbursement.

## Kebutuhan

- PHP 8.2 atau lebih baru
- Composer
- Node.js 20.19+ atau 22.12+
- MySQL atau MariaDB

> Catatan: build masih bisa berjalan di Node 20.17 pada mesin ini, tetapi Vite
> memberi warning dan merekomendasikan Node 20.19+.

## Setup Lokal

1. Install dependency PHP.

```bash
composer install
```

2. Install dependency frontend.

```bash
npm install
```

3. Buat file environment.

```bash
cp .env.example .env
```

Di Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

4. Sesuaikan koneksi database di `.env`.

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=be_inventory
DB_USERNAME=root
DB_PASSWORD=Password*123
```

5. Buat database MySQL.

```sql
CREATE DATABASE be_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

6. Generate application key.

```bash
php artisan key:generate
```

7. Jalankan migrasi dan seeder.

```bash
php artisan migrate --seed
```

Untuk reset database lokal:

```bash
php artisan migrate:fresh --seed
```

8. Buat symbolic link storage publik.

```bash
php artisan storage:link
```

9. Build asset frontend.

```bash
npm run build
```

10. Jalankan server lokal.

```bash
php artisan serve
```

Aplikasi tersedia di:

```text
http://127.0.0.1:8000
```

## Akun Dummy

Seeder default membuat akun superadmin:

```text
email: nofiyanto@artasolusindo.com
password: password
role: superadmin
```

## Command Harian

Jalankan server Laravel:

```bash
php artisan serve
```

Jalankan Vite dev server:

```bash
npm run dev
```

Jalankan semua service development dari Composer:

```bash
composer run dev
```

Jalankan test:

```bash
php artisan test
```

Build asset:

```bash
npm run build
```

## Catatan Penting

- Login admin memakai tabel `employees`, bukan tabel `users`.
- Hanya employee aktif dengan role `admin` atau `superadmin` yang boleh masuk dashboard admin.
- File upload publik disimpan ke `storage/app/public` dan diakses melalui `public/storage`.
- Jika gambar/foto tidak muncul, jalankan ulang `php artisan storage:link`.
- Jangan jalankan `php artisan migrate:fresh --seed` pada database berisi data penting karena semua tabel akan di-drop.
