<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReorderService;
use App\Services\SupplierPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class Lab7ProcurementTest extends TestCase
{
    use RefreshDatabase;

    private static int $sequence = 0;

    private function user(string $role): User
    {
        self::$sequence++;

        return User::create([
            'name' => ucfirst($role).' User '.self::$sequence,
            'email' => 'lab7-'.$role.'-'.self::$sequence.'@example.test',
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function supplier(array $attributes = []): Supplier
    {
        self::$sequence++;

        return Supplier::create(array_merge([
            'company_name' => 'Lab 7 Supplier '.self::$sequence,
            'contact_person' => 'Contact '.self::$sequence,
            'email' => 'lab7-supplier-'.self::$sequence.'@example.test',
            'phone' => '02-1234-5678',
            'address' => 'Test address',
            'payment_terms' => 'Net 30',
            'lead_time_days' => 5,
            'minimum_order_amount' => 0,
            'is_active' => true,
        ], $attributes));
    }

    private function product(Supplier $supplier, array $attributes = []): Product
    {
        self::$sequence++;

        $brand = Brand::create([
            'name' => 'Lab 7 Brand '.self::$sequence,
            'slug' => 'lab7-brand-'.self::$sequence,
        ]);
        $category = Category::create([
            'name' => 'Lab 7 Category '.self::$sequence,
            'slug' => 'lab7-category-'.self::$sequence,
        ]);

        return Product::create(array_merge([
            'name' => 'Lab 7 Product '.self::$sequence,
            'sku' => 'LAB7-'.self::$sequence,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'quantity' => 10,
            'reorder_level' => 5,
            'unit_price' => 1000,
            'supplier' => $supplier->company_name,
            'supplier_id' => $supplier->id,
            'is_active' => true,
        ], $attributes));
    }

    private function order(Supplier $supplier, Product $product, array $attributes = [], array $itemAttributes = []): PurchaseOrder
    {
        $order = PurchaseOrder::create(array_merge([
            'reference_number' => PurchaseOrder::generateReferenceNumber(),
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_PENDING,
            'order_date' => today(),
            'expected_delivery_date' => today()->addDays($supplier->lead_time_days),
            'user_id' => $this->user('staff')->id,
            'terms' => $supplier->payment_terms,
        ], $attributes));

        $order->items()->create(array_merge([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $product->unit_price,
            'received_quantity' => 0,
        ], $itemAttributes));
        $order->calculateTotal();

        return $order->fresh();
    }

    public function test_supplier_soft_delete_preserves_history_and_can_be_restored(): void
    {
        $admin = $this->user('admin');
        $supplier = $this->supplier();
        $product = $this->product($supplier);
        $order = $this->order($supplier, $product, [
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'actual_delivery_date' => today(),
            'received_at' => now(),
        ], ['received_quantity' => 1]);

        $this->actingAs($admin)
            ->delete(route('suppliers.destroy', $supplier))
            ->assertRedirect(route('suppliers.index'));

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
        $this->assertNull(Supplier::find($supplier->id));
        $this->assertEquals($supplier->id, $order->fresh()->supplier->id);

        $this->actingAs($admin)
            ->post(route('suppliers.restore', $supplier))
            ->assertRedirect(route('suppliers.show', $supplier));

        $this->assertNotSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_supplier_with_open_order_cannot_be_soft_deleted(): void
    {
        $admin = $this->user('admin');
        $supplier = $this->supplier();
        $product = $this->product($supplier);
        $this->order($supplier, $product);

        $this->actingAs($admin)
            ->delete(route('suppliers.destroy', $supplier))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_reorder_calculation_uses_supplier_lead_time_and_product_overrides(): void
    {
        $supplier = $this->supplier(['lead_time_days' => 5]);
        $product = $this->product($supplier, [
            'quantity' => 2,
            'daily_usage_override' => 4,
            'safety_stock_override' => 10,
            'package_size' => 1,
        ]);

        $service = app(ReorderService::class);

        $this->assertSame(30, $service->calculateReorderPoint($product));
        $this->assertSame(56, $service->calculateOptimalOrderQuantity($product));

        $candidates = $service->getProductsNeedingReorder();

        $this->assertCount(1, $candidates);
        $this->assertStringStartsWith('Lab 7 Supplier', $candidates->first()->supplierRecord->company_name);
        $this->assertSame($supplier->id, $candidates->first()->supplier_id);
    }

    public function test_scheduled_command_previews_then_generates_purchase_orders(): void
    {
        $this->user('admin');
        $supplier = $this->supplier();
        $this->product($supplier, [
            'quantity' => 1,
            'daily_usage_override' => 4,
            'safety_stock_override' => 10,
        ]);

        $this->artisan('inventory:check-reorder-points', ['--dry-run' => true])
            ->expectsOutputToContain('LAB7-')
            ->assertSuccessful();

        $this->assertDatabaseCount('purchase_orders', 0);

        $this->artisan('inventory:check-reorder-points')
            ->expectsOutputToContain('Purchase orders created: 1')
            ->assertSuccessful();

        $this->assertDatabaseCount('purchase_orders', 1);

        $order = PurchaseOrder::first();
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $order->status);
        $this->assertTrue($order->auto_generated);
        $this->assertDatabaseHas('purchase_order_approvals', [
            'subject_type' => $order->getMorphClass(),
            'subject_id' => $order->id,
            'action' => 'submitted',
        ]);
    }

    public function test_manager_approves_standard_order_but_high_value_order_needs_admin(): void
    {
        $manager = $this->user('manager');
        $admin = $this->user('admin');
        $supplier = $this->supplier();

        $standardProduct = $this->product($supplier, ['unit_price' => 1000]);
        $standardOrder = $this->order($supplier, $standardProduct);

        $this->assertTrue($manager->canApprovePurchaseOrders());
        $this->assertTrue($standardOrder->canBeApproved());
        $this->assertNotNull(Gate::getPolicyFor(PurchaseOrder::class));
        $this->assertTrue(Gate::forUser($manager)->allows('approve', $standardOrder));

        $this->actingAs($manager)
            ->post(route('purchase-orders.approve', $standardOrder))
            ->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $standardOrder->fresh()->status);
        $this->assertSame($manager->id, (int) $standardOrder->fresh()->approved_by);
        $this->assertDatabaseHas('purchase_order_approvals', [
            'subject_id' => $standardOrder->id,
            'action' => 'approved',
        ]);

        $highValueProduct = $this->product($supplier, ['unit_price' => 60000]);
        $highValueOrder = $this->order($supplier, $highValueProduct);

        $this->assertTrue($highValueOrder->requires_second_approval);

        $this->actingAs($manager)
            ->post(route('purchase-orders.approve', $highValueOrder))
            ->assertForbidden();

        $this->assertSame(PurchaseOrder::STATUS_PENDING, $highValueOrder->fresh()->status);

        $this->actingAs($admin)
            ->post(route('purchase-orders.approve', $highValueOrder))
            ->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $highValueOrder->fresh()->status);
    }

    public function test_warehouse_receipt_creates_stock_movements_and_completes_order(): void
    {
        $admin = $this->user('admin');
        $warehouse = $this->user('warehouse');
        $staff = $this->user('staff');
        $supplier = $this->supplier();
        $product = $this->product($supplier, ['quantity' => 5]);
        $order = $this->order($supplier, $product, [
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ], ['quantity' => 10]);
        $item = $order->items()->first();

        $this->actingAs($staff)
            ->post(route('purchase-orders.receive', $order), [
                'items' => [['item_id' => $item->id, 'received_quantity' => 4]],
            ])
            ->assertForbidden();

        $this->actingAs($warehouse)
            ->post(route('purchase-orders.receive', $order), [
                'items' => [['item_id' => $item->id, 'received_quantity' => 4]],
            ])
            ->assertRedirect();

        $this->assertSame(9, $product->fresh()->quantity);
        $this->assertSame(4, (int) $item->fresh()->received_quantity);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id,
            'type' => InventoryTransaction::TYPE_IN,
            'quantity' => 4,
            'reference_number' => $order->reference_number,
        ]);

        $this->actingAs($warehouse)
            ->post(route('purchase-orders.receive', $order), [
                'items' => [['item_id' => $item->id, 'received_quantity' => 10]],
                'actual_delivery_date' => today()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(15, $product->fresh()->quantity);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $order->fresh()->status);
        $this->assertSame(2, InventoryTransaction::where('product_id', $product->id)->count());
    }

    public function test_search_and_exports_only_return_matching_rows(): void
    {
        $admin = $this->user('admin');
        $supplierA = $this->supplier(['company_name' => 'Alpha Industrial']);
        $supplierB = $this->supplier(['company_name' => 'Beta Industrial']);
        $productA = $this->product($supplierA);
        $productB = $this->product($supplierB);

        $matching = $this->order($supplierA, $productA);
        $other = $this->order($supplierB, $productB, ['status' => PurchaseOrder::STATUS_RECEIVED]);

        $this->actingAs($admin)
            ->get(route('suppliers.index', ['search' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Industrial')
            ->assertDontSee('Beta Industrial');

        $csv = $this->actingAs($admin)
            ->get(route('purchase-orders.export', ['status' => PurchaseOrder::STATUS_PENDING]));

        $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $csv->streamedContent();
        $this->assertStringContainsString($matching->reference_number, $content);
        $this->assertStringNotContainsString($other->reference_number, $content);

        $pdf = $this->actingAs($admin)
            ->get(route('purchase-orders.export-pdf', ['search' => $matching->reference_number]));

        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
    }

    public function test_dashboard_widgets_and_api_endpoints_expose_live_procurement_data(): void
    {
        $admin = $this->user('admin');
        $supplier = $this->supplier();
        $product = $this->product($supplier, [
            'quantity' => 1,
            'daily_usage_override' => 4,
            'safety_stock_override' => 10,
        ]);
        $order = $this->order($supplier, $product);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($order->reference_number);

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.pending-orders'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.reference_number', $order->reference_number);

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.recent-orders'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.reorder-alerts'))
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.supplier-performance'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_supplier_performance_metrics_track_on_time_delivery(): void
    {
        $supplier = $this->supplier();
        $product = $this->product($supplier, ['unit_price' => 1000]);

        $onTime = $this->order($supplier, $product, [
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'expected_delivery_date' => today()->addDays(5),
            'actual_delivery_date' => today()->addDays(4),
            'received_at' => now(),
        ], ['quantity' => 2, 'unit_price' => 1000, 'received_quantity' => 2]);

        $late = $this->order($supplier, $product, [
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'expected_delivery_date' => today()->subDay(),
            'actual_delivery_date' => today(),
            'received_at' => now(),
        ], ['quantity' => 2, 'unit_price' => 500, 'received_quantity' => 2]);

        // An open order must not drag the delivered-quantity accuracy down.
        $pending = $this->order($supplier, $product, [
            'status' => PurchaseOrder::STATUS_PENDING,
        ], ['quantity' => 9, 'received_quantity' => 0]);

        $metrics = app(SupplierPerformanceService::class)->metrics($supplier);

        $this->assertSame(3, $metrics['total_orders']);
        $this->assertSame(2, $metrics['delivered_orders']);
        $this->assertSame(1, $metrics['delivered_on_time']);
        $this->assertEquals(50.0, $metrics['on_time_rate']);
        $this->assertEquals(100.0, $metrics['accuracy_rate']);
        $this->assertEquals(2.0, $metrics['avg_delivery_days']);
        $this->assertEquals(3.0, $metrics['avg_quoted_days']);
        $this->assertEquals(-1.0, $metrics['avg_variance_days']);
        $this->assertEquals(
            (float) $onTime->total + (float) $late->total + (float) $pending->total,
            $metrics['total_value']
        );
    }

    public function test_only_admins_can_delete_draft_purchase_orders(): void
    {
        $staff = $this->user('staff');
        $admin = $this->user('admin');
        $supplier = $this->supplier();
        $product = $this->product($supplier);
        $order = $this->order($supplier, $product, [
            'status' => PurchaseOrder::STATUS_DRAFT,
            'user_id' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->delete(route('purchase-orders.destroy', $order))
            ->assertForbidden();

        $this->assertNotSoftDeleted('purchase_orders', ['id' => $order->id]);

        $this->actingAs($admin)
            ->delete(route('purchase-orders.destroy', $order))
            ->assertRedirect(route('purchase-orders.index'));

        $this->assertSoftDeleted('purchase_orders', ['id' => $order->id]);
    }

    public function test_stockout_products_are_still_reorder_candidates(): void
    {
        $this->user('admin');
        $supplier = $this->supplier();
        $product = $this->product($supplier, [
            'quantity' => 1,
            'reorder_level' => 5,
        ]);

        InventoryTransaction::create([
            'product_id' => $product->id,
            'type' => InventoryTransaction::TYPE_OUT,
            'direction' => InventoryTransaction::DIRECTION_OUT,
            'quantity' => 1,
            'reference_number' => 'STOCKOUT-TEST',
            'user_id' => auth()->id(),
        ]);

        $this->assertFalse($product->fresh()->is_active);

        $candidates = app(ReorderService::class)->getProductsNeedingReorder();

        $this->assertCount(1, $candidates);
        $this->assertSame($product->id, $candidates->first()->id);
        $this->assertSame($supplier->id, $candidates->first()->supplier_id);
    }

    public function test_partial_receipt_records_delivery_date_and_rejects_no_op_updates(): void
    {
        $admin = $this->user('admin');
        $warehouse = $this->user('warehouse');
        $supplier = $this->supplier();
        $product = $this->product($supplier, ['quantity' => 0]);
        $order = $this->order($supplier, $product, [
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ], ['quantity' => 10]);
        $item = $order->items()->first();
        $deliveryDate = today()->addDays(2)->toDateString();

        $this->actingAs($warehouse)
            ->post(route('purchase-orders.receive', $order), [
                'items' => [['item_id' => $item->id, 'received_quantity' => 4]],
                'actual_delivery_date' => $deliveryDate,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $order->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'actual_delivery_date' => $deliveryDate.' 00:00:00',
        ]);

        $this->actingAs($warehouse)
            ->post(route('purchase-orders.receive', $order), [
                'items' => [['item_id' => $item->id, 'received_quantity' => 4]],
            ])
            ->assertSessionHas('error');

        $this->actingAs($warehouse)
            ->post(route('purchase-orders.receive', $order), [
                'items' => [['item_id' => $item->id, 'received_quantity' => 2]],
            ])
            ->assertSessionHas('error');

        $this->assertSame(4, (int) $item->fresh()->received_quantity);
        $this->assertSame(4, $product->fresh()->quantity);
        $this->assertSame(1, InventoryTransaction::where('product_id', $product->id)->count());
    }

    public function test_product_quantity_filters_are_applied_to_exports(): void
    {
        $admin = $this->user('admin');
        $supplier = $this->supplier();
        $low = $this->product($supplier, ['quantity' => 2]);
        $high = $this->product($supplier, ['quantity' => 80]);

        $csv = $this->actingAs($admin)
            ->get(route('products.export', ['min_quantity' => 10, 'max_quantity' => 100]));

        $csv->assertOk();
        $content = $csv->streamedContent();

        $this->assertStringContainsString($high->sku, $content);
        $this->assertStringNotContainsString($low->sku, $content);
    }

    public function test_created_mail_attaches_a_printable_purchase_order(): void
    {
        $supplier = $this->supplier();
        $product = $this->product($supplier);
        $order = $this->order($supplier, $product);

        $attachments = (new \App\Mail\PurchaseOrderCreated($order))->attachments();

        $this->assertCount(1, $attachments);
        $this->assertSame(
            "purchase-order-{$order->reference_number}.pdf",
            $attachments[0]->as
        );
    }

    public function test_supplier_metrics_cache_is_invalidated_when_an_order_changes(): void
    {
        $supplier = $this->supplier();
        $product = $this->product($supplier);
        $service = app(SupplierPerformanceService::class);

        $this->assertSame(0, $service->metrics($supplier)['total_orders']);

        $this->order($supplier, $product);

        $this->assertSame(1, $service->metrics($supplier)['total_orders']);
    }
}
