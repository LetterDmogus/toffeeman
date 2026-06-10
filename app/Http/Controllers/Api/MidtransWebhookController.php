<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MidtransWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $signatureKey = $payload['signature_key'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;

        if (! $orderId || ! $statusCode || ! $grossAmount || ! $signatureKey) {
            return response()->json(['message' => 'Invalid notification payload.'], 400);
        }

        // Verify Signature Key
        $serverKey = config('midtrans.server_key');
        $localSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        if ($localSignature !== $signatureKey) {
            logger()->warning('Midtrans Signature Mismatch for Order: '.$orderId);

            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        // Find Order
        $order = Order::where('order_number', $orderId)->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        // Process status
        if ($transactionStatus === 'settlement' || $transactionStatus === 'capture') {
            if ($order->payment_status !== 'paid') {
                DB::transaction(function () use ($order, $payload) {
                    $paymentMethod = ($payload['payment_type'] ?? '') === 'bank_transfer' ? 'transfer' : ($order->payment_method ?: 'qris');

                    $order->update([
                        'payment_status' => 'paid',
                        'payment_method' => $paymentMethod,
                        'status' => $order->status === 'ready' ? 'ready' : ($order->status === 'served' ? 'served' : $order->status),
                    ]);

                    // Generate Ledger transaction
                    $order->transaction()->create([
                        'transaction_number' => 'TRX-'.strtoupper(Str::random(10)),
                        'type' => 'income',
                        'category' => 'order_sales',
                        'amount' => $order->final_amount,
                        'description' => 'Penjualan Midtrans: '.$order->order_number,
                        'payment_method' => $paymentMethod,
                        'payment_status' => 'completed',
                        'transaction_date' => now(),
                    ]);
                });

                logger()->info('Midtrans Webhook Success: Order '.$orderId.' has been marked as PAID.');
            }
        } elseif ($transactionStatus === 'cancel' || $transactionStatus === 'deny' || $transactionStatus === 'expire') {
            DB::transaction(function () use ($order) {
                $order->update([
                    'status' => 'cancelled',
                ]);
            });
            logger()->info('Midtrans Webhook Cancel/Expire: Order '.$orderId.' has been marked as CANCELLED.');
        }

        return response()->json(['status' => 'success']);
    }
}
