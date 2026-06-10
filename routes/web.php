<?php

use App\Http\Controllers\Api\AddOnController;
use App\Http\Controllers\Api\AttendanceController as ApiAttendanceController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\IngredientBatchController;
use App\Http\Controllers\Api\IngredientCategoryController;
use App\Http\Controllers\Api\IngredientController;
use App\Http\Controllers\Api\IngredientMutationController;
use App\Http\Controllers\Api\InventoryCategoryController;
use App\Http\Controllers\Api\InventoryInController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\InventoryMutationController;
use App\Http\Controllers\Api\InventoryOpnameController;
use App\Http\Controllers\Api\InventoryOutController;
use App\Http\Controllers\Api\KioskAuthController;
use App\Http\Controllers\Api\KioskOrderController;
use App\Http\Controllers\Api\KitchenController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PayrollController as ApiPayrollController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\PromoController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\Settings\AppSettingController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::inertia('/', 'Welcome')->name('home');

// ── Barcode Scanner (public, no auth required for phone) ──────────────────
Route::get('/scanner', function () {
    return Inertia\Inertia::render('pos/Scanner', [
        'session' => request('session'),
    ]);
})->name('scanner');

Route::get('/api/scanner/poll/{session}', [ScannerController::class, 'poll'])->name('api.scanner.poll');
Route::post('/api/scanner/submit', [ScannerController::class, 'submit'])->name('api.scanner.submit');

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
    Route::inertia('reports/orders', 'reports/Orders')->name('reports.orders');

    // Payroll
    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('payroll/export-list', [PayrollController::class, 'exportList'])->name('payroll.export-list');
    Route::get('payroll/export-report', [PayrollController::class, 'exportReport'])->name('payroll.export-report');
    Route::get('payroll/generate', [PayrollController::class, 'create'])->name('payroll.create');
    Route::post('payroll/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
    Route::get('payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
    Route::get('payroll/{payroll}/export', [PayrollController::class, 'export'])->name('payroll.export');
    Route::patch('payroll/{payroll}', [PayrollController::class, 'update'])->name('payroll.update');
    Route::patch('payroll/{payroll}/approve', [PayrollController::class, 'approve'])->name('payroll.approve');
    Route::patch('payroll/{payroll}/pay', [PayrollController::class, 'pay'])->name('payroll.pay');
    Route::delete('payroll/{payroll}', [PayrollController::class, 'destroy'])->name('payroll.destroy');

    // Katalog Produk
    Route::prefix('catalog')->name('catalog.')->group(function () {
        Route::inertia('menu', 'catalog/Menu')->name('menu');
        Route::inertia('categories', 'catalog/Categories')->name('categories');
        Route::inertia('packages', 'catalog/Packages')->name('packages');
        Route::inertia('add-ons', 'catalog/AddOns')->name('add-ons');
        Route::inertia('promos', 'catalog/Promos')->name('promos');
        Route::inertia('recipe-book', 'catalog/RecipeBook')->name('recipe-book');
    });

    // Operasional & Stok
    Route::prefix('ops')->name('ops.')->group(function () {
        Route::inertia('tables', 'ops/Tables')->name('tables');
        Route::inertia('inventory', 'ops/Inventory')->name('inventory');
        Route::inertia('inventory-categories', 'ops/InventoryCategories')->name('inventory-categories');
        Route::inertia('inventory-ins', 'ops/InventoryIns')->name('inventory-ins');
        Route::inertia('inventory-outs', 'ops/InventoryOuts')->name('inventory-outs');
        Route::inertia('inventory-opnames', 'ops/InventoryOpnames')->name('inventory-opnames');
        Route::inertia('inventory-mutations', 'ops/InventoryMutations')->name('inventory-mutations');
        Route::inertia('ingredients', 'ops/Ingredients')->name('ingredients');
        Route::inertia('ingredient-categories', 'ops/IngredientCategories')->name('ingredient-categories');
        Route::inertia('ingredient-batches', 'ops/IngredientBatches')->name('ingredient-batches');
        Route::inertia('ingredient-mutations', 'ops/IngredientMutations')->name('ingredient-mutations');
    });

    // Manajemen Tim
    Route::prefix('team')->name('team.')->group(function () {
        Route::inertia('employees', 'team/Employees')->name('employees');
        Route::inertia('positions', 'team/Positions')->name('positions');
        Route::inertia('users', 'team/Users')->name('users');
        Route::inertia('roles-permissions', 'team/RolesPermissions')->name('roles-permissions');
    });

    // Pengaturan Sistem / Site Settings
    Route::prefix('site-settings')->name('site-settings.')->group(function () {
        Route::get('ip-location', [AppSettingController::class, 'edit'])->name('ip-location.edit');
        Route::patch('ip-location', [AppSettingController::class, 'update'])->name('ip-location.update');
    });

    // Absensi Karyawan
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('attendance/kiosk', [AttendanceController::class, 'kiosk'])->name('attendance.kiosk');
    Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');

    // API routes for CRUD tables (JSON responses)
    Route::prefix('api')->name('api.')->group(function () {
        $resources = [
            'menu-items' => MenuItemController::class,
            'tables' => TableController::class,
            'packages' => PackageController::class,
            'categories' => CategoryController::class,
            'inventory-categories' => InventoryCategoryController::class,
            'inventory-items' => InventoryItemController::class,
            'inventory-ins' => InventoryInController::class,
            'inventory-outs' => InventoryOutController::class,
            'inventory-opnames' => InventoryOpnameController::class,
            'ingredient-categories' => IngredientCategoryController::class,
            'ingredients' => IngredientController::class,
            'ingredient-batches' => IngredientBatchController::class,
            'ingredient-mutations' => IngredientMutationController::class,
            'employees' => EmployeeController::class,
            'positions' => PositionController::class,
            'users' => UserController::class,
            'add-ons' => AddOnController::class,
            'promos' => PromoController::class,
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
        Route::get('roles', [RolePermissionController::class, 'index'])->name('roles.index');
        Route::post('roles', [RolePermissionController::class, 'storeRole'])->name('roles.store');
        Route::delete('roles/{role}', [RolePermissionController::class, 'destroyRole'])->name('roles.destroy');
        Route::get('permissions', [RolePermissionController::class, 'permissions'])->name('permissions.index');
        Route::post('roles/{role}/permissions', [RolePermissionController::class, 'syncPermissions'])->name('roles.permissions.sync');
        Route::post('positions/{position}/permissions', [RolePermissionController::class, 'syncPositionPermissions'])->name('positions.permissions.sync');
        Route::post('packages/{package}/items', [PackageController::class, 'syncItems'])->name('packages.items.sync');
        Route::get('inventory-items/{inventory_item}/barcode', [InventoryItemController::class, 'barcode'])->name('inventory-items.barcode');
        Route::post('inventory-mutations', [InventoryMutationController::class, 'store'])->name('inventory-mutations.store');
        Route::get('inventory-mutations', [InventoryMutationController::class, 'index'])->name('inventory-mutations.index');

        // Kitchen API
        Route::get('kitchen/orders', [KitchenController::class, 'index'])->name('kitchen.orders');
        Route::patch('kitchen/orders/{order}/status', [KitchenController::class, 'updateStatus'])->name('kitchen.orders.status');
        Route::patch('kitchen/items/{item}/status', [KitchenController::class, 'updateItemStatus'])->name('kitchen.items.status');
        Route::get('kitchen/orders/{order}/tts-text', [KitchenController::class, 'getOrderTtsText'])->name('kitchen.orders.tts-text');
        Route::get('kitchen/items/{item}/tts-text', [KitchenController::class, 'getItemTtsText'])->name('kitchen.items.tts-text');

        // Order Extensions
        Route::post('orders/{order}/items', [OrderController::class, 'addItems'])->name('orders.items.add');
        Route::patch('orders/{order}/pay', [OrderController::class, 'processPayment'])->name('orders.pay');
        Route::patch('order-items/{item}/cancel', [OrderController::class, 'cancelItem'])->name('orders.items.cancel');

        // Attendance Management API
        Route::get('attendances', [ApiAttendanceController::class, 'index'])->name('attendances.index');
        Route::post('attendances', [ApiAttendanceController::class, 'store'])->name('attendances.store');
        Route::get('attendances/{attendance}', [ApiAttendanceController::class, 'show'])->name('attendances.show');
        Route::patch('attendances/{attendance}/verify', [ApiAttendanceController::class, 'verify'])->name('attendances.verify');
        Route::delete('attendances/{attendance}', [ApiAttendanceController::class, 'destroy'])->name('attendances.destroy');
        Route::put('attendances/{attendance}/restore', [ApiAttendanceController::class, 'restore'])->name('attendances.restore')->withTrashed();
        Route::delete('attendances/{attendance}/force', [ApiAttendanceController::class, 'forceDelete'])->name('attendances.force')->withTrashed();

        // Reports API
        Route::get('reports/dashboard', [ReportController::class, 'dashboard'])->name('reports.dashboard');
        Route::get('reports/transactions', [ReportController::class, 'transactions'])->name('reports.transactions');
        Route::get('reports/orders', [ReportController::class, 'ordersReport'])->name('reports.orders');

        // Payroll API
        Route::get('payrolls', [ApiPayrollController::class, 'index'])->name('payrolls.index');
        Route::get('payrolls/report', [ApiPayrollController::class, 'report'])->name('payrolls.report');
        Route::get('payrolls/missing-employees', [ApiPayrollController::class, 'missingEmployees'])->name('payrolls.missing');

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
