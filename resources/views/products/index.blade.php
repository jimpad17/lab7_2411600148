@extends('layouts.app')

@section('title', 'Products')

@section('heading')
    Product <span class="text-warning fw-bold">Management</span>
@endsection

@section('content')
    <!-- Lab 7: shared filter toolbar component -->
    <x-filter-card action="{{ route('products.index') }}">
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Brand</label>
                    <select name="brand_id" class="form-select">
                        <option value="">All Brands</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" {{ (string) ($filters['brand_id'] ?? '') === (string) $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ (string) ($filters['category_id'] ?? '') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Supplier</label>
                    <select name="supplier_id" class="form-select">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ (string) ($filters['supplier_id'] ?? '') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Available</option>
                        <option value="in" {{ ($filters['status'] ?? '') === 'in' ? 'selected' : '' }}>In Stock</option>
                        <option value="low" {{ ($filters['status'] ?? '') === 'low' ? 'selected' : '' }}>Low Stock</option>
                        <option value="out" {{ ($filters['status'] ?? '') === 'out' ? 'selected' : '' }}>Out of Stock</option>
                        <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Unavailable</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-bold text-muted mb-1">Min Qty</label>
                    <input type="number" name="min_quantity" class="form-control" min="0" value="{{ $filters['min_quantity'] ?? '' }}">
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-bold text-muted mb-1">Max Qty</label>
                    <input type="number" name="max_quantity" class="form-control" min="0" value="{{ $filters['max_quantity'] ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">Search (name, SKU, brand or supplier)</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search products..." value="{{ $filters['search'] ?? '' }}">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    @if (Auth::user()->isAdmin())
                        <a href="{{ route('products.create') }}" class="btn btn-success btn-sm px-3">
                            <i class="bi bi-plus-circle me-1"></i> Add Product
                        </a>
                    @endif
                    <a href="{{ route('products.export', request()->only(['brand_id', 'category_id', 'supplier_id', 'status', 'min_quantity', 'max_quantity', 'search'])) }}"
                       class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-download me-1"></i> Export CSV
                    </a>
                    <a href="{{ route('products.export-pdf', request()->only(['brand_id', 'category_id', 'supplier_id', 'status', 'min_quantity', 'max_quantity', 'search'])) }}"
                       class="btn btn-outline-danger btn-sm px-3">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
                    </a>
                    <a href="{{ route('inventory.index') }}" class="btn btn-outline-warning btn-sm px-3">
                        <i class="bi bi-clipboard-data me-1"></i> Stock Overview
                    </a>
                </div>
    </x-filter-card>

    <div class="card shadow-sm">
        <div class="card-header bg-white pt-3 pb-2 d-flex justify-content-between align-items-center border-bottom-0">
            <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam text-warning me-2"></i> Product List</h5>
            <small class="text-muted">Showing {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} of {{ $products->total() }} products</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">SKU</th>
                            <th>Product Name</th>
                            <th>Brand</th>
                            <th>Category</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr class="{{ $product->is_active ? '' : 'table-inactive' }}">
                                <td class="ps-4"><code class="sku-tag">{{ $product->sku }}</code></td>
                                <td><strong>{{ $product->name }}</strong></td>
                                <td><small>{{ $product->brand?->name ?? '—' }}</small></td>
                                <td><span class="badge badge-category">{{ $product->category?->name ?? '—' }}</span></td>
                                <td>{{ $product->quantity }}</td>
                                <td>₱{{ number_format($product->unit_price, 2) }}</td>
                                <td>
                                    <span class="badge badge-{{ $product->quantity == 0 ? 'out' : ($product->isLowStock() ? 'low' : 'in') }}{{ $product->is_active ? '' : ' badge-inactive' }}">
                                        {{ $product->stock_status }}
                                    </span>
                                </td>
                                <td class="text-end pe-4 text-nowrap">
                                    <a href="{{ route('products.show', $product) }}" class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if (Auth::user()->isAdmin())
                                        <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        @if ($product->is_active)
                                            <form method="POST" action="{{ route('products.destroy', $product) }}" class="d-inline"
                                                  onsubmit="return confirm('Mark \'{{ addslashes($product->name) }}\' as unavailable? No records will be deleted.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Mark unavailable">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('products.restore', $product) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Restore">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No products match your filter criteria.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-body border-top d-flex justify-content-between align-items-center">
            <small class="text-muted">Inactive products stay in the database — nothing is deleted.</small>
            {{ $products->links() }}
        </div>
    </div>
@endsection
