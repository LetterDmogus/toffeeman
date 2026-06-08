<?php

namespace App\Http\Controllers\Api;

use App\Models\Promo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PromoController extends BaseController
{
    protected string $model = Promo::class;

    /**
     * Display a listing of promos.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Promo::with(['conditionMenuItem', 'rewardMenuItem', 'menuItems']);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }

        if ($request->boolean('all')) {
            return response()->json($query->orderBy('name')->get());
        }

        $promos = $query->latest()->paginate($request->integer('per_page', 15));

        return response()->json($promos);
    }

    /**
     * Store a newly created promo.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'unique:promos,code'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string'],
            'condition_type' => ['required', 'string', 'in:min_amount,min_qty,specific_item_qty'],
            'condition_value' => ['nullable', 'numeric', 'min:0'],
            'condition_menu_item_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'reward_type' => ['required', 'string', 'in:discount_percent,discount_nominal,free_item'],
            'reward_value' => ['nullable', 'numeric', 'min:0'],
            'reward_menu_item_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'reward_scope' => ['required', 'string', 'in:all,specific'],
            'menu_item_ids' => ['nullable', 'array'],
            'menu_item_ids.*' => ['integer', 'exists:menu_items,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'schedule_days' => ['nullable', 'array'],
            'schedule_days.*' => ['integer', 'between:0,6'],
            'is_active' => ['boolean'],
        ]);

        if (isset($validated['image']) && str_starts_with($validated['image'], 'data:image')) {
            $validated['image'] = $this->handleBase64Image($validated['image']);
        }

        $menuItemIds = $validated['menu_item_ids'] ?? [];
        unset($validated['menu_item_ids']);

        $promo = Promo::create($validated);

        if ($promo->reward_scope === 'specific' && ! empty($menuItemIds)) {
            $promo->menuItems()->sync($menuItemIds);
        }

        return response()->json($promo->load(['conditionMenuItem', 'rewardMenuItem', 'menuItems']), 201);
    }

    /**
     * Display the specified promo.
     */
    public function show(Promo $promo): JsonResponse
    {
        return response()->json($promo->load(['conditionMenuItem', 'rewardMenuItem', 'menuItems']));
    }

    /**
     * Update the specified promo.
     */
    public function update(Request $request, Promo $promo): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'unique:promos,code,'.$promo->id],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string'],
            'condition_type' => ['sometimes', 'string', 'in:min_amount,min_qty,specific_item_qty'],
            'condition_value' => ['nullable', 'numeric', 'min:0'],
            'condition_menu_item_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'reward_type' => ['sometimes', 'string', 'in:discount_percent,discount_nominal,free_item'],
            'reward_value' => ['nullable', 'numeric', 'min:0'],
            'reward_menu_item_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'reward_scope' => ['sometimes', 'string', 'in:all,specific'],
            'menu_item_ids' => ['nullable', 'array'],
            'menu_item_ids.*' => ['integer', 'exists:menu_items,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'schedule_days' => ['nullable', 'array'],
            'schedule_days.*' => ['integer', 'between:0,6'],
            'is_active' => ['boolean'],
        ]);

        if (isset($validated['image']) && str_starts_with($validated['image'], 'data:image')) {
            if ($promo->image) {
                Storage::disk('public')->delete($promo->image);
            }
            $validated['image'] = $this->handleBase64Image($validated['image']);
        }

        $menuItemIds = $validated['menu_item_ids'] ?? null;
        unset($validated['menu_item_ids']);

        $promo->update($validated);

        $scope = $validated['reward_scope'] ?? $promo->reward_scope;
        if ($scope === 'specific' && $menuItemIds !== null) {
            $promo->menuItems()->sync($menuItemIds);
        } elseif ($scope === 'all') {
            $promo->menuItems()->detach();
        }

        return response()->json($promo->load(['conditionMenuItem', 'rewardMenuItem', 'menuItems']));
    }

    /**
     * Remove the specified promo.
     */
    public function destroy(Promo $promo): JsonResponse
    {
        $promo->delete();

        return response()->json(['message' => 'Promo deleted.']);
    }

    /**
     * Restore the specified promo.
     */
    public function restore(Promo $promo): JsonResponse
    {
        $promo->restore();

        return response()->json($promo);
    }

    /**
     * Force delete the specified promo.
     */
    public function forceDelete(Promo $promo): JsonResponse
    {
        if ($promo->image) {
            Storage::disk('public')->delete($promo->image);
        }
        $promo->menuItems()->detach();
        $promo->forceDelete();

        return response()->json(['message' => 'Promo permanently deleted.']);
    }

    /**
     * Helper to process Base64 image strings.
     */
    private function handleBase64Image(?string $base64): ?string
    {
        if (! $base64) {
            return null;
        }

        if (str_starts_with($base64, 'http://') || str_starts_with($base64, 'https://') || str_starts_with($base64, '/storage/')) {
            // Strip /storage/ prefix if present to store just the path
            return str_replace('/storage/', '', $base64);
        }

        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
            $ext = strtolower($type[1]);
            $data = substr($base64, strpos($base64, ',') + 1);
            $data = base64_decode($data);

            if ($data === false) {
                return null;
            }

            $fileName = 'promos/'.Str::random(40).'.'.$ext;
            Storage::disk('public')->put($fileName, $data);

            return $fileName;
        }

        return $base64;
    }
}
