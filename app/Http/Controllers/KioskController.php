<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Package;
use App\Models\Table;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KioskController extends Controller
{
    /**
     * Show the kiosk self-order page for a given table token.
     */
    public function show(string $token): Response
    {
        $table = Table::where('qr_code', $token)->firstOrFail();

        abort_if($table->status === 'maintenance', 503, 'Meja sedang dalam perbaikan.');

        $categories = Category::orderBy('name')->get(['id', 'name']);

        $menuItems = MenuItem::with(['category', 'addOns', 'options.values'])
            ->where('status', 'available')
            ->orderBy('name')
            ->get();

        $packages = Package::with(['packageItems.menuItem', 'packageItems.inventoryItem'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        /** @var User|null $customer */
        $customer = auth()->user();
        $isCustomer = $customer && $customer->hasRole('customer');

        return Inertia::render('kiosk/Show', [
            'table' => $table->only(['id', 'number', 'name', 'capacity', 'location']),
            'token' => $token,
            'categories' => $categories,
            'menuItems' => $menuItems,
            'packages' => $packages,
            'customer' => $isCustomer ? $customer->only(['id', 'name', 'email', 'phone']) : null,
        ]);
    }

    /**
     * Show order history for a logged-in customer via kiosk.
     */
    public function orders(Request $request, string $token): Response
    {
        Table::where('qr_code', $token)->firstOrFail();

        /** @var User $customer */
        $customer = auth()->user();

        abort_unless($customer && $customer->hasRole('customer'), 401);

        $orders = Order::with(['items', 'table'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->paginate(10);

        return Inertia::render('kiosk/Orders', [
            'token' => $token,
            'customer' => $customer->only(['id', 'name', 'email', 'phone']),
            'orders' => $orders,
        ]);
    }
}
