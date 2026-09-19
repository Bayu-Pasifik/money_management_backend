<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SummaryController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $startOfMonth = Carbon::now()->startOfMonth();

        $income = Transaction::where('user_id', $user->id)
            ->where('type', 'income')
            ->where('occurred_at', '>=', $startOfMonth)
            ->sum('amount');

        $expense = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('occurred_at', '>=', $startOfMonth)
            ->sum('amount');

        $categories = Category::where('user_id', $user->id)
            ->get()
            ->map(function (Category $category) use ($startOfMonth) {
                $spent = Transaction::where('category_id', $category->id)
                    ->where('type', 'expense')
                    ->where('occurred_at', '>=', $startOfMonth)
                    ->sum('amount');

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type,
                    'monthly_budget' => $category->monthly_budget,
                    'spent' => $spent,
                    'remaining' => $category->monthly_budget !== null
                        ? $category->monthly_budget - $spent
                        : null,
                ];
            });

        $accounts = Account::where('user_id', $user->id)
            ->get()
            ->map(function (Account $account) {
                $accountIncome = Transaction::where('account_id', $account->id)->where('type', 'income')->sum('amount');
                $accountExpense = Transaction::where('account_id', $account->id)->where('type', 'expense')->sum('amount');

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'balance' => (float) $account->initial_balance + $accountIncome - $accountExpense,
                ];
            });

        return response()->json([
            'month' => $startOfMonth->format('Y-m'),
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'categories' => $categories,
            'accounts' => $accounts,
        ]);
    }
}
