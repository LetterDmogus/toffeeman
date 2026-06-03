<?php

namespace App\Http\Controllers\Api;

use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PackageController extends BaseController
{
    protected string $model = Package::class;

    /**
     * Display a listing of packages.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Package::with(['packageItems.menuItem', 'packageItems.inventoryItem']);

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

        $packages = $query->latest()->paginate($request->integer('per_page', 15));

        return response()->json($packages);
    }

    /**
     * Store a newly created package.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
            'image_url' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $package = Package::create($validated);

        return response()->json($package, 201);
    }

    /**
     * Display the specified package.
     */
    public function show(Package $package): JsonResponse
    {
        return response()->json($package->load(['packageItems.menuItem', 'packageItems.inventoryItem']));
    }

    /**
     * Update the specified package.
     */
    public function update(Request $request, Package $package): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:active,inactive'],
            'image_url' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $package->update($validated);

        return response()->json($package);
    }

    /**
     * Remove the specified package.
     */
    public function destroy(Package $package): JsonResponse
    {
        $package->delete();

        return response()->json(['message' => 'Package deleted.']);
    }

    /**
     * Sync menu items to the package.
     */
    public function syncItems(Request $request, Package $package): JsonResponse
    {
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string'],
            'items.*.menu_item_id' => ['nullable', 'exists:menu_items,id'],
            'items.*.inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
        ]);

        $package->packageItems()->delete();
        foreach ($request->items as $item) {
            $package->packageItems()->create([
                'menu_item_id' => $item['menu_item_id'] ?? null,
                'inventory_item_id' => $item['inventory_item_id'] ?? null,
                'qty' => $item['qty'],
                'notes' => $item['notes'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Items synced successfully.']);
    }

    /**
     * Restore the specified package.
     */
    public function restore(Package $package): JsonResponse
    {
        $package->restore();

        return response()->json($package);
    }

    /**
     * Force delete the specified package.
     */
    public function forceDelete(Package $package): JsonResponse
    {
        $package->forceDelete();

        return response()->json(['message' => 'Package permanently deleted.']);
    }
}
