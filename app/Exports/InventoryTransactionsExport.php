<?php

namespace App\Exports;

use App\Services\SearchService;

/** Lab 7 export class for stock movements / inventory transactions. */
class InventoryTransactionsExport
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
            'inventory-transactions-'.now()->format('Ymd-His').'.csv',
            ['ID', 'Date', 'Product', 'SKU', 'Type', 'Quantity', 'Balance after', 'Reference', 'User', 'Notes'],
            $this->transactions()->map(fn ($transaction) => [
                $transaction->id,
                $transaction->created_at?->format('Y-m-d H:i:s'),
                $transaction->product?->name ?? 'Deleted product',
                $transaction->product?->sku ?? '—',
                $transaction->type_label,
                $transaction->quantity_display,
                $transaction->balance_after,
                $transaction->reference_number ?? '',
                $transaction->user?->name ?? 'System',
                $transaction->notes ?? '',
            ])
        );
    }

    public function pdf()
    {
        return $this->exporter->pdf(
            'inventory-transactions-'.now()->format('Ymd-His').'.pdf',
            'Inventory Transaction Log',
            ['ID', 'Date', 'Product', 'Type', 'Qty', 'Reference', 'User'],
            $this->transactions()->map(fn ($transaction) => [
                $transaction->id,
                $transaction->created_at?->format('Y-m-d H:i:s'),
                $transaction->product?->name ?? 'Deleted product',
                $transaction->type_label,
                $transaction->quantity_display,
                $transaction->reference_number ?? '',
                $transaction->user?->name ?? 'System',
            ])
        );
    }

    protected function transactions()
    {
        return $this->search->transactions($this->filters)->get();
    }
}
