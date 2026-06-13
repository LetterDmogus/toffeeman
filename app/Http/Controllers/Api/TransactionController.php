<?php

namespace App\Http\Controllers\Api;

use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TransactionController extends BaseController
{
    protected string $model = Transaction::class;

    /**
     * Display a listing of transactions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::with(['user']);

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }

        $transactions = $query->latest('transaction_date')
            ->paginate($request->integer('per_page', 15));

        return response()->json($transactions);
    }

    /**
     * Store a newly created transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:income,expense'],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,e_wallet,qris,card'],
            'payment_status' => ['required', 'string', 'in:pending,completed,failed'],
            'description' => ['nullable', 'string'],
            'transaction_date' => ['required', 'date'],
        ]);

        $validated['transaction_number'] = 'TRX-'.strtoupper(Str::random(10));
        $validated['user_id'] = auth()->id();

        $transaction = Transaction::create($validated);

        return response()->json($transaction, 201);
    }

    /**
     * Display the specified transaction.
     */
    public function show(Transaction $transaction): JsonResponse
    {
        return response()->json($transaction->load(['user']));
    }

    /**
     * Update the specified transaction.
     */
    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['sometimes', 'string', 'max:100'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'payment_method' => ['sometimes', 'string', 'in:cash,bank_transfer,e_wallet,qris,card'],
            'payment_status' => ['sometimes', 'string', 'in:pending,completed,failed'],
            'description' => ['nullable', 'string'],
            'transaction_date' => ['sometimes', 'date'],
        ]);

        $transaction->update($validated);

        return response()->json($transaction->fresh()->load(['user']));
    }

    /**
     * Remove the specified transaction.
     */
    public function destroy(Transaction $transaction): JsonResponse
    {
        $transaction->delete();

        return response()->json(['message' => 'Transaction deleted.']);
    }

    /**
     * Restore the specified transaction.
     */
    public function restore(Transaction $transaction): JsonResponse
    {
        $transaction->restore();

        return response()->json($transaction->load(['user']));
    }

    /**
     * Force delete the specified transaction.
     */
    public function forceDelete(Transaction $transaction): JsonResponse
    {
        $transaction->forceDelete();

        return response()->json(['message' => 'Transaction permanently deleted.']);
    }
}
