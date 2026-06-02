<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends BaseController
{
    protected string $model = Order::class;

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['items', 'table', 'customer']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where('order_number', 'like', "%{$search}%");
        }

        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }

        $items = $query->latest()->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:users,id'],
            'table_id' => ['nullable', 'exists:tables,id'],
            'type' => ['required', 'in:dine_in,take_away'],
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
            'payment_method' => ['required', 'in:cash,qris,transfer'],
            'payment_status' => ['nullable', 'in:paid,unpaid'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($validated) {
            $totalAmount = collect($validated['items'])->sum(fn($i) => $i['price'] * $i['qty']);
            $discount = $validated['discount'] ?? 0;
            $tax = $validated['tax'] ?? 0;
            $finalAmount = $totalAmount - $discount + $tax;
            
            $isGateway = in_array($validated['payment_method'], ['qris', 'transfer']);
            $paymentStatus = $isGateway ? 'unpaid' : ($validated['payment_status'] ?? 'paid');

            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                'customer_id' => $validated['customer_id'] ?? null,
                'table_id' => $validated['table_id'] ?? null,
                'order_type' => $validated['type'],
                'total_amount' => $totalAmount,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'final_amount' => $finalAmount,
                'payment_method' => $validated['payment_method'],
                'status' => 'pending', 
                'payment_status' => $paymentStatus,
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

            $qrUrl = null;
            $vaNumber = null;
            $vaBank = null;
            if ($validated['payment_method'] === 'qris') {
                try {
                    $midtrans = app(\App\Services\MidtransService::class);
                    $charge = $midtrans->chargeQris($order->order_number, $order->final_amount);
                    $actions = $charge['actions'] ?? [];
                    foreach ($actions as $action) {
                        if (($action['name'] ?? '') === 'generate-qr-code') {
                            $qrUrl = $action['url'] ?? null;
                            break;
                        }
                    }
                    $order->update([
                        'payment_metadata' => [
                            'qr_url' => $qrUrl
                        ]
                    ]);
                } catch (\Exception $e) {
                    throw new \RuntimeException('Gagal memproses QRIS ke Midtrans: ' . $e->getMessage());
                }
            } elseif ($validated['payment_method'] === 'transfer') {
                try {
                    $midtrans = app(\App\Services\MidtransService::class);
                    $bank = request()->input('bank', 'bca');
                    $charge = $midtrans->chargeBankTransfer($order->order_number, $order->final_amount, $bank);
                    
                    $vaNumber = null;
                    $vaBank = null;
                    $billKey = null;
                    $billerCode = null;

                    if (isset($charge['va_numbers'][0]['va_number'])) {
                        $vaNumber = $charge['va_numbers'][0]['va_number'];
                        $vaBank = $charge['va_numbers'][0]['bank'] ?? $bank;
                    } elseif (isset($charge['permata_va_number'])) {
                        $vaNumber = $charge['permata_va_number'];
                        $vaBank = 'permata';
                    } elseif (isset($charge['bill_key']) && isset($charge['biller_code'])) {
                        $billKey = $charge['bill_key'];
                        $billerCode = $charge['biller_code'];
                        $vaNumber = $charge['biller_code'] . ' - ' . $charge['bill_key'];
                        $vaBank = 'mandiri';
                    } else {
                        $vaBank = $bank;
                    }

                    $order->update([
                        'payment_metadata' => [
                            'va_number' => $vaNumber,
                            'va_bank' => $vaBank,
                            'requested_bank' => $bank,
                            'bill_key' => $billKey,
                            'biller_code' => $billerCode,
                        ]
                    ]);
                } catch (\Exception $e) {
                    throw new \RuntimeException('Gagal memproses Bank Transfer ke Midtrans: ' . $e->getMessage());
                }
            }

            // Only create Ledger Transaction if paid
            if ($paymentStatus === 'paid' && !$isGateway) {
                $this->createLedgerEntry($order);
            }

            $resData = $order->load('items')->toArray();
            if ($qrUrl) {
                $resData['qr_url'] = $qrUrl;
                $resData['payment_status'] = 'pending';
            }
            if ($vaNumber) {
                $resData['va_number'] = $vaNumber;
                $resData['va_bank'] = $vaBank;
                $resData['requested_bank'] = $bank;
                if ($billKey && $billerCode) {
                    $resData['bill_key'] = $billKey;
                    $resData['biller_code'] = $billerCode;
                }
                $resData['payment_status'] = 'pending';
            }

            return response()->json($resData, 201);
        });
    }

    /**
     * Add items to an existing order (Hold Order flow).
     */
    public function addItems(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
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

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Pesanan sudah dibayar, tidak bisa menambah item.'], 422);
        }

        return DB::transaction(function () use ($validated, $order) {
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

            $this->recalculateTotals($order);
            
            // Set status back to pending to notify kitchen of new items
            $order->update(['status' => 'pending']);

            return response()->json($order->load('items'));
        });
    }

    /**
     * Process payment for an existing unpaid order.
     */
    public function processPayment(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'in:cash,qris,transfer'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Pesanan sudah lunas.'], 422);
        }

        if ($validated['payment_method'] === 'qris') {
            return DB::transaction(function () use ($validated, $order) {
                $order->update([
                    'payment_method' => 'qris',
                    'discount_amount' => $validated['discount'] ?? $order->discount_amount,
                    'payment_status' => 'unpaid',
                ]);

                $this->recalculateTotals($order);

                // Reuse saved QR code if available
                $metadata = $order->payment_metadata ?? [];
                if (isset($metadata['qr_url'])) {
                    return response()->json([
                        'payment_status' => 'pending',
                        'payment_method' => 'qris',
                        'qr_url' => $metadata['qr_url'],
                        'order' => $order->load('items')
                    ]);
                }

                try {
                    $midtrans = app(\App\Services\MidtransService::class);
                    $charge = $midtrans->chargeQris($order->order_number, $order->final_amount);
                    $actions = $charge['actions'] ?? [];
                    $qrUrl = null;
                    foreach ($actions as $action) {
                        if (($action['name'] ?? '') === 'generate-qr-code') {
                            $qrUrl = $action['url'] ?? null;
                            break;
                        }
                    }

                    $order->update([
                        'payment_metadata' => array_merge($metadata, ['qr_url' => $qrUrl])
                    ]);

                    return response()->json([
                        'payment_status' => 'pending',
                        'payment_method' => 'qris',
                        'qr_url' => $qrUrl,
                        'order' => $order->load('items')
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Gagal memproses pembayaran QRIS ke Midtrans: ' . $e->getMessage()
                    ], 500);
                }
            });
        }

        if ($validated['payment_method'] === 'transfer') {
            return DB::transaction(function () use ($validated, $order) {
                $order->update([
                    'payment_method' => 'transfer',
                    'discount_amount' => $validated['discount'] ?? $order->discount_amount,
                    'payment_status' => 'unpaid',
                ]);

                $this->recalculateTotals($order);

                // Reuse saved VA details if available
                $metadata = $order->payment_metadata ?? [];
                if (isset($metadata['va_number'])) {
                    return response()->json([
                        'payment_status' => 'pending',
                        'payment_method' => 'transfer',
                        'va_number' => $metadata['va_number'],
                        'va_bank' => $metadata['va_bank'] ?? 'bca',
                        'requested_bank' => $metadata['requested_bank'] ?? null,
                        'bill_key' => $metadata['bill_key'] ?? null,
                        'biller_code' => $metadata['biller_code'] ?? null,
                        'order' => $order->load('items')
                    ]);
                }

                try {
                    $midtrans = app(\App\Services\MidtransService::class);
                    $bank = request()->input('bank', 'bca');
                    $charge = $midtrans->chargeBankTransfer($order->order_number, $order->final_amount, $bank);
                    
                    $vaNumber = null;
                    $vaBank = null;
                    $billKey = null;
                    $billerCode = null;

                    if (isset($charge['va_numbers'][0]['va_number'])) {
                        $vaNumber = $charge['va_numbers'][0]['va_number'];
                        $vaBank = $charge['va_numbers'][0]['bank'] ?? $bank;
                    } elseif (isset($charge['permata_va_number'])) {
                        $vaNumber = $charge['permata_va_number'];
                        $vaBank = 'permata';
                    } elseif (isset($charge['bill_key']) && isset($charge['biller_code'])) {
                        $billKey = $charge['bill_key'];
                        $billerCode = $charge['biller_code'];
                        $vaNumber = $charge['biller_code'] . ' - ' . $charge['bill_key'];
                        $vaBank = 'mandiri';
                    } else {
                        $vaBank = $bank;
                    }

                    $order->update([
                        'payment_metadata' => array_merge($metadata, [
                            'va_number' => $vaNumber,
                            'va_bank' => $vaBank,
                            'requested_bank' => $bank,
                            'bill_key' => $billKey,
                            'biller_code' => $billerCode,
                        ])
                    ]);

                    return response()->json([
                        'payment_status' => 'pending',
                        'payment_method' => 'transfer',
                        'va_number' => $vaNumber,
                        'va_bank' => $vaBank,
                        'requested_bank' => $bank,
                        'bill_key' => $billKey,
                        'biller_code' => $billerCode,
                        'order' => $order->load('items')
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Gagal memproses pembayaran Bank Transfer ke Midtrans: ' . $e->getMessage()
                    ], 500);
                }
            });
        }

        return DB::transaction(function () use ($validated, $order) {
            $order->update([
                'payment_method' => $validated['payment_method'],
                'discount_amount' => $validated['discount'] ?? $order->discount_amount,
                'payment_status' => 'paid',
                'status' => $order->status === 'ready' ? 'ready' : ($order->status === 'served' ? 'served' : $order->status)
            ]);

            $this->recalculateTotals($order);
            $this->createLedgerEntry($order);

            return response()->json($order->load('items'));
        });
    }

    /**
     * Cancel an individual order item.
     */
    public function cancelItem(Request $request, OrderItem $item): JsonResponse
    {
        if ($item->order->payment_status === 'paid') {
            return response()->json(['message' => 'Pesanan sudah dibayar, item tidak bisa dibatalkan.'], 422);
        }

        $item->update(['status' => 'cancelled']);
        
        $this->recalculateTotals($item->order);

        return response()->json(['message' => 'Item berhasil dibatalkan', 'order' => $item->order->load('items')]);
    }

    private function recalculateTotals(Order $order): void
    {
        $activeItems = $order->items()->where('status', '!=', 'cancelled')->get();
        $totalAmount = $activeItems->sum('subtotal');
        $tax = round($totalAmount * 0.1);
        
        $order->update([
            'total_amount' => $totalAmount,
            'tax_amount' => $tax,
            'final_amount' => $totalAmount - $order->discount_amount + $tax
        ]);
    }

    private function createLedgerEntry(Order $order): void
    {
        $order->transaction()->create([
            'transaction_number' => 'TRX-' . strtoupper(Str::random(10)),
            'type' => 'income',
            'category' => 'order_sales',
            'amount' => $order->final_amount,
            'description' => 'Penjualan: ' . $order->order_number,
            'payment_method' => $order->payment_method,
            'payment_status' => 'completed',
            'transaction_date' => now(),
            'user_id' => auth()->id(),
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order->load(['items', 'table', 'customer', 'transaction']));
    }
}
