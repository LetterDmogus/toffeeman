<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TransactionSampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::role('admin')->first() ?? User::first();

        // ─── 1. Income transactions linked to paid orders ───────────────────
        $paidOrders = Order::where('payment_status', 'paid')->get();

        foreach ($paidOrders as $order) {
            // Skip if transaction already exists for this order
            if ($order->transaction()->exists()) {
                continue;
            }

            $transactionDate = $order->created_at ?? Carbon::now()->subDays(rand(1, 30));

            Transaction::create([
                'transaction_number' => 'TXN-ORD-'.strtoupper(Str::random(6)),
                'type' => 'income',
                'category' => 'order_sales',
                'reference_id' => $order->id,
                'reference_type' => Order::class,
                'user_id' => $admin?->id,
                'amount' => $order->final_amount,
                'payment_method' => $order->payment_method ?? 'cash',
                'payment_status' => 'completed',
                'description' => "Pembayaran pesanan #{$order->order_number}",
                'transaction_date' => $transactionDate,
            ]);
        }

        // ─── 2. Standalone income transactions (walk-in cash sales, etc.) ───
        $standaloneIncomes = [
            ['category' => 'order_sales',    'amount' => 185000, 'method' => 'cash',     'desc' => 'Penjualan tunai — meja walk-in'],
            ['category' => 'order_sales',    'amount' => 320000, 'method' => 'qris',     'desc' => 'Penjualan QRIS — paket keluarga'],
            ['category' => 'order_sales',    'amount' => 95000,  'method' => 'transfer',  'desc' => 'Transfer bank — take away lunch'],
            ['category' => 'order_sales',    'amount' => 450000, 'method' => 'cash',     'desc' => 'Penjualan tunai — catering internal'],
            ['category' => 'order_sales',    'amount' => 210000, 'method' => 'qris',     'desc' => 'QRIS — pesanan weekend'],
            ['category' => 'other_income',   'amount' => 75000,  'method' => 'cash',     'desc' => 'Pendapatan lain — sewa tempat foto'],
            ['category' => 'order_sales',    'amount' => 155000, 'method' => 'cash',     'desc' => 'Penjualan tunai — meja 7'],
            ['category' => 'order_sales',    'amount' => 280000, 'method' => 'transfer',  'desc' => 'Transfer — pesanan korporasi'],
        ];

        foreach ($standaloneIncomes as $i => $data) {
            $daysAgo = rand(0, 29);
            $hour = rand(9, 21);

            Transaction::create([
                'transaction_number' => 'TXN-INC-'.strtoupper(Str::random(6)),
                'type' => 'income',
                'category' => $data['category'],
                'reference_id' => null,
                'reference_type' => null,
                'user_id' => $admin?->id,
                'amount' => $data['amount'],
                'payment_method' => $data['method'],
                'payment_status' => 'completed',
                'description' => $data['desc'],
                'transaction_date' => Carbon::now()->subDays($daysAgo)->setHour($hour)->setMinute(rand(0, 59)),
            ]);
        }

        // ─── 3. Expense transactions (bahan baku, gaji, dll.) ────────────────
        $expenses = [
            ['category' => 'inventory_purchase', 'amount' => 850000,  'method' => 'transfer', 'desc' => 'Pembelian bahan baku — sayur & rempah mingguan'],
            ['category' => 'inventory_purchase', 'amount' => 1200000, 'method' => 'transfer', 'desc' => 'Pembelian bahan baku — daging & seafood'],
            ['category' => 'inventory_purchase', 'amount' => 420000,  'method' => 'cash',     'desc' => 'Belanja harian — pasar tradisional'],
            ['category' => 'inventory_purchase', 'amount' => 310000,  'method' => 'cash',     'desc' => 'Pembelian bumbu & minyak goreng'],
            ['category' => 'salary',             'amount' => 3500000, 'method' => 'transfer', 'desc' => 'Gaji karyawan dapur — periode Juni'],
            ['category' => 'salary',             'amount' => 2800000, 'method' => 'transfer', 'desc' => 'Gaji pramusaji — periode Juni'],
            ['category' => 'maintenance',        'amount' => 350000,  'method' => 'cash',     'desc' => 'Servis kompor gas & peralatan dapur'],
            ['category' => 'maintenance',        'amount' => 150000,  'method' => 'cash',     'desc' => 'Penggantian lampu & kebutuhan listrik'],
            ['category' => 'utilities',          'amount' => 780000,  'method' => 'transfer', 'desc' => 'Tagihan listrik bulan Mei'],
            ['category' => 'utilities',          'amount' => 120000,  'method' => 'transfer', 'desc' => 'Tagihan air bulan Mei'],
            ['category' => 'marketing',          'amount' => 200000,  'method' => 'transfer', 'desc' => 'Iklan sosial media — promo weekend'],
            ['category' => 'other_expense',      'amount' => 95000,   'method' => 'cash',     'desc' => 'ATK & kebutuhan operasional kantor'],
        ];

        foreach ($expenses as $data) {
            $daysAgo = rand(0, 29);
            $hour = rand(8, 17);

            Transaction::create([
                'transaction_number' => 'TXN-EXP-'.strtoupper(Str::random(6)),
                'type' => 'expense',
                'category' => $data['category'],
                'reference_id' => null,
                'reference_type' => null,
                'user_id' => $admin?->id,
                'amount' => $data['amount'],
                'payment_method' => $data['method'],
                'payment_status' => 'completed',
                'description' => $data['desc'],
                'transaction_date' => Carbon::now()->subDays($daysAgo)->setHour($hour)->setMinute(rand(0, 59)),
            ]);
        }
    }
}
