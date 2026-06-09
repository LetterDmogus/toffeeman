<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Promo;
use App\Models\Table;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OperationalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure basic data from main DatabaseSeeder exists
        $admin = User::firstOrCreate(
            ['email' => 'admin@restaurant.com'],
            [
                'name' => 'Admin Restaurant',
                'password' => bcrypt('password'),
            ]
        );

        $table1 = Table::firstOrCreate(
            ['number' => 'T1'],
            ['name' => 'Rose Garden Alcove', 'capacity' => 2, 'location' => 'indoor', 'status' => 'available', 'qr_code' => 'tbl-t1-rose']
        );
        $table2 = Table::firstOrCreate(
            ['number' => 'T2'],
            ['name' => 'Royal Parlour', 'capacity' => 4, 'location' => 'indoor', 'status' => 'available', 'qr_code' => 'tbl-t2-royal']
        );

        // Ensure menu items exist (or trigger seeder fallback)
        $scone = MenuItem::where('slug', 'classic-scone-clotted-cream')->first();
        if (! $scone) {
            $this->call(DatabaseSeeder::class);
            $scone = MenuItem::where('slug', 'classic-scone-clotted-cream')->first();
        }

        $tea = MenuItem::where('slug', 'traditional-breakfast-tea')->first();
        $cake = MenuItem::where('slug', 'victoria-sponge-slice')->first();

        // 2. Seed Promos
        $promo1 = Promo::firstOrCreate(
            ['code' => 'DISC10'],
            [
                'name' => 'Diskon Pembukaan 10%',
                'description' => 'Diskon 10% untuk pembelanjaan minimal Rp 50.000',
                'condition_type' => 'min_amount',
                'condition_value' => 50000.00,
                'reward_type' => 'discount_percent',
                'reward_value' => 10.00,
                'reward_scope' => 'all',
                'start_date' => now()->subDays(5),
                'end_date' => now()->addDays(30),
                'is_active' => true,
            ]
        );

        $promo2 = Promo::firstOrCreate(
            ['code' => 'BUY1GET1'],
            [
                'name' => 'Beli Scone Gratis Cake',
                'description' => 'Beli minimal 1 Classic Scone gratis 1 Victoria Sponge Cake',
                'condition_type' => 'specific_item_qty',
                'condition_value' => 1.00,
                'condition_menu_item_id' => $scone?->id,
                'reward_type' => 'free_item',
                'reward_value' => 1.00,
                'reward_menu_item_id' => $cake?->id,
                'reward_scope' => 'specific',
                'start_date' => now()->subDays(5),
                'end_date' => now()->addDays(30),
                'is_active' => true,
            ]
        );

        $promo3 = Promo::firstOrCreate(
            ['code' => 'CASHBACK20K'],
            [
                'name' => 'Diskon Nominal Rp 20.000',
                'description' => 'Potongan langsung Rp 20.000 untuk minimal transaksi Rp 150.000',
                'condition_type' => 'min_amount',
                'condition_value' => 150000.00,
                'reward_type' => 'discount_nominal',
                'reward_value' => 20000.00,
                'reward_scope' => 'all',
                'start_date' => now()->subDays(5),
                'end_date' => now()->addDays(30),
                'is_active' => true,
            ]
        );

        // 3. Seed Completed Orders with transactions
        // Order A (Dine In at Table 1)
        $orderA = Order::create([
            'order_number' => 'ORD-'.strtoupper(Str::random(8)),
            'table_id' => $table1->id,
            'customer_id' => null,
            'waiter_id' => $admin->id,
            'order_type' => 'dine_in',
            'source' => 'pos',
            'status' => 'completed',
            'total_amount' => 122000.00, // (45k * 2) + 32k = 122k
            'discount_amount' => 12200.00, // 10% DISC10
            'tax_amount' => 10980.00, // 10% of (122k - 12.2k)
            'final_amount' => 120780.00, // 122k - 12.2k + 10.98k
            'payment_method' => 'qris',
            'payment_status' => 'paid',
            'notes' => 'Gunakan promo DISC10',
            'created_at' => now()->subHours(2),
        ]);

        OrderItem::create([
            'order_id' => $orderA->id,
            'menu_item_id' => $scone?->id,
            'name' => $scone?->name ?? 'Classic Warm Scone with Clotted Cream',
            'qty' => 2,
            'price' => 45000.00,
            'subtotal' => 90000.00,
            'notes' => 'Scone hangat',
        ]);

        OrderItem::create([
            'order_id' => $orderA->id,
            'menu_item_id' => $tea?->id,
            'name' => $tea?->name ?? 'Traditional English Breakfast Tea',
            'qty' => 1,
            'price' => 32000.00,
            'subtotal' => 32000.00,
            'notes' => 'Teh tanpa gula',
        ]);

        // Create transaction for Order A
        Transaction::create([
            'transaction_number' => 'TXN-'.strtoupper(Str::random(10)),
            'type' => 'income',
            'category' => 'order_sales',
            'reference_type' => Order::class,
            'reference_id' => $orderA->id,
            'user_id' => $admin->id,
            'amount' => $orderA->final_amount,
            'payment_method' => 'qris',
            'payment_status' => 'completed',
            'description' => 'Penjualan pesanan #'.$orderA->order_number,
            'transaction_date' => now()->subHours(2),
        ]);

        // Order B (Dine In at Table 2)
        $orderB = Order::create([
            'order_number' => 'ORD-'.strtoupper(Str::random(8)),
            'table_id' => $table2->id,
            'customer_id' => null,
            'waiter_id' => $admin->id,
            'order_type' => 'dine_in',
            'source' => 'kiosk',
            'guest_name' => 'John Doe',
            'status' => 'completed',
            'total_amount' => 100000.00, // (55k + 45k)
            'discount_amount' => 0.00,
            'tax_amount' => 10000.00,
            'final_amount' => 110000.00,
            'payment_method' => 'transfer',
            'payment_status' => 'paid',
            'created_at' => now()->subMinutes(30),
        ]);

        OrderItem::create([
            'order_id' => $orderB->id,
            'menu_item_id' => $cake?->id,
            'name' => $cake?->name ?? 'Victoria Sponge Cake Slice',
            'qty' => 1,
            'price' => 55000.00,
            'subtotal' => 55000.00,
        ]);

        OrderItem::create([
            'order_id' => $orderB->id,
            'menu_item_id' => $scone?->id,
            'name' => $scone?->name ?? 'Classic Warm Scone with Clotted Cream',
            'qty' => 1,
            'price' => 45000.00,
            'subtotal' => 45000.00,
        ]);

        Transaction::create([
            'transaction_number' => 'TXN-'.strtoupper(Str::random(10)),
            'type' => 'income',
            'category' => 'order_sales',
            'reference_type' => Order::class,
            'reference_id' => $orderB->id,
            'user_id' => $admin->id,
            'amount' => $orderB->final_amount,
            'payment_method' => 'transfer',
            'payment_status' => 'completed',
            'description' => 'Penjualan pesanan Kiosk #'.$orderB->order_number,
            'transaction_date' => now()->subMinutes(30),
        ]);

        // Order C (Ongoing Takeaway)
        $orderC = Order::create([
            'order_number' => 'ORD-'.strtoupper(Str::random(8)),
            'table_id' => null,
            'customer_id' => null,
            'waiter_id' => $admin->id,
            'order_type' => 'takeaway',
            'source' => 'pos',
            'status' => 'preparing',
            'total_amount' => 45000.00,
            'discount_amount' => 0.00,
            'tax_amount' => 4500.00,
            'final_amount' => 49500.00,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'created_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $orderC->id,
            'menu_item_id' => $scone?->id,
            'name' => $scone?->name ?? 'Classic Warm Scone with Clotted Cream',
            'qty' => 1,
            'price' => 45000.00,
            'subtotal' => 45000.00,
        ]);

        // 4. Force calculation for ingredients and batches to ensure consistency
        Ingredient::all()->each->recalculateQty();
    }
}
