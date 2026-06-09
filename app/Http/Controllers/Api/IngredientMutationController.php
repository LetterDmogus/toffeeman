<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\IngredientMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IngredientMutationController extends Controller
{
    /**
     * Display a listing of ingredient mutations.
     */
    public function index(Request $request): JsonResponse
    {
        $query = IngredientMutation::with(['ingredient', 'batch', 'creator', 'reference']);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('type', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('ingredient', function ($qi) use ($search) {
                        $qi->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('batch', function ($qb) use ($search) {
                        $qb->where('batch_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('ingredient_id')) {
            $query->where('ingredient_id', $request->integer('ingredient_id'));
        }

        $items = $query->latest()->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    /**
     * Store a newly created manual ingredient mutation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'ingredient_batch_id' => ['required', 'exists:ingredient_batches,id'],
            'type' => ['required', 'in:in,out'],
            'qty' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $ingredient = Ingredient::findOrFail($validated['ingredient_id']);
        $batch = IngredientBatch::where('ingredient_id', $ingredient->id)
            ->findOrFail($validated['ingredient_batch_id']);

        // Convert the input quantity (in main unit) to the base/small unit
        $factor = (float) ($ingredient->conversion_factor ?? 1.0);
        $convertedQty = $validated['qty'] * $factor;

        // If type is out, check if batch has enough raw stock
        if ($validated['type'] === 'out') {
            $rawBatchQty = (float) $batch->getRawOriginal('qty');
            if ($rawBatchQty < $convertedQty) {
                $availableDisplay = $rawBatchQty / $factor;
                throw ValidationException::withMessages([
                    'qty' => ["Stok batch tidak mencukupi. Tersedia: {$availableDisplay} {$ingredient->unit}."],
                ]);
            }
        }

        return DB::transaction(function () use ($validated, $ingredient, $batch, $convertedQty, $factor) {
            // Update the batch quantity
            $rawBatchQty = (float) $batch->getRawOriginal('qty');
            if ($validated['type'] === 'in') {
                $newRawQty = $rawBatchQty + $convertedQty;
            } else {
                $newRawQty = $rawBatchQty - $convertedQty;
            }

            // Set the batch quantity using the model mutator (which expects the main unit value)
            $batch->qty = $newRawQty / $factor;
            $batch->save();

            // Create the mutation log
            $mutation = IngredientMutation::create([
                'ingredient_id' => $ingredient->id,
                'ingredient_batch_id' => $batch->id,
                'type' => $validated['type'],
                'qty' => $convertedQty, // Stored in base unit
                'notes' => 'MUTASI MANUAL: '.$validated['notes'],
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Mutasi bahan baku berhasil dicatat.',
                'mutation' => $mutation->load(['ingredient', 'batch', 'creator']),
            ], 201);
        });
    }
}
