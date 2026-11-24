<div class="card shadow-sm h-100" id="widget-recent-orders">
    <div class="card-header bg-white pt-3 border-bottom-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary me-2"></i> Recent Purchase Orders</h6>
        <a href="{{ route('purchase-orders.index') }}" class="small text-decoration-none fw-semibold">View all</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light"><tr><th class="ps-3">Reference</th><th>Supplier</th><th>Status</th><th class="text-end pe-3">Total</th></tr></thead>
                <tbody id="recent-orders-body">
                    @forelse ($recentOrders as $order)
                        <tr>
                            <td class="ps-3"><a href="{{ route('purchase-orders.show', $order) }}" class="text-decoration-none fw-bold">{{ $order->reference_number }}</a></td>
                            <td>{{ $order->supplier?->company_name ?? '—' }}</td>
                            <td><span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span></td>
                            <td class="text-end pe-3">₱{{ number_format((float) $order->total, 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No purchase orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
