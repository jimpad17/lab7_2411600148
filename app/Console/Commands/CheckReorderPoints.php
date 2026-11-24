<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ActivityLogger;
use App\Services\PurchaseOrderNotifier;
use App\Services\ReorderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckReorderPoints extends Command
{
    /** @var string */
    protected $signature = 'inventory:check-reorder-points {--dry-run : Report results without creating purchase orders}';

    /** @var string */
    protected $description = 'Check calculated reorder points and generate purchase orders for low stock';

    public function handle(ReorderService $service, PurchaseOrderNotifier $notifier): int
    {
        $this->info('Checking reorder points... '.now()->format('Y-m-d H:i:s'));

        try {
            $products = $service->getProductsNeedingReorder();

            if ($products->isEmpty()) {
                $this->info('No products need reordering. All levels are above their reorder points.');
                ActivityLogger::log('checked', 'Procurement', 'Scheduled reorder check found no products needing replenishment.');

                return self::SUCCESS;
            }

            $this->table(
                ['Product', 'SKU', 'Supplier', 'On hand', 'Reorder point', 'Suggested qty'],
                $products->map(fn (Product $product) => [
                    $product->name,
                    $product->sku,
                    $product->supplierRecord?->company_name ?? '—',
                    $product->quantity,
                    $product->reorder_point,
                    $product->suggested_quantity,
                ])->all()
            );

            if ($this->option('dry-run')) {
                $this->warn('--dry-run: no purchase orders were created.');

                return self::SUCCESS;
            }

            $result = $service->generatePurchaseOrders();

            $this->info("Products scanned: {$result['products_scanned']}");
            $this->info("Products needing reorder: {$result['products_needing_reorder']}");
            $this->info("Purchase orders created: {$result['orders_created']}");
            $this->warn("Skipped (already on an open order): {$result['orders_skipped']}");

            foreach ($result['orders'] as $order) {
                $this->line('  → '.$order->reference_number.' · '.$order->supplier?->company_name);
            }

            $notifier->notifyLowStock($products, $result['orders_created']);

            ActivityLogger::log(
                'created',
                'Procurement',
                "Scheduled reorder check created {$result['orders_created']} purchase order(s) from {$result['products_needing_reorder']} low-stock product(s)."
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            Log::error('Scheduled reorder check failed: '.$exception->getMessage(), [
                'exception' => $exception,
            ]);

            $this->error('Reorder check failed: '.$exception->getMessage());
            ActivityLogger::log('failed', 'Procurement', 'Scheduled reorder check failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
