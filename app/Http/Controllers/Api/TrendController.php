<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TrendController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $months = min(12, max(1, (int) $request->integer('months', 6)));

        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $rows = Transaction::where('user_id', $user->id)
            ->where('occurred_at', '>=', $start)
            ->get(['type', 'amount', 'occurred_at']);

        $points = [];

        for ($i = 0; $i < $months; $i++) {
            $monthDate = $start->copy()->addMonths($i);
            $key = $monthDate->format('Y-m');

            $monthRows = $rows->filter(fn ($row) => $row->occurred_at->format('Y-m') === $key);

            $points[] = [
                'month' => $key,
                'label' => $monthDate->translatedFormat('M'),
                'income' => (float) $monthRows->where('type', 'income')->sum('amount'),
                'expense' => (float) $monthRows->where('type', 'expense')->sum('amount'),
            ];
        }

        return response()->json(['points' => $points]);
    }
}
