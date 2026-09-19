<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AiConversation;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class FinanceAdvisorService
{
    public function __construct(private GeminiService $gemini) {}

    public function handleMessage(User $user, string $message): string
    {
        $context = $this->buildFinancialContext($user);
        $history = $this->buildConversationHistory($user);

        $systemPrompt = <<<PROMPT
Kamu adalah asisten keuangan pribadi di Telegram. Balas dalam Bahasa Indonesia, singkat, nada teman yang jujur (boleh tegas kalau kondisi keuangan user sedang ketat).

Data keuangan user bulan ini:
{$context}

Riwayat obrolan terakhir:
{$history}

Tugas kamu:
1. Kalau user cerita RENCANA pengeluaran (mau beli sesuatu), nilai berdasarkan sisa budget & saldo akun yang relevan. Kalau budget kategori terkait sudah habis, atau saldo akun yang mau dipakai tipis, SARANKAN TUNDA dan jelaskan alasannya pakai angka nyata. Kalau masih aman, boleh kasih lampu hijau dengan catatan.
2. Kalau user melaporkan transaksi YANG SUDAH TERJADI (contoh: "abis beli kabel 50rb pakai BCA", "gajian 5jt masuk BRI pribadi", "bayar qris pake cash"), catat sebagai transaksi: tentukan type (income/expense), amount (angka murni, konversi "50rb"=50000, "5jt"=5000000), category (nama singkat, contoh: "PC Part", "Makan", "Gaji"), description singkat, dan account (cocokkan ke salah satu nama akun user di atas persis seperti tertulis; kalau user tidak sebut akun atau tidak jelas, isi null).
3. Kalau cuma nanya-nanya / curhat, jawab natural tanpa membuat transaksi.

WAJIB balas HANYA JSON valid dengan struktur persis ini, tanpa markdown code fence:
{"reply": "teks balasan ke user", "is_transaction": true/false, "type": "income" atau "expense" atau null, "amount": angka atau null, "category": "nama kategori" atau null, "description": "deskripsi singkat" atau null, "account": "nama akun" atau null}
PROMPT;

        AiConversation::create(['user_id' => $user->id, 'role' => 'user', 'message' => $message]);

        $result = $this->gemini->askFinanceAdvisor($systemPrompt, $message);

        if ($result['is_transaction'] && $result['amount'] > 0 && in_array($result['type'], ['income', 'expense'], true)) {
            $category = Category::firstOrCreate(
                ['user_id' => $user->id, 'name' => $result['category'] ?? 'Lainnya'],
                ['type' => $result['type']],
            );

            $account = $this->matchAccount($user, $result['account'] ?? null);

            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $account?->id,
                'category_id' => $category->id,
                'type' => $result['type'],
                'amount' => $result['amount'],
                'description' => $result['description'],
                'source' => 'telegram',
                'occurred_at' => now(),
            ]);
        }

        AiConversation::create(['user_id' => $user->id, 'role' => 'assistant', 'message' => $result['reply']]);

        return $result['reply'];
    }

    private function matchAccount(User $user, ?string $name): ?Account
    {
        if (! $name) {
            return null;
        }

        $accounts = Account::where('user_id', $user->id)->get();
        $needle = Str::lower(trim($name));

        return $accounts->first(fn (Account $account) => Str::lower($account->name) === $needle)
            ?? $accounts->first(fn (Account $account) => str_contains(Str::lower($account->name), $needle) || str_contains($needle, Str::lower($account->name)));
    }

    private function buildFinancialContext(User $user): string
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        $income = Transaction::where('user_id', $user->id)
            ->where('type', 'income')
            ->where('occurred_at', '>=', $startOfMonth)
            ->sum('amount');

        $expense = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('occurred_at', '>=', $startOfMonth)
            ->sum('amount');

        $balance = $income - $expense;

        $categoryLines = Category::where('user_id', $user->id)
            ->whereNotNull('monthly_budget')
            ->get()
            ->map(function (Category $category) use ($startOfMonth) {
                $spent = Transaction::where('category_id', $category->id)
                    ->where('type', 'expense')
                    ->where('occurred_at', '>=', $startOfMonth)
                    ->sum('amount');

                $remaining = $category->monthly_budget - $spent;

                return "- {$category->name}: budget Rp".number_format((float) $category->monthly_budget, 0, ',', '.').
                    ", terpakai Rp".number_format($spent, 0, ',', '.').
                    ", sisa Rp".number_format($remaining, 0, ',', '.');
            })->implode("\n");

        $accountLines = Account::where('user_id', $user->id)
            ->get()
            ->map(function (Account $account) {
                $accIncome = Transaction::where('account_id', $account->id)->where('type', 'income')->sum('amount');
                $accExpense = Transaction::where('account_id', $account->id)->where('type', 'expense')->sum('amount');
                $accBalance = (float) $account->initial_balance + $accIncome - $accExpense;

                return "- {$account->name}: saldo Rp".number_format($accBalance, 0, ',', '.');
            })->implode("\n");

        return "Total pemasukan: Rp".number_format($income, 0, ',', '.')."\n".
            "Total pengeluaran: Rp".number_format($expense, 0, ',', '.')."\n".
            "Saldo semua akun: Rp".number_format($balance, 0, ',', '.')."\n\n".
            "Daftar akun/dompet user (pakai nama persis ini untuk field account):\n".
            ($accountLines ?: '- belum ada akun terdaftar')."\n\n".
            "Budget per kategori:\n".($categoryLines ?: '- belum ada budget kategori diset');
    }

    private function buildConversationHistory(User $user): string
    {
        $messages = AiConversation::where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return '- (belum ada riwayat)';
        }

        return $messages->map(fn (AiConversation $m) => "{$m->role}: {$m->message}")->implode("\n");
    }
}
