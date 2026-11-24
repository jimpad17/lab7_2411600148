<?php

namespace App\Exports;

use App\Services\SearchService;

/** Lab 7 export class for the product catalogue. */
class ProductsExport
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
            'products-'.now()->format('Ymd-His').'.csv',
            ['SKU', 'Product', 'Brand', 'Category', 'Supplier', 'Quantity', 'Reorder level', 'Unit price', 'Stock value', 'Status'],
            $this->products()->map(fn ($product) => [
                $product->sku,
                $product->name,
                $product->brand?->name ?? '',
                $product->category?->name ?? '',
                $product->supplierRecord?->company_name ?? $product->supplier ?? '',
                $product->quantity,
                $product->reorder_level,
                number_format((float) $product->unit_price, 2, '.', ''),
                number_format((float) $product->stock_value, 2, '.', ''),
                $product->stock_status,
            ])
        );
    }

    public function pdf()
    {
        return $this->exporter->pdf(
            'products-'.now()->format('Ymd-His').'.pdf',
            'Product Catalogue',
            ['SKU', 'Product', 'Category', 'Qty', 'Unit price', 'Status'],
            $this->products()->map(fn ($product) => [
                $product->sku,
                $product->name,
                $product->category?->name ?? '—',
                $product->quantity,
                '₱'.number_format((float) $product->unit_price, 2),
                $product->stock_status,
            ])
        );
    }

    protected function products()
    {
        return $this->search->products($this->filters)->get();
    }
}
