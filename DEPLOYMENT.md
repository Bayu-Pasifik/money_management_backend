# Deploy Backend (Laravel API)

Panduan upload `money-management-backend` ke server produksi. Backend ini butuh PHP 8.2+, MySQL, dan (untuk fitur Telegram) domain dengan HTTPS aktif.

## Pilihan hosting

| Opsi | Cocok untuk | Catatan |
|---|---|---|
| **IDWebhost (cPanel)** ✅ dipakai sekarang | Sudah punya, murah | Ikuti bagian "Deploy ke IDWebhost" di bawah |
| **VPS** (Ubuntu + Nginx/Apache) | Kontrol penuh, upgrade nanti | Perlu setup manual (Nginx, PHP-FPM, MySQL, SSL) |
| **Railway / Render** | Paling cepat online, auto-deploy dari GitHub | Tinggal connect repo, set env vars |

Bagian di bawah ini dibagi dua: **langkah khusus IDWebhost** (baca ini kalau itu hosting kamu), lalu **langkah generik** untuk VPS/host lain.

---

## Deploy ke IDWebhost (cPanel)

IDWebhost pakai cPanel, jadi Laravel di-deploy lewat File Manager + phpMyAdmin, bukan `git push` biasa.

### A. Siapkan project sebelum upload (di laptop)

Karena kebanyakan paket shared hosting tidak punya Composer/SSH, install dependency **di laptop dulu**, baru upload hasilnya:

```bash
cd money-management-backend
composer install --optimize-autoloader --no-dev
```

Ini bikin folder `vendor/` terisi — folder ini yang biasanya tidak ada kalau cuma `git clone` di server.

Generate `APP_KEY` juga di laptop (supaya tidak perlu terminal di server):

```bash
php artisan key:generate --show
```

Simpan output-nya (`base64:xxxxx...`), nanti dipakai di `.env` server.

### B. Buat subdomain untuk API

1. Login cPanel IDWebhost (lewat member area IDWebhost → cPanel, atau `namadomain.com/cpanel`).
2. Buka **Domains** → **Subdomains** (atau **Create A New Domain**).
3. Buat subdomain, misal `api` untuk `api.namadomain.com`.
4. Pas isi **Document Root**, jangan biarkan default — ketik manual: `api_backend/public`
   (cPanel otomatis bikin folder `api_backend` di home directory, dengan document root menunjuk ke `api_backend/public`).

### C. Upload project

1. Zip seluruh folder `money-management-backend` **di laptop** (boleh exclude folder `.git` biar lebih kecil).
2. cPanel → **File Manager** → masuk ke folder `api_backend` (yang dibuat di langkah B).
3. Upload file zip, lalu klik kanan → **Extract**.
4. Pastikan struktur akhirnya: `api_backend/app`, `api_backend/public`, `api_backend/vendor`, dst — **bukan** `api_backend/money-management-backend/app` (kalau ke-nest, pindahkan isinya naik satu level).

### D. Buat database MySQL

1. cPanel → **MySQL® Databases**.
2. Bagian **Create New Database**: isi nama, misal `money_management` → jadi `cpanelusername_money_management`.
3. Bagian **MySQL Users**: buat user baru + password kuat → jadi `cpanelusername_dbuser`.
4. Bagian **Add User To Database**: pilih user & database tadi, centang **ALL PRIVILEGES**.
5. Catat 3 hal ini: nama database lengkap, username lengkap, password.

### E. Buat file `.env`

1. File Manager → masuk ke `api_backend` (root project, **bukan** `public/`).
2. Buat file baru bernama `.env` (kalau File Manager sembunyikan file berawalan titik, aktifkan **Settings → Show Hidden Files**).
3. Isi seperti ini (paste `APP_KEY` dari langkah A):

```env
APP_NAME="Money Management API"
APP_ENV=production
APP_KEY=base64:xxxxx-paste-dari-laptop
APP_DEBUG=false
APP_URL=https://api.namadomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpanelusername_money_management
DB_USERNAME=cpanelusername_dbuser
DB_PASSWORD=<password_mysql_tadi>

GEMINI_API_KEY=<key dari aistudio.google.com>
GEMINI_MODEL=gemini-2.0-flash

TELEGRAM_BOT_TOKEN=<token dari @BotFather>
TELEGRAM_WEBHOOK_SECRET=<string acak>
```

### F. Set versi PHP

cPanel → **Select PHP Version** (atau **MultiPHP Manager**) → pilih **PHP 8.2** atau lebih baru untuk domain `api.namadomain.com`. Pastikan extension berikut aktif (biasanya sudah default, tinggal dicentang kalau belum): `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`.

### G. Jalankan migrasi database

**Kalau paket kamu ada akses Terminal** (cPanel → cari ikon **Terminal**, beberapa paket IDWebhost menyediakan ini):

```bash
cd api_backend
php artisan migrate --force
php artisan config:cache
```

**Kalau tidak ada Terminal**, pakai cara berikut (aman, sekali pakai lalu dihapus):

1. Di laptop, buat file `public/deploy-migrate.php` di dalam project dengan isi:

   ```php
   <?php
   if (($_GET['token'] ?? '') !== 'GANTI-DENGAN-STRING-RAHASIA-ACAK') {
       http_response_code(403);
       exit('Forbidden');
   }
   require __DIR__.'/../vendor/autoload.php';
   $app = require_once __DIR__.'/../bootstrap/app.php';
   $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
   $kernel->call('migrate', ['--force' => true]);
   echo $kernel->output();
   ```

2. Upload file ini ke `api_backend/public/deploy-migrate.php` lewat File Manager.
3. Buka `https://api.namadomain.com/deploy-migrate.php?token=GANTI-DENGAN-STRING-RAHASIA-ACAK` di browser sekali saja.
4. Kalau outputnya menunjukkan migrasi sukses, **langsung hapus file `deploy-migrate.php` dari server** (File Manager) — jangan dibiarkan nyangkut, karena ini pintu belakang ke database.

### H. Aktifkan SSL

cPanel → **SSL/TLS Status** → centang domain & subdomain → **Run AutoSSL**. Tunggu beberapa menit sampai statusnya hijau (IDWebhost pakai AutoSSL/Let's Encrypt gratis).

### I. Sambungkan webhook Telegram

Sama seperti host lain, lihat bagian [Hubungkan bot Telegram](#7-hubungkan-bot-telegram) di bawah — tinggal ganti URL ke `https://api.namadomain.com/api/telegram/webhook`.

### J. Update kode di kemudian hari

Tanpa Terminal, alurnya: edit/build ulang di laptop → zip ulang folder yang berubah → upload & extract via File Manager (timpa file lama) → kalau ada migration baru, ulangi trik `deploy-migrate.php` di langkah G lalu hapus lagi setelah selesai.

---

## Langkah generik (VPS / host lain)

## 1. Siapkan server

- PHP >= 8.2 dengan extension: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`
- Composer
- MySQL 8 (atau MariaDB)
- Domain/subdomain dengan **HTTPS** (wajib untuk webhook Telegram, contoh: `https://api.bukukas.app`)

## 2. Clone & install

```bash
git clone https://github.com/Bayu-Pasifik/money_management_backend.git
cd money_management_backend
composer install --optimize-autoloader --no-dev
```

## 3. Konfigurasi `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Isi variabel penting di `.env`:

```env
APP_NAME="Money Management API"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.bukukas.app

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=money_management
DB_USERNAME=<user_mysql>
DB_PASSWORD=<password_mysql>

GEMINI_API_KEY=<key dari aistudio.google.com>
GEMINI_MODEL=gemini-2.0-flash

TELEGRAM_BOT_TOKEN=<token dari @BotFather>
TELEGRAM_WEBHOOK_SECRET=<string acak, misal hasil `openssl rand -hex 20`>
```

## 4. Migrasi database

```bash
php artisan migrate --force
```

## 5. Optimasi Laravel untuk produksi

```bash
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

> Setiap kali ubah `.env` di server, jalankan `php artisan config:clear` dulu sebelum `config:cache` ulang, kalau tidak perubahan tidak kebaca.

## 6. Arahkan web server ke `public/`

**Nginx** (contoh):

```nginx
server {
    listen 443 ssl http2;
    server_name api.bukukas.app;
    root /var/www/money_management_backend/public;

    ssl_certificate     /etc/letsencrypt/live/api.bukukas.app/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.bukukas.app/privkey.pem;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

**Apache**: pastikan `DocumentRoot` mengarah ke folder `public/`, dan `mod_rewrite` aktif (`.htaccess` bawaan Laravel sudah cukup).

**Shared hosting cPanel**: kalau tidak bisa ubah document root, upload seluruh project di luar `public_html`, lalu isi `public_html` dengan isi folder `public/` (index.php diedit path-nya ke `../money_management_backend/vendor/autoload.php` dan `../money_management_backend/bootstrap/app.php`).

## 7. Hubungkan bot Telegram

Setelah domain aktif dengan HTTPS:

```bash
curl -X POST "https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook" \
  -d "url=https://api.bukukas.app/api/telegram/webhook" \
  -d "secret_token=<TELEGRAM_WEBHOOK_SECRET>"
```

Cek status webhook:

```bash
curl "https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/getWebhookInfo"
```

## 8. Update saat ada perubahan kode

```bash
git pull origin main
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

## Checklist sebelum live

- [ ] `APP_DEBUG=false` (jangan bocorkan stack trace ke publik)
- [ ] `APP_ENV=production`
- [ ] Domain sudah HTTPS (webhook Telegram akan ditolak kalau masih HTTP)
- [ ] `GEMINI_API_KEY` dan `TELEGRAM_BOT_TOKEN` terisi
- [ ] `TELEGRAM_WEBHOOK_SECRET` di `.env` sama persis dengan yang dikirim ke `setWebhook`
- [ ] `php artisan migrate --force` sudah jalan tanpa error
- [ ] Endpoint `GET https://api.bukukas.app/up` (health check bawaan Laravel) balas 200
