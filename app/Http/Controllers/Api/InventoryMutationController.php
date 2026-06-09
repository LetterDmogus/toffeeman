<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryIn;
use App\Models\InventoryItem;
use App\Models\InventoryOut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryMutationController extends Controller
{
    /**
     * Display a listing of stock mutations.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryOut::with(['creator', 'details.item'])
            ->where('reference_number', 'like', 'MUT-OUT-%');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('details.item', function ($qi) use ($search) {
                        $qi->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $items = $query->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        // Get all reference suffixes from the items
        $suffixes = $items->getCollection()->map(function ($out) {
            return str_replace('MUT-OUT-', '', $out->reference_number);
        })->toArray();

        // Fetch corresponding incoming records with their details in one query to avoid N+1 queries
        $incomingRecords = InventoryIn::with('details')
            ->whereIn('reference_number', array_map(fn ($s) => "MUT-IN-{$s}", $suffixes))
            ->get()
            ->keyBy(fn ($in) => str_replace('MUT-IN-', '', $in->reference_number));

        $items->getCollection()->transform(function ($out) use ($incomingRecords) {
            $detail = $out->details->first();
            $item = $detail?->item;

            $fromStatus = '—';
            $qty = 0;
            if ($detail) {
                if ((float) $detail->qty_good > 0) {
                    $fromStatus = 'Baik';
                    $qty = (float) $detail->qty_good;
                } elseif ((float) $detail->qty_fair > 0) {
                    $fromStatus = 'Kurang Baik';
                    $qty = (float) $detail->qty_fair;
                } elseif ((float) $detail->qty_damaged > 0) {
                    $fromStatus = 'Rusak';
                    $qty = (float) $detail->qty_damaged;
                }
            }

            $toStatus = '—';
            $suffix = str_replace('MUT-OUT-', '', $out->reference_number);
            $matchingIn = $incomingRecords->get($suffix);
            if ($matchingIn) {
                $inDetail = $matchingIn->details->first();
                if ($inDetail) {
                    if ((float) $inDetail->qty_good > 0) {
                        $toStatus = 'Baik';
                    } elseif ((float) $inDetail->qty_fair > 0) {
                        $toStatus = 'Kurang Baik';
                    } elseif ((float) $inDetail->qty_damaged > 0) {
                        $toStatus = 'Rusak';
                    }
                }
            }

            // Fallback to regex if matching In record details are missing
            if ($toStatus === '—' && $out->notes && preg_match('/ke (Baik|Kurang Baik|Rusak)/u', $out->notes, $matches)) {
                $toStatus = $matches[1];
            }

            return [
                'id' => $out->id,
                'date' => $out->date ? $out->date->format('Y-m-d') : null,
                'reference_number' => $out->reference_number,
                'item_name' => $item?->name ?? '—',
                'qty' => $qty.' '.($item?->unit ?? ''),
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'creator' => $out->creator?->name ?? '—',
                'notes' => str_replace('MUTASI STATUS (KELUAR): ', '', $out->notes),
            ];
        });

        return response()->json($items);
    }

    /**
     * Process a stock status mutation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'qty' => ['required', 'numeric', 'min:0.01'],
            'from_status' => ['required', 'in:qty_good,qty_fair,qty_damaged'],
            'to_status' => ['required', 'in:qty_good,qty_fair,qty_damaged', 'different:from_status'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $item = InventoryItem::findOrFail($validated['inventory_item_id']);

        // Check if there is enough stock in the source status
        $availableQty = (float) $item->{$validated['from_status']};
        if ($availableQty < (float) $validated['qty']) {
            throw ValidationException::withMessages([
                'qty' => ["Stok tidak mencukupi. Tersedia: {$availableQty} {$item->unit}."],
            ]);
        }

        return DB::transaction(function () use ($validated, $item) {
            $dateToday = date('Y-m-d');
            $refSuffix = date('Ymd-His');

            $statusLabels = [
                'qty_good' => 'Baik',
                'qty_fair' => 'Kurang Baik',
                'qty_damaged' => 'Rusak',
            ];

            $fromLabel = $statusLabels[$validated['from_status']];
            $toLabel = $statusLabels[$validated['to_status']];

            $notes = $validated['notes'] ?? "Mutasi status stok {$item->name} dari {$fromLabel} ke {$toLabel}";

            // Map status field names to detail column names (qty_good, qty_fair, qty_damaged)
            $fromField = str_replace('qty_', '', $validated['from_status']);
            $toField = str_replace('qty_', '', $validated['to_status']);

            // 1. Create Outgoing Inventory Entry (Barang Keluar) to deduct from source status
            $inventoryOut = InventoryOut::create([
                'reference_number' => "MUT-OUT-{$refSuffix}",
                'date' => $dateToday,
                'notes' => 'MUTASI STATUS (KELUAR): '.$notes,
                'created_by' => auth()->id(),
            ]);

            $inventoryOut->details()->create([
                'inventory_item_id' => $item->id,
                'qty_good' => $fromField === 'good' ? $validated['qty'] : 0,
                'qty_fair' => $fromField === 'fair' ? $validated['qty'] : 0,
                'qty_damaged' => $fromField === 'damaged' ? $validated['qty'] : 0,
            ]);

            // 2. Create Incoming Inventory Entry (Barang Masuk) to add to destination status
            $inventoryIn = InventoryIn::create([
                'reference_number' => "MUT-IN-{$refSuffix}",
                'date' => $dateToday,
                'notes' => 'MUTASI STATUS (MASUK): '.$notes,
                'created_by' => auth()->id(),
            ]);

            $inventoryIn->details()->create([
                'inventory_item_id' => $item->id,
                'qty_good' => $toField === 'good' ? $validated['qty'] : 0,
                'qty_fair' => $toField === 'fair' ? $validated['qty'] : 0,
                'qty_damaged' => $toField === 'damaged' ? $validated['qty'] : 0,
                'price' => 0, // Mutation is internal, price is 0
            ]);

            return response()->json([
                'message' => 'Mutasi stok berhasil diproses.',
                'inventory_out' => $inventoryOut->load('details'),
                'inventory_in' => $inventoryIn->load('details'),
                'item' => $item->refresh(),
            ], 201);
        });
    }
}
