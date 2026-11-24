@extends('layouts.app')

@section('title', 'Stock Transactions')

@section('heading')
    Stock <span class="text-warning fw-bold">Transactions</span>
@endsection

@section('actions')
    <a href="{{ route('transactions.create') }}" class="btn btn-success btn-sm px-3">
        <i class="bi bi-plus-circle me-1"></i> Record Movement
    </a>
    <a href="{{ route('transactions.export', request()->only(['product_id', 'type', 'from', 'to', 'reference', 'include_reversed'])) }}"
       class="btn btn-outline-primary btn-sm px-3">
        <i class="bi bi-file-earmark-excel me-1"></i> CSV
    </a>
    <a href="{{ route('transactions.export-pdf', request()->only(['product_id', 'type', 'from', 'to', 'reference', 'include_reversed'])) }}"
       class="btn btn-outline-danger btn-sm px-3">
        <i class="bi bi-file-earmark-pdf me-1"></i> PDF
    </a>
    @if (Auth::user()->isAdmin())
        <a href="{{ route('adjustments.create') }}" class="btn btn-outline-warning btn-sm px-3">
            <i class="bi bi-sliders2 me-1"></i> Adjust Count
        </a>
        <a href="{{ route('reports.tracking') }}" class="btn btn-outline-primary btn-sm px-3">
            <i class="bi bi-bar-chart-line me-1"></i> Tracking Report
        </a>
    @endif
@endsection

@section('content')
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card stat-card shadow-sm text-center py-3">
                <div class="card-body py-2">
                    <h6 class="card-title text-uppercase fw-bold mb-2">Movements Today</h6>
                    <h2 class="fw-bold text-primary mb-0">{{ $todayCount }}</h2>
                    <small class="text-muted">Recorded stock movements</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stat-card shadow-sm text-center py-3">
                <div class="card-body py-2">
                    <h6 class="card-title text-uppercase fw-bold mb-2">Units Received</h6>
                    <h2 class="fw-bold text-success mb-0">{{ number_format($totalIn) }}</h2>
                    <small class="text-muted">All stock-in transactions</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stat-card shadow-sm text-center py-3">
                <div class="card-body py-2">
                    <h6 class="card-title text-uppercase fw-bold mb-2">Units Issued</h6>
                    <h2 class="fw-bold text-warning mb-0">{{ number_format($totalOut) }}</h2>
                    <small class="text-muted">All stock-out transactions</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stat-card shadow-sm text-center py-3">
                <div class="card-body py-2">
                    <h6 class="card-title text-uppercase fw-bold mb-2">Adjustments</h6>
                    <h2 class="fw-bold text-danger mb-0">{{ number_format($adjustments) }}</h2>
                    <small class="text-muted">Physical count corrections</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Lab 7: shared filter toolbar component -->
    <x-filter-card action="{{ route('transactions.index') }}">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Product</label>
                    <select name="product_id" class="form-select form-select-sm">
                        <option value="">All products</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) $filters['product_id'] === (string) $product->id)>
                                {{ $product->name }} ({{ $product->sku }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All types</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Reference Number</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="reference" value="{{ $filters['reference'] }}"
                               placeholder="PO-2026-001" class="form-control">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                        <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check form-check-inline small">
                        <input class="form-check-input" type="checkbox" name="include_reversed" value="1"
                               id="includeReversed" @checked($filters['include_reversed'])>
                        <label class="form-check-label text-muted" for="includeReversed">Include reversed transactions</label>
                    </div>
                </div>
    </x-filter-card>

    <div class="card shadow-sm">
        <div class="card-header bg-white pt-3 border-bottom-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right text-warning me-2"></i> Transaction Log</h5>
            <small class="text-muted">Showing {{ $transactions->firstItem() ?? 0 }}–{{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }} movements</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Date / Time</th><th>Product</th><th>Type</th><th>Quantity</th>
                            <th>Balance</th><th>Reference</th><th>User</th><th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $transaction)
                            <tr class="{{ $transaction->is_reversed ? 'table-inactive' : '' }}">
                                <td class="ps-4 text-nowrap">{{ $transaction->created_at->format('M d, Y h:i A') }}</td>
                                <td>
                                    <a href="{{ route('products.show', $transaction->product_id) }}" class="fw-semibold text-decoration-none">
                                        {{ $transaction->product?->name ?? 'Deleted product' }}
                                    </a>
                                    @if ($transaction->product)<div><code class="sku-tag">{{ $transaction->product->sku }}</code></div>@endif
                                </td>
                                <td>
                                    <span class="badge {{ $transaction->badge_class }}">{{ $transaction->type_label }}</span>
                                    @if ($transaction->is_reversed)
                                        <span class="badge bg-secondary ms-1">Reversed</span>
                                    @endif
                                </td>
                                <td class="fw-bold {{ $transaction->signedQuantity() < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ $transaction->quantity_display }}
                                </td>
                                <td>{{ $transaction->balance_after ?? '—' }}</td>
                                <td><code>{{ $transaction->reference_number ?? '—' }}</code></td>
                                <td>{{ $transaction->user?->name ?? 'System' }}</td>
                                <td class="text-end pe-4 text-nowrap">
                                    <a href="{{ route('transactions.show', $transaction) }}"
                                       class="btn btn-sm btn-outline-primary" title="View details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if (Auth::user()->isAdmin() && ! $transaction->is_reversed)
                                        <form method="POST" action="{{ route('transactions.destroy', $transaction) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('Reverse this movement? The original record is kept for auditing.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Reverse transaction">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-4 text-muted">No stock movements match your filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-body border-top d-flex justify-content-between align-items-center">
            <small class="text-muted">Every movement is immutable — reversals create a new balancing transaction.</small>
            {{ $transactions->links() }}
        </div>
    </div>
@endsection
