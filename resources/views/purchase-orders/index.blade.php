@extends('layouts.app')

@section('title', 'Purchase Orders')

@section('heading')
    Purchase <span class="text-warning fw-bold">Orders</span>
@endsection

@section('actions')
    <a href="{{ route('purchase-orders.create') }}" class="btn btn-success btn-sm px-3">
        <i class="bi bi-plus-circle me-1"></i> New Order
    </a>
@endsection

@section('content')
    <x-filter-card action="{{ route('purchase-orders.index') }}">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Reference, supplier or notes..." value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Supplier</label>
                    <select name="supplier_id" class="form-select">
                        <option value="">All suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ (string) ($filters['supplier_id'] ?? '') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-bold text-muted mb-1">From</label>
                    <input type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-bold text-muted mb-1">To</label>
                    <input type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i> Search</button>
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <a href="{{ route('purchase-orders.export', request()->only(['search', 'status', 'supplier_id', 'from', 'to'])) }}" class="btn btn-outline-primary btn-sm px-3">
                        <i class="bi bi-file-earmark-excel me-1"></i> Export CSV
                    </a>
                    <a href="{{ route('purchase-orders.export-pdf', request()->only(['search', 'status', 'supplier_id', 'from', 'to'])) }}" class="btn btn-outline-danger btn-sm px-3">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
                    </a>
                </div>
    </x-filter-card>

    <div class="card shadow-sm">
        <div class="card-header bg-white pt-3 pb-2 d-flex justify-content-between align-items-center border-bottom-0">
            <h5 class="mb-0 fw-bold"><i class="bi bi-cart-plus text-warning me-2"></i> Purchase Order Register</h5>
            <small class="text-muted">Showing {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }}</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Reference</th>
                            <th>Supplier</th>
                            <th>Order date</th>
                            <th>Expected</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Created by</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="ps-4">
                                    <a href="{{ route('purchase-orders.show', $order) }}" class="text-decoration-none fw-bold">{{ $order->reference_number }}</a>
                                    @if ($order->auto_generated) <span class="badge bg-dark ms-1" title="Created by the scheduler">AUTO</span> @endif
                                    @if ($order->is_overdue) <span class="badge bg-danger ms-1">Overdue</span> @endif
                                </td>
                                <td>{{ $order->supplier?->company_name ?? '—' }}</td>
                                <td>{{ $order->order_date?->format('M d, Y') }}</td>
                                <td>{{ $order->expected_delivery_date?->format('M d, Y') ?? '—' }}</td>
                                <td>{{ $order->items()->count() }}</td>
                                <td class="fw-bold">₱{{ number_format((float) $order->total, 2) }}</td>
                                <td><span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span></td>
                                <td><small>{{ $order->createdBy?->name ?? '—' }}</small></td>
                                <td class="text-end pe-4 text-nowrap">
                                    <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                    @can('update', $order)
                                        <a href="{{ route('purchase-orders.edit', $order) }}" class="btn btn-sm btn-outline-warning" title="Edit draft"><i class="bi bi-pencil-square"></i></a>
                                    @endcan
                                    @can('approve', $order)
                                        <form method="POST" action="{{ route('purchase-orders.approve', $order) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No purchase orders match your filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-body border-top">
            {{ $orders->links() }}
        </div>
    </div>
@endsection
