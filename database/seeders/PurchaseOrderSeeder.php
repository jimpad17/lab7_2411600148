<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierPerformanceService;
use Illuminate\Database\Seeder;

/**
 * Lab 7 demo data. These orders feed the approval widgets, the goods-receipt
 * workflow and the supplier performance charts so every report has data on a
 * fresh `php artisan migrate:fresh --seed`.
 */
class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'jimpadillo@gmail.com')->firstOrFail();
        $manager = User::where('email', 'manager@jimtrack.local')->firstOrFail();
        $warehouse = User::where('email', 'warehouse@jimtrack.local')->firstOrFail();

        $orders = [
            [
                'reference_number' => 'PO-DEMO-001',
                'supplier' => 'Ironbulk Supply',
                'status' => PurchaseOrder::STATUS_RECEIVED,
                'order_date' => now()->subDays(20)->toDateString(),
                'expected_delivery_date' => now()->subDays(13)->toDateString(),
                'actual_delivery_date' => now()->subDays(14)->toDateString(),
                'approved_by' => $admin->id,
                'user_id' => $admin->id,
                'terms' => 'Net 30',
                'notes' => 'On-time replenishment of fast-moving training equipment.',
                'items' => [
                    ['sku' => 'JT-EQP-001', 'quantity' => 10, 'received_quantity' => 10, 'unit_price' => 12500],
                    ['sku' => 'JT-EQP-006', 'quantity' => 8, 'received_quantity' => 8, 'unit_price' => 2450],
                ],
                'approvals' => ['submitted', 'approved', 'sent', 'received'],
            ],
            [
                'reference_number' => 'PO-DEMO-002',
                'supplier' => 'Stride Mechanics',
                'status' => PurchaseOrder::STATUS_RECEIVED,
                'order_date' => now()->subDays(30)->toDateString(),
                'expected_delivery_date' => now()->subDays(16)->toDateString(),
                'actual_delivery_date' => now()->subDays(10)->toDateString(),
                'approved_by' => $manager->id,
                'user_id' => $admin->id,
                'terms' => 'Net 45',
                'notes' => 'Delivered late; recorded for on-time performance tracking.',
                'items' => [
                    ['sku' => 'JT-EQP-003', 'quantity' => 2, 'received_quantity' => 2, 'unit_price' => 45000],
                ],
                'approvals' => ['submitted', 'approved', 'sent', 'received'],
            ],
            [
                'reference_number' => 'PO-DEMO-003',
                'supplier' => 'NutriCore Pharma',
                'status' => PurchaseOrder::STATUS_SENT,
                'order_date' => now()->subDays(5)->toDateString(),
                'expected_delivery_date' => now()->addDays(2)->toDateString(),
                'approved_by' => $manager->id,
                'user_id' => $admin->id,
                'sent_at' => now()->subDays(4),
                'terms' => 'COD',
                'notes' => 'Awaiting delivery.',
                'items' => [
                    ['sku' => 'JT-SUP-002', 'quantity' => 20, 'received_quantity' => 0, 'unit_price' => 1195],
                    ['sku' => 'JT-NUT-002', 'quantity' => 12, 'received_quantity' => 0, 'unit_price' => 1050],
                ],
                'approvals' => ['submitted', 'approved', 'sent'],
            ],
            [
                'reference_number' => 'PO-DEMO-004',
                'supplier' => 'FlexFit Distributors',
                'status' => PurchaseOrder::STATUS_PENDING,
                'order_date' => today()->toDateString(),
                'expected_delivery_date' => now()->addDays(5)->toDateString(),
                'user_id' => $warehouse->id,
                'terms' => 'Net 30',
                'notes' => 'Pending manager approval for recovery-stock replenishment.',
                'items' => [
                    ['sku' => 'JT-REC-001', 'quantity' => 12, 'received_quantity' => 0, 'unit_price' => 1150],
                    ['sku' => 'JT-REC-002', 'quantity' => 4, 'received_quantity' => 0, 'unit_price' => 3295],
                ],
                'approvals' => ['submitted'],
            ],
            [
                'reference_number' => 'PO-DEMO-005',
                'supplier' => 'ActiveWear Co.',
                'status' => PurchaseOrder::STATUS_APPROVED,
                'order_date' => now()->subDays(2)->toDateString(),
                'expected_delivery_date' => now()->addDays(8)->toDateString(),
                'approved_by' => $admin->id,
                'user_id' => $manager->id,
                'approved_at' => now()->subDay(),
                'terms' => 'Net 30',
                'notes' => 'Partially received; remaining stock still on order.',
                'items' => [
                    ['sku' => 'JT-APP-001', 'quantity' => 20, 'received_quantity' => 5, 'unit_price' => 750],
                ],
                'approvals' => ['submitted', 'approved'],
            ],
            [
                'reference_number' => 'PO-DEMO-006',
                'supplier' => 'HydroGear',
                'status' => PurchaseOrder::STATUS_REJECTED,
                'order_date' => now()->subDays(8)->toDateString(),
                'expected_delivery_date' => now()->addDays(4)->toDateString(),
                'user_id' => $warehouse->id,
                'rejection_reason' => 'Budget held until the next purchasing cycle.',
                'terms' => 'Net 15',
                'notes' => 'Rejected example for the approval workflow.',
                'items' => [
                    ['sku' => 'JT-ACC-003', 'quantity' => 40, 'received_quantity' => 0, 'unit_price' => 250],
                ],
                'approvals' => ['submitted', 'rejected'],
            ],
        ];

        foreach ($orders as $definition) {
            $supplier = Supplier::where('company_name', $definition['supplier'])->firstOrFail();

            $order = PurchaseOrder::updateOrCreate(
                ['reference_number' => $definition['reference_number']],
                [
                    'supplier_id' => $supplier->id,
                    'status' => $definition['status'],
                    'order_date' => $definition['order_date'],
                    'expected_delivery_date' => $definition['expected_delivery_date'],
                    'actual_delivery_date' => $definition['actual_delivery_date'] ?? null,
                    'approved_by' => $definition['approved_by'] ?? null,
                    'approved_at' => $definition['approved_at'] ?? null,
                    'sent_at' => $definition['sent_at'] ?? null,
                    'received_at' => isset($definition['actual_delivery_date']) ? now()->subDays(14) : null,
                    'rejection_reason' => $definition['rejection_reason'] ?? null,
                    'notes' => $definition['notes'],
                    'terms' => $definition['terms'],
                    'user_id' => $definition['user_id'],
                    'auto_generated' => false,
                ]
            );

            $order->items()->delete();
            foreach ($definition['items'] as $item) {
                $product = Product::where('sku', $item['sku'])->firstOrFail();

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'received_quantity' => $item['received_quantity'],
                    'unit_price' => $item['unit_price'],
                    'notes' => 'Seeded demo line item.',
                ]);
            }
            $order->calculateTotal();

            $order->approvals()->delete();
            foreach ($definition['approvals'] as $action) {
                $order->approvals()->create([
                    'user_id' => $action === 'submitted' ? $definition['user_id'] : ($definition['approved_by'] ?? $definition['user_id']),
                    'action' => $action,
                    'note' => 'Seeded purchase-order history.',
                ]);
            }
        }

        app(SupplierPerformanceService::class)->clearCache(1);
        foreach (Supplier::pluck('id') as $id) {
            app(SupplierPerformanceService::class)->clearCache((int) $id);
        }
    }
}
