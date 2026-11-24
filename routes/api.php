<?php

use App\Http\Controllers\Api\DashboardWidgetApiController;
use App\Http\Controllers\Api\InventoryApiController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

/*
 * Lab 6 API endpoints consumed by the dashboard's AJAX polling script.
 * The session/web middleware group is applied so the same logged-in user is
 * recognised on same-origin fetch() calls (no Sanctum token required).
 */
Route::middleware(['web', 'auth', EnsureUserIsActive::class])->group(function () {
    Route::get('dashboard/stats', [InventoryApiController::class, 'stats'])->name('api.dashboard.stats');
    Route::get('products/low-stock', [InventoryApiController::class, 'lowStock'])->name('api.products.low-stock');
    Route::get('products/{product}/stock', [InventoryApiController::class, 'productStock'])->name('api.products.stock')->whereNumber('product');
    Route::get('transactions/recent', [InventoryApiController::class, 'recentTransactions'])->name('api.transactions.recent');

    /* Lab 7 - procurement widget payloads for AJAX refresh. */
    Route::get('dashboard/pending-orders', [DashboardWidgetApiController::class, 'pendingOrders'])->name('api.dashboard.pending-orders');
    Route::get('dashboard/recent-orders', [DashboardWidgetApiController::class, 'recentOrders'])->name('api.dashboard.recent-orders');
    Route::get('dashboard/supplier-performance', [DashboardWidgetApiController::class, 'supplierPerformance'])->name('api.dashboard.supplier-performance');
    Route::get('dashboard/reorder-alerts', [DashboardWidgetApiController::class, 'reorderAlerts'])->name('api.dashboard.reorder-alerts');
});
