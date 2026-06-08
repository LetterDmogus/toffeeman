<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class KitchenController extends Controller
{
    /**
     * Get active orders for the kitchen.
     * Orders with status pending, preparing, or ready.
     */
    public function index()
    {
        $orders = Order::with(['items.menuItem.category', 'items.package', 'table', 'customer'])
            ->whereIn('status', ['pending', 'preparing', 'ready'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($orders);
    }

    /**
     * Update order status.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,preparing,ready,served,completed,cancelled',
        ]);

        $order->update(['status' => $validated['status']]);

        // If order is ready, mark all items as done
        if ($validated['status'] === 'ready') {
            $order->items()->update(['status' => 'done']);
        }

        // If order is re-opened (moved back to preparing), reset items status if they were done
        if ($validated['status'] === 'preparing') {
            $order->items()->where('status', 'done')->update(['status' => 'cooking']);
        }

        return response()->json([
            'message' => 'Status pesanan berhasil diperbarui',
            'order' => $order->load(['items', 'table', 'customer']),
        ]);
    }

    /**
     * Update individual item status.
     */
    public function updateItemStatus(Request $request, OrderItem $item)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,cooking,done,served',
        ]);

        $item->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Status item berhasil diperbarui',
            'item' => $item,
        ]);
    }

    /**
     * Get TTS text for an individual item.
     */
    public function getItemTtsText(OrderItem $item)
    {
        $item->load(['order.table', 'order.customer']);
        $order = $item->order;
        $location = $order->table ? 'Meja '.$order->table->number : 'Bawa pulang';

        $text = "{$location}. ";
        $text .= "{$item->qty} {$item->name}. ";

        if (! empty($item->variants)) {
            $variants = [];
            foreach ($item->variants as $v) {
                $name = $v['name'] ?? $v['value_name'] ?? '';
                if ($name) {
                    $variants[] = $name;
                }
            }
            if (! empty($variants)) {
                $text .= 'Varian '.implode(', ', $variants).'. ';
            }
        }

        if (! empty($item->add_ons)) {
            $addOns = [];
            foreach ($item->add_ons as $a) {
                $name = $a['name'] ?? '';
                if ($name) {
                    $addOns[] = $name;
                }
            }
            if (! empty($addOns)) {
                $text .= 'Tambahan '.implode(', ', $addOns).'. ';
            }
        }

        if ($item->notes) {
            $text .= "Catatan: {$item->notes}.";
        }

        return response()->json(['text' => trim($text)]);
    }

    /**
     * Get TTS text for the entire order.
     */
    public function getOrderTtsText(Order $order)
    {
        $order->load(['items', 'table', 'customer']);
        $location = $order->table ? 'Meja '.$order->table->number : 'Bawa pulang';
        $customer = $order->customer ? 'Atas nama '.$order->customer->name : 'Walk in';

        $text = "Pesanan baru. {$location}. {$customer}. ";

        if ($order->notes) {
            $text .= "Catatan umum: {$order->notes}. ";
        }

        $text .= 'Menu yang dipesan: ';
        $itemsText = [];
        foreach ($order->items as $item) {
            if ($item->status === 'cancelled') {
                continue;
            }
            $itemText = "{$item->qty} {$item->name}";

            $itemDetails = [];
            if (! empty($item->variants)) {
                $variants = [];
                foreach ($item->variants as $v) {
                    $name = $v['name'] ?? $v['value_name'] ?? '';
                    if ($name) {
                        $variants[] = $name;
                    }
                }
                if (! empty($variants)) {
                    $itemDetails[] = 'varian '.implode(', ', $variants);
                }
            }

            if (! empty($item->add_ons)) {
                $addOns = [];
                foreach ($item->add_ons as $a) {
                    $name = $a['name'] ?? '';
                    if ($name) {
                        $addOns[] = $name;
                    }
                }
                if (! empty($addOns)) {
                    $itemDetails[] = 'tambahan '.implode(', ', $addOns);
                }
            }

            if ($item->notes) {
                $itemDetails[] = 'catatan '.$item->notes;
            }

            if (! empty($itemDetails)) {
                $itemText .= ' dengan '.implode(', ', $itemDetails);
            }

            $itemsText[] = $itemText;
        }

        $text .= implode('. ', $itemsText).'.';

        return response()->json(['text' => trim($text)]);
    }
}
