<div class="card shadow-sm h-100" id="widget-reorder-alerts">
    <div class="card-header bg-white pt-3 border-bottom-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="bi bi-exclamation-octagon-fill text-danger me-2"></i> Reorder Alerts
            <span class="badge bg-danger ms-1">{{ $reorderCount ?? $reorderProducts->count() }}</span>
        </h6>
        <a href="{{ route('inventory.index') }}" class="small text-decoration-none fw-semibold">Stock</a>
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush small">
            @forelse ($reorderProducts as $product)
                <li class="list-group-item d-flex justify-content-between align-items-center px-3">
                    <div>
                        <strong>{{ $product->name }}</strong>
                        <div class="text-muted">{{ $product->supplierRecord?->company_name ?? 'No supplier' }} · reorder at {{ $product->reorder_point }}</div>
                    </div>
                    <span class="badge bg-danger">{{ $product->quantity }} pcs</span>
                </li>
            @empty
                <li class="list-group-item text-muted px-3">All stocked products are above their reorder points.</li>
            @endforelse
        </ul>
    </div>
</div>
