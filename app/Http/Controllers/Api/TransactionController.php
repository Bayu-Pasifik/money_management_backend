<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->transactions()->with(['category', 'account'])->latest('occurred_at');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->integer('account_id'));
        }

        if ($request->filled('month')) {
            $query->whereMonth('occurred_at', $request->integer('month'));
        }

        return $query->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'exists:accounts,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'type' => ['required', 'in:income,expense'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $transaction = $request->user()->transactions()->create([
            ...$data,
            'source' => 'manual',
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);

        return response()->json($transaction->load(['category', 'account']), 201);
    }

    public function show(Request $request, Transaction $transaction)
    {
        $this->authorizeOwner($request, $transaction);

        return $transaction->load(['category', 'account']);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $this->authorizeOwner($request, $transaction);

        $data = $request->validate([
            'account_id' => ['nullable', 'exists:accounts,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'type' => ['sometimes', 'in:income,expense'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['sometimes', 'date'],
        ]);

        $transaction->update($data);

        return $transaction->load(['category', 'account']);
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        $this->authorizeOwner($request, $transaction);
        $transaction->delete();

        return response()->json(null, 204);
    }

    private function authorizeOwner(Request $request, Transaction $transaction): void
    {
        abort_if($transaction->user_id !== $request->user()->id, 403);
    }
}
