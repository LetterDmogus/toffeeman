<?php

namespace App\Http\Controllers\Api;

use App\Models\InventoryOut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InventoryOutController extends BaseController
{
    protected string $model = InventoryOut::class;

    /**
     * Display a listing of outgoing inventory records.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryOut::with(['creator', 'attachments'])
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
     * Store a newly created outgoing inventory record.
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
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:2048'], // Max 2MB per photo
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $inventoryOut = InventoryOut::create([
                'reference_number' => $validated['reference_number'] ?? null,
                'date' => $validated['date'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['details'] as $detail) {
                $inventoryOut->details()->create([
                    'inventory_item_id' => $detail['inventory_item_id'],
                    'qty_good' => $detail['qty_good'],
                    'qty_fair' => $detail['qty_fair'],
                    'qty_damaged' => $detail['qty_damaged'],
                ]);
            }

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('inventory', 'public');
                    $inventoryOut->attachments()->create([
                        'file_path' => $path,
                    ]);
                }
            }

            return response()->json(
                $inventoryOut->load(['details.item', 'attachments', 'creator']),
                201
            );
        });
    }

    /**
     * Display the specified outgoing inventory record.
     */
    public function show(InventoryOut $inventoryOut): JsonResponse
    {
        return response()->json($inventoryOut->load(['details.item', 'attachments', 'creator']));
    }

    /**
     * Remove the specified record and revert inventory changes.
     */
    public function destroy(InventoryOut $inventoryOut): JsonResponse
    {
        DB::transaction(function () use ($inventoryOut) {
            // Delete details first to trigger their deleted event (increments inventory item stock back)
            foreach ($inventoryOut->details as $detail) {
                $detail->delete();
            }

            // Delete attachments from storage and DB
            foreach ($inventoryOut->attachments as $attachment) {
                if (Storage::exists($attachment->file_path)) {
                    Storage::delete($attachment->file_path);
                }
                $attachment->delete();
            }

            $inventoryOut->delete();
        });

        return response()->json(['message' => 'Record deleted and stock changes reverted.']);
    }
}
