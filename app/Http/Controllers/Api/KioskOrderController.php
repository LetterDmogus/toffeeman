<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Package;
use App\Models\Promo;
use App\Models\Table;
use App\Models\User;
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
            'promo_id' => ['nullable', 'exists:promos,id'],
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

        // Stock Validation Checker
        $requiredStock = [];
        foreach ($validated['items'] as $item) {
            if (! empty($item['menu_item_id'])) {
                $menuItem = MenuItem::find($item['menu_item_id']);
                if ($menuItem && $menuItem->inventory_item_id) {
                    $invId = $menuItem->inventory_item_id;
                    $requiredStock[$invId] = ($requiredStock[$invId] ?? 0) + $item['qty'];
                }
            }
            if (! empty($item['package_id'])) {
                $package = Package::find($item['package_id']);
                if ($package) {
                    $packageItems = $package->packageItems()->whereNotNull('inventory_item_id')->get();
                    foreach ($packageItems as $pkgItem) {
                        $invId = $pkgItem->inventory_item_id;
                        $requiredStock[$invId] = ($requiredStock[$invId] ?? 0) + ($item['qty'] * $pkgItem->qty);
                    }
                }
            }
        }

        foreach ($requiredStock as $invId => $reqQty) {
            $invItem = InventoryItem::find($invId);
            if (! $invItem || $invItem->qty_good < $reqQty) {
                $itemName = $invItem ? $invItem->name : 'Barang inventoris';

                return response()->json([
                    'message' => "Stok tidak mencukupi untuk: {$itemName}. Silakan kurangi jumlah pesanan Anda.",
                    'errors' => ['items' => ["Stok tidak mencukupi untuk: {$itemName}."]],
                ], 422);
            }
        }

        return DB::transaction(function () use ($validated, $table) {
            $subtotal = collect($validated['items'])->sum(fn ($i) => $i['price'] * $i['qty']);
            $discount = 0.00;
            $promoId = $validated['promo_id'] ?? null;
            $promo = null;
            $freeItems = [];

            if ($promoId) {
                $promo = Promo::where('id', $promoId)
                    ->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('start_date')->orWhere('start_date', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                    })
                    ->with('menuItems')
                    ->first();

                if ($promo) {
                    $conditionMet = false;
                    if ($promo->condition_type === 'min_amount') {
                        $conditionMet = $subtotal >= $promo->condition_value;
                    } elseif ($promo->condition_type === 'min_qty') {
                        $totalQty = collect($validated['items'])->sum('qty');
                        $conditionMet = $totalQty >= $promo->condition_value;
                    } elseif ($promo->condition_type === 'specific_item_qty') {
                        $conditionQty = collect($validated['items'])
                            ->where('menu_item_id', $promo->condition_menu_item_id)
                            ->sum('qty');
                        $conditionMet = $conditionQty >= $promo->condition_value;
                    }

                    if ($conditionMet) {
                        if ($promo->reward_type === 'discount_percent') {
                            if ($promo->reward_scope === 'all') {
                                $discount = round($subtotal * ($promo->reward_value / 100));
                            } else {
                                $applicableItemIds = $promo->menuItems->pluck('id')->toArray();
                                $applicableSubtotal = collect($validated['items'])
                                    ->whereIn('menu_item_id', $applicableItemIds)
                                    ->sum(fn ($i) => $i['price'] * $i['qty']);
                                $discount = round($applicableSubtotal * ($promo->reward_value / 100));
                            }
                        } elseif ($promo->reward_type === 'discount_nominal') {
                            if ($promo->reward_scope === 'all') {
                                $discount = min($promo->reward_value, $subtotal);
                            } else {
                                $applicableItemIds = $promo->menuItems->pluck('id')->toArray();
                                $applicableSubtotal = collect($validated['items'])
                                    ->whereIn('menu_item_id', $applicableItemIds)
                                    ->sum(fn ($i) => $i['price'] * $i['qty']);
                                $discount = min($promo->reward_value, $applicableSubtotal);
                            }
                        } elseif ($promo->reward_type === 'free_item') {
                            $freeTimes = 1;
                            if ($promo->condition_type === 'specific_item_qty') {
                                $conditionQty = collect($validated['items'])
                                    ->where('menu_item_id', $promo->condition_menu_item_id)
                                    ->sum('qty');
                                $freeTimes = floor($conditionQty / $promo->condition_value);
                            }

                            if ($freeTimes > 0) {
                                $getMenuItem = MenuItem::find($promo->reward_menu_item_id);
                                if ($getMenuItem) {
                                    $freeItems[] = [
                                        'menu_item_id' => $getMenuItem->id,
                                        'package_id' => null,
                                        'name' => $getMenuItem->name.' (Promo Gratis)',
                                        'qty' => $freeTimes,
                                        'price' => 0.00,
                                        'subtotal' => 0.00,
                                        'notes' => 'Free item from promo '.($promo->code ?? $promo->name),
                                        'variants' => [],
                                        'add_ons' => [],
                                        'package_items' => [],
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            $totalAfterDiscount = max(0, $subtotal - $discount);
            $tax = round($totalAfterDiscount * 0.1);
            $finalAmount = $totalAfterDiscount + $tax;

            /** @var User|null $customer */
            $customer = auth()->user();
            $customerId = ($customer && $customer->hasRole('customer')) ? $customer->id : null;

            $order = Order::create([
                'order_number' => 'KSK-'.strtoupper(Str::random(8)),
                'table_id' => $table->id,
                'customer_id' => $customerId,
                'order_type' => 'dine_in',
                'source' => 'kiosk',
                'guest_name' => $validated['guest_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'total_amount' => $subtotal,
                'discount_amount' => $discount,
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

            foreach ($freeItems as $fItem) {
                $order->items()->create($fItem);
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
