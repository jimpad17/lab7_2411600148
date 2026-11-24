@extends('layouts.app')

@section('title', 'Suppliers')

@section('heading')
    Supplier <span class="text-warning fw-bold">Management</span>
@endsection

@section('actions')
    <a href="{{ route('suppliers.performance') }}" class="btn btn-outline-primary btn-sm px-3 me-1">
        <i class="bi bi-graph-up me-1"></i> Performance
    </a>
    @if (Auth::user()->isAdmin())
        <a href="{{ route('suppliers.create') }}" class="btn btn-success btn-sm px-3">
            <i class="bi bi-plus-circle me-1"></i> Add Supplier
        </a>
    @endif
@endsection

@section('content')
    <x-filter-card action="{{ route('suppliers.index') }}">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, contact or email..." value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Status</label>
                    <select name="status" class="form-select">
                        @foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'trashed' => 'Archived'] as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['status'] ?? 'all') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Sort by</label>
                    <select name="sort" class="form-select">
                        @foreach (['company_name' => 'Company', 'lead_time_days' => 'Lead time', 'created_at' => 'Date added'] as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['sort'] ?? 'company_name') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Direction</label>
                    <select name="direction" class="form-select">
                        <option value="asc" {{ ($filters['direction'] ?? 'asc') === 'asc' ? 'selected' : '' }}>Ascending</option>
                        <option value="desc" {{ ($filters['direction'] ?? '') === 'desc' ? 'selected' : '' }}>Descending</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i> Search</button>
                    <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
    </x-filter-card>

    <div class="card shadow-sm">
        <div class="card-header bg-white pt-3 pb-2 d-flex justify-content-between align-items-center border-bottom-0">
            <h5 class="mb-0 fw-bold"><i class="bi bi-truck text-warning me-2"></i> Supplier Directory</h5>
            <small class="text-muted">Showing {{ $suppliers->firstItem() ?? 0 }}–{{ $suppliers->lastItem() ?? 0 }} of {{ $suppliers->total() }}</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Company</th>
                            <th>Contact</th>
                            <th>Terms</th>
                            <th>Lead time</th>
                            <th>Min. order</th>
                            <th>Products / POs</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($suppliers as $supplier)
                            <tr>
                                <td class="ps-4">
                                    <strong>{{ $supplier->company_name }}</strong>
                                    @if ($supplier->trashed())
                                        <span class="badge bg-dark ms-1">Archived</span>
                                    @endif
                                </td>
                                <td><small>{{ $supplier->full_contact }}</small></td>
                                <td><span class="badge badge-category">{{ $supplier->payment_terms }}</span></td>
                                <td>{{ $supplier->lead_time_days }} day(s)</td>
                                <td>₱{{ number_format((float) $supplier->minimum_order_amount, 2) }}</td>
                                <td><small>{{ $supplier->products_count }} / {{ $supplier->purchase_orders_count }}</small></td>
                                <td><span class="badge badge-{{ $supplier->is_active ? 'in' : 'out' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end pe-4 text-nowrap">
                                    @if ($supplier->trashed())
                                        @if (Auth::user()->isAdmin())
                                            <form method="POST" action="{{ route('suppliers.restore', $supplier->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Restore">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                        @if (Auth::user()->isAdmin())
                                            <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-sm btn-outline-warning" title="Edit"><i class="bi bi-pencil-square"></i></a>
                                            <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="d-inline"
                                                  onsubmit="return confirm('Archive \'{{ addslashes($supplier->company_name) }}\'? Historical orders will remain intact.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Archive (soft delete)"><i class="bi bi-archive"></i></button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No suppliers match your filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-body border-top d-flex justify-content-between align-items-center">
            <small class="text-muted">Archived suppliers are soft-deleted — purchase orders still reference them.</small>
            {{ $suppliers->links() }}
        </div>
    </div>
@endsection
