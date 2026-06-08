<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ScannerController extends Controller
{
    /**
     * Poll the status of a scanner session.
     */
    public function poll(string $session): JsonResponse
    {
        $cacheKey = "scanner_session_{$session}";

        if (Cache::has($cacheKey)) {
            $sku = Cache::get($cacheKey);
            Cache::forget($cacheKey);

            return response()->json([
                'success' => true,
                'sku' => $sku,
            ]);
        }

        return response()->json([
            'success' => false,
            'sku' => null,
        ]);
    }

    /**
     * Submit a scanned barcode/SKU from the phone.
     */
    public function submit(Request $request): JsonResponse
    {
        $request->validate([
            'session' => 'required|string',
            'sku' => 'required|string',
        ]);

        $session = $request->input('session');
        $sku = $request->input('sku');
        $cacheKey = "scanner_session_{$session}";

        // Save the scanned SKU in cache for 60 seconds
        Cache::put($cacheKey, $sku, 60);

        return response()->json([
            'success' => true,
            'message' => 'Barcode berhasil terkirim ke POS.',
        ]);
    }
}
