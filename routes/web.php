<?php

use App\Http\Controllers\Api\AddOnController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\IngredientBatchController;
use App\Http\Controllers\Api\IngredientCategoryController;
use App\Http\Controllers\Api\IngredientController;
use App\Http\Controllers\Api\InventoryCategoryController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\KioskAuthController;
use App\Http\Controllers\Api\KioskOrderController;
use App\Http\Controllers\Api\KitchenController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\KioskController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::inertia('/', 'Welcome')->name('home');

Route::post('api/midtrans/webhook', [MidtransWebhookController::class, 'handle'])->name('midtrans.webhook');

// ── Kiosk (public, no auth required) ──────────────────────────────────────
Route::prefix('kiosk/{token}')->name('kiosk.')->group(function () {
    Route::get('/', [KioskController::class, 'show'])->name('show');
    Route::get('/orders', [KioskController::class, 'orders'])->name('orders');

    // Kiosk API
    Route::post('/api/orders', [KioskOrderController::class, 'store'])->name('api.orders.store');
    Route::get('/api/orders/{order}', [KioskOrderController::class, 'show'])->name('api.orders.show');

    // Customer auth (login/register from kiosk)
    Route::post('/api/auth/register', [KioskAuthController::class, 'register'])->name('api.auth.register');
    Route::post('/api/auth/login', [KioskAuthController::class, 'login'])->name('api.auth.login');
    Route::post('/api/auth/logout', [KioskAuthController::class, 'logout'])->name('api.auth.logout');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('pos', 'pos/Index')->name('pos');
    Route::inertia('kitchen', 'kitchen/Index')->name('kitchen');
    Route::inertia('orders', 'orders/Index')->name('orders');
    Route::inertia('reports', 'reports/Index')->name('reports');

    // Katalog Produk
    Route::prefix('catalog')->name('catalog.')->group(function () {
        Route::inertia('menu', 'catalog/Menu')->name('menu');
        Route::inertia('categories', 'catalog/Categories')->name('categories');
        Route::inertia('packages', 'catalog/Packages')->name('packages');
        Route::inertia('add-ons', 'catalog/AddOns')->name('add-ons');
    });

    // Operasional & Stok
    Route::prefix('ops')->name('ops.')->group(function () {
        Route::inertia('tables', 'ops/Tables')->name('tables');
        Route::inertia('inventory', 'ops/Inventory')->name('inventory');
        Route::inertia('inventory-categories', 'ops/InventoryCategories')->name('inventory-categories');
        Route::inertia('ingredients', 'ops/Ingredients')->name('ingredients');
        Route::inertia('ingredient-categories', 'ops/IngredientCategories')->name('ingredient-categories');
        Route::inertia('ingredient-batches', 'ops/IngredientBatches')->name('ingredient-batches');
    });

    // Manajemen Tim
    Route::prefix('team')->name('team.')->group(function () {
        Route::inertia('employees', 'team/Employees')->name('employees');
        Route::inertia('positions', 'team/Positions')->name('positions');
        Route::inertia('users', 'team/Users')->name('users');
    });

    // API routes for CRUD tables (JSON responses)
    Route::prefix('api')->name('api.')->group(function () {
        $resources = [
            'menu-items' => MenuItemController::class,
            'tables' => TableController::class,
            'packages' => PackageController::class,
            'categories' => CategoryController::class,
            'inventory-categories' => InventoryCategoryController::class,
            'inventory-items' => InventoryItemController::class,
            'ingredient-categories' => IngredientCategoryController::class,
            'ingredients' => IngredientController::class,
            'ingredient-batches' => IngredientBatchController::class,
            'employees' => EmployeeController::class,
            'positions' => PositionController::class,
            'users' => UserController::class,
            'add-ons' => AddOnController::class,
            'orders' => OrderController::class,
        ];

        foreach ($resources as $uri => $controller) {
            $parameter = str_replace('-', '_', Str::singular($uri));

            // Bulk Actions
            Route::delete("{$uri}/bulk-delete", [$controller, 'bulkDelete'])->name("{$uri}.bulk-delete");
            Route::put("{$uri}/bulk-restore", [$controller, 'bulkRestore'])->name("{$uri}.bulk-restore");
            Route::delete("{$uri}/bulk-force", [$controller, 'bulkForceDelete'])->name("{$uri}.bulk-force");

            // Individual Restore & Force Delete
            Route::put("{$uri}/{{$parameter}}/restore", [$controller, 'restore'])->name("{$uri}.restore")->withTrashed();
            Route::delete("{$uri}/{{$parameter}}/force", [$controller, 'forceDelete'])->name("{$uri}.force")->withTrashed();
        }

        // Special routes
        Route::get('users/roles', [UserController::class, 'roles'])->name('users.roles');
        Route::post('packages/{package}/items', [PackageController::class, 'syncItems'])->name('packages.items.sync');

        // Kitchen API
        Route::get('kitchen/orders', [KitchenController::class, 'index'])->name('kitchen.orders');
        Route::patch('kitchen/orders/{order}/status', [KitchenController::class, 'updateStatus'])->name('kitchen.orders.status');
        Route::patch('kitchen/items/{item}/status', [KitchenController::class, 'updateItemStatus'])->name('kitchen.items.status');

        // Order Extensions
        Route::post('orders/{order}/items', [OrderController::class, 'addItems'])->name('orders.items.add');
        Route::patch('orders/{order}/pay', [OrderController::class, 'processPayment'])->name('orders.pay');
        Route::patch('order-items/{item}/cancel', [OrderController::class, 'cancelItem'])->name('orders.items.cancel');

        // Reports API
        Route::get('reports/dashboard', [ReportController::class, 'dashboard'])->name('reports.dashboard');
        Route::get('reports/transactions', [ReportController::class, 'transactions'])->name('reports.transactions');

        // Table QR Code
        Route::post('tables/{table}/qr/generate', [TableController::class, 'generateQr'])->name('tables.qr.generate');
        Route::get('tables/{table}/qr/download', [TableController::class, 'downloadQr'])->name('tables.qr.download');

        // Standard API Resources
        foreach ($resources as $uri => $controller) {
            Route::apiResource($uri, $controller);
        }
    });
});

require __DIR__.'/settings.php';
