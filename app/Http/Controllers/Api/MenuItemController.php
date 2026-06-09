<?php

namespace App\Http\Controllers\Api;

use App\Models\InventoryItem;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuItemController extends BaseController
{
    protected string $model = MenuItem::class;

    /**
     * Display a listing with optional search and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MenuItem::with(['category', 'addOns', 'options.values', 'recipeItems.ingredient']);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($catQuery) use ($search) {
                        $catQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }

        $items = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'status' => ['required', 'in:available,sold_out,draft'],
            'inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'image_url' => ['nullable', 'string'],
            'allergens' => ['nullable', 'array'],
            'tags' => ['nullable', 'array'],
            'calories' => ['nullable', 'integer', 'min:0'],
            'preparation_time' => ['nullable', 'integer', 'min:0'],
            'add_on_ids' => ['nullable', 'array'],
            'add_on_ids.*' => ['integer', 'exists:add_ons,id'],
            'options' => ['nullable', 'array'],
            'options.*.name' => ['required', 'string'],
            'options.*.is_required' => ['boolean'],
            'options.*.type' => ['required', 'in:single,multiple'],
            'options.*.values' => ['required', 'array'],
            'options.*.values.*.name' => ['required', 'string'],
            'options.*.values.*.additional_price' => ['required', 'numeric', 'min:0'],
            'recipe' => ['nullable', 'array'],
            'recipe.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'recipe.*.qty' => ['required', 'numeric', 'min:0.01'],
        ]);

        if (($validated['status'] ?? null) === 'available' && ! empty($validated['inventory_item_id'])) {
            $invItem = InventoryItem::find($validated['inventory_item_id']);
            if ($invItem && $invItem->qty <= 0) {
                return response()->json([
                    'message' => 'Stok barang inventoris kosong. Menu tidak dapat diatur sebagai Tersedia.',
                    'errors' => ['status' => ['Stok barang inventoris kosong.']],
                ], 422);
            }
        }

        $itemData = collect($validated)->except(['add_on_ids', 'options', 'recipe'])->all();
        $itemData['slug'] = Str::slug($validated['name']);

        if (isset($validated['image_url'])) {
            $itemData['image_url'] = $this->handleBase64Image($validated['image_url']);
        }

        $item = MenuItem::create($itemData);

        if ($request->has('recipe')) {
            foreach ($request->recipe as $recipeData) {
                $item->recipeItems()->create([
                    'ingredient_id' => $recipeData['ingredient_id'],
                    'qty' => $recipeData['qty'],
                ]);
            }
        }

        return response()->json($item->load(['category', 'addOns', 'options.values', 'recipeItems.ingredient']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MenuItem $menuItem): JsonResponse
    {
        return response()->json($menuItem->load(['category', 'addOns', 'options.values', 'recipeItems.ingredient']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MenuItem $menuItem): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'status' => ['sometimes', 'in:available,sold_out,draft'],
            'inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'image_url' => ['nullable', 'string'],
            'allergens' => ['nullable', 'array'],
            'tags' => ['nullable', 'array'],
            'calories' => ['nullable', 'integer', 'min:0'],
            'preparation_time' => ['nullable', 'integer', 'min:0'],
            'add_on_ids' => ['nullable', 'array'],
            'add_on_ids.*' => ['integer', 'exists:add_ons,id'],
            'options' => ['nullable', 'array'],
            'options.*.name' => ['required', 'string'],
            'options.*.is_required' => ['boolean'],
            'options.*.type' => ['required', 'in:single,multiple'],
            'options.*.values' => ['required', 'array'],
            'options.*.values.*.name' => ['required', 'string'],
            'options.*.values.*.additional_price' => ['required', 'numeric', 'min:0'],
            'recipe' => ['nullable', 'array'],
            'recipe.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'recipe.*.qty' => ['required', 'numeric', 'min:0.01'],
        ]);

        $newStatus = array_key_exists('status', $validated) ? $validated['status'] : $menuItem->status;
        $newInvId = array_key_exists('inventory_item_id', $validated) ? $validated['inventory_item_id'] : $menuItem->inventory_item_id;

        if ($newStatus === 'available' && ! empty($newInvId)) {
            $invItem = InventoryItem::find($newInvId);
            if ($invItem && $invItem->qty <= 0) {
                return response()->json([
                    'message' => 'Stok barang inventoris kosong. Menu tidak dapat diatur sebagai Tersedia.',
                    'errors' => ['status' => ['Stok barang inventoris kosong.']],
                ], 422);
            }
        }

        $itemData = collect($validated)->except(['add_on_ids', 'options', 'recipe'])->all();

        if (isset($validated['name'])) {
            $itemData['slug'] = Str::slug($validated['name']);
        }

        if (isset($validated['image_url'])) {
            $itemData['image_url'] = $this->handleBase64Image($validated['image_url']);
        }

        $menuItem->update($itemData);

        if ($request->has('add_on_ids')) {
            $menuItem->addOns()->sync($request->add_on_ids);
        }

        if ($request->has('options')) {
            $menuItem->options()->delete(); // Simple approach: delete and recreate
            foreach ($request->options as $optionData) {
                $option = $menuItem->options()->create([
                    'name' => $optionData['name'],
                    'is_required' => $optionData['is_required'] ?? false,
                    'type' => $optionData['type'] ?? 'single',
                ]);

                foreach ($optionData['values'] as $valueData) {
                    $option->values()->create($valueData);
                }
            }
        }

        if ($request->has('recipe')) {
            $menuItem->recipeItems()->delete(); // delete and recreate
            foreach ($request->recipe as $recipeData) {
                $menuItem->recipeItems()->create([
                    'ingredient_id' => $recipeData['ingredient_id'],
                    'qty' => $recipeData['qty'],
                ]);
            }
        }

        return response()->json($menuItem->fresh()->load(['category', 'addOns', 'options.values', 'recipeItems.ingredient']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MenuItem $menuItem): JsonResponse
    {
        $menuItem->delete();

        return response()->json(['message' => 'Menu item deleted.']);
    }

    /**
     * Decode and store base64 image data URI.
     */
    protected function handleBase64Image(?string $base64): ?string
    {
        if (! $base64) {
            return null;
        }

        if (str_starts_with($base64, 'http://') || str_starts_with($base64, 'https://')) {
            return $base64;
        }

        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
            $ext = strtolower($type[1]);
            $data = substr($base64, strpos($base64, ',') + 1);
            $data = base64_decode($data);

            if ($data === false) {
                return null;
            }

            $filename = Str::random(40).'.'.$ext;
            Storage::disk('public')->put('uploads/'.$filename, $data);

            return Storage::url('uploads/'.$filename);
        }

        return $base64;
    }

    /**
     * Restore the specified resource from storage.
     */
    public function restore(MenuItem $menuItem): JsonResponse
    {
        $menuItem->restore();

        return response()->json($menuItem->load('category'));
    }

    /**
     * Force delete the specified resource from storage.
     */
    public function forceDelete(MenuItem $menuItem): JsonResponse
    {
        $menuItem->forceDelete();

        return response()->json(['message' => 'Menu item permanently deleted.']);
    }
}
