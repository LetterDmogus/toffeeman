<?php

namespace App\Http\Controllers\Api;

use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PositionController extends BaseController
{
    protected string $model = Position::class;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Position::query();

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

        $positions = $query->latest()->paginate($request->integer('per_page', 15));

        return response()->json($positions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starting_page' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Handle unique slug manually if needed or via validation
        $count = Position::where('slug', 'like', "{$validated['slug']}%")->count();
        if ($count > 0) {
            $validated['slug'] .= '-'.($count + 1);
        }

        $position = Position::create($validated);

        return response()->json($position, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Position $position): JsonResponse
    {
        return response()->json($position);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Position $position): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starting_page' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
            // Unique check
            $count = Position::where('slug', 'like', "{$validated['slug']}%")
                ->where('id', '!=', $position->id)
                ->count();
            if ($count > 0) {
                $validated['slug'] .= '-'.($count + 1);
            }
        }

        $position->update($validated);

        return response()->json($position);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Position $position): JsonResponse
    {
        $position->delete();

        return response()->json(['message' => 'Position soft deleted.']);
    }

    /**
     * Restore the specified resource.
     */
    public function restore($id): JsonResponse
    {
        $position = Position::onlyTrashed()->findOrFail($id);
        $position->restore();

        return response()->json($position);
    }

    /**
     * Force delete the specified resource.
     */
    public function forceDelete($id): JsonResponse
    {
        $position = Position::onlyTrashed()->findOrFail($id);
        $position->forceDelete();

        return response()->json(['message' => 'Position permanently deleted.']);
    }
}
