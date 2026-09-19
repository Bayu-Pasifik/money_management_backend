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

## Deploy ke IDWebhost (cPanel + Terminal)

Paket kamu ada akses **Terminal** di cPanel, jadi alurnya bisa `git clone` + `composer` + `artisan` langsung di server — jauh lebih simpel daripada zip-upload manual.

### A. Buat subdomain untuk API

1. Login cPanel IDWebhost (lewat member area IDWebhost → cPanel, atau `namadomain.com/cpanel`).
2. Buka **Domains** → **Subdomains** (atau **Create A New Domain**).
3. Buat subdomain, misal `api` untuk `api.namadomain.com`.
4. Pas isi **Document Root**, ketik manual: `api_backend/public`
   (cPanel otomatis bikin folder `api_backend` di home directory, document root menunjuk ke `api_backend/public`).

### B. Buka Terminal & cek tool yang tersedia

cPanel → cari ikon **Terminal** → buka. Cek dulu apa yang sudah ada:

```bash
php -v
git --version
composer --version
```

- Kalau `composer --version` gagal (command not found), download Composer sendiri (sekali saja, taruh di home directory):

  ```bash
  cd ~
  curl -sS https://getcomposer.org/installer | php
  # dipakai sebagai: php composer.phar install (ganti "composer" jadi "php composer.phar" di step berikutnya)
  ```

### C. Clone & install project

```bash
cd ~
rm -rf api_backend      # hapus folder kosong bikinan cPanel di step A kalau masih ada isinya default
git clone https://github.com/Bayu-Pasifik/money_management_backend.git api_backend
cd api_backend
composer install --optimize-autoloader --no-dev
```

(Ganti `composer install` jadi `php composer.phar install` kalau tadi pakai Composer versi manual.)

### D. Buat database MySQL

1. cPanel → **MySQL® Databases**.
2. Bagian **Create New Database**: isi nama, misal `money_management` → jadi `cpanelusername_money_management`.
3. Bagian **MySQL Users**: buat user baru + password kuat → jadi `cpanelusername_dbuser`.
4. Bagian **Add User To Database**: pilih user & database tadi, centang **ALL PRIVILEGES**.
5. Catat nama database lengkap, username lengkap, dan password — dipakai di langkah E.

### E. Konfigurasi `.env` & generate key

Masih di Terminal, di dalam folder `api_backend`:

```bash
cp .env.example .env
php artisan key:generate
```

Lalu edit `.env` (pakai `nano .env` di Terminal, atau lewat File Manager kalau lebih nyaman klik-klik):

```env
APP_NAME="Money Management API"
APP_ENV=production
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
TELEGRAM_WEBHOOK_SECRET=<string acak, misal hasil `openssl rand -hex 20`>
```

(`nano`: setelah edit, `Ctrl+O` lalu `Enter` untuk save, `Ctrl+X` untuk keluar.)

### F. Set versi PHP

cPanel → **Select PHP Version** (atau **MultiPHP Manager**) → pilih **PHP 8.2** atau lebih baru untuk domain `api.namadomain.com`. Pastikan extension berikut aktif: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`.

### G. Migrasi & optimasi

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

### H. Aktifkan SSL

cPanel → **SSL/TLS Status** → centang domain & subdomain → **Run AutoSSL**. Tunggu beberapa menit sampai statusnya hijau.

### I. Sambungkan webhook Telegram

Masih di Terminal (atau dari laptop, sama saja):

```bash
curl -X POST "https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook" \
  -d "url=https://api.namadomain.com/api/telegram/webhook" \
  -d "secret_token=<TELEGRAM_WEBHOOK_SECRET>"
```

### J. Update kode di kemudian hari

Karena sudah `git clone`, update tinggal seperti biasa lewat Terminal:

```bash
cd ~/api_backend
git pull origin main
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

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
