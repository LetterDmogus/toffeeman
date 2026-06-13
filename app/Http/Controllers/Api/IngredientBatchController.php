<?php

namespace App\Http\Controllers\Api;

use App\Models\IngredientBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngredientBatchController extends BaseController
{
    protected string $model = IngredientBatch::class;

    /**
     * Display a listing of ingredient batches.
     */
    public function index(Request $request): JsonResponse
    {
        $query = IngredientBatch::with(['ingredient', 'creator']);

        if ($request->filled('ingredient_id')) {
            $query->where('ingredient_id', $request->integer('ingredient_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where('batch_number', 'like', "%{$search}%");
        }

        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }

        $batches = $query->latest()->paginate($request->integer('per_page', 15));

        return response()->json($batches);
    }

    /**
     * Store a newly created ingredient batch.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'batch_number' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'expiration_date' => ['required', 'date'],
        ]);

        $validated['created_by'] = auth()->id();

        $batch = IngredientBatch::create($validated);

        return response()->json($batch, 201);
    }

    /**
     * Display the specified ingredient batch.
     */
    public function show(IngredientBatch $ingredientBatch): JsonResponse
    {
        return response()->json($ingredientBatch->load(['ingredient', 'creator']));
    }

    /**
     * Update the specified ingredient batch.
     */
    public function update(Request $request, IngredientBatch $ingredientBatch): JsonResponse
    {
        $validated = $request->validate([
            'batch_number' => ['sometimes', 'string', 'max:255'],
            'qty' => ['sometimes', 'numeric', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'expiration_date' => ['sometimes', 'date'],
        ]);

        $ingredientBatch->update($validated);

        return response()->json($ingredientBatch->fresh()->load(['ingredient', 'creator']));
    }

    /**
     * Remove the specified ingredient batch.
     */
    public function destroy(IngredientBatch $ingredientBatch): JsonResponse
    {
        $ingredientBatch->delete();

        return response()->json(['message' => 'Ingredient batch deleted.']);
    }

    /**
     * Restore the specified ingredient batch.
     */
    public function restore(IngredientBatch $ingredientBatch): JsonResponse
    {
        $ingredientBatch->restore();

        return response()->json($ingredientBatch->load(['ingredient', 'creator']));
    }

    /**
     * Force delete the specified ingredient batch.
     */
    public function forceDelete(IngredientBatch $ingredientBatch): JsonResponse
    {
        $ingredientBatch->forceDelete();

        return response()->json(['message' => 'Ingredient batch permanently deleted.']);
    }
}
