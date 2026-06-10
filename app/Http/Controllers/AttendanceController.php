<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Position;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * Show the attendance management page for managers/superadmins.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if ($user->role !== 'superadmin' && ! $user->hasPermissionTo('attendance-management')) {
            abort(403, 'Unauthorized action.');
        }

        $positions = Position::all(['id', 'name']);

        return Inertia::render('attendance/Index', [
            'positions' => $positions,
        ]);
    }

    /**
     * Show the shared kiosk attendance scan page.
     */
    public function kiosk(Request $request): Response
    {
        $user = $request->user();
        if ($user->role !== 'superadmin' && ! $user->hasPermissionTo('kiosk-attendance-access')) {
            abort(403, 'Unauthorized action.');
        }

        $selectedPositionId = $request->query('position_id');
        $positions = Position::all();

        $employees = collect();

        if ($selectedPositionId) {
            // Load only active employees of the selected position who have registered face photos
            $employeesCollection = Employee::with(['user.position'])
                ->where('status', 'active')
                ->whereNotNull('face_photo_path')
                ->whereHas('user', function ($q) use ($selectedPositionId) {
                    $q->where('position_id', $selectedPositionId);
                })
                ->get();

            // Load today's attendance logs to prevent double attendance
            $today = Carbon::today()->toDateString();
            $attendancesToday = Attendance::whereDate('created_at', $today)
                ->whereIn('employee_id', $employeesCollection->pluck('id'))
                ->whereIn('status', ['auto_approved', 'approved', 'pending'])
                ->get()
                ->groupBy('employee_id');

            $employees = $employeesCollection->map(function ($employee) use ($attendancesToday) {
                $logs = $attendancesToday->get($employee->id) ?? collect();
                $hasClockIn = $logs->contains('type', 'in');
                $hasClockOut = $logs->contains('type', 'out');

                return [
                    'id' => $employee->id,
                    'name' => $employee->user?->name ?? 'Karyawan #'.$employee->id,
                    'position' => $employee->user?->position?->name ?? 'Staff',
                    'face_photo_url' => $employee->face_photo_path,
                    'has_clock_in' => $hasClockIn,
                    'has_clock_out' => $hasClockOut,
                    'can_clock_in' => ! $hasClockIn,
                    'can_clock_out' => $hasClockIn && ! $hasClockOut,
                ];
            });
        }

        // Get restaurant IP and radius settings for frontend info
        $settings = AppSetting::first() ?? (object) [
            'restaurant_ip' => '127.0.0.1',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_meters' => 100,
        ];

        return Inertia::render('attendance/Kiosk', [
            'positions' => $positions,
            'selectedPositionId' => $selectedPositionId ? (int) $selectedPositionId : null,
            'employees' => $employees,
            'settings' => $settings,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Store employee attendance log.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'type' => ['required', 'in:in,out'],
            'photo' => ['required', 'string'], // base64 string from video capture
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $employee = Employee::findOrFail($request->employee_id);
        $settings = AppSetting::first();

        $status = 'pending';
        $clientIp = $request->ip();

        // 1. Check Geolocation & IP Address constraints
        if ($settings) {
            $isIpMatched = $settings->restaurant_ip && ($settings->restaurant_ip === $clientIp);

            $isLocationMatched = false;
            if ($settings->latitude && $settings->longitude && $request->latitude && $request->longitude) {
                $distance = $this->calculateDistance(
                    (float) $settings->latitude,
                    (float) $settings->longitude,
                    (float) $request->latitude,
                    (float) $request->longitude
                );

                if ($distance <= (float) $settings->radius_meters) {
                    $isLocationMatched = true;
                }
            }

            if ($isIpMatched || $isLocationMatched) {
                $status = 'auto_approved';
            }
        } else {
            // Default to auto_approved if no settings configured yet
            $status = 'auto_approved';
        }

        // 2. Process and save base64 selfie image
        $photoPath = null;
        if (preg_match('/^data:image\/(\w+);base64,/', $request->photo, $type)) {
            $data = substr($request->photo, strpos($request->photo, ',') + 1);
            $type = strtolower($type[1]); // png, jpg, jpeg

            if (in_array($type, ['jpg', 'jpeg', 'png'])) {
                $data = base64_decode($data);
                $filename = 'attendance_'.$employee->id.'_'.time().'_'.Str::random(5).'.'.$type;
                $photoPath = 'attendances/'.$filename;

                Storage::disk('public')->put($photoPath, $data);
            }
        }

        // 3. Create the attendance record
        Attendance::create([
            'employee_id' => $employee->id,
            'type' => $request->type,
            'photo_path' => $photoPath,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'ip_address' => $clientIp,
            'status' => $status,
            'verified_at' => $status === 'auto_approved' ? now() : null,
            'notes' => $status === 'auto_approved' ? 'Disetujui otomatis oleh sistem (masuk parameter radius/IP).' : null,
        ]);

        $message = $status === 'auto_approved'
            ? 'Absensi '.($request->type === 'in' ? 'Masuk' : 'Keluar').' berhasil diverifikasi!'
            : 'Absensi terkirim! Menunggu verifikasi manager (di luar area restoran).';

        Inertia::flash('toast', [
            'type' => $status === 'auto_approved' ? 'success' : 'warning',
            'message' => $message,
        ]);

        return to_route('attendance.kiosk');
    }

    /**
     * Calculate distance between two coordinates in meters using Haversine formula.
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Earth radius in meters

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
