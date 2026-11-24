<?php

namespace App\Exports;

use App\Services\SupplierPerformanceService;

/** Lab 7 export class for aggregated supplier performance metrics. */
class SupplierPerformanceExport
{
    public function __construct(
        protected ?SupplierPerformanceService $service = null,
        protected ?ExportService $exporter = null
    ) {
        $this->service ??= app(SupplierPerformanceService::class);
        $this->exporter ??= app(ExportService::class);
    }

    public function csv()
    {
        return $this->exporter->csv(
            'supplier-performance-'.now()->format('Ymd-His').'.csv',
            ['Supplier', 'Total orders', 'On-time deliveries', 'Delivered orders', 'On-time %', 'Actual days', 'Quoted days', 'Variance (days)', 'Accuracy %', 'Total value (₱)'],
            $this->ranking()->map(fn ($row) => [
                $row->company_name,
                $row->total_orders,
                $row->delivered_on_time,
                $row->delivered_orders,
                $row->on_time_rate.'%',
                $row->avg_delivery_days,
                $row->avg_quoted_days,
                $row->avg_variance_days,
                $row->accuracy_rate.'%',
                number_format((float) $row->total_value, 2, '.', ''),
            ])
        );
    }

    public function pdf()
    {
        return $this->exporter->pdf(
            'supplier-performance-'.now()->format('Ymd-His').'.pdf',
            'Supplier Performance Report',
            ['Supplier', 'Orders', 'On-time %', 'Actual days', 'Quoted days', 'Variance', 'Accuracy %', 'Value (₱)'],
            $this->ranking()->map(fn ($row) => [
                $row->company_name,
                $row->total_orders,
                $row->on_time_rate.'%',
                $row->avg_delivery_days,
                $row->avg_quoted_days,
                $row->avg_variance_days,
                $row->accuracy_rate.'%',
                number_format((float) $row->total_value, 2),
            ])
        );
    }

    protected function ranking()
    {
        return $this->service->ranking(50);
    }
}
