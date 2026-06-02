<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KioskOrderController extends Controller
{
    /**
     * Place a new order from the kiosk.
     */
    public function store(Request $request, string $token): JsonResponse
    {
        $table = Table::where('qr_code', $token)->firstOrFail();

        abort_if($table->status === 'maintenance', 503, 'Meja sedang dalam perbaikan.');

        $validated = $request->validate([
            'guest_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['nullable', 'exists:menu_items,id'],
            'items.*.package_id' => ['nullable', 'exists:packages,id'],
            'items.*.name' => ['required', 'string'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
            'items.*.variants' => ['nullable', 'array'],
            'items.*.add_ons' => ['nullable', 'array'],
            'items.*.package_items' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($validated, $table, $request) {
            $totalAmount = collect($validated['items'])->sum(fn ($i) => $i['price'] * $i['qty']);
            $tax = round($totalAmount * 0.1);
            $finalAmount = $totalAmount + $tax;

            /** @var \App\Models\User|null $customer */
            $customer = auth()->user();
            $customerId = ($customer && $customer->hasRole('customer')) ? $customer->id : null;

            $order = Order::create([
                'order_number' => 'KSK-' . strtoupper(Str::random(8)),
                'table_id' => $table->id,
                'customer_id' => $customerId,
                'order_type' => 'dine_in',
                'source' => 'kiosk',
                'guest_name' => $validated['guest_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'total_amount' => $totalAmount,
                'discount_amount' => 0,
                'tax_amount' => $tax,
                'final_amount' => $finalAmount,
                'payment_method' => 'cash',
                'payment_status' => 'unpaid',
                'status' => 'pending',
            ]);

            foreach ($validated['items'] as $item) {
                $order->items()->create([
                    'menu_item_id' => $item['menu_item_id'] ?? null,
                    'package_id' => $item['package_id'] ?? null,
                    'name' => $item['name'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $item['price'] * $item['qty'],
                    'notes' => $item['notes'] ?? null,
                    'variants' => $item['variants'] ?? [],
                    'add_ons' => $item['add_ons'] ?? [],
                    'package_items' => $item['package_items'] ?? [],
                ]);
            }

            return response()->json($order->load('items'), 201);
        });
    }

    /**
     * Get order status for polling on the confirmation page.
     */
    public function show(string $token, Order $order): JsonResponse
    {
        Table::where('qr_code', $token)->firstOrFail();

        // Only allow access if the order belongs to this table
        abort_unless($order->table->qr_code === $token, 403);

        return response()->json($order->load(['items', 'table']));
    }
}
