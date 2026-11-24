<div class="card shadow-sm h-100" id="widget-supplier-performance">
    <div class="card-header bg-white pt-3 border-bottom-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i> Supplier Performance</h6>
        <a href="{{ route('suppliers.performance') }}" class="small text-decoration-none fw-semibold">Report</a>
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush small" id="supplier-performance-list">
            @forelse ($topSuppliers as $index => $supplier)
                <li class="list-group-item d-flex justify-content-between align-items-center px-3">
                    <span><span class="badge bg-warning text-dark me-2">#{{ $index + 1 }}</span>{{ $supplier->company_name }}</span>
                    <span class="fw-bold {{ $supplier->on_time_rate >= 90 ? 'text-success' : 'text-warning' }}">{{ $supplier->on_time_rate }}%</span>
                </li>
            @empty
                <li class="list-group-item text-muted px-3">No supplier delivery data yet.</li>
            @endforelse
        </ul>
    </div>
</div>
