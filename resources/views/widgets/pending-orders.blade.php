<div class="card shadow-sm h-100" id="widget-pending-orders">
    <div class="card-header bg-white pt-3 border-bottom-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="bi bi-hourglass-split text-warning me-2"></i> Pending Approvals
            <span class="badge bg-warning text-dark ms-1" id="pending-count">{{ $pendingCount }}</span>
        </h6>
        <a href="{{ route('purchase-orders.index', ['status' => 'pending']) }}" class="small text-decoration-none fw-semibold">View all</a>
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush small" id="pending-list">
            @forelse ($pendingOrders as $order)
                <li class="list-group-item d-flex justify-content-between align-items-center px-3">
                    <div>
                        <a href="{{ route('purchase-orders.show', $order) }}" class="text-decoration-none fw-bold text-dark">{{ $order->reference_number }}</a>
                        <div class="text-muted">{{ $order->supplier?->company_name ?? '—' }}</div>
                    </div>
                    <span class="fw-bold text-danger">₱{{ number_format((float) $order->total, 0) }}</span>
                </li>
            @empty
                <li class="list-group-item text-muted px-3">No orders awaiting approval.</li>
            @endforelse
        </ul>
    </div>
</div>
