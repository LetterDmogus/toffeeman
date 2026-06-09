<?php

namespace App\Http\Controllers\Api;

use App\Models\Ingredient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IngredientController extends BaseController
{
    protected string $model = Ingredient::class;

    /**
     * Display a listing of ingredients.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ingredient::with(['category', 'creator', 'batches']);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('ingredient_category_id')) {
            $query->where('ingredient_category_id', $request->integer('ingredient_category_id'));
        }

        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }

        if ($request->boolean('all')) {
            return response()->json($query->orderBy('name')->get());
        }

        $items = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    /**
     * Store a newly created ingredient.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:ingredients,sku'],
            'ingredient_category_id' => ['required', 'exists:ingredient_categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'qty' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'small_unit' => ['required', 'string', 'max:50'],
            'conversion_factor' => ['required', 'numeric', 'min:0.01'],
            'min_qty' => ['required', 'numeric', 'min:0'],
            'storage_temperature' => ['nullable', 'string', 'max:100'],
            'small_unit_qty' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['qty'] = $validated['qty'] ?? 0;
        $validated['small_unit_qty'] = $validated['small_unit_qty'] ?? 0;
        $validated['slug'] = Str::slug($validated['name']);
        $validated['created_by'] = auth()->id();

        $item = Ingredient::create($validated);

        return response()->json($item->load(['category', 'creator', 'batches']), 201);
    }

    /**
     * Display the specified ingredient.
     */
    public function show(Ingredient $ingredient): JsonResponse
    {
        return response()->json($ingredient->load(['category', 'creator', 'batches']));
    }

    /**
     * Update the specified ingredient.
     */
    public function update(Request $request, Ingredient $ingredient): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', "unique:ingredients,sku,{$ingredient->id}"],
            'ingredient_category_id' => ['sometimes', 'exists:ingredient_categories,id'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'qty' => ['sometimes', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'small_unit' => ['sometimes', 'string', 'max:50'],
            'conversion_factor' => ['sometimes', 'numeric', 'min:0.01'],
            'min_qty' => ['sometimes', 'numeric', 'min:0'],
            'storage_temperature' => ['nullable', 'string', 'max:100'],
            'small_unit_qty' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $ingredient->update($validated);

        return response()->json($ingredient->fresh()->load(['category', 'creator', 'batches']));
    }

    /**
     * Remove the specified ingredient.
     */
    public function destroy(Ingredient $ingredient): JsonResponse
    {
        $ingredient->delete();

        return response()->json(['message' => 'Ingredient deleted.']);
    }

    /**
     * Restore the specified ingredient.
     */
    public function restore(Ingredient $ingredient): JsonResponse
    {
        $ingredient->restore();

        return response()->json($ingredient->load('category'));
    }

    /**
     * Force delete the specified ingredient.
     */
    public function forceDelete(Ingredient $ingredient): JsonResponse
    {
        $ingredient->forceDelete();

        return response()->json(['message' => 'Ingredient permanently deleted.']);
    }
}
