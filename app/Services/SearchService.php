<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;

/**
 * Lab 7 - reusable advanced search/filter logic shared by pages and exports.
 * Every method returns a query builder so callers decide between get(),
 * paginate() or a CSV stream.
 */
class SearchService
{
    /** Products by name, SKU, category, brand and supplier. */
    public function products(array $filters = [])
    {
        $term = $filters['search'] ?? null;
        $requestedSort = $filters['sort'] ?? 'id';
        $sort = in_array($requestedSort, ['id', 'name', 'sku', 'quantity', 'unit_price'], true)
            ? $requestedSort
            : 'id';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return Product::query()
            ->with(['brand', 'category', 'supplier'])
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when(! empty($filters['brand_id']), fn ($q) => $q->where('brand_id', $filters['brand_id']))
            ->when(! empty($filters['supplier_id']), fn ($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when(isset($filters['min_quantity']) && $filters['min_quantity'] !== '',
                fn ($q) => $q->where('quantity', '>=', (int) $filters['min_quantity']))
            ->when(isset($filters['max_quantity']) && $filters['max_quantity'] !== '',
                fn ($q) => $q->where('quantity', '<=', (int) $filters['max_quantity']))
            ->when(! empty($filters['status']), fn ($q) => $q->applyFilters(['status' => $filters['status']]))
            ->when($term, fn ($q) => $q->applyFilters(['search' => $term]))
            ->orderBy('products.'.$sort, $direction);
    }

    /** Purchase orders by reference, supplier, status, user and date range. */
    public function purchaseOrders(array $filters = [])
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'createdBy', 'approvedBy'])
            ->search($filters['search'] ?? null)
            ->byStatus($filters['status'] ?? null)
            ->bySupplier(! empty($filters['supplier_id']) ? (int) $filters['supplier_id'] : null)
            ->forUser(! empty($filters['user_id']) ? (int) $filters['user_id'] : null)
            ->betweenDates($filters['from'] ?? null, $filters['to'] ?? null)
            ->orderByDesc('order_date')
            ->orderByDesc('id');
    }

    /** Inventory transactions by product, type, user, reference and dates. */
    public function transactions(array $filters = [])
    {
        $term = $filters['search'] ?? null;

        return InventoryTransaction::query()
            ->with(['product', 'user'])
            ->when(! empty($filters['product_id']), fn ($q) => $q->forProduct((int) $filters['product_id']))
            ->when(! empty($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when(empty($filters['include_reversed']), fn ($q) => $q->notReversed())
            ->betweenDates($filters['from'] ?? null, $filters['to'] ?? null)
            ->when($term, function ($query) use ($term) {
                $term = '%'.strip_tags((string) $term).'%';

                $query->where(function ($q) use ($term) {
                    $q->where('reference_number', 'like', $term)
                        ->orWhere('notes', 'like', $term)
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $term)
                            ->orWhere('sku', 'like', $term));
                });
            })
            ->latest()
            ->orderByDesc('id');
    }
}
