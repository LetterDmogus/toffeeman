<?php

namespace App\Http\Controllers\Api;

use App\Models\AddOn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddOnController extends BaseController
{
    protected string $model = AddOn::class;

    public function index(Request $request): JsonResponse
    {
        $query = AddOn::query();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where('name', 'like', "%{$search}%");
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $item = AddOn::create($validated);

        return response()->json($item, 201);
    }

    public function show(AddOn $addOn): JsonResponse
    {
        return response()->json($addOn);
    }

    public function update(Request $request, AddOn $addOn): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $addOn->update($validated);

        return response()->json($addOn);
    }

    public function destroy(AddOn $addOn): JsonResponse
    {
        $addOn->delete();

        return response()->json(['message' => 'Add-on deleted.']);
    }

    public function restore(AddOn $addOn): JsonResponse
    {
        $addOn->restore();

        return response()->json($addOn);
    }

    public function forceDelete(AddOn $addOn): JsonResponse
    {
        $addOn->forceDelete();

        return response()->json(['message' => 'Add-on permanently deleted.']);
    }
}
