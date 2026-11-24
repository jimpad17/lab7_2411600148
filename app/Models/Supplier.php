<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_name',
        'contact_person',
        'email',
        'phone',
        'address',
        'payment_terms',
        'lead_time_days',
        'minimum_order_amount',
        'is_active',
    ];

    protected $casts = [
        'lead_time_days' => 'integer',
        'minimum_order_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /** Lab 6 products now point to a real supplier record. */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** Many-to-many: categories this supplier is approved to supply. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'supplier_categories')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('suppliers.is_active', true);
    }

    /** Suppliers that can deliver within the given number of days. */
    public function scopeWithLeadTime(Builder $query, int $days): Builder
    {
        return $query->where('lead_time_days', '<=', $days);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || $term === '') {
            return $query;
        }

        $term = '%'.strip_tags($term).'%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('company_name', 'like', $term)
                ->orWhere('contact_person', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('phone', 'like', $term);
        });
    }

    public function getFullContactAttribute(): string
    {
        $parts = array_filter([
            $this->contact_person,
            $this->email,
            $this->phone,
        ]);

        return implode(' · ', $parts) ?: 'No contact details';
    }

    /** On-time delivery percentage used by supplier performance widgets. */
    public function getPerformanceRatingAttribute(): float
    {
        $metrics = app(\App\Services\SupplierPerformanceService::class)->metrics($this);

        return (float) $metrics['on_time_rate'];
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return $this->is_active ? 'badge-in' : 'badge-out';
    }

    public static function paymentTermsOptions(): array
    {
        return ['COD', 'Net 15', 'Net 30', 'Net 45', 'Net 60'];
    }
}
