<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.telegram.org/bot'.config('services.telegram.bot_token').'/',
            'timeout' => 15,
        ]);
    }

    public function sendMessage(string $chatId, string $text): void
    {
        try {
            $this->client->post('sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage failed', ['error' => $e->getMessage()]);
        }
    }
}
