<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://generativelanguage.googleapis.com/',
            'timeout' => 30,
        ]);
    }

    /**
     * Ask Gemini for a JSON-structured finance-advisor reply.
     *
     * @return array{reply: string, is_transaction: bool, type: ?string, amount: ?float, category: ?string, description: ?string, account: ?string}
     */
    public function askFinanceAdvisor(string $systemPrompt, string $userMessage): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model');

        $response = $this->client->post("v1beta/models/{$model}:generateContent", [
            'query' => ['key' => $apiKey],
            'json' => [
                'system_instruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $userMessage]],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'responseMimeType' => 'application/json',
                ],
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (! $text) {
            Log::warning('Gemini returned no text', ['body' => $body]);

            return [
                'reply' => 'Maaf, AI lagi gangguan. Coba lagi nanti ya.',
                'is_transaction' => false,
                'type' => null,
                'amount' => null,
                'category' => null,
                'description' => null,
                'account' => null,
            ];
        }

        $parsed = json_decode($text, true);

        if (! is_array($parsed)) {
            return [
                'reply' => $text,
                'is_transaction' => false,
                'type' => null,
                'amount' => null,
                'category' => null,
                'description' => null,
                'account' => null,
            ];
        }

        return [
            'reply' => $parsed['reply'] ?? 'Oke, dicatat.',
            'is_transaction' => (bool) ($parsed['is_transaction'] ?? false),
            'type' => $parsed['type'] ?? null,
            'amount' => isset($parsed['amount']) ? (float) $parsed['amount'] : null,
            'category' => $parsed['category'] ?? null,
            'description' => $parsed['description'] ?? null,
            'account' => $parsed['account'] ?? null,
        ];
    }
}
