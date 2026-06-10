<?php

namespace App\Http\Controllers\Api;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttendanceController extends BaseController
{
    protected string $model = Attendance::class;

    /**
     * Display a paginated listing of attendance logs with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with([
            'employee.user.position',
            'verifier',
        ]);

        // Filter by date / month / year / range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->string('start_date'))->startOfDay(),
                Carbon::parse($request->string('end_date'))->endOfDay(),
            ]);
        } elseif ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('created_at', $request->integer('month'))
                ->whereYear('created_at', $request->integer('year'));
        } elseif ($request->filled('month')) {
            $query->whereMonth('created_at', $request->integer('month'))
                ->whereYear('created_at', now()->year);
        } elseif ($request->filled('year')) {
            $query->whereYear('created_at', $request->integer('year'));
        } elseif ($request->filled('date')) {
            $query->whereDate('created_at', $request->string('date'));
        } elseif (! $request->boolean('export') && ! $request->filled('search')) {
            // Default to today if no date filter, no search query, and not exporting
            $query->whereDate('created_at', Carbon::today()->toDateString());
        }

        // Filter by position
        if ($request->filled('position_id')) {
            $query->whereHas('employee.user', function ($q) use ($request) {
                $q->where('position_id', $request->string('position_id'));
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        // Search by employee name
        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->whereHas('employee.user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }

        // CSV Export Option
        if ($request->boolean('export')) {
            return $this->exportToCsv($query);
        }

        $attendances = $query->latest()->paginate($request->integer('per_page', 15));

        // Append photo URL to each attendance
        $attendances->through(function (Attendance $attendance) {
            return [
                'id' => $attendance->id,
                'type' => $attendance->type,
                'status' => $attendance->status,
                'notes' => $attendance->notes,
                'photo_url' => $attendance->photo_path
                    ? Storage::disk('public')->url($attendance->photo_path)
                    : null,
                'latitude' => $attendance->latitude,
                'longitude' => $attendance->longitude,
                'ip_address' => $attendance->ip_address,
                'verified_at' => $attendance->verified_at?->toISOString(),
                'created_at' => $attendance->created_at?->toISOString(),
                'deleted_at' => $attendance->deleted_at?->toISOString(),
                'employee' => $attendance->employee ? [
                    'id' => $attendance->employee->id,
                    'name' => $attendance->employee->user?->name ?? 'Karyawan #'.$attendance->employee->id,
                    'position' => $attendance->employee->user?->position?->name ?? '—',
                    'face_photo_url' => $attendance->employee->face_photo_path,
                ] : null,
                'verifier' => $attendance->verifier ? [
                    'id' => $attendance->verifier->id,
                    'name' => $attendance->verifier->name,
                ] : null,
            ];
        });

        return response()->json($attendances);
    }

    /**
     * Store a manual attendance record (by manager/superadmin).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'superadmin' && ! $user->hasPermissionTo('attendance-management')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'type' => ['required', 'in:in,out'],
            'notes' => ['nullable', 'string', 'max:500'],
            'created_at' => ['nullable', 'date'],
        ]);

        $attendance = Attendance::create([
            'employee_id' => $validated['employee_id'],
            'type' => $validated['type'],
            'status' => 'approved',
            'notes' => $validated['notes'] ?? 'Dibuat manual oleh manager.',
            'verified_by' => $user->id,
            'verified_at' => now(),
            'created_at' => $validated['created_at'] ?? now(),
        ]);

        $attendance->load(['employee.user.position', 'verifier']);

        return response()->json([
            'message' => 'Absensi manual berhasil dicatat.',
            'data' => $attendance,
        ], 201);
    }

    /**
     * Verify (approve/reject) a pending attendance record.
     */
    public function verify(Request $request, Attendance $attendance): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'superadmin' && ! $user->hasPermissionTo('attendance-management')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $attendance->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $attendance->notes,
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        $attendance->load(['employee.user.position', 'verifier']);

        return response()->json([
            'message' => 'Status absensi berhasil diperbarui.',
            'data' => $attendance,
        ]);
    }

    /**
     * Show a single attendance record.
     */
    public function show(Attendance $attendance): JsonResponse
    {
        $attendance->load(['employee.user.position', 'verifier']);

        return response()->json([
            'id' => $attendance->id,
            'type' => $attendance->type,
            'status' => $attendance->status,
            'notes' => $attendance->notes,
            'photo_url' => $attendance->photo_path
                ? Storage::disk('public')->url($attendance->photo_path)
                : null,
            'latitude' => $attendance->latitude,
            'longitude' => $attendance->longitude,
            'ip_address' => $attendance->ip_address,
            'verified_at' => $attendance->verified_at?->toISOString(),
            'created_at' => $attendance->created_at?->toISOString(),
            'employee' => $attendance->employee ? [
                'id' => $attendance->employee->id,
                'name' => $attendance->employee->user?->name ?? 'Karyawan #'.$attendance->employee->id,
                'position' => $attendance->employee->user?->position?->name ?? '—',
                'face_photo_url' => $attendance->employee->face_photo_path,
            ] : null,
            'verifier' => $attendance->verifier ? [
                'id' => $attendance->verifier->id,
                'name' => $attendance->verifier->name,
            ] : null,
        ]);
    }

    /**
     * Soft delete an attendance record.
     */
    public function destroy(Attendance $attendance): JsonResponse
    {
        $user = request()->user();

        if ($user->role !== 'superadmin' && ! $user->hasPermissionTo('attendance-management')) {
            abort(403, 'Unauthorized action.');
        }

        $attendance->delete();

        return response()->json(['message' => 'Absensi dihapus.']);
    }

    /**
     * Restore a soft-deleted attendance record.
     */
    public function restore(Attendance $attendance): JsonResponse
    {
        $attendance->restore();

        return response()->json(['message' => 'Absensi dipulihkan.']);
    }

    /**
     * Force delete an attendance record.
     */
    public function forceDelete(Attendance $attendance): JsonResponse
    {
        $attendance->forceDelete();

        return response()->json(['message' => 'Absensi dihapus permanen.']);
    }

    /**
     * Export attendance query results to a CSV file.
     */
    protected function exportToCsv($query)
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rekap_absensi_'.now()->format('Y-m-d_H-i-s').'.csv"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM for proper excel alignment
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write CSV headers
            fputcsv($handle, [
                'ID Absensi',
                'Nama Karyawan',
                'Jabatan',
                'Tipe Absensi',
                'Waktu Absen',
                'Status',
                'Verifikator',
                'Tanggal Verifikasi',
                'Keterangan / Catatan',
                'Latitude',
                'Longitude',
                'IP Address',
            ]);

            // Chunk database result to consume minimal memory
            $query->chunk(200, function ($records) use ($handle) {
                foreach ($records as $record) {
                    $employeeName = $record->employee->user?->name ?? 'Karyawan #'.$record->employee_id;
                    $positionName = $record->employee->user?->position?->name ?? '—';
                    $statusLabel = match ($record->status) {
                        'pending' => 'Menunggu Verifikasi',
                        'auto_approved' => 'Disetujui Otomatis',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default => $record->status,
                    };
                    $typeLabel = $record->type === 'in' ? 'Masuk' : 'Keluar';

                    fputcsv($handle, [
                        $record->id,
                        $employeeName,
                        $positionName,
                        $typeLabel,
                        $record->created_at?->format('Y-m-d H:i:s') ?? '—',
                        $statusLabel,
                        $record->verifier?->name ?? '—',
                        $record->verified_at?->format('Y-m-d H:i:s') ?? '—',
                        $record->notes ?? '—',
                        $record->latitude ?? '—',
                        $record->longitude ?? '—',
                        $record->ip_address ?? '—',
                    ]);
                }
            });

            fclose($handle);
        }, 'rekap_absensi_'.now()->format('Y-m-d_H-i-s').'.csv', $headers);
    }
}
