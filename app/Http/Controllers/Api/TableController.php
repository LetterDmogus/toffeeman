<?php

namespace App\Http\Controllers\Api;

use App\Models\Table;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TableController extends BaseController
{
    protected string $model = Table::class;

    /**
     * Display a listing of tables.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Table::query();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }

        if ($request->boolean('all')) {
            return response()->json($query->orderBy('number')->get());
        }

        $tables = $query->orderBy('number')->paginate($request->integer('per_page', 15));

        return response()->json($tables);
    }

    /**
     * Store a newly created table.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:50', 'unique:tables,number'],
            'name' => ['nullable', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'location' => ['required', 'in:indoor,outdoor,private'],
            'status' => ['required', 'in:available,occupied,reserved,maintenance'],
        ]);

        $table = Table::create($validated);

        return response()->json($table, 201);
    }

    /**
     * Display the specified table.
     */
    public function show(Table $table): JsonResponse
    {
        return response()->json($table);
    }

    /**
     * Update the specified table.
     */
    public function update(Request $request, Table $table): JsonResponse
    {
        $validated = $request->validate([
            'number' => ['sometimes', 'string', 'max:50', "unique:tables,number,{$table->id}"],
            'name' => ['nullable', 'string', 'max:255'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'location' => ['sometimes', 'in:indoor,outdoor,private'],
            'status' => ['sometimes', 'in:available,occupied,reserved,maintenance'],
        ]);

        $table->update($validated);

        return response()->json($table);
    }

    /**
     * Remove the specified table.
     */
    public function destroy(Table $table): JsonResponse
    {
        $table->delete();

        return response()->json(['message' => 'Table deleted.']);
    }

    /**
     * Restore the specified table.
     */
    public function restore(Table $table): JsonResponse
    {
        $table->restore();

        return response()->json($table);
    }

    /**
     * Force delete the specified table.
     */
    public function forceDelete(Table $table): JsonResponse
    {
        $table->forceDelete();

        return response()->json(['message' => 'Table permanently deleted.']);
    }

    /**
     * Generate (or regenerate) a QR code for a table.
     */
    public function generateQr(Table $table, QrCodeService $qrCodeService): JsonResponse
    {
        $path = $qrCodeService->generateForTable($table);

        return response()->json([
            'message' => 'QR code generated.',
            'qr_image_path' => $path,
            'qr_image_url' => asset('storage/'.$path),
        ]);
    }

    /**
     * Download a printable QR code card as SVG.
     */
    public function downloadQr(Table $table, QrCodeService $qrCodeService): Response
    {
        $svg = $qrCodeService->generatePrintableSvg($table);
        $filename = 'qr-meja-'.$table->number.'.svg';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
