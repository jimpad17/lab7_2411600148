@extends('layouts.app')

@section('title', 'Supplier Performance')

@section('heading')
    Supplier <span class="text-warning fw-bold">Performance Report</span>
@endsection

@section('actions')
    <a href="{{ route('suppliers.performance.export') }}" class="btn btn-outline-primary btn-sm px-3 me-1">
        <i class="bi bi-file-earmark-excel me-1"></i> CSV
    </a>
    <a href="{{ route('suppliers.performance.export-pdf') }}" class="btn btn-outline-danger btn-sm px-3">
        <i class="bi bi-file-earmark-pdf me-1"></i> PDF
    </a>
@endsection

@section('content')
    <div class="row mb-4">
        <div class="col-lg-8 mb-3">
            <div class="card shadow-sm p-3 h-100">
                <h6 class="fw-bold mb-3"><i class="bi bi-bar-chart-line text-primary me-2"></i> On-Time Delivery Rate by Supplier</h6>
                <div class="chart-box"><canvas id="supplierOnTimeChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white pt-3 border-bottom-0">
                    <h6 class="fw-bold mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i> Top Performers</h6>
                </div>
                <div class="card-body">
                    @forelse ($topSuppliers as $index => $supplier)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2 small">
                            <span><span class="badge bg-warning text-dark me-2">#{{ $index + 1 }}</span>{{ $supplier->company_name }}</span>
                            <strong class="text-success">{{ $supplier->on_time_rate }}%</strong>
                        </div>
                    @empty
                        <small class="text-muted">No delivered orders yet.</small>
                    @endforelse
                    <hr>
                    <small class="text-muted fw-bold d-block mb-2">Needs attention</small>
                    @forelse ($bottomSuppliers as $supplier)
                        <div class="d-flex justify-content-between align-items-center py-1 small">
                            <span>{{ $supplier->company_name }}</span>
                            <strong class="{{ $supplier->on_time_rate < 80 ? 'text-danger' : 'text-warning' }}">{{ $supplier->on_time_rate }}%</strong>
                        </div>
                    @empty
                        <small class="text-muted">No data.</small>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white pt-3 border-bottom-0">
            <h5 class="mb-0 fw-bold"><i class="bi bi-table text-warning me-2"></i> Performance Summary</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Supplier</th>
                            <th>Orders</th>
                            <th>Delivered</th>
                            <th>On time</th>
                            <th>On-time %</th>
                            <th>Actual days</th>
                            <th>Quoted days</th>
                            <th>Variance</th>
                            <th>Accuracy %</th>
                            <th class="text-end pe-4">Total value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($suppliers as $supplier)
                            @php $m = $supplier->performance; @endphp
                            <tr>
                                <td class="ps-4">
                                    <a href="{{ route('suppliers.show', $supplier) }}" class="text-decoration-none fw-bold text-dark">{{ $supplier->company_name }}</a>
                                    @if ($supplier->trashed()) <span class="badge bg-dark ms-1">Archived</span> @endif
                                </td>
                                <td>{{ $m->total_orders }}</td>
                                <td>{{ $m->delivered_orders }}</td>
                                <td>{{ $m->delivered_on_time }}</td>
                                <td>
                                    <span class="badge {{ $m->on_time_rate >= 90 ? 'bg-success' : ($m->on_time_rate >= 70 ? 'bg-warning text-dark' : 'bg-danger') }}">
                                        {{ $m->on_time_rate }}%
                                    </span>
                                </td>
                                <td>{{ $m->avg_delivery_days }}</td>
                                <td>{{ $m->avg_quoted_days }}</td>
                                <td class="{{ $m->avg_variance_days > 0 ? 'text-danger' : 'text-success' }}">
                                    {{ $m->avg_variance_days > 0 ? '+' : '' }}{{ $m->avg_variance_days }}
                                </td>
                                <td>{{ $m->accuracy_rate }}%</td>
                                <td class="text-end pe-4 fw-bold">₱{{ number_format((float) $m->total_value, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No suppliers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvas = document.getElementById('supplierOnTimeChart');
            if (!canvas || typeof Chart === 'undefined') return;

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: @json($chartLabels),
                    datasets: [{
                        label: 'On-time delivery %',
                        data: @json($chartRates),
                        backgroundColor: '#fd7e14',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } },
                    plugins: { legend: { display: false } }
                }
            });
        });
    </script>
@endpush
