<?php

namespace App\Http\Controllers\Api;

use App\Models\InventoryIn;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InventoryInController extends BaseController
{
    protected string $model = InventoryIn::class;

    /**
     * Display a listing of incoming inventory records.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryIn::with(['creator', 'attachments'])
            ->withCount('details');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where('reference_number', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%");
        }

        $items = $query->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    /**
     * Store a newly created incoming inventory record.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference_number' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'details.*.qty_good' => ['required', 'numeric', 'min:0'],
            'details.*.qty_fair' => ['required', 'numeric', 'min:0'],
            'details.*.qty_damaged' => ['required', 'numeric', 'min:0'],
            'details.*.price' => ['required', 'numeric', 'min:0'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:2048'], // Max 2MB per photo
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $inventoryIn = InventoryIn::create([
                'reference_number' => $validated['reference_number'] ?? null,
                'date' => $validated['date'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $totalAmount = 0.00;
            foreach ($validated['details'] as $detail) {
                $inventoryIn->details()->create([
                    'inventory_item_id' => $detail['inventory_item_id'],
                    'qty_good' => $detail['qty_good'],
                    'qty_fair' => $detail['qty_fair'],
                    'qty_damaged' => $detail['qty_damaged'],
                    'price' => $detail['price'],
                ]);
                $qty = (float) $detail['qty_good'] + (float) $detail['qty_fair'] + (float) $detail['qty_damaged'];
                $totalAmount += $qty * (float) $detail['price'];
            }

            if ($totalAmount > 0) {
                Transaction::create([
                    'transaction_number' => 'TRX-'.strtoupper(Str::random(10)),
                    'type' => 'expense',
                    'category' => 'inventory_purchase',
                    'reference_type' => get_class($inventoryIn),
                    'reference_id' => $inventoryIn->id,
                    'user_id' => auth()->id(),
                    'amount' => $totalAmount,
                    'payment_method' => 'cash',
                    'payment_status' => 'completed',
                    'description' => 'Pembelian inventaris'.($inventoryIn->reference_number ? ' ('.$inventoryIn->reference_number.')' : ''),
                    'transaction_date' => $inventoryIn->date,
                ]);
            }

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('inventory', 'public');
                    $inventoryIn->attachments()->create([
                        'file_path' => $path,
                    ]);
                }
            }

            return response()->json(
                $inventoryIn->load(['details.item', 'attachments', 'creator']),
                201
            );
        });
    }

    /**
     * Display the specified incoming inventory record.
     */
    public function show(InventoryIn $inventoryIn): JsonResponse
    {
        return response()->json($inventoryIn->load(['details.item', 'attachments', 'creator']));
    }

    /**
     * Remove the specified record and revert inventory changes.
     */
    public function destroy(InventoryIn $inventoryIn): JsonResponse
    {
        DB::transaction(function () use ($inventoryIn) {
            // Delete details first to trigger their deleted event (decrements inventory item stock)
            foreach ($inventoryIn->details as $detail) {
                $detail->delete();
            }

            // Delete associated transactions
            Transaction::where('reference_type', get_class($inventoryIn))
                ->where('reference_id', $inventoryIn->id)
                ->delete();

            // Delete attachments from storage and DB
            foreach ($inventoryIn->attachments as $attachment) {
                if (Storage::exists($attachment->file_path)) {
                    Storage::delete($attachment->file_path);
                }
                $attachment->delete();
            }

            $inventoryIn->delete();
        });

        return response()->json(['message' => 'Record deleted and stock changes reverted.']);
    }
}
