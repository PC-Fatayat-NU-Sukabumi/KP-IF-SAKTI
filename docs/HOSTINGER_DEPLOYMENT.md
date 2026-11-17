# Panduan Deployment Shared Hosting Hostinger (hPanel)

Panduan ini menjelaskan cara men-deploy **KP-IF-SAKTI** (Laravel 12 + React SPA dalam satu domain) ke **Shared Hosting Hostinger** tanpa Docker dan tanpa Node.js di server.

---

## Prasyarat di hPanel Hostinger

1. **Versi PHP**: Masuk ke **hPanel → Advanced → PHP Configuration**, pilih **PHP 8.2** atau **PHP 8.3**.
2. **Database MySQL**: Masuk ke **hPanel → Databases → Management**, buat database baru dan catat:
   - `DB_DATABASE` (contoh: `u123456789_kpdb`)
   - `DB_USERNAME` (contoh: `u123456789_kpuser`)
   - `DB_PASSWORD`
   - `DB_HOST`: `localhost`
3. **SSL / HTTPS**: Pastikan SSL aktif untuk domain Anda di **hPanel → Security → SSL**.

---

## Metode 1: Upload ZIP via File Manager (Direkomendasikan)

Metode ini paling praktis karena build React dan dependensi `vendor/` sudah disiapkan dari komputer lokal.

### Langkah 1: Bangun Paket di Komputer Lokal

Jalankan perintah berikut dari root repositori:

```bash
bash scripts/package-hostinger.sh
```

Script ini akan menghasilkan file siap upload:
- `dist-hostinger/kp-if-sakti-hostinger.zip`

### Langkah 2: Upload & Extract ke `public_html`

1. Buka **hPanel → Files → File Manager**.
2. Masuk ke folder `public_html/` (pastikan folder bersih dari file bawaan seperti `default.php`).
3. Upload `kp-if-sakti-hostinger.zip` lalu klik **Extract** langsung di dalam `public_html/`.
4. Pastikan struktur di `public_html/` menjadi:
   ```text
   public_html/
   ├── .htaccess          (mengarahkan trafik ke backend/public/)
   └── backend/
       ├── .env.sharedhosting.example
       ├── app/
       ├── public/
       │   ├── assets/
       │   ├── spa.html
       │   └── index.php
       └── vendor/
   ```

### Langkah 3: Konfigurasi `.env`

1. Masuk ke `public_html/backend/`.
2. Salin/rename `.env.sharedhosting.example` menjadi `.env`.
3. Edit `.env` dan sesuaikan nilai berikut:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://domainanda.com
   FRONTEND_URL=https://domainanda.com

   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=u123456789_kpdb
   DB_USERNAME=u123456789_kpuser
   DB_PASSWORD=PasswordDatabaseAnda

   QUEUE_CONNECTION=sync
   SESSION_DRIVER=database
   CACHE_STORE=database
   ```

### Langkah 4: Migrasi Database & Optimasi (via SSH Hostinger)

Aktifkan SSH di **hPanel → Advanced → SSH Access**, lalu login dan jalankan:

```bash
cd ~/domains/domainanda.com/public_html/backend

php artisan key:generate
php artisan migrate --force --seed
php artisan storage:link
php artisan optimize
```

> **Catatan jika `storage:link` gagal** (fungsi `symlink()` dibatasi hosting): Tidak masalah—aplikasi sudah dilengkapi fallback route `/storage/{path}` di `routes/web.php` untuk menyajikan file dari `storage/app/public/` secara aman.

> **Catatan jika paket hosting tidak memiliki SSH**:
> - Generate `APP_KEY` di lokal dengan `php artisan key:generate --show`, lalu tempel ke `.env`.
> - Import schema database melalui **phpMyAdmin** di hPanel (export SQL dari lokal atau jalankan migrasi ke DB lokal lalu export `.sql`).

---

## Metode 2: Auto-Deploy Otomatis via GitHub Actions (Direkomendasikan untuk Tim)

Repositori sudah dilengkapi workflow [`.github/workflows/deploy-hostinger.yml`](../.github/workflows/deploy-hostinger.yml) yang otomatis mem-build React SPA, menginstal `composer install --no-dev`, mengirim file via `rsync` ke Hostinger, dan menjalankan `php artisan migrate --force` setiap kali ada `push` ke branch `production`.

### Langkah 1: Aktifkan SSH di Hostinger hPanel
1. Buka **hPanel → Advanced → SSH Access**, lalu klik **Enable**.
2. Catat **SSH IP / Host**, **Port** (umumnya `65002`), dan **Username** (contoh: `u123456789`).
3. Buat SSH key pair (di lokal: `ssh-keygen -t ed25519 -f ~/.ssh/hostinger_deploy -N ""`):
   - Tempel isi **public key** (`hostinger_deploy.pub`) ke Hostinger (**hPanel → SSH Access → Manage SSH Keys** atau `~/.ssh/authorized_keys` di server).

### Langkah 2: Tambahkan GitHub Secrets
Buka repositori GitHub → **Settings → Secrets and variables → Actions → New repository secret**, lalu tambahkan:

| Nama Secret | Contoh Nilai | Keterangan |
| :--- | :--- | :--- |
| `HOSTINGER_HOST` | `185.xxx.xxx.xxx` / `srv123.hstgr.io` | IP/Host SSH dari hPanel |
| `HOSTINGER_PORT` | `65002` | Port SSH Hostinger (default `65002`) |
| `HOSTINGER_USER` | `u123456789` | Username SSH Hostinger |
| `HOSTINGER_SSH_KEY` | `-----BEGIN OPENSSH PRIVATE KEY-----...` | Isi file private key `hostinger_deploy` |
| `HOSTINGER_DEPLOY_PATH` | `/home/u123456789/domains/domainanda.com/public_html` | Path absolut ke folder `public_html` |

### Langkah 3: Siapkan `.env` Sekali Saja di Server
Workflow sengaja **tidak menimpa** `backend/.env` dan `backend/storage/`. Sebelum push pertama, masuk via SSH ke server lalu buat `.env`:
```bash
cd /home/u123456789/domains/domainanda.com/public_html/backend
cp .env.sharedhosting.example .env
# Edit .env (APP_URL, FRONTEND_URL, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
php artisan key:generate
```
Setelah itu, setiap `git push origin production` (atau klik **Run workflow** di tab **Actions** GitHub) akan otomatis men-deploy versi terbaru ke Hostinger.

---

## Metode 3: Deploy via Fitur Git di hPanel Hostinger

Jika Anda menghubungkan repositori GitHub langsung melalui **hPanel → Advanced → Git**:

1. Atur branch ke `production` dan target directory ke `public_html` (default Hostinger).
2. Setelah pull selesai, masuk via SSH ke `public_html/backend`:
   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.sharedhosting.example .env
   # Edit .env (APP_URL, FRONTEND_URL, DB_*)
   php artisan key:generate
   php artisan migrate --force --seed
   php artisan storage:link
   php artisan optimize
   ```
   *(Artefak build React di `backend/public/assets/` dan `backend/public/spa.html` sudah ikut ter-commit di branch `production`, sehingga tidak perlu Node.js di server.)*

---

## Verifikasi Setelah Deploy

1. Buka `https://domainanda.com` → Website publik React SPA tampil normal, peta PAC termuat, dan refresh di halaman seperti `/tentang` atau `/pengajuan-data-pac` tidak menghasilkan 404.
2. Buka `https://domainanda.com/login` → Halaman login Admin Laravel tampil normal.
3. Login menggunakan akun default seeder (segera ubah password setelah login pertama):
   - Email: `admin@fatayatnu.or.id`
   - Password: `password`
4. Pastikan file sensitif terlindungi: mengakses `https://domainanda.com/.env` atau `https://domainanda.com/backend/.env` harus mengembalikan **403 Forbidden**.

---

## Troubleshooting Umum di Hostinger

| Gejala | Penyebab & Solusi |
| :--- | :--- |
| **500 Internal Server Error** | Cek `backend/storage/logs/laravel.log`. Pastikan `APP_KEY` sudah terisi, versi PHP >= 8.2, dan permission folder `backend/storage` & `backend/bootstrap/cache` bernilai `755` atau `775`. |
| **403 Forbidden di halaman utama** | Pastikan file `.htaccess` di `public_html/` ada (tampilkan hidden files di File Manager) dan `mod_rewrite` aktif. |
| **CSRF Token Mismatch / gagal login** | Pastikan `APP_URL` dan `FRONTEND_URL` di `.env` memakai `https://` yang sama persis dengan URL browser, lalu jalankan `php artisan config:clear && php artisan optimize`. |
| **Gambar upload tidak muncul** | Jalankan `php artisan storage:link` via SSH, atau pastikan folder `backend/storage/app/public/` ada dan permission-nya `755`. |
