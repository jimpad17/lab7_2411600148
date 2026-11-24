<?php

namespace App\Providers;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Policies\PurchaseOrderPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep pagination markup consistent with the Bootstrap 5 UI from Lab 4.
        Paginator::useBootstrapFive();

        // Lab 7 authorization: a simple Gate plus a model-specific Policy.
        Gate::define('access-procurement', fn (User $user): bool => $user->is_active);
        Gate::define('approve-high-value-orders', fn (User $user, PurchaseOrder $order): bool =>
            $user->canApprovePurchaseOrders() && $order->requires_second_approval);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
    }
}
