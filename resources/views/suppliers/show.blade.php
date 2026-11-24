@extends('layouts.app')

@section('title', $supplier->company_name)

@section('heading')
    {{ $supplier->company_name }}
@endsection

@section('actions')
    @if (Auth::user()->isAdmin())
        <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-warning btn-sm px-3">
            <i class="bi bi-pencil-square me-1"></i> Edit
        </a>
    @endif
    <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary btn-sm px-3">Back</a>
@endsection

@section('content')
    <div class="row mb-4">
        <div class="col-lg-4 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white pt-3 border-bottom-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-building text-warning me-2"></i> Supplier Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0 small">
                        <tr><th style="width:40%">Contact</th><td>{{ $supplier->contact_person ?? '—' }}</td></tr>
                        <tr><th>Email</th><td>{{ $supplier->email }}</td></tr>
                        <tr><th>Phone</th><td>{{ $supplier->phone ?? '—' }}</td></tr>
                        <tr><th>Address</th><td>{{ $supplier->address ?? '—' }}</td></tr>
                        <tr><th>Payment terms</th><td><span class="badge badge-category">{{ $supplier->payment_terms }}</span></td></tr>
                        <tr><th>Lead time</th><td>{{ $supplier->lead_time_days }} days</td></tr>
                        <tr><th>Minimum order</th><td>₱{{ number_format((float) $supplier->minimum_order_amount, 2) }}</td></tr>
                        <tr><th>Status</th><td><span class="badge badge-{{ $supplier->is_active ? 'in' : 'out' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                    </table>
                    <div class="mt-3">
                        <small class="text-muted fw-bold d-block mb-1">Supply categories</small>
                        @forelse ($supplier->categories as $category)
                            <span class="badge badge-category me-1">{{ $category->name }}</span>
                        @empty
                            <small class="text-muted">No categories assigned.</small>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white pt-3 border-bottom-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-speedometer2 text-success me-2"></i> Performance Metrics</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 text-center">
                        <div class="col-6 col-md-4">
                            <div class="border rounded p-3 h-100">
                                <h3 class="fw-bold text-primary mb-0">{{ $metrics['total_orders'] }}</h3>
                                <small class="text-muted">Total orders</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="border rounded p-3 h-100">
                                <h3 class="fw-bold text-success mb-0">{{ $metrics['on_time_rate'] }}%</h3>
                                <small class="text-muted">On-time delivery</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="border rounded p-3 h-100">
                                <h3 class="fw-bold text-warning mb-0">{{ $metrics['avg_delivery_days'] }}</h3>
                                <small class="text-muted">Avg actual days</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="border rounded p-3 h-100">
                                <h3 class="fw-bold text-secondary mb-0">{{ $metrics['avg_quoted_days'] }}</h3>
                                <small class="text-muted">Avg quoted days</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="border rounded p-3 h-100">
                                <h3 class="fw-bold {{ $metrics['avg_variance_days'] > 0 ? 'text-danger' : 'text-success' }} mb-0">
                                    {{ $metrics['avg_variance_days'] > 0 ? '+' : '' }}{{ $metrics['avg_variance_days'] }}
                                </h3>
                                <small class="text-muted">Actual vs quoted (days)</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="border rounded p-3 h-100">
                                <h3 class="fw-bold text-info mb-0">{{ $metrics['accuracy_rate'] }}%</h3>
                                <small class="text-muted">Order accuracy</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="border rounded p-3 h-100">
                                <h3 class="fw-bold text-primary mb-0">₱{{ number_format((float) $metrics['total_value'], 0) }}</h3>
                                <small class="text-muted">Total order value</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="border rounded p-3 h-100">
                                <h3 class="fw-bold text-secondary mb-0">{{ $metrics['delivered_orders'] }}</h3>
                                <small class="text-muted">Delivered orders</small>
                            </div>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-3">Metrics are aggregated in SQL and cached for 10 minutes (Query Caching).</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white pt-3 border-bottom-0 d-flex justify-content-between">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-cart-plus text-primary me-2"></i> Recent Purchase Orders</h5>
                    <a href="{{ route('purchase-orders.index', ['supplier_id' => $supplier->id]) }}" class="small text-decoration-none fw-semibold">View all</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light"><tr><th class="ps-3">Reference</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse ($recentOrders as $order)
                                    <tr>
                                        <td class="ps-3"><a href="{{ route('purchase-orders.show', $order) }}" class="text-decoration-none fw-bold">{{ $order->reference_number }}</a></td>
                                        <td>{{ $order->order_date?->format('M d, Y') }}</td>
                                        <td>₱{{ number_format((float) $order->total, 2) }}</td>
                                        <td><span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-3">No purchase orders yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white pt-3 border-bottom-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam text-warning me-2"></i> Products Supplied ({{ $supplier->products->count() }})</h5>
                </div>
                <div class="card-body" style="max-height: 360px; overflow-y: auto;">
                    @forelse ($supplier->products as $product)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2 small">
                            <div>
                                <a href="{{ route('products.show', $product) }}" class="text-decoration-none fw-bold text-dark">{{ $product->name }}</a>
                                <div class="text-muted"><code>{{ $product->sku }}</code> · {{ $product->quantity }} pcs</div>
                            </div>
                            <span class="badge badge-{{ $product->quantity == 0 ? 'out' : ($product->isLowStock() ? 'low' : 'in') }}">{{ $product->stock_status }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No products linked to this supplier.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
