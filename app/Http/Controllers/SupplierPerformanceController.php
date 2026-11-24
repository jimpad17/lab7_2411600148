<?php

namespace App\Http\Controllers;

use App\Exports\SupplierPerformanceExport;
use App\Models\Supplier;
use App\Services\SupplierPerformanceService;
use Illuminate\Http\Request;

class SupplierPerformanceController extends Controller
{
    public function index(SupplierPerformanceService $service)
    {
        $ranking = $service->ranking(20);

        $suppliers = Supplier::withTrashed()
            ->withCount('purchaseOrders as total_orders')
            ->orderBy('company_name')
            ->get()
            ->map(function (Supplier $supplier) use ($service) {
                $supplier->performance = (object) $service->metrics($supplier);

                return $supplier;
            });

        // Rank every supplier, not just the truncated chart ranking, so the
        // bottom performers are the real worst performers.
        $allPerformance = $suppliers
            ->filter(fn (Supplier $supplier) => $supplier->performance->total_orders > 0)
            ->map(fn (Supplier $supplier) => (object) array_merge(
                ['id' => $supplier->id, 'company_name' => $supplier->company_name],
                (array) $supplier->performance
            ));

        return view('suppliers.performance', [
            'suppliers' => $suppliers,
            'ranking' => $ranking,
            'topSuppliers' => $allPerformance->sortByDesc('on_time_rate')->take(3)->values(),
            'bottomSuppliers' => $allPerformance->sortBy('on_time_rate')->take(3)->values(),
            'chartLabels' => $ranking->pluck('company_name')->all(),
            'chartRates' => $ranking->pluck('on_time_rate')->all(),
            'chartValues' => $ranking->pluck('total_value')->all(),
        ]);
    }

    public function exportCsv(SupplierPerformanceExport $exports)
    {
        return $exports->csv();
    }

    public function exportPdf(SupplierPerformanceExport $exports)
    {
        return $exports->pdf();
    }
}
