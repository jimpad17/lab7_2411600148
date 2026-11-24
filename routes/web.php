<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPerformanceController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\LogActivity;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    return redirect(auth()->check() ? route('dashboard') : route('login'));
});

/*
 * Every authenticated page visit / search is logged by LogActivity;
 * EnsureUserIsActive immediately logs out deactivated accounts.
 */
Route::middleware(['auth', EnsureUserIsActive::class, LogActivity::class])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /*
     * Shared by BOTH roles. Staff handle the day-to-day work: read the
     * catalog, check stock levels and post Stock In / Stock Out movements.
     */
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory/{product}/stock', [InventoryController::class, 'stock'])->name('inventory.stock');

    /* Lab 6 - transaction log, alerts and real-time stock tracking. */
    Route::get('inventory-transactions', [InventoryTransactionController::class, 'index'])->name('transactions.index');
    Route::get('inventory-transactions/create', [InventoryTransactionController::class, 'create'])->name('transactions.create');
    Route::post('inventory-transactions', [InventoryTransactionController::class, 'store'])->name('transactions.store');
    Route::get('inventory-transactions/export/csv', [InventoryTransactionController::class, 'exportCsv'])->name('transactions.export');
    Route::get('inventory-transactions/export/pdf', [InventoryTransactionController::class, 'exportPdf'])->name('transactions.export-pdf');
    Route::get('inventory-transactions/{transaction}', [InventoryTransactionController::class, 'show'])->name('transactions.show')->whereNumber('transaction');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read')->whereUuid('notification');

    /* Purchase / sales orders: staff submit, admins review and approve. */
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('sales-orders', [OrderController::class, 'sales'])->name('sales-orders');
    // Legacy route name kept for Lab 6 bookmarks/tests; the Lab 7 purchase
    // order module uses the `purchase-orders.*` names below.
    Route::get('orders/purchase', [OrderController::class, 'purchase'])->name('purchase-orders');
    Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show')->whereNumber('order');

    /* Records / monitoring menu pages. */
    Route::get('receipts', [ReceiptController::class, 'index'])->name('receipts.index');
    Route::get('receipts/{order}', [ReceiptController::class, 'show'])->name('receipts.show')->whereNumber('order');
    Route::get('transaction-history', [InventoryTransactionController::class, 'index'])->name('transaction-history');
    Route::get('product-history', [MenuController::class, 'productHistory'])->name('product-history');
    Route::get('audit-trail', [ActivityLogController::class, 'index'])->name('audit-trail');
    Route::get('product-report', [MenuController::class, 'productReport'])->name('product-report');

    Route::get('profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');

    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/export/csv', [ProductController::class, 'exportCsv'])->name('products.export');
    Route::get('products/export/pdf', [ProductController::class, 'exportPdf'])->name('products.export-pdf');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show')->whereNumber('product');

    Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');

    /*
     * Lab 7 - Supplier Management, Purchase Orders and Procurement reporting.
     * Read access is shared; mutations are protected by Policies/Gates in the
     * controllers and by the `admin` middleware for supplier master data.
     */
    Route::get('suppliers/performance', [SupplierPerformanceController::class, 'index'])->name('suppliers.performance');
    Route::get('suppliers/performance/export/csv', [SupplierPerformanceController::class, 'exportCsv'])->name('suppliers.performance.export');
    Route::get('suppliers/performance/export/pdf', [SupplierPerformanceController::class, 'exportPdf'])->name('suppliers.performance.export-pdf');
    Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show')->whereNumber('supplier');

    Route::get('purchase-orders/export/csv', [PurchaseOrderController::class, 'exportCsv'])->name('purchase-orders.export');
    Route::get('purchase-orders/export/pdf', [PurchaseOrderController::class, 'exportPdf'])->name('purchase-orders.export-pdf');
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
    Route::get('purchase-orders/{order}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit')->whereNumber('order');
    Route::get('purchase-orders/{order}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show')->whereNumber('order');
    Route::put('purchase-orders/{order}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update')->whereNumber('order');
    Route::delete('purchase-orders/{order}', [PurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy')->whereNumber('order');
    Route::post('purchase-orders/{order}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve')->whereNumber('order');
    Route::post('purchase-orders/{order}/reject', [PurchaseOrderController::class, 'reject'])->name('purchase-orders.reject')->whereNumber('order');
    Route::post('purchase-orders/{order}/send', [PurchaseOrderController::class, 'send'])->name('purchase-orders.send')->whereNumber('order');
    Route::post('purchase-orders/{order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive')->whereNumber('order');

    /*
     * Admin-only: master-data changes, people, money valuation and the
     * audit trail. The `admin` middleware aborts with 403 for staff.
     */
    Route::middleware('admin')->group(function () {
        // Products - destroy = deactivate; restore brings records back
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');

        // Brands
        Route::get('brands/create', [BrandController::class, 'create'])->name('brands.create');
        Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
        Route::get('brands/{brand}/edit', [BrandController::class, 'edit'])->name('brands.edit');
        Route::put('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
        Route::post('brands/{brand}/restore', [BrandController::class, 'restore'])->name('brands.restore');

        // Categories
        Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])->name('categories.restore');

        // Lab 7 - supplier master data (soft deletes + restore)
        Route::get('suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit')->whereNumber('supplier');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update')->whereNumber('supplier');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy')->whereNumber('supplier');
        Route::post('suppliers/{supplier}/restore', [SupplierController::class, 'restore'])->name('suppliers.restore')->whereNumber('supplier');

        // Users
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::post('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Daily report pages (admin management/monitoring menu)
        Route::get('daily-report', [MenuController::class, 'dailyReport'])->name('daily-report');
        Route::get('daily-report-history', [MenuController::class, 'dailyReportHistory'])->name('daily-report-history');

        // Audit trail (legacy URL kept for bookmarks)
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

        // Reversing a transaction keeps the original audit record intact.
        Route::delete('inventory-transactions/{transaction}', [InventoryTransactionController::class, 'destroy'])
            ->name('transactions.destroy')->whereNumber('transaction');

        // Physical-count adjustments are authorized admin actions.
        Route::get('adjustments/create', [StockAdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('adjustments', [StockAdjustmentController::class, 'store'])->name('adjustments.store');

        // Admin-only order review: edit the quantity, then approve or reject.
        Route::get('orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit')->whereNumber('order');
        Route::put('orders/{order}', [OrderController::class, 'update'])->name('orders.update')->whereNumber('order');
        Route::post('orders/{order}/approve', [OrderController::class, 'approve'])->name('orders.approve')->whereNumber('order');
        Route::post('orders/{order}/reject', [OrderController::class, 'reject'])->name('orders.reject')->whereNumber('order');

        // Reports (reached from the dashboard top-right button, not the sidebar)
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export/csv', [ReportController::class, 'exportCsv'])->name('reports.export');

        // Lab 6 - stock movement / low stock / valuation tracking report.
        Route::get('reports/tracking', [ReportController::class, 'tracking'])->name('reports.tracking');
        Route::get('reports/tracking/export', [ReportController::class, 'exportTrackingCsv'])->name('reports.tracking.export');
    });
});

require __DIR__.'/auth.php';

/*
 * Any unmatched URL - including random slash/dot combos like
 * /products//////.......////// - returns to the last real page the user
 * opened. The target is stored by LogActivity as a request URI, so it is
 * always local and cannot be poisoned into an external redirect.
 */
Route::fallback(function () {
    $target = session('last_page_url');

    if (is_string($target) && Str::startsWith($target, '/') && ! Str::startsWith($target, '//')) {
        return redirect()->to($target);
    }

    return redirect(auth()->check() ? route('dashboard') : route('login'));
})->name('fallback');
