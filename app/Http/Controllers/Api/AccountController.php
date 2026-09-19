<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->accounts()->orderBy('name')->get()->map(
            fn (Account $account) => $this->withBalance($account)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:cash,bank,ewallet,other'],
            'initial_balance' => ['nullable', 'numeric'],
        ]);

        $account = $request->user()->accounts()->create($data);

        return response()->json($this->withBalance($account), 201);
    }

    public function show(Request $request, Account $account)
    {
        $this->authorizeOwner($request, $account);

        return $this->withBalance($account);
    }

    public function update(Request $request, Account $account)
    {
        $this->authorizeOwner($request, $account);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:cash,bank,ewallet,other'],
            'initial_balance' => ['sometimes', 'numeric'],
        ]);

        $account->update($data);

        return $this->withBalance($account);
    }

    public function destroy(Request $request, Account $account)
    {
        $this->authorizeOwner($request, $account);
        $account->delete();

        return response()->json(null, 204);
    }

    private function withBalance(Account $account): array
    {
        $income = Transaction::where('account_id', $account->id)->where('type', 'income')->sum('amount');
        $expense = Transaction::where('account_id', $account->id)->where('type', 'expense')->sum('amount');

        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type,
            'initial_balance' => $account->initial_balance,
            'balance' => (float) $account->initial_balance + $income - $expense,
        ];
    }

    private function authorizeOwner(Request $request, Account $account): void
    {
        abort_if($account->user_id !== $request->user()->id, 403);
    }
}
