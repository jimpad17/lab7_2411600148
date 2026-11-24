<?php

namespace App\Exports;

use App\Services\SearchService;

/** Lab 7 export class for the purchase order register. */
class PurchaseOrdersExport
{
    public function __construct(
        protected array $filters = [],
        protected ?SearchService $search = null,
        protected ?ExportService $exporter = null
    ) {
        $this->search ??= app(SearchService::class);
        $this->exporter ??= app(ExportService::class);
    }

    public function withFilters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function csv()
    {
        return $this->exporter->csv(
            'purchase-orders-'.now()->format('Ymd-His').'.csv',
            ['Reference', 'Supplier', 'Status', 'Order date', 'Expected', 'Items', 'Subtotal', 'Tax', 'Total', 'Created by', 'Approved by'],
            $this->orders()->map(fn ($order) => [
                $order->reference_number,
                $order->supplier?->company_name ?? '—',
                $order->status_label,
                $order->order_date?->format('Y-m-d'),
                $order->expected_delivery_date?->format('Y-m-d') ?? '',
                $order->items()->count(),
                number_format((float) $order->subtotal, 2, '.', ''),
                number_format((float) $order->tax, 2, '.', ''),
                number_format((float) $order->total, 2, '.', ''),
                $order->createdBy?->name ?? '—',
                $order->approvedBy?->name ?? '—',
            ])
        );
    }

    public function pdf()
    {
        return $this->exporter->pdf(
            'purchase-orders-'.now()->format('Ymd-His').'.pdf',
            'Purchase Order Register',
            ['Reference', 'Supplier', 'Status', 'Order date', 'Total (₱)'],
            $this->orders()->map(fn ($order) => [
                $order->reference_number,
                $order->supplier?->company_name ?? '—',
                $order->status_label,
                $order->order_date?->format('Y-m-d'),
                number_format((float) $order->total, 2),
            ])
        );
    }

    protected function orders()
    {
        return $this->search->purchaseOrders($this->filters)->get();
    }
}
