@extends('layouts.app')

@section('title', 'Dashboard')

@section('heading')
    {{ $greeting }}, <span class="text-warning fw-bold">{{ Auth::user()->name }}</span>!
@endsection

@section('actions')
    <a href="{{ route('transactions.create') }}" class="btn btn-success btn-sm px-3 me-1">
        <i class="bi bi-plus-circle me-1"></i> Record Movement
    </a>
    @if (Auth::user()->isAdmin())
        <a href="{{ route('reports.tracking') }}" class="btn btn-outline-primary btn-sm px-3 me-1">
            <i class="bi bi-activity me-1"></i> Tracking
        </a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-warning btn-sm px-3">
            <i class="bi bi-bar-chart-line me-1"></i> Reports
        </a>
    @endif
@endsection

@section('content')
    <!-- KPI cards: real MySQL values (replaces Lab 4 localStorage values) -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card stat-card shadow-sm text-center py-3">
                <div class="card-body py-2">
                    <h6 class="card-title text-uppercase fw-bold mb-2">Total Products</h6>
                    <h2 class="fw-bold text-primary mb-0" id="live-total-products">{{ $totalProducts }}</h2>
                    <small class="text-muted">{{ $inStock }} items in stock</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stat-card shadow-sm text-center py-3">
                <div class="card-body py-2">
                    <h6 class="card-title text-uppercase fw-bold mb-2">Low Stock Alerts</h6>
                    <h2 class="fw-bold text-warning mb-0" id="live-low-stock">{{ $lowStock }}</h2>
                    <small class="text-muted">At or below reorder level</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stat-card shadow-sm text-center py-3">
                <div class="card-body py-2">
                    <h6 class="card-title text-uppercase fw-bold mb-2">Out of Stock</h6>
                    <h2 class="fw-bold text-danger mb-0" id="live-out-of-stock">{{ $outOfStock }}</h2>
                    <small class="text-muted">Needs immediate restock</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stat-card shadow-sm text-center py-3">
                <div class="card-body py-2">
                    <h6 class="card-title text-uppercase fw-bold mb-2">Inventory Value</h6>
                    <h2 class="fw-bold text-success mb-0" id="live-inventory-value">₱{{ number_format($inventoryValue, 2) }}</h2>
                    <small class="text-muted">{{ $activeBrands }} brands · {{ $activeCategories }} categories</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Lab 6: live polling status -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <small class="text-muted">
            <i class="bi bi-broadcast-pin text-success live-dot me-1"></i>
            Live inventory tracking — refreshes automatically every 30 seconds
        </small>
        <small class="text-muted">Last updated: <strong id="last-updated" class="text-dark">{{ now()->format('M d, Y h:i A') }}</strong></small>
    </div>

    {{-- Lab 7: procurement widgets bound automatically by View Composers --}}
    <h5 class="fw-bold mb-3 mt-4">
        <i class="bi bi-truck text-warning me-2"></i> Procurement Overview
        <small class="text-muted fw-normal ms-2">View Composer widgets · refreshed from the widget API</small>
    </h5>
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">@include('widgets.pending-orders')</div>
        <div class="col-xl-3 col-md-6 mb-3">@include('widgets.recent-orders')</div>
        <div class="col-xl-3 col-md-6 mb-3">@include('widgets.supplier-performance')</div>
        <div class="col-xl-3 col-md-6 mb-3">@include('widgets.reorder-alerts')</div>
    </div>

    <!-- Visualizations: same Chart.js approach as Lab 4, now driven by Eloquent data -->
    <div class="row mb-4">
        <div class="col-lg-4 mb-3">
            <div class="card h-100 shadow-sm p-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Inventory Category Spread</h6>
                <div class="chart-box"><canvas id="categoryChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="card h-100 shadow-sm p-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Stock Health</h6>
                <div class="chart-box"><canvas id="stockHealthChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="card h-100 shadow-sm p-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-trophy-fill text-warning me-2"></i>Top 5 Products by Value</h6>
                <div class="chart-box"><canvas id="topProductsChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-8 mb-3">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 pt-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history text-primary me-2"></i>Recently Added Products</h5>
                    <a href="{{ route('products.index') }}" class="small text-decoration-none fw-semibold">View all</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th class="ps-3">SKU</th><th>Product</th><th>Brand</th><th>Category</th><th>Qty</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($recentProducts as $item)
                                    <tr>
                                        <td class="ps-3"><code class="sku-tag">{{ $item->sku }}</code></td>
                                        <td><strong>{{ $item->name }}</strong></td>
                                        <td><small>{{ $item->brand?->name ?? '—' }}</small></td>
                                        <td><span class="badge badge-category">{{ $item->category?->name ?? '—' }}</span></td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>
                                            <span class="badge badge-{{ $item->quantity == 0 ? 'out' : ($item->isLowStock() ? 'low' : 'in') }}">
                                                {{ $item->stock_status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No products yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white pt-3 border-bottom-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-bell-fill text-warning me-2"></i>Restock Alerts</h5>
                </div>
                <div class="card-body" id="live-low-stock-list">
                    @forelse ($lowStockProducts as $item)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2 small">
                            <div>
                                <div class="fw-bold">{{ $item->name }}</div>
                                <div class="text-muted">{{ $item->quantity }} pcs left · min {{ $item->reorder_level }}</div>
                            </div>
                            <span class="badge badge-{{ $item->quantity == 0 ? 'out' : 'low' }}">{{ $item->stock_status }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">All products are above reorder level. Nice!</p>
                    @endforelse
                    <a href="{{ route('inventory.index') }}" class="small text-decoration-none fw-semibold d-block mt-3">
                        Open stock overview →
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-8 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white pt-3 border-bottom-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right text-success me-2"></i>Recent Stock Movements</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th class="ps-3">Product</th><th>Type</th><th>Quantity</th><th>Reference</th><th>User</th><th>Date</th></tr>
                            </thead>
                            <tbody id="live-recent-transactions">
                                @forelse ($recentTransactions as $transaction)
                                    <tr>
                                        <td class="ps-3">{{ $transaction->product?->name ?? 'Deleted product' }}</td>
                                        <td><span class="badge {{ $transaction->badge_class }}">{{ $transaction->type_label }}</span></td>
                                        <td class="fw-bold {{ $transaction->signedQuantity() < 0 ? 'text-danger' : 'text-success' }}">{{ $transaction->quantity_display }}</td>
                                        <td><code>{{ $transaction->reference_number ?? '—' }}</code></td>
                                        <td>{{ $transaction->user?->name ?? 'System' }}</td>
                                        <td>{{ $transaction->created_at->format('M d, Y h:i A') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No stock movements recorded.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white pt-3 border-bottom-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-activity text-primary me-2"></i>Recent Activity</h5>
                    @if (Auth::user()->isAdmin())
                        <a href="{{ route('activity-logs.index') }}" class="small text-decoration-none fw-semibold">View all</a>
                    @endif
                </div>
                <div class="card-body">
                    @forelse ($recentActivity as $log)
                        <div class="border-bottom py-2 small">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">{{ $log->user?->name ?? 'System' }}</span>
                                <span class="badge bg-light text-dark">{{ $log->module }}</span>
                            </div>
                            <div class="text-muted">{{ $log->description }}</div>
                            <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No activity recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================
         Role-specific reports (new in this iteration)
         Admin: sales & purchase, product report, daily history, product history
         Staff: product report + their own orders / profile shortcut
         ================================================================ --}}
    @if ($isAdmin)
        @if ($pendingOrders > 0)
            <div class="alert alert-warning d-flex align-items-center justify-content-between py-2 mb-4">
                <div class="small">
                    <i class="bi bi-shield-check me-1"></i>
                    <strong>{{ $pendingOrders }}</strong> order{{ $pendingOrders === 1 ? '' : 's' }} waiting for your review.
                    Correct any mistake before it is posted to inventory.
                </div>
                <a href="{{ route('orders.index', ['status' => 'pending']) }}" class="btn btn-sm btn-warning fw-bold">
                    Review now <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        @endif

        <!-- Sales & Purchase Report -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white pt-3 border-bottom-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-cart-check text-success me-2"></i>Sales Report</h5>
                        <a href="{{ route('orders.index', ['type' => 'sales']) }}" class="small text-decoration-none fw-semibold">View all</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th class="ps-3">Order #</th><th>Product</th><th>Qty</th><th>Value</th><th>Date</th></tr></thead>
                                <tbody>
                                    @forelse ($salesOrders as $order)
                                        <tr>
                                            <td class="ps-3"><a href="{{ route('orders.show', $order) }}" class="text-decoration-none"><code>{{ $order->order_number }}</code></a></td>
                                            <td>{{ $order->product?->name ?? 'Deleted product' }}</td>
                                            <td class="fw-bold">{{ $order->quantity }}</td>
                                            <td>₱{{ number_format($order->total, 2) }}</td>
                                            <td>{{ $order->order_date->format('M d, Y') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center py-4 text-muted">No approved sales yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white pt-3 border-bottom-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-bag-plus text-primary me-2"></i>Purchase Report</h5>
                        <a href="{{ route('orders.index', ['type' => 'purchase']) }}" class="small text-decoration-none fw-semibold">View all</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th class="ps-3">Order #</th><th>Product</th><th>Qty</th><th>Value</th><th>Date</th></tr></thead>
                                <tbody>
                                    @forelse ($purchaseOrders as $order)
                                        <tr>
                                            <td class="ps-3"><a href="{{ route('orders.show', $order) }}" class="text-decoration-none"><code>{{ $order->order_number }}</code></a></td>
                                            <td>{{ $order->product?->name ?? 'Deleted product' }}</td>
                                            <td class="fw-bold">{{ $order->quantity }}</td>
                                            <td>₱{{ number_format($order->total, 2) }}</td>
                                            <td>{{ $order->order_date->format('M d, Y') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center py-4 text-muted">No approved purchases yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Report + Daily Report History -->
        <div class="row mb-4">
            <div class="col-lg-7 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white pt-3 border-bottom-0">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam text-warning me-2"></i>Product Report</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th class="ps-3">Product</th><th>Brand / Category</th><th>On Hand</th><th>Sales</th><th>Purchases</th><th>Status</th></tr></thead>
                                <tbody>
                                    @forelse ($productReport as $item)
                                        <tr>
                                            <td class="ps-3"><strong>{{ $item->name }}</strong><div><code class="sku-tag">{{ $item->sku }}</code></div></td>
                                            <td><small>{{ $item->brand?->name ?? '—' }} / {{ $item->category?->name ?? '—' }}</small></td>
                                            <td class="fw-bold">{{ $item->quantity }}</td>
                                            <td>{{ $item->sales_orders_count }}</td>
                                            <td>{{ $item->purchase_orders_count }}</td>
                                            <td><span class="badge badge-{{ $item->quantity == 0 ? 'out' : ($item->isLowStock() ? 'low' : 'in') }}">{{ $item->stock_status }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No products to report.</td></tr>
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
                        <h5 class="mb-0 fw-bold"><i class="bi bi-calendar2-week text-primary me-2"></i>Daily Report History</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th class="ps-3">Date</th><th>Type</th><th>Orders</th><th>Units</th></tr></thead>
                                <tbody>
                                    @forelse ($dailyHistory as $row)
                                        <tr>
                                            <td class="ps-3">{{ \Carbon\Carbon::parse($row->order_date)->format('M d, Y') }}</td>
                                            <td><span class="badge {{ $row->type === 'purchase' ? 'badge-in' : 'badge-category' }}">{{ ucfirst($row->type) }}</span></td>
                                            <td>{{ $row->orders }}</td>
                                            <td class="fw-bold">{{ $row->units }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-4 text-muted">No daily history yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product History -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white pt-3 border-bottom-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history text-success me-2"></i>Product History (Stock Movements)</h5>
                        <a href="{{ route('reports.tracking') }}" class="small text-decoration-none fw-semibold">Open tracking report →</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th class="ps-3">Product</th><th>Movement</th><th>Qty</th><th>Balance</th><th>User</th><th>Date</th></tr></thead>
                                <tbody>
                                    @forelse ($productHistory as $movement)
                                        <tr>
                                            <td class="ps-3">{{ $movement->product?->name ?? 'Deleted product' }}</td>
                                            <td><span class="badge {{ $movement->badge_class }}">{{ $movement->type_label }}</span></td>
                                            <td class="fw-bold {{ $movement->signedQuantity() < 0 ? 'text-danger' : 'text-success' }}">{{ $movement->quantity_display }}</td>
                                            <td>{{ $movement->balance_after ?? '—' }}</td>
                                            <td>{{ $movement->user?->name ?? 'System' }}</td>
                                            <td>{{ $movement->created_at->format('M d, Y h:i A') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No stock movements recorded.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- Staff dashboard: product report + own orders + profile -->
        <div class="row mb-4">
            <div class="col-lg-7 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white pt-3 border-bottom-0">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam text-warning me-2"></i>Product Report</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th class="ps-3">Product</th><th>Brand / Category</th><th>On Hand</th><th>Status</th></tr></thead>
                                <tbody>
                                    @forelse ($productReport as $item)
                                        <tr>
                                            <td class="ps-3"><strong>{{ $item->name }}</strong><div><code class="sku-tag">{{ $item->sku }}</code></div></td>
                                            <td><small>{{ $item->brand?->name ?? '—' }} / {{ $item->category?->name ?? '—' }}</small></td>
                                            <td class="fw-bold">{{ $item->quantity }}</td>
                                            <td><span class="badge badge-{{ $item->quantity == 0 ? 'out' : ($item->isLowStock() ? 'low' : 'in') }}">{{ $item->stock_status }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-4 text-muted">No products to report.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white pt-3 border-bottom-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-cart3 text-primary me-2"></i>My Orders</h5>
                        <a href="{{ route('profile') }}" class="small text-decoration-none fw-semibold">My profile →</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th class="ps-3">Order #</th><th>Product</th><th>Qty</th><th>Status</th></tr></thead>
                                <tbody>
                                    @forelse ($myOrders as $order)
                                        <tr>
                                            <td class="ps-3"><a href="{{ route('orders.show', $order) }}" class="text-decoration-none"><code>{{ $order->order_number }}</code></a></td>
                                            <td>{{ $order->product?->name ?? 'Deleted product' }}</td>
                                            <td>{{ $order->quantity }}</td>
                                            <td><span class="badge {{ $order->badge_class }}">{{ $order->status_label }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-4 text-muted">You have not submitted any orders yet. <a href="{{ route('orders.create') }}">Create one →</a></td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        const categoryLabels = @json($categoryBreakdown->pluck('category'));
        const categoryItems = @json($categoryBreakdown->pluck('items'));
        const topNames = @json($topProducts->pluck('name'));
        const topValues = @json($topProducts->pluck('stock_value'));

        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{ data: categoryItems, backgroundColor: ['#fd7e14', '#0d6efd', '#198754', '#6f42c1', '#ffc107', '#20c997'] }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

        new Chart(document.getElementById('stockHealthChart'), {
            type: 'pie',
            data: {
                labels: ['In Stock', 'Low Stock', 'Out of Stock'],
                datasets: [{ data: [{{ $inStock }}, {{ $lowStock }}, {{ $outOfStock }}], backgroundColor: ['#198754', '#ffc107', '#dc3545'] }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

        new Chart(document.getElementById('topProductsChart'), {
            type: 'bar',
            data: {
                labels: topNames,
                datasets: [{ label: 'Stock Value (₱)', data: topValues, backgroundColor: '#212529' }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true } }
            }
        });
    </script>

    <!-- Lab 6: AJAX polling (30s) for stats, low stock and recent movements -->
    <script src="{{ asset('js/inventory-live.js') }}?v={{ filemtime(public_path('js/inventory-live.js')) }}" defer></script>

    <!-- Lab 7: refresh every procurement widget from its JSON endpoint -->
    <script>
        (function () {
            const headers = { 'Accept': 'application/json' };
            const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

            async function refresh(endpoint, callback) {
                try {
                    const response = await fetch(endpoint, { headers, credentials: 'same-origin' });
                    if (response.ok) callback(await response.json());
                } catch (error) {
                    console.warn('Widget refresh failed:', error);
                }
            }

            function reset(element, message) {
                element.replaceChildren();
                const row = document.createElement('li');
                row.className = 'list-group-item text-muted px-3';
                row.textContent = message;
                element.appendChild(row);
            }

            function renderRecentOrders(orders) {
                const body = document.getElementById('recent-orders-body');
                if (!body) return;

                body.replaceChildren();
                if (!orders.length) {
                    const row = document.createElement('tr');
                    const cell = document.createElement('td');
                    cell.colSpan = 4;
                    cell.className = 'text-muted text-center py-3';
                    cell.textContent = 'No purchase orders yet.';
                    row.appendChild(cell);
                    body.appendChild(row);
                    return;
                }

                orders.forEach(function (order) {
                    const row = document.createElement('tr');

                    const reference = document.createElement('td');
                    reference.className = 'ps-3';
                    const link = document.createElement('a');
                    link.href = order.url;
                    link.className = 'text-decoration-none fw-bold';
                    link.textContent = order.reference_number;
                    reference.appendChild(link);

                    const supplier = document.createElement('td');
                    supplier.textContent = order.supplier || '—';

                    const status = document.createElement('td');
                    const badge = document.createElement('span');
                    badge.className = 'badge ' + (order.status_badge || 'bg-secondary');
                    badge.textContent = order.status_label;
                    status.appendChild(badge);

                    const total = document.createElement('td');
                    total.className = 'text-end pe-3';
                    total.textContent = money.format(order.total || 0);

                    row.append(reference, supplier, status, total);
                    body.appendChild(row);
                });
            }

            function renderSupplierPerformance(suppliers) {
                const list = document.getElementById('supplier-performance-list');
                if (!list) return;

                list.replaceChildren();
                if (!suppliers.length) {
                    reset(list, 'No supplier delivery data yet.');
                    return;
                }

                suppliers.forEach(function (supplier, index) {
                    const row = document.createElement('li');
                    row.className = 'list-group-item d-flex justify-content-between align-items-center px-3';

                    const name = document.createElement('span');
                    const rank = document.createElement('span');
                    rank.className = 'badge bg-warning text-dark me-2';
                    rank.textContent = '#' + (index + 1);
                    name.append(rank, document.createTextNode(supplier.company_name));

                    const rate = document.createElement('span');
                    rate.className = 'fw-bold ' + (supplier.on_time_rate >= 90 ? 'text-success' : 'text-warning');
                    rate.textContent = supplier.on_time_rate + '%';

                    row.append(name, rate);
                    list.appendChild(row);
                });
            }

            function refreshAll() {
                refresh('{{ route('api.dashboard.pending-orders') }}', function (payload) {
                    const count = document.getElementById('pending-count');
                    if (count) count.textContent = payload.count ?? 0;
                });
                refresh('{{ route('api.dashboard.recent-orders') }}', function (payload) {
                    renderRecentOrders(payload.data || []);
                });
                refresh('{{ route('api.dashboard.supplier-performance') }}', function (payload) {
                    renderSupplierPerformance(payload.data || []);
                });
                refresh('{{ route('api.dashboard.reorder-alerts') }}', function (payload) {
                    const badge = document.querySelector('#widget-reorder-alerts .badge');
                    if (badge) badge.textContent = payload.count ?? 0;
                });
            }

            setInterval(refreshAll, 60000);
        })();
    </script>
@endpush
