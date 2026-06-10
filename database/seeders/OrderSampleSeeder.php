<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tables = Table::all();
        $menuItems = MenuItem::all();
        $waiter = User::role('user')->first() ?? User::first();
        $customer = User::role('customer')->first();

        if ($menuItems->isEmpty()) {
            $this->command->warn('No menu items found. Please run DatabaseSeeder first.');

            return;
        }

        // Configuration for 10 varied orders
        $orderConfigs = [
            [
                'order_type' => 'dine_in',
                'status' => 'served',
                'payment_status' => 'paid',
                'payment_method' => 'cash',
                'item_count' => 5, // Banyak item
            ],
            [
                'order_type' => 'dine_in',
                'status' => 'ready',
                'payment_status' => 'unpaid',
                'payment_method' => null,
                'item_count' => 2, // Dikit item
            ],
            [
                'order_type' => 'take_away',
                'status' => 'served',
                'payment_status' => 'paid',
                'payment_method' => 'qris',
                'item_count' => 1,
            ],
            [
                'order_type' => 'dine_in',
                'status' => 'processing',
                'payment_status' => 'unpaid',
                'payment_method' => null,
                'item_count' => 4,
            ],
            [
                'order_type' => 'dine_in',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => null,
                'item_count' => 2,
            ],
            [
                'order_type' => 'take_away',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => null,
                'item_count' => 3,
            ],
            [
                'order_type' => 'dine_in',
                'status' => 'served',
                'payment_status' => 'paid',
                'payment_method' => 'transfer',
                'item_count' => 6, // Banyak item
            ],
            [
                'order_type' => 'take_away',
                'status' => 'served',
                'payment_status' => 'paid',
                'payment_method' => 'cash',
                'item_count' => 1, // Dikit item
            ],
            [
                'order_type' => 'dine_in',
                'status' => 'cancelled',
                'payment_status' => 'unpaid',
                'payment_method' => null,
                'item_count' => 3,
            ],
            [
                'order_type' => 'dine_in',
                'status' => 'processing',
                'payment_status' => 'paid', // Kadang bayar di depan
                'payment_method' => 'qris',
                'item_count' => 3,
            ],
        ];

        foreach ($orderConfigs as $index => $cfg) {
            $table = $cfg['order_type'] === 'dine_in' ? $tables->random() : null;

            // Calculate random items and amounts
            $chosenItems = $menuItems->random(min($cfg['item_count'], $menuItems->count()));

            $subtotal = 0;
            $itemsData = [];

            foreach ($chosenItems as $menu) {
                $qty = rand(1, 3);
                $price = (float) $menu->price;
                $itemSubtotal = $price * $qty;
                $subtotal += $itemSubtotal;

                $itemsData[] = [
                    'menu_item_id' => $menu->id,
                    'package_id' => null,
                    'name' => $menu->name,
                    'price' => $price,
                    'qty' => $qty,
                    'subtotal' => $itemSubtotal,
                    'notes' => rand(0, 10) > 7 ? 'Level 2 pedas / kurang manis' : null,
                ];
            }

            $discount = $cfg['payment_status'] === 'paid' && rand(0, 1) ? 10000 : 0;
            $tax = ($subtotal - $discount) * 0.1;
            $final = ($subtotal - $discount) + $tax;

            // Generate unique order number
            $orderNumber = 'ORD-'.strtoupper(Str::random(3)).rand(100, 999);

            $order = Order::create([
                'order_number' => $orderNumber,
                'table_id' => $table ? $table->id : null,
                'customer_id' => $customer ? $customer->id : null,
                'waiter_id' => $waiter ? $waiter->id : null,
                'order_type' => $cfg['order_type'],
                'status' => $cfg['status'],
                'total_amount' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'final_amount' => $final,
                'payment_status' => $cfg['payment_status'],
                'payment_method' => $cfg['payment_method'],
                'payment_metadata' => $cfg['payment_method'] ? ['paid_via' => $cfg['payment_method'], 'transaction_id' => 'TXN-'.rand(10000, 99999)] : null,
            ]);

            // Save items without triggering events that could fail, or by passing attributes
            foreach ($itemsData as $it) {
                OrderItem::create(array_merge($it, ['order_id' => $order->id]));
            }
        }
    }
}
