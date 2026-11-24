<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * Mass assignable attributes (Model layer - Lab 5).
     */
    protected $fillable = [
        'name',
        'sku',
        'description',
        'brand_id',
        'category_id',
        'quantity',
        'reorder_level',
        'unit_price',
        'supplier',
        'supplier_id',
        'package_size',
        'daily_usage_override',
        'safety_stock_override',
        'is_active',
        'low_stock_notified_at',
        'stockout_notified_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reorder_level' => 'integer',
        'unit_price' => 'decimal:2',
        'supplier_id' => 'integer',
        'package_size' => 'integer',
        'daily_usage_override' => 'decimal:2',
        'safety_stock_override' => 'integer',
        'is_active' => 'boolean',
        'low_stock_notified_at' => 'datetime',
        'stockout_notified_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Lab 7: structured supplier relationship. Named `supplierRecord` because
     * the legacy `supplier` text column would otherwise shadow the relation
     * when accessing `$product->supplier`.
     */
    public function supplierRecord(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id')->withTrashed();
    }

    /** Backwards-compatible alias for relationship queries such as whereHas. */
    public function supplier(): BelongsTo
    {
        return $this->supplierRecord();
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * One product has many stock movement records.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class)->latest();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Lab 6: the newest stock movement for this product. */
    public function latestTransaction(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(InventoryTransaction::class)->latest();
    }

    public function scopeActive($query)
    {
        // Qualified so the scope also works when joined with brands/categories.
        return $query->where('products.is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('products.is_active', false);
    }

    public function scopeLowStock($query)
    {
        return $query->active()
            ->where('quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'reorder_level');
    }

    public function scopeOutOfStock($query)
    {
        return $query->active()->where('quantity', 0);
    }

    /** Lab 6: filter by category id or category name. */
    public function scopeInCategory($query, $category)
    {
        if (is_numeric($category)) {
            return $query->where('category_id', (int) $category);
        }

        return $query->whereHas('category', function ($q) use ($category) {
            $q->where('name', 'like', '%'.strip_tags((string) $category).'%');
        });
    }

    /**
     * Shared search/filter logic for Products, Inventory and CSV exports.
     */
    public function scopeApplyFilters($query, array $filters)
    {
        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['status'])) {
            match ($filters['status']) {
                'active' => $query->active(),
                'inactive', 'unavailable' => $query->inactive(),
                'low' => $query->lowStock(),
                'out' => $query->outOfStock(),
                'in' => $query->active()->whereColumn('quantity', '>', 'reorder_level'),
                default => null,
            };
        }

        if (isset($filters['min_quantity']) && $filters['min_quantity'] !== '') {
            $query->where('quantity', '>=', (int) $filters['min_quantity']);
        }

        if (isset($filters['max_quantity']) && $filters['max_quantity'] !== '') {
            $query->where('quantity', '<=', (int) $filters['max_quantity']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.strip_tags((string) $filters['search']).'%';

            $query->where(function ($q) use ($term) {
                $q->where('products.name', 'like', $term)
                    ->orWhere('products.sku', 'like', $term)
                    ->orWhere('products.supplier', 'like', $term)
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $term))
                    ->orWhereHas('supplier', fn ($s) => $s->where('company_name', 'like', $term));
            });
        }

        return $query->orderBy('id');
    }

    /**
     * Business rule: at or below the reorder level means the item needs restocking.
     */
    public function isLowStock(): bool
    {
        return $this->quantity <= $this->reorder_level;
    }

    public function isOutOfStock(): bool
    {
        return (int) $this->quantity <= 0;
    }

    public function getStockStatusAttribute(): string
    {
        if (! $this->is_active) {
            // Auto-set by the observer when an outbound movement empties stock.
            return 'Unavailable';
        }

        if ($this->isOutOfStock()) {
            return 'Out of Stock';
        }

        return $this->isLowStock() ? 'Low Stock' : 'In Stock';
    }

    /** Bootstrap contextual color used by badges and dashboard accents. */
    public function getStockStatusColorAttribute(): string
    {
        if (! $this->is_active) {
            return 'secondary';
        }

        if ($this->isOutOfStock()) {
            return 'danger';
        }

        return $this->isLowStock() ? 'warning' : 'success';
    }

    public function getStockValueAttribute(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }

    public static function statusOptions(): array
    {
        return ['in', 'low', 'out', 'active', 'inactive', 'unavailable'];
    }
}
