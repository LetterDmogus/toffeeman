<?php

namespace App\Http\Controllers\Api;

use App\Models\InventoryCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InventoryCategoryController extends BaseController
{
    protected string $model = InventoryCategory::class;

    /**
     * Display a listing of inventory categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryCategory::query();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }

        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }

        if ($request->boolean('all')) {
            return response()->json($query->orderBy('name')->get());
        }

        $categories = $query->latest()->paginate($request->integer('per_page', 15));

        return response()->json($categories);
    }

    /**
     * Store a newly created inventory category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category = InventoryCategory::create($validated);

        return response()->json($category, 201);
    }

    /**
     * Display the specified inventory category.
     */
    public function show(InventoryCategory $inventoryCategory): JsonResponse
    {
        return response()->json($inventoryCategory);
    }

    /**
     * Update the specified inventory category.
     */
    public function update(Request $request, InventoryCategory $inventoryCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $inventoryCategory->update($validated);

        return response()->json($inventoryCategory);
    }

    /**
     * Remove the specified inventory category.
     */
    public function destroy(InventoryCategory $inventoryCategory): JsonResponse
    {
        $inventoryCategory->delete();

        return response()->json(['message' => 'Inventory category deleted.']);
    }

    /**
     * Restore the specified inventory category.
     */
    public function restore(InventoryCategory $inventoryCategory): JsonResponse
    {
        $inventoryCategory->restore();

        return response()->json($inventoryCategory);
    }

    /**
     * Force delete the specified inventory category.
     */
    public function forceDelete(InventoryCategory $inventoryCategory): JsonResponse
    {
        $inventoryCategory->forceDelete();

        return response()->json(['message' => 'Inventory category permanently deleted.']);
    }
}
