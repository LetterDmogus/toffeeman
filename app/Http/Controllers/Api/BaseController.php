<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    protected string $model;

    /**
     * Bulk restore resource from trash.
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array']);

        $count = $this->model::onlyTrashed()
            ->whereIn('id', $request->ids)
            ->restore();

        return response()->json(['message' => "{$count} items restored."]);
    }

    /**
     * Bulk soft delete resource.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array']);

        $count = $this->model::whereIn('id', $request->ids)
            ->delete();

        return response()->json(['message' => "{$count} items soft deleted."]);
    }

    /**
     * Bulk force delete resource.
     */
    public function bulkForceDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array']);

        $count = $this->model::onlyTrashed()
            ->whereIn('id', $request->ids)
            ->forceDelete();

        return response()->json(['message' => "{$count} items permanently deleted."]);
    }
}
