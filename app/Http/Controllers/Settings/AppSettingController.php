<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AppSettingUpdateRequest;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppSettingController extends Controller
{
    /**
     * Show the restaurant setting edit page.
     */
    public function edit(Request $request): Response
    {
        // Check authorization (only superadmin, manager, or admin)
        $user = $request->user();
        if (! $user || ! ($user->hasRole('superadmin') || $user->hasRole('manager') || $user->hasRole('admin'))) {
            abort(403, 'Unauthorized action.');
        }

        $settings = AppSetting::firstOrCreate(
            ['id' => 1],
            [
                'restaurant_ip' => '127.0.0.1',
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'radius_meters' => 100,
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
}
