<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends BaseController
{
    protected string $model = Employee::class;

    /**
     * Display a listing of employees.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['user', 'position']);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('position_id')) {
            $query->where('position_id', $request->string('position_id'));
        }

        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }

        $employees = $query->latest()->paginate($request->integer('per_page', 15));

        return response()->json($employees);
    }

    /**
     * Store a newly created employee.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'salary' => ['required', 'numeric', 'min:0'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'status' => ['required', 'string', 'in:active,inactive,suspended'],
            'hired_at' => ['nullable', 'date'],
        ]);

        return DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => bcrypt($validated['password']),
                'status' => 'active',
            ]);

            // Assign employee role
            $user->assignRole('admin'); // Default employee role or based on logic

            $employee = Employee::create([
                'user_id' => $user->id,
                'position_id' => $validated['position_id'],
                'salary' => $validated['salary'],
                'status' => $validated['status'],
                'hired_at' => $validated['hired_at'],
            ]);

            return response()->json($employee->load(['user', 'position']), 201);
        });
    }

    /**
     * Display the specified employee.
     */
    public function show(Employee $employee): JsonResponse
    {
        return response()->json($employee->load(['user', 'position']));
    }

    /**
     * Update the specified employee.
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', "unique:users,email,{$employee->user_id}"],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'salary' => ['sometimes', 'numeric', 'min:0'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'status' => ['sometimes', 'string', 'in:active,inactive,suspended'],
            'hired_at' => ['nullable', 'date'],
        ]);

        return DB::transaction(function () use ($validated, $employee) {
            $userData = [];
            if (isset($validated['name'])) {
                $userData['name'] = $validated['name'];
            }
            if (isset($validated['email'])) {
                $userData['email'] = $validated['email'];
            }
            if (isset($validated['phone'])) {
                $userData['phone'] = $validated['phone'];
            }
            if (isset($validated['password'])) {
                $userData['password'] = bcrypt($validated['password']);
            }

            if (! empty($userData)) {
                $employee->user->update($userData);
            }

            $employeeData = [];
            if (isset($validated['salary'])) {
                $employeeData['salary'] = $validated['salary'];
            }
            if (isset($validated['position_id'])) {
                $employeeData['position_id'] = $validated['position_id'];
            }
            if (isset($validated['status'])) {
                $employeeData['status'] = $validated['status'];
            }
            if (isset($validated['hired_at'])) {
                $employeeData['hired_at'] = $validated['hired_at'];
            }

            if (! empty($employeeData)) {
                $employee->update($employeeData);
            }

            return response()->json($employee->fresh(['user', 'position']));
        });
    }

    /**
     * Remove the specified employee.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return response()->json(['message' => 'Employee soft deleted.']);
    }

    /**
     * Restore the specified employee.
     */
    public function restore($id): JsonResponse
    {
        $employee = Employee::onlyTrashed()->findOrFail($id);
        $employee->restore();

        return response()->json($employee->load(['user', 'position']));
    }

    /**
     * Force delete the specified employee.
     */
    public function forceDelete($id): JsonResponse
    {
        $employee = Employee::onlyTrashed()->findOrFail($id);
        $employee->forceDelete();

        return response()->json(['message' => 'Employee permanently deleted.']);
    }
}
