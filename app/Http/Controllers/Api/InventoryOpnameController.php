<?php

namespace App\Http\Controllers\Api;

use App\Models\InventoryOpname;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InventoryOpnameController extends BaseController
{
    protected string $model = InventoryOpname::class;

    /**
     * Display a listing of inventory opname records.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryOpname::with(['creator', 'attachments'])
            ->withCount('details');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where('opname_number', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%");
        }

        $items = $query->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    /**
     * Store a newly created inventory opname record.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'details.*.qty_good_physical' => ['required', 'numeric', 'min:0'],
            'details.*.qty_fair_physical' => ['required', 'numeric', 'min:0'],
            'details.*.qty_damaged_physical' => ['required', 'numeric', 'min:0'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:2048'], // Max 2MB per photo
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Auto-generate opname number: OP-YYYYMMDD-XXXX
            $datePrefix = date('Ymd', strtotime($validated['date']));
            $countToday = InventoryOpname::where('opname_number', 'like', "OP-{$datePrefix}-%")->count();
            $opnameNumber = 'OP-'.$datePrefix.'-'.str_pad($countToday + 1, 4, '0', STR_PAD_LEFT);

            $inventoryOpname = InventoryOpname::create([
                'opname_number' => $opnameNumber,
                'date' => $validated['date'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['details'] as $detail) {
                $inventoryOpname->details()->create([
                    'inventory_item_id' => $detail['inventory_item_id'],
                    'qty_good_physical' => $detail['qty_good_physical'],
                    'qty_fair_physical' => $detail['qty_fair_physical'],
                    'qty_damaged_physical' => $detail['qty_damaged_physical'],
                ]);
            }

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('inventory', 'public');
                    $inventoryOpname->attachments()->create([
                        'file_path' => $path,
                    ]);
                }
            }

            return response()->json(
                $inventoryOpname->load(['details.item', 'attachments', 'creator']),
                201
            );
        });
    }

    /**
     * Display the specified inventory opname record.
     */
    public function show(InventoryOpname $inventoryOpname): JsonResponse
    {
        return response()->json($inventoryOpname->load(['details.item', 'attachments', 'creator']));
    }

    /**
     * Remove the specified record and revert inventory changes.
     */
    public function destroy(InventoryOpname $inventoryOpname): JsonResponse
    {
        DB::transaction(function () use ($inventoryOpname) {
            // Delete details first to trigger their deleted event (reverts inventory item stock back to system qty)
            foreach ($inventoryOpname->details as $detail) {
                $detail->delete();
            }

            // Delete attachments from storage and DB
            foreach ($inventoryOpname->attachments as $attachment) {
                if (Storage::exists($attachment->file_path)) {
                    Storage::delete($attachment->file_path);
                }
                $attachment->delete();
            }

            $inventoryOpname->delete();
        });

        return response()->json(['message' => 'Record deleted and stock reverted to pre-opname values.']);
    }
}
