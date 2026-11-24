<?php

namespace App\Providers;

use App\Models\PurchaseOrder;
use App\Services\ReorderService;
use App\Services\SupplierPerformanceService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Lab 7 - dashboard widgets are bound with View Composers so any page that
 * includes a widget receives its data automatically.
 */
class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('widgets.pending-orders', function ($view) {
            $view->with([
                'pendingCount' => PurchaseOrder::pending()->count(),
                'pendingOrders' => PurchaseOrder::pending()
                    ->with('supplier')
                    ->latest()
                    ->take(5)
                    ->get(),
            ]);
        });

        View::composer('widgets.recent-orders', function ($view) {
            $view->with([
                'recentOrders' => PurchaseOrder::query()
                    ->with(['supplier', 'createdBy'])
                    ->latest()
                    ->take(5)
                    ->get(),
            ]);
        });

        View::composer('widgets.supplier-performance', function ($view) {
            $view->with([
                'topSuppliers' => app(SupplierPerformanceService::class)->ranking(5),
            ]);
        });

        View::composer('widgets.reorder-alerts', function ($view) {
            $candidates = app(ReorderService::class)->getProductsNeedingReorder();

            $view->with([
                'reorderCount' => $candidates->count(),
                'reorderProducts' => $candidates->take(5),
            ]);
        });
    }
}
