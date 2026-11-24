<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Lab 7 - supplier delivery metrics with query caching. Aggregate work is done
 * in SQL (not in PHP loops) and the result is cached for 10 minutes.
 */
class SupplierPerformanceService
{
    public const CACHE_TTL = 600; // seconds

    /** @return array<string, float|int> */
    public function metrics(Supplier $supplier): array
    {
        if (! $supplier->exists) {
            return $this->emptyMetrics();
        }

        return Cache::remember(
            "supplier:performance:{$supplier->id}",
            now()->addSeconds(self::CACHE_TTL),
            fn () => $this->calculate($supplier->id)
        );
    }

    /** Ranking used by the performance report and dashboard widget. */
    public function ranking(int $limit = 20): Collection
    {
        return Cache::remember(
            "supplier:performance:ranking:{$limit}",
            now()->addSeconds(self::CACHE_TTL),
            function () use ($limit) {
                return Supplier::query()
                    ->withTrashed()
                    ->whereHas('purchaseOrders')
                    ->withCount('purchaseOrders as total_orders')
                    ->get()
                    ->map(function (Supplier $supplier) {
                        return (object) array_merge(
                            ['id' => $supplier->id, 'company_name' => $supplier->company_name],
                            $this->metrics($supplier)
                        );
                    })
                    ->sortByDesc('on_time_rate')
                    ->take($limit)
                    ->values();
            }
        );
    }

    /** Call after a receipt changes supplier performance. */
    public function clearCache(int $supplierId): void
    {
        Cache::forget("supplier:performance:{$supplierId}");

        // Ranking keys are limited; clear the common ones used by the app.
        foreach ([5, 10, 20, 50] as $limit) {
            Cache::forget("supplier:performance:ranking:{$limit}");
        }
    }

    /** @return array<string, float|int> */
    private function calculate(int $supplierId): array
    {
        $orders = DB::table('purchase_orders')
            ->where('supplier_id', $supplierId)
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(*) as total_orders,
                COALESCE(SUM(CASE WHEN status IN ('approved', 'sent', 'received') THEN 1 ELSE 0 END), 0) as completed_orders,
                COALESCE(SUM(CASE WHEN status = 'received' AND actual_delivery_date IS NOT NULL
                    AND expected_delivery_date IS NOT NULL AND actual_delivery_date <= expected_delivery_date
                    THEN 1 ELSE 0 END), 0) as delivered_on_time,
                COALESCE(SUM(CASE WHEN status = 'received' AND actual_delivery_date IS NOT NULL THEN 1 ELSE 0 END), 0) as delivered_orders,
                COALESCE(SUM(total), 0) as total_value
            ")
            ->first();

        // Average lead time is portable across MySQL and SQLite: fetch only the
        // two date columns and calculate the difference in PHP.
        $deliveryDates = DB::table('purchase_orders')
            ->where('supplier_id', $supplierId)
            ->whereNull('deleted_at')
            ->whereNotNull('actual_delivery_date')
            ->select(['order_date', 'expected_delivery_date', 'actual_delivery_date'])
            ->get();
        $avgDeliveryDays = $deliveryDates->isEmpty()
            ? 0.0
            : $deliveryDates->avg(fn ($row) => abs((int) Carbon::parse($row->actual_delivery_date)
                ->diffInDays(Carbon::parse($row->order_date))));

        $quotedDates = $deliveryDates->filter(fn ($row) => $row->expected_delivery_date !== null);
        $avgQuotedDays = $quotedDates->isEmpty()
            ? 0.0
            : $quotedDates->avg(fn ($row) => abs((int) Carbon::parse($row->expected_delivery_date)
                ->diffInDays(Carbon::parse($row->order_date))));

        // Only delivered orders can be scored for quantity accuracy; pending
        // or rejected orders must not drag the ratio down.
        $accuracy = DB::table('purchase_order_items as items')
            ->join('purchase_orders as orders', 'orders.id', '=', 'items.purchase_order_id')
            ->where('orders.supplier_id', $supplierId)
            ->whereNull('orders.deleted_at')
            ->where('orders.status', PurchaseOrder::STATUS_RECEIVED)
            ->selectRaw('
                COALESCE(SUM(items.quantity), 0) as ordered,
                COALESCE(SUM(CASE WHEN items.received_quantity < items.quantity
                    THEN items.received_quantity ELSE items.quantity END), 0) as accurate
            ')
            ->first();

        $delivered = (int) ($orders->delivered_orders ?? 0);
        $onTime = (int) ($orders->delivered_on_time ?? 0);
        $ordered = (int) ($accuracy->ordered ?? 0);

        return [
            'total_orders' => (int) ($orders->total_orders ?? 0),
            'completed_orders' => (int) ($orders->completed_orders ?? 0),
            'delivered_orders' => $delivered,
            'delivered_on_time' => $onTime,
            'avg_delivery_days' => round($avgDeliveryDays, 1),
            'avg_quoted_days' => round($avgQuotedDays, 1),
            'avg_variance_days' => round($avgDeliveryDays - $avgQuotedDays, 1),
            'on_time_rate' => $delivered > 0 ? round($onTime / $delivered * 100, 1) : 0.0,
            'accuracy_rate' => $ordered > 0 ? round((int) ($accuracy->accurate ?? 0) / $ordered * 100, 1) : 0.0,
            'total_value' => round((float) ($orders->total_value ?? 0), 2),
        ];
    }

    /** @return array<string, float|int> */
    private function emptyMetrics(): array
    {
        return [
            'total_orders' => 0,
            'completed_orders' => 0,
            'delivered_orders' => 0,
            'delivered_on_time' => 0,
            'avg_delivery_days' => 0.0,
            'avg_quoted_days' => 0.0,
            'avg_variance_days' => 0.0,
            'on_time_rate' => 0.0,
            'accuracy_rate' => 0.0,
            'total_value' => 0.0,
        ];
    }
}
