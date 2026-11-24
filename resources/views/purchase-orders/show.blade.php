@extends('layouts.app')

@section('title', $order->reference_number)

@section('heading')
    Purchase Order <span class="text-warning fw-bold">{{ $order->reference_number }}</span>
@endsection

@section('actions')
    <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary btn-sm px-3">Back to list</a>
@endsection

@section('content')
    @if ($order->requires_second_approval && $order->status === \App\Models\PurchaseOrder::STATUS_PENDING)
        <div class="alert alert-warning py-2 small">
            <i class="bi bi-shield-lock me-1"></i> High-value order (₱50,000+) — a manager can reject it, but only an administrator can approve it.
        </div>
    @endif

    <div class="row mb-4">
        <div class="col-lg-8 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white pt-3 border-bottom-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-file-text text-warning me-2"></i> Order Information</h5>
                    <span class="badge {{ $order->status_badge_class }} fs-6">{{ $order->status_label }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 small">
                        <div class="col-md-4"><strong>Supplier</strong><div>{{ $order->supplier?->company_name ?? '—' }}</div></div>
                        <div class="col-md-4"><strong>Order date</strong><div>{{ $order->order_date?->format('M d, Y') }}</div></div>
                        <div class="col-md-4"><strong>Expected delivery</strong><div>{{ $order->expected_delivery_date?->format('M d, Y') ?? '—' }}</div></div>
                        <div class="col-md-4"><strong>Created by</strong><div>{{ $order->createdBy?->name ?? 'System' }}{{ $order->auto_generated ? ' (scheduler)' : '' }}</div></div>
                        <div class="col-md-4"><strong>Approved by</strong><div>{{ $order->approvedBy?->name ?? '—' }}</div></div>
                        <div class="col-md-4"><strong>Approved at</strong><div>{{ $order->approved_at?->format('M d, Y h:i A') ?? '—' }}</div></div>
                        <div class="col-md-4"><strong>Payment terms</strong><div>{{ $order->terms ?? $order->supplier?->payment_terms ?? '—' }}</div></div>
                        <div class="col-md-4"><strong>Sent at</strong><div>{{ $order->sent_at?->format('M d, Y h:i A') ?? '—' }}</div></div>
                        <div class="col-md-4"><strong>Received at</strong><div>{{ $order->received_at?->format('M d, Y h:i A') ?? '—' }}</div></div>
                    </div>
                    @if ($order->notes)
                        <div class="mt-3 small"><strong>Notes:</strong> {{ $order->notes }}</div>
                    @endif
                    @if ($order->rejection_reason)
                        <div class="alert alert-danger py-2 small mt-3 mb-0"><strong>Rejection reason:</strong> {{ $order->rejection_reason }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white pt-3 border-bottom-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-calculator text-success me-2"></i> Order Totals</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0 small">
                        <tr><th>Subtotal</th><td class="text-end">₱{{ number_format((float) $order->subtotal, 2) }}</td></tr>
                        <tr><th>Tax (12%)</th><td class="text-end">₱{{ number_format((float) $order->tax, 2) }}</td></tr>
                        <tr class="table-warning"><th class="fs-6">Total</th><td class="text-end fs-6 fw-bold">₱{{ number_format((float) $order->total, 2) }}</td></tr>
                    </table>
                    <hr>
                    <div class="d-grid gap-2">
                        @if ($canEdit)
                            <a href="{{ route('purchase-orders.edit', $order) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil-square me-1"></i> Edit Draft</a>
                        @endif
                        @if ($canApprove)
                            <form method="POST" action="{{ route('purchase-orders.approve', $order) }}">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-check2-circle me-1"></i> Approve Order</button>
                            </form>
                        @endif
                        @if ($canSend)
                            <form method="POST" action="{{ route('purchase-orders.send', $order) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-send me-1"></i> Send to Supplier</button>
                            </form>
                        @endif
                        @if ($canDelete)
                            <form method="POST" action="{{ route('purchase-orders.destroy', $order) }}"
                                  onsubmit="return confirm('Delete this draft purchase order?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-trash me-1"></i> Delete Draft</button>
                            </form>
                        @elseif ($canCancel)
                            <form method="POST" action="{{ route('purchase-orders.destroy', $order) }}"
                                  onsubmit="return confirm('Cancel this purchase order?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-x-circle me-1"></i> Cancel Order</button>
                            </form>
                        @endif
                    </div>

                    @if ($canReject)
                        <hr>
                        <form method="POST" action="{{ route('purchase-orders.reject', $order) }}">
                            @csrf
                            <label class="form-label small fw-bold">Reject with reason</label>
                            <textarea name="rejection_reason" class="form-control form-control-sm" rows="2" maxlength="500" required placeholder="Explain why this order was rejected..."></textarea>
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100 mt-2"><i class="bi bi-x-lg me-1"></i> Reject Order</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white pt-3 border-bottom-0">
            <h5 class="mb-0 fw-bold"><i class="bi bi-list-check text-primary me-2"></i> Line Items</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead class="table-light">
                        <tr><th class="ps-4">Product</th><th>SKU</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Outstanding</th><th class="text-end">Unit price</th><th class="text-end pe-4">Line total</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($order->items as $item)
                            <tr>
                                <td class="ps-4">
                                    <a href="{{ route('products.show', $item->product) }}" class="text-decoration-none fw-bold text-dark">{{ $item->product?->name ?? 'Deleted product' }}</a>
                                    @if ($item->notes) <div class="text-muted">{{ $item->notes }}</div> @endif
                                </td>
                                <td><code>{{ $item->product?->sku ?? '—' }}</code></td>
                                <td class="text-end">{{ $item->quantity }}</td>
                                <td class="text-end">{{ $item->received_quantity }}</td>
                                <td class="text-end"><span class="badge {{ $item->status_badge_class }}">{{ $item->outstanding_quantity }}</span></td>
                                <td class="text-end">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="text-end pe-4 fw-bold">₱{{ number_format($item->line_total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">No line items.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($canReceive)
        <div class="card shadow-sm mb-4 border-warning">
            <div class="card-header bg-warning bg-opacity-10 pt-3 border-bottom-0">
                <h5 class="mb-0 fw-bold"><i class="bi bi-box-arrow-in-down text-success me-2"></i> Record Goods Receipt</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('purchase-orders.receive', $order) }}">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-3">
                            <thead class="table-light"><tr><th>Product</th><th>Ordered</th><th>Already received</th><th style="width:180px;">Total received now</th></tr></thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td>{{ $item->product?->name ?? 'Product #'.$item->product_id }}</td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>{{ $item->received_quantity }}</td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" min="{{ $item->received_quantity }}" max="{{ $item->quantity }}"
                                                   name="items[{{ $loop->index }}][item_id]" value="{{ $item->id }}" hidden>
                                            <input type="number" class="form-control form-control-sm" min="{{ $item->received_quantity }}" max="{{ $item->quantity }}"
                                                   name="items[{{ $loop->index }}][received_quantity]" value="{{ $item->outstanding_quantity + $item->received_quantity }}" required>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Actual delivery date</label>
                            <input type="date" name="actual_delivery_date" class="form-control form-control-sm" value="{{ today()->toDateString() }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Notes</label>
                            <input type="text" name="notes" class="form-control form-control-sm" maxlength="500" placeholder="Condition, batch, etc.">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-check2 me-1"></i> Receive</button>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">Received quantities create Stock In transactions and update inventory automatically.</small>
                </form>
            </div>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white pt-3 border-bottom-0">
            <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history text-primary me-2"></i> Approval History</h5>
        </div>
        <div class="card-body">
            <ul class="list-unstyled mb-0">
                @forelse ($order->approvals as $approval)
                    <li class="d-flex gap-3 border-bottom py-2">
                        <span class="badge {{ $approval->badge_class }}">{{ $approval->action_label }}</span>
                        <div class="small">
                            <strong>{{ $approval->user?->name ?? 'System' }}</strong>
                            @if ($approval->note)<div class="text-muted">{{ $approval->note }}</div>@endif
                        </div>
                        <small class="text-muted ms-auto">{{ $approval->created_at->format('M d, Y h:i A') }}</small>
                    </li>
                @empty
                    <li class="text-muted small">No workflow actions recorded yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
