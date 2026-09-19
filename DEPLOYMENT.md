# Deploy Backend (Laravel API)

Panduan upload `money-management-backend` ke server produksi. Backend ini butuh PHP 8.2+, MySQL, dan (untuk fitur Telegram) domain dengan HTTPS aktif.

## Pilihan hosting

| Opsi | Cocok untuk | Catatan |
|---|---|---|
| **VPS** (Ubuntu + Nginx/Apache) | Kontrol penuh, murah jangka panjang | Perlu setup manual (Nginx, PHP-FPM, MySQL, SSL) |
| **Railway / Render** | Paling cepat online, auto-deploy dari GitHub | Tinggal connect repo, set env vars |
| **Shared hosting cPanel** (Niagahoster, dsb) | Familiar, murah | Pastikan support PHP 8.2+ dan "Setup Node.js/PHP App" atau Laravel installer |

Di bawah ini langkah generik yang berlaku ke semua opsi, plus catatan khusus VPS.

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
