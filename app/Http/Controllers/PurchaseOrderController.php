<?php

namespace App\Http\Controllers;

use App\Exports\PurchaseOrdersExport;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Notifications\PurchaseOrderNotification;
use App\Services\ActivityLogger;
use App\Services\PurchaseOrderNotifier;
use App\Services\SearchService;
use App\Services\SupplierPerformanceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    use AuthorizesRequests;

    /** Paginated, filterable purchase order list with eager-loaded relations. */
    public function index(Request $request)
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(PurchaseOrder::statusOptions()))],
            'supplier_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $orders = app(SearchService::class)->purchaseOrders($filters)->paginate(12)->withQueryString();

        return view('purchase-orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'suppliers' => Supplier::active()->orderBy('company_name')->get(),
            'statuses' => PurchaseOrder::statusOptions(),
        ]);
    }

    /** Create form with dynamic line items (JavaScript in the Blade view). */
    public function create()
    {
        $this->authorize('create', PurchaseOrder::class);

        return view('purchase-orders.create', [
            'suppliers' => Supplier::active()->with('products')->orderBy('company_name')->get(),
            'products' => \App\Models\Product::active()->with(['supplier', 'category'])->orderBy('name')->get(),
            'order' => new PurchaseOrder([
                'order_date' => today()->toDateString(),
                'status' => PurchaseOrder::STATUS_PENDING,
            ]),
        ]);
    }

    public function store(Request $request, PurchaseOrderNotifier $notifier)
    {
        $this->authorize('create', PurchaseOrder::class);

        $data = $this->validateOrder($request);

        $productIds = collect($data['items'])->pluck('product_id');
        if ($productIds->duplicates()->isNotEmpty()) {
            return back()->withInput()->with('error', 'A product can only appear once in a purchase order.');
        }

        $products = \App\Models\Product::whereIn('id', $productIds)->get()->keyBy('id');
        foreach ($data['items'] as $item) {
            if ((int) ($products[$item['product_id']]->supplier_id ?? 0) !== (int) $data['supplier_id']) {
                return back()->withInput()->with('error', 'One of the selected products is not supplied by the chosen supplier.');
            }
        }

        $status = $request->input('submit_action') === 'draft'
            ? PurchaseOrder::STATUS_DRAFT
            : PurchaseOrder::STATUS_PENDING;

        $order = DB::transaction(function () use ($data, $status) {
            $order = PurchaseOrder::create([
                'reference_number' => PurchaseOrder::generateReferenceNumber(),
                'supplier_id' => $data['supplier_id'],
                'status' => $status,
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? Supplier::find($data['supplier_id'])?->payment_terms,
                'user_id' => auth()->id(),
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $order->calculateTotal();
            $order->addApproval(
                'submitted',
                $status === PurchaseOrder::STATUS_DRAFT ? 'Saved as draft.' : 'Submitted for approval.'
            );
            ActivityLogger::log(
                'created',
                'Procurement',
                "Created {$order->reference_number} (".count($data['items'])." line item(s)).",
                null,
                $order
            );

            return $order;
        });

        if ($status === PurchaseOrder::STATUS_PENDING) {
            $notifier->notifyCreated($order);
        }

        return redirect()->route('purchase-orders.show', $order)
            ->with('success', $status === PurchaseOrder::STATUS_DRAFT
                ? 'Draft purchase order saved.'
                : 'Purchase order submitted for approval. Approvers have been notified.');
    }

    public function show(PurchaseOrder $order)
    {
        $this->authorize('view', $order);

        $order->load([
            'supplier.categories',
            'items.product',
            'createdBy',
            'approvedBy',
            'approvals.user',
        ]);

        return view('purchase-orders.show', [
            'order' => $order,
            'canApprove' => auth()->user()->can('approve', $order),
            'canReject' => auth()->user()->can('reject', $order),
            'canSend' => auth()->user()->can('send', $order),
            'canReceive' => auth()->user()->can('receive', $order),
            'canEdit' => auth()->user()->can('update', $order),
            'canDelete' => auth()->user()->can('delete', $order),
            'canCancel' => auth()->user()->isAdmin() && $order->canBeCancelled(),
        ]);
    }

    public function edit(PurchaseOrder $order)
    {
        $this->authorize('update', $order);

        return view('purchase-orders.edit', [
            'order' => $order->load('items.product'),
            'suppliers' => Supplier::active()->with('products')->orderBy('company_name')->get(),
            'products' => \App\Models\Product::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, PurchaseOrder $order)
    {
        $this->authorize('update', $order);
        $data = $this->validateOrder($request);

        $products = \App\Models\Product::whereIn('id', collect($data['items'])->pluck('product_id'))
            ->get()->keyBy('id');

        foreach ($data['items'] as $item) {
            if ((int) ($products[$item['product_id']]->supplier_id ?? 0) !== (int) $data['supplier_id']) {
                return back()->withInput()->with('error', 'One of the selected products is not supplied by the chosen supplier.');
            }
        }

        DB::transaction(function () use ($order, $data) {
            $order->update([
                'supplier_id' => $data['supplier_id'],
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $order->items()->delete();
            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $order->calculateTotal();
            ActivityLogger::log('updated', 'Procurement', "Updated draft {$order->reference_number}.", null, $order);
        });

        return redirect()->route('purchase-orders.show', $order)
            ->with('success', 'Draft purchase order updated.');
    }

    /** Soft delete a draft, or cancel a non-draft order (admin only). */
    public function destroy(PurchaseOrder $order)
    {
        $this->authorize('delete', $order);

        if ($order->canBeEdited()) {
            ActivityLogger::log('deactivated', 'Procurement', "Cancelled draft {$order->reference_number}.", null, $order);
            $order->delete();

            return redirect()->route('purchase-orders.index')
                ->with('success', 'Draft purchase order deleted.');
        }

        if (! auth()->user()->isAdmin() || ! $order->canBeCancelled()) {
            return back()->with('error', 'Only administrators can cancel this purchase order.');
        }

        $order->update(['status' => PurchaseOrder::STATUS_CANCELLED]);
        $order->addApproval('cancelled', 'Cancelled by administrator.');
        ActivityLogger::log('deactivated', 'Procurement', "Cancelled {$order->reference_number}.", null, $order);

        return redirect()->route('purchase-orders.show', $order)
            ->with('success', 'Purchase order cancelled.');
    }

    public function approve(PurchaseOrder $order, PurchaseOrderNotifier $notifier)
    {
        $this->authorize('approve', $order);

        if (! $order->canBeApproved()) {
            return back()->with('error', 'This purchase order is no longer awaiting approval.');
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'status' => PurchaseOrder::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            $order->addApproval('approved', 'Approved by '.auth()->user()->name);
            ActivityLogger::log('approved', 'Procurement', "Approved {$order->reference_number} (₱".number_format((float) $order->total, 2).').', null, $order);
        });

        $notifier->notifyApproved($order->fresh(['createdBy', 'supplier']));

        return back()->with('success', "{$order->reference_number} approved. The creator has been notified.");
    }

    public function reject(Request $request, PurchaseOrder $order)
    {
        $this->authorize('reject', $order);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($order, $data) {
            $order->update([
                'status' => PurchaseOrder::STATUS_REJECTED,
                'rejection_reason' => $data['rejection_reason'],
            ]);
            $order->addApproval('rejected', $data['rejection_reason']);
            ActivityLogger::log('rejected', 'Procurement', "Rejected {$order->reference_number}: {$data['rejection_reason']}", null, $order);
        });

        return back()->with('success', 'Purchase order rejected and the reason was recorded.');
    }

    public function send(PurchaseOrder $order, PurchaseOrderNotifier $notifier)
    {
        $this->authorize('send', $order);

        $order->update([
            'status' => PurchaseOrder::STATUS_SENT,
            'sent_at' => now(),
        ]);
        $order->addApproval('sent', 'Sent to '.$order->supplier?->company_name);
        ActivityLogger::log('updated', 'Procurement', "Sent {$order->reference_number} to supplier.", null, $order);

        $notifier->notifySent($order->fresh(['supplier', 'createdBy']));

        return back()->with('success', 'Purchase order sent to the supplier.');
    }

    /**
     * Record partial/full receipt. Each received unit creates an inventory
     * transaction; the observer updates stock and low-stock flags atomically.
     */
    public function receive(Request $request, PurchaseOrder $order, SupplierPerformanceService $performance)
    {
        $this->authorize('receive', $order);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', Rule::exists('purchase_order_items', 'id')],
            'items.*.received_quantity' => ['required', 'integer', 'min:0'],
            'actual_delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $orderItems = $order->items()->get()->keyBy('id');
        $hasNewReceipt = false;

        foreach ($data['items'] as $entry) {
            $item = $orderItems->get((int) $entry['item_id']);

            if (! $item) {
                return back()->with('error', 'One of the submitted line items does not belong to this order.');
            }

            $cumulative = (int) $entry['received_quantity'];
            $alreadyReceived = (int) $item->received_quantity;

            if ($cumulative < $alreadyReceived) {
                return back()->with('error', "Received quantity cannot be reduced for {$item->product?->name}.");
            }

            if ($cumulative > (int) $item->quantity) {
                return back()->with('error', "Received quantity cannot exceed the ordered quantity for {$item->product?->name}.");
            }

            if ($cumulative > $alreadyReceived) {
                $hasNewReceipt = true;
            }
        }

        if (! $hasNewReceipt) {
            return back()->with('error', 'Enter a received quantity greater than the amount already recorded.');
        }

        $updates = collect($data['items'])->mapWithKeys(fn ($entry) => [
            (int) $entry['item_id'] => (int) $entry['received_quantity'],
        ]);

        DB::transaction(function () use ($order, $updates, $data, $orderItems) {
            foreach ($updates as $itemId => $cumulativeReceived) {
                $item = $orderItems->get($itemId);
                $delta = $cumulativeReceived - (int) $item->received_quantity;

                if ($delta <= 0) {
                    continue;
                }

                $item->update(['received_quantity' => $cumulativeReceived]);

                InventoryTransaction::create([
                    'product_id' => $item->product_id,
                    'type' => InventoryTransaction::TYPE_IN,
                    'direction' => InventoryTransaction::DIRECTION_IN,
                    'quantity' => $delta,
                    'reference_number' => $order->reference_number,
                    'notes' => trim('Received from '.$order->reference_number.'. '.($data['notes'] ?? '')),
                    'user_id' => auth()->id(),
                ]);
            }

            $allReceived = $order->items()->whereColumn('received_quantity', '>=', 'quantity')->count()
                === $order->items()->count();

            $orderUpdates = [];

            if ($allReceived) {
                $orderUpdates['status'] = PurchaseOrder::STATUS_RECEIVED;
                $orderUpdates['received_at'] = now();
            }

            if (! empty($data['actual_delivery_date'])) {
                // Partial deliveries also count toward delivery-time metrics.
                $orderUpdates['actual_delivery_date'] = $data['actual_delivery_date'];
            } elseif ($allReceived) {
                $orderUpdates['actual_delivery_date'] = today()->toDateString();
            }

            if ($orderUpdates) {
                $order->update($orderUpdates);
            }

            $order->addApproval(
                'received',
                $allReceived ? 'All goods received.' : 'Partial receipt recorded.'
            );
            ActivityLogger::log(
                'updated',
                'Procurement',
                "Recorded goods receipt for {$order->reference_number}".($allReceived ? ' (complete).' : ' (partial).'),
                null,
                $order
            );
        });

        $performance->clearCache((int) $order->supplier_id);

        Notification::send(
            \App\Models\User::active()->whereIn('role', ['admin', 'manager'])->get(),
            new PurchaseOrderNotification(
                $order->fresh(),
                'received',
                'Goods Received',
                "Stock from {$order->reference_number} was updated."
            )
        );

        return back()->with('success', 'Goods received. Inventory levels and supplier metrics were updated.');
    }

    public function exportCsv(Request $request, PurchaseOrdersExport $exports)
    {
        return $exports->withFilters($request->only([
            'search', 'status', 'supplier_id', 'user_id', 'from', 'to',
        ]))->csv();
    }

    public function exportPdf(Request $request, PurchaseOrdersExport $exports)
    {
        return $exports->withFilters($request->only([
            'search', 'status', 'supplier_id', 'user_id', 'from', 'to',
        ]))->pdf();
    }

    /** @return array<string, mixed> */
    private function validateOrder(Request $request): array
    {
        return $request->validate([
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where('is_active', true)),
            ],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ], [
            'items.min' => 'Add at least one product to the purchase order.',
            'supplier_id.exists' => 'Choose an active supplier.',
        ]);
    }
}
