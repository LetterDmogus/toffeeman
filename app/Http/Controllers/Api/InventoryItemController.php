<?php

namespace App\Http\Controllers\Api;

use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InventoryItemController extends BaseController
{
    protected string $model = InventoryItem::class;

    /**
     * Display a listing of inventory items.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryItem::with(['category', 'creator']);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('inventory_category_id')) {
            $query->where('inventory_category_id', $request->integer('inventory_category_id'));
        }

        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }

        $items = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    /**
     * Store a newly created inventory item.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:inventory_items,sku'],
            'inventory_category_id' => ['required', 'exists:inventory_categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'qty' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'min_qty' => ['required', 'numeric', 'min:0'],
            'purchase_date' => ['nullable', 'date'],
            'qty_good' => ['required', 'numeric', 'min:0'],
            'qty_fair' => ['required', 'numeric', 'min:0'],
            'qty_damaged' => ['required', 'numeric', 'min:0'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['created_by'] = auth()->id();

        $item = InventoryItem::create($validated);

        return response()->json($item->load(['category', 'creator']), 201);
    }

    /**
     * Display the specified inventory item.
     */
    public function show(InventoryItem $inventoryItem): JsonResponse
    {
        return response()->json($inventoryItem->load(['category', 'creator']));
    }

    /**
     * Update the specified inventory item.
     */
    public function update(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', "unique:inventory_items,sku,{$inventoryItem->id}"],
            'inventory_category_id' => ['sometimes', 'exists:inventory_categories,id'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'qty' => ['sometimes', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'min_qty' => ['sometimes', 'numeric', 'min:0'],
            'purchase_date' => ['nullable', 'date'],
            'qty_good' => ['sometimes', 'numeric', 'min:0'],
            'qty_fair' => ['sometimes', 'numeric', 'min:0'],
            'qty_damaged' => ['sometimes', 'numeric', 'min:0'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $inventoryItem->update($validated);

        return response()->json($inventoryItem->fresh()->load(['category', 'creator']));
    }

    /**
     * Remove the specified inventory item.
     */
    public function destroy(InventoryItem $inventoryItem): JsonResponse
    {
        $inventoryItem->delete();

        return response()->json(['message' => 'Inventory item deleted.']);
    }

    /**
     * Restore the specified inventory item.
     */
    public function restore(InventoryItem $inventoryItem): JsonResponse
    {
        $inventoryItem->restore();

        return response()->json($inventoryItem->load('category'));
    }

    /**
     * Force delete the specified inventory item.
     */
    public function forceDelete(InventoryItem $inventoryItem): JsonResponse
    {
        $inventoryItem->forceDelete();

        return response()->json(['message' => 'Inventory item permanently deleted.']);
    }
}
