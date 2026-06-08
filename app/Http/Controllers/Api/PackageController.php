<?php

namespace App\Http\Controllers\Api;

use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        if (isset($validated['image_url'])) {
            $validated['image_url'] = $this->handleBase64Image($validated['image_url']);
        }

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

        if (isset($validated['image_url'])) {
            $validated['image_url'] = $this->handleBase64Image($validated['image_url']);
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

    /**
     * Decode and store base64 image data URI.
     */
    protected function handleBase64Image(?string $base64): ?string
    {
        if (! $base64) {
            return null;
        }

        if (str_starts_with($base64, 'http://') || str_starts_with($base64, 'https://') || str_starts_with($base64, '/storage/')) {
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
            Storage::disk('public')->put('packages/'.$filename, $data);

            return Storage::url('packages/'.$filename);
        }

        return $base64;
    }
}
