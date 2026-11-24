<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lab 7 - business logic for automated replenishment.
 *
 * Reorder Point = (Average Daily Usage × Lead Time) + Safety Stock
 * Order Quantity covers lead time + review period, respects the supplier's
 * minimum order amount and rounds up to the product's package size.
 */
class ReorderService
{
    public const USAGE_WINDOW_DAYS = 30;
    public const SAFETY_FACTOR = 0.20;
    public const REVIEW_PERIOD_DAYS = 7;
    public const DEFAULT_LEAD_TIME_DAYS = 7;

    /** Units consumed per day, based on the last 30 days of stock movements. */
    public function averageDailyUsage(Product $product): float
    {
        if ($product->daily_usage_override !== null) {
            return max(0.01, (float) $product->daily_usage_override);
        }

        $outbound = (float) InventoryTransaction::query()
            ->where('product_id', $product->id)
            ->where('created_at', '>=', now()->subDays(self::USAGE_WINDOW_DAYS))
            ->notReversed()
            ->where(function ($query) {
                $query->where('type', InventoryTransaction::TYPE_OUT)
                    ->orWhere(function ($query) {
                        $query->where('type', InventoryTransaction::TYPE_ADJUSTMENT)
                            ->where('direction', InventoryTransaction::DIRECTION_OUT);
                    });
            })
            ->sum('quantity');

        if ($outbound > 0) {
            return round($outbound / self::USAGE_WINDOW_DAYS, 2);
        }

        // No history yet: assume the existing reorder level represents one week.
        return round(max(1, (int) $product->reorder_level) / 7, 2);
    }

    public function leadTimeDays(Product $product): int
    {
        return max(1, (int) ($product->supplierRecord?->lead_time_days ?? self::DEFAULT_LEAD_TIME_DAYS));
    }

    public function safetyStock(Product $product, ?float $averageUsage = null): int
    {
        $averageUsage ??= $this->averageDailyUsage($product);

        if ($product->safety_stock_override !== null) {
            return max(0, (int) $product->safety_stock_override);
        }

        return (int) ceil($averageUsage * $this->leadTimeDays($product) * self::SAFETY_FACTOR);
    }

    public function calculateReorderPoint(Product $product): int
    {
        $averageUsage = $this->averageDailyUsage($product);
        $point = ($averageUsage * $this->leadTimeDays($product)) + $this->safetyStock($product, $averageUsage);

        return max(1, (int) ceil($point));
    }

    public function calculateOptimalOrderQuantity(Product $product): int
    {
        $averageUsage = $this->averageDailyUsage($product);
        $leadTime = $this->leadTimeDays($product);
        $safety = $this->safetyStock($product, $averageUsage);

        // Cover the lead time plus a weekly review period, minus what is on hand.
        $quantity = (int) ceil($averageUsage * ($leadTime + self::REVIEW_PERIOD_DAYS) + $safety - (int) $product->quantity);
        $quantity = max(0, $quantity);

        $unitPrice = max(0.01, (float) $product->unit_price);
        $minimumByAmount = (int) ceil((float) ($product->supplierRecord?->minimum_order_amount ?? 0) / $unitPrice);
        $quantity = max($quantity, $minimumByAmount);

        $packageSize = max(1, (int) ($product->package_size ?: 1));

        return (int) (ceil($quantity / $packageSize) * $packageSize);
    }

    public function daysOfCover(Product $product): float
    {
        $usage = $this->averageDailyUsage($product);

        return $usage > 0 ? round((int) $product->quantity / $usage, 1) : 0.0;
    }

    /**
     * @return Collection<int, Product> Products with calculated attributes attached.
     */
    public function getProductsNeedingReorder(): Collection
    {
        return Product::query()
            ->where(function ($query) {
                // Include products automatically deactivated by a stockout so
                // the scheduler can still replenish empty shelves.
                $query->where('is_active', true)->orWhere('quantity', 0);
            })
            ->with(['supplierRecord', 'category'])
            ->whereNotNull('supplier_id')
            ->orderBy('quantity')
            ->get()
            ->filter(function (Product $product) {
                $point = $this->calculateReorderPoint($product);
                $product->setAttribute('reorder_point', $point);

                return (int) $product->quantity <= $point;
            })
            ->map(function (Product $product) {
                $product->setAttribute('suggested_quantity', $this->calculateOptimalOrderQuantity($product));
                $product->setAttribute('days_of_cover', $this->daysOfCover($product));

                return $product;
            })
            ->values();
    }

    /** Group replenishment candidates by supplier for one PO per supplier. */
    public function groupProductsBySupplier($products): Collection
    {
        return collect($products)
            ->filter(fn (Product $product) => $product->supplier_id !== null)
            ->groupBy('supplier_id');
    }

    /**
     * Create a draft purchase order per supplier. Existing open orders for the
     * same product/supplier are skipped to prevent duplicate replenishment.
     *
     * @return array{products_scanned: int, products_needing_reorder: int, orders_created: int, orders_skipped: int, orders: Collection, products: Collection}
     */
    public function generatePurchaseOrders(?User $creator = null): array
    {
        $needReorder = $this->getProductsNeedingReorder();
        $groups = $this->groupProductsBySupplier($needReorder);
        $orders = collect();
        $skipped = 0;

        $creator ??= User::whereIn('role', ['admin', 'manager'])->active()->first();

        foreach ($groups as $supplierId => $products) {
            /** @var Supplier|null $supplier */
            $supplier = Supplier::withTrashed()->find($supplierId);

            if (! $supplier || ! $supplier->is_active) {
                $skipped += $products->count();

                continue;
            }

            $freshProducts = $products->filter(function (Product $product) {
                $pending = DB::table('purchase_order_items as items')
                    ->join('purchase_orders as orders', 'orders.id', '=', 'items.purchase_order_id')
                    ->where('items.product_id', $product->id)
                    ->where('orders.supplier_id', $product->supplier_id)
                    ->whereNull('orders.deleted_at')
                    ->whereNotIn('orders.status', [
                        PurchaseOrder::STATUS_RECEIVED,
                        PurchaseOrder::STATUS_REJECTED,
                        PurchaseOrder::STATUS_CANCELLED,
                    ])
                    ->exists();

                return ! $pending;
            })->filter(fn (Product $product) => $product->suggested_quantity > 0);

            if ($freshProducts->isEmpty()) {
                $skipped += $products->count();

                continue;
            }

            $order = DB::transaction(function () use ($freshProducts, $supplier, $creator) {
                $order = PurchaseOrder::create([
                    'reference_number' => PurchaseOrder::generateReferenceNumber(),
                    'supplier_id' => $supplier->id,
                    'status' => PurchaseOrder::STATUS_DRAFT,
                    'order_date' => today(),
                    'expected_delivery_date' => today()->addDays((int) $supplier->lead_time_days),
                    'notes' => 'Auto-generated by the scheduled reorder check.',
                    'terms' => $supplier->payment_terms,
                    'user_id' => $creator?->id ?? User::first()?->id,
                    'auto_generated' => true,
                ]);

                foreach ($freshProducts as $product) {
                    $order->items()->create([
                        'product_id' => $product->id,
                        'quantity' => (int) $product->suggested_quantity,
                        'unit_price' => $product->unit_price,
                        'notes' => 'Reorder point '.$product->reorder_point.' · on hand '.$product->quantity,
                    ]);
                }

                $order->calculateTotal();
                $order->addApproval(
                    'submitted',
                    'Automatically generated from reorder point calculation.',
                    $creator
                );
                ActivityLogger::log(
                    'created',
                    'Procurement',
                    "Auto-generated {$order->reference_number} for {$supplier->company_name} ({$freshProducts->count()} product(s)).",
                    $creator,
                    $order
                );

                return $order;
            });

            $orders->push($order);
        }

        return [
            'products_scanned' => Product::active()->count(),
            'products_needing_reorder' => $needReorder->count(),
            'orders_created' => $orders->count(),
            'orders_skipped' => $skipped,
            'orders' => $orders,
            'products' => $needReorder,
        ];
    }
}
