<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AppSettingUpdateRequest;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppSettingController extends Controller
{
    /**
     * Show the restaurant setting edit page.
     */
    public function edit(Request $request): Response
    {
        // Check authorization (only superadmin or users with settings-access permission)
        $user = $request->user();
        if (! $user || ($user->role !== 'superadmin' && ! $user->hasPermissionTo('settings-access'))) {
            abort(403, 'Unauthorized. You do not have permission to access system settings.');
        }

        $settings = AppSetting::firstOrCreate(
            ['id' => 1],
            [
                'restaurant_ip' => '127.0.0.1',
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'radius_meters' => 100,
                'website_name' => 'Toffee Manor',
                'address' => 'Jl. M.H. Thamrin No. 1, Jakarta Pusat',
                'contact' => '+62 812-3456-7890',
            ]
        );

        return Inertia::render('site-settings/IpLocation', [
            'settings' => $settings,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the restaurant settings.
     */
    public function update(AppSettingUpdateRequest $request): RedirectResponse
    {
        $settings = AppSetting::firstOrCreate(['id' => 1]);
        $settings->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Restaurant settings updated.')]);

        return to_route('site-settings.ip-location.edit');
    }

    /**
     * Show the database management page.
     */
    public function databaseEdit(Request $request): Response
    {
        $user = $request->user();
        if (! $user || ($user->role !== 'superadmin' && ! $user->hasPermissionTo('settings-access'))) {
            abort(403, 'Unauthorized. You do not have permission to access system settings.');
        }

        return Inertia::render('site-settings/DatabaseManagement', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Backup the SQLite database file.
     */
    public function backup(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        if (! $user || ($user->role !== 'superadmin' && ! $user->hasPermissionTo('settings-access'))) {
            abort(403, 'Unauthorized. You do not have permission to access system settings.');
        }

        $dbPath = database_path('database.sqlite');

        if (! file_exists($dbPath)) {
            abort(404, 'Database file not found.');
        }

        $filename = 'backup-toffeeman-'.date('Y-m-d-His').'.sqlite';

        return response()->download($dbPath, $filename);
    }

    /**
     * Reset the database using migrate:fresh and seeding.
     */
    public function reset(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ($user->role !== 'superadmin' && ! $user->hasPermissionTo('settings-access'))) {
            abort(403, 'Unauthorized. You do not have permission to access system settings.');
        }

        // Run fresh migration and seeding
        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);

        // Logout user as database state (including sessions) has been reset
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', 'Database berhasil di-reset ke data bawaan.');
    }
}
