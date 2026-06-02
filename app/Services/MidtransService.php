<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\CoreApi;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key') ?? '';
        Config::$isProduction = config('midtrans.is_production') ?? false;
        Config::$isSanitized = config('midtrans.is_sanitized') ?? true;
        Config::$is3ds = config('midtrans.is_3ds') ?? true;
    }

    /**
     * Create QRIS transaction charge.
     */
    public function chargeQris(string $orderNumber, int $amount): array
    {
        $params = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $orderNumber,
                'gross_amount' => $amount,
            ],
            'qris' => [
                'acquirer' => 'gopay', // Default for QRIS in Midtrans
            ]
        ];

        try {
            $response = CoreApi::charge($params);
            return json_decode(json_encode($response), true);
        } catch (\Exception $e) {
            logger()->error('Midtrans QRIS Charge Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create Bank Transfer Virtual Account charge.
     */
    public function chargeBankTransfer(string $orderNumber, int $amount, string $bank): array
    {
        if ($bank === 'mandiri') {
            $params = [
                'payment_type' => 'echannel',
                'transaction_details' => [
                    'order_id' => $orderNumber,
                    'gross_amount' => $amount,
                ],
                'echannel' => [
                    'bill_info1' => 'Payment for order',
                    'bill_info2' => $orderNumber,
                ]
            ];
        } else {
            $params = [
                'payment_type' => 'bank_transfer',
                'transaction_details' => [
                    'order_id' => $orderNumber,
                    'gross_amount' => $amount,
                ],
                'bank_transfer' => [
                    'bank' => $bank,
                ]
            ];
        }

        try {
            $response = CoreApi::charge($params);
            return json_decode(json_encode($response), true);
        } catch (\Exception $e) {
            logger()->error('Midtrans Bank Transfer Charge Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
