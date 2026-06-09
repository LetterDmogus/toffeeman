<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    /**
     * Display a listing of roles and positions with their permissions.
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->filled('page') || $request->filled('search')) {
            $query = Role::query()->with('permissions');
            if ($request->filled('search')) {
                $query->where('name', 'like', "%{$request->string('search')}%");
            }
            $roles = $query->latest()->paginate($request->integer('per_page', 15));

            return response()->json($roles);
        }

        $roles = Role::with('permissions')->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'type' => 'role',
            'permissions' => $r->permissions,
        ]);

        $positions = Position::with('permissions')->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'type' => 'position',
            'permissions' => $p->permissions,
        ]);

        return response()->json([
            'roles' => $roles,
            'positions' => $positions,
        ]);
    }

    /**
     * Display a listing of all permissions.
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::all();

        return response()->json($permissions);
    }

    /**
     * Store a newly created role.
     */
    public function storeRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:roles,name'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        return response()->json($role, 201);
    }

    /**
     * Remove the specified role.
     */
    public function destroyRole(Role $role): JsonResponse
    {
        if (in_array($role->name, ['superadmin', 'admin', 'user', 'customer'])) {
            return response()->json(['message' => 'Role bawaan sistem tidak dapat dihapus.'], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role berhasil dihapus.']);
    }

    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        if ($role->name === 'superadmin') {
            return response()->json(['message' => 'Izin untuk role superadmin tidak dapat diubah.'], 422);
        }

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'type' => 'role',
            'permissions' => $role->load('permissions')->permissions,
        ]);
    }

    /**
     * Sync permissions for a specific position.
     */
    public function syncPositionPermissions(Request $request, Position $position): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $position->syncPermissions($validated['permissions'] ?? []);

        return response()->json([
            'id' => $position->id,
            'name' => $position->name,
            'type' => 'position',
            'permissions' => $position->load('permissions')->permissions,
        ]);
    }
}
