<?php

namespace App\Http\Controllers\Api;

use App\Models\IngredientCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IngredientCategoryController extends BaseController
{
    protected string $model = IngredientCategory::class;

    /**
     * Display a listing of ingredient categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = IngredientCategory::query();

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
     * Store a newly created ingredient category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category = IngredientCategory::create($validated);

        return response()->json($category, 201);
    }

    /**
     * Display the specified ingredient category.
     */
    public function show(IngredientCategory $ingredientCategory): JsonResponse
    {
        return response()->json($ingredientCategory);
    }

    /**
     * Update the specified ingredient category.
     */
    public function update(Request $request, IngredientCategory $ingredientCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $ingredientCategory->update($validated);

        return response()->json($ingredientCategory);
    }

    /**
     * Remove the specified ingredient category.
     */
    public function destroy(IngredientCategory $ingredientCategory): JsonResponse
    {
        $ingredientCategory->delete();

        return response()->json(['message' => 'Ingredient category deleted.']);
    }

    /**
     * Restore the specified ingredient category.
     */
    public function restore(IngredientCategory $ingredientCategory): JsonResponse
    {
        $ingredientCategory->restore();

        return response()->json($ingredientCategory);
    }

    /**
     * Force delete the specified ingredient category.
     */
    public function forceDelete(IngredientCategory $ingredientCategory): JsonResponse
    {
        $ingredientCategory->forceDelete();

        return response()->json(['message' => 'Ingredient category permanently deleted.']);
    }
}
