<?php

namespace App\Http\Controllers;

use App\Models\TelegramUser;
use App\Services\FinanceAdvisorService;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramService $telegram, FinanceAdvisorService $advisor)
    {
        $secret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($secret !== config('services.telegram.webhook_secret')) {
            abort(403);
        }

        $chatId = $request->input('message.chat.id');
        $text = trim((string) $request->input('message.text'));

        if (! $chatId || $text === '') {
            return response()->json(['ok' => true]);
        }

        if ($text === '/start') {
            $telegram->sendMessage((string) $chatId,
                "Halo! Chat ID kamu: `{$chatId}`\n\nBuka aplikasi web, masuk ke menu *Hubungkan Telegram*, tempel Chat ID ini biar aku bisa bantu catat & kasih saran keuangan kamu."
            );

            return response()->json(['ok' => true]);
        }

        $telegramUser = TelegramUser::where('chat_id', (string) $chatId)->where('is_active', true)->first();

        if (! $telegramUser) {
            $telegram->sendMessage((string) $chatId,
                "Chat ID kamu belum terhubung ke akun. Ketik /start dulu buat lihat Chat ID, lalu hubungkan lewat aplikasi web."
            );

            return response()->json(['ok' => true]);
        }

        $reply = $advisor->handleMessage($telegramUser->user, $text);

        $telegram->sendMessage((string) $chatId, $reply);

        return response()->json(['ok' => true]);
    }
}
