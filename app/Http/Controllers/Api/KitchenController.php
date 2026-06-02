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
}
