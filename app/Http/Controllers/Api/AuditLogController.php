<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if ($user->role !== 'superadmin' && ! $user->hasPermissionTo('view-audit-logs')) {
            abort(403, 'Unauthorized. You do not have permission to view activity logs.');
        }
        $request->validate([
            'auditable_type' => ['required', 'string'],
            'auditable_id' => ['nullable', 'numeric'],
        ]);

        // Map short names if needed, but using fully-qualified model names is standard
        $type = $request->string('auditable_type');
        if (! str_contains($type, '\\')) {
            $type = 'App\\Models\\'.ucfirst($type);
        }

        $query = AuditLog::with(['user'])
            ->where('auditable_type', $type);

        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', $request->integer('auditable_id'));
        }

        $logs = $query->latest()->paginate(15);

        return response()->json($logs);
    }
}
