# Money Management Backend (Laravel API)

API buat aplikasi money management + AI reminder lewat Telegram.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Isi `.env`:

- `GEMINI_API_KEY` — API key gratis dari [Google AI Studio](https://aistudio.google.com/apikey).
- `TELEGRAM_BOT_TOKEN` — token bot dari [@BotFather](https://t.me/BotFather).
- `TELEGRAM_WEBHOOK_SECRET` — string rahasia bebas, dipakai buat validasi webhook.

## Hubungkan Telegram Bot

1. Deploy backend ke server yang punya HTTPS publik (Telegram wajib HTTPS), atau pakai tunnel seperti `ngrok http 8000` waktu development.
2. Set webhook:

```bash
curl -X POST "https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook" \
  -d "url=https://<domain-kamu>/api/telegram/webhook" \
  -d "secret_token=<TELEGRAM_WEBHOOK_SECRET>"
```

3. User buka bot, ketik `/start` buat dapat Chat ID, lalu hubungkan Chat ID itu lewat frontend (menu Telegram).

## Alur AI Reminder

`app/Services/FinanceAdvisorService.php` orchestrate:

1. Ambil ringkasan keuangan user bulan berjalan (income, expense, saldo, budget per kategori).
2. Kirim ke Gemini (`app/Services/GeminiService.php`) sebagai system prompt + pesan user, minta balasan JSON terstruktur.
3. Kalau AI mendeteksi user melaporkan transaksi yang sudah terjadi, otomatis simpan ke tabel `transactions`.
4. Kalau user cerita rencana pembelian, AI kasih saran tunda/lanjut berdasarkan sisa budget & saldo nyata.
5. Balasan dikirim balik ke user via Telegram `sendMessage`.

## Endpoint Utama

- `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`
- `POST /api/auth/telegram` — hubungkan Chat ID Telegram ke akun (butuh auth)
- `GET/POST/PUT/DELETE /api/categories`
- `GET/POST/PUT/DELETE /api/transactions`
- `GET /api/summary` — ringkasan bulan berjalan + budget per kategori
- `POST /api/telegram/webhook` — endpoint dipanggil Telegram (bukan buat dipanggil frontend)

Auth pakai Laravel Sanctum (Bearer token), bukan cookie session — cocok buat frontend SPA terpisah.
