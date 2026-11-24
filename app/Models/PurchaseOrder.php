<?php

namespace App\Models;

use App\Services\SupplierPerformanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SENT = 'sent';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    /** Simplified VAT used by the Lab 7 order totals. */
    public const TAX_RATE = 0.12;

    /** Orders at/above this total need a second (admin) approval. */
    public const HIGH_VALUE_THRESHOLD = 50000;

    protected $fillable = [
        'reference_number',
        'supplier_id',
        'status',
        'order_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'subtotal',
        'tax',
        'total',
        'notes',
        'terms',
        'user_id',
        'approved_by',
        'approved_at',
        'sent_at',
        'received_at',
        'rejection_reason',
        'auto_generated',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'auto_generated' => 'boolean',
    ];

    /**
     * Keep supplier dashboards fresh whenever an order is created, approved,
     * received, cancelled, soft-deleted or restored.
     */
    protected static function booted(): void
    {
        $clearPerformanceCache = function (self $order): void {
            $service = app(SupplierPerformanceService::class);
            $service->clearCache((int) $order->supplier_id);

            $previousSupplierId = $order->getOriginal('supplier_id');
            if ($previousSupplierId && (int) $previousSupplierId !== (int) $order->supplier_id) {
                $service->clearCache((int) $previousSupplierId);
            }
        };

        static::saved($clearPerformanceCache);
        static::deleted($clearPerformanceCache);
        static::restored($clearPerformanceCache);
    }

    public function supplier(): BelongsTo
    {
        // Historical orders must still resolve their supplier after archival.
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Polymorphic approval history. */
    public function approvals(): MorphMany
    {
        return $this->morphMany(PurchaseOrderApproval::class, 'subject')->latest();
    }

    /** Polymorphic activity log entries for this order. */
    public function activities(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /** Orders that still require stock movement: not received or cancelled. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_RECEIVED, self::STATUS_CANCELLED, self::STATUS_REJECTED]);
    }

    public function scopeByStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, fn (Builder $q) => $q->where('status', $status));
    }

    public function scopeBySupplier(Builder $query, ?int $supplierId): Builder
    {
        return $query->when($supplierId, fn (Builder $q) => $q->where('supplier_id', $supplierId));
    }

    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        return $query->when($userId, fn (Builder $q) => $q->where('user_id', $userId));
    }

    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->whereDate('order_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('order_date', '<=', $to));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || $term === '') {
            return $query;
        }

        $term = '%'.strip_tags($term).'%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('reference_number', 'like', $term)
                ->orWhere('notes', 'like', $term)
                ->orWhereHas('supplier', fn (Builder $s) => $s->where('company_name', 'like', $term));
        });
    }

    /** Recalculate subtotal/tax/total from the persisted line items. */
    public function calculateTotal(): float
    {
        $subtotal = (float) $this->items()->sum(\DB::raw('quantity * unit_price'));
        $tax = round($subtotal * self::TAX_RATE, 2);
        $total = round($subtotal + $tax, 2);

        if ($this->exists) {
            $this->updateQuietly([
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
            ]);
        }

        return $total;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeReceived(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_SENT], true)
            && $this->items()->whereColumn('received_quantity', '<', 'quantity')->exists();
    }

    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_SENT], true);
    }

    public function addApproval(string $action, ?string $note = null, ?User $user = null): void
    {
        $this->approvals()->create([
            'user_id' => ($user ?? auth()->user())?->id,
            'action' => $action,
            'note' => $note,
        ]);
    }

    /** PO-2026-0007 style unique reference number. */
    public static function generateReferenceNumber(): string
    {
        $prefix = 'PO-'.date('Y').'-';
        $next = (int) static::withTrashed()->where('reference_number', 'like', $prefix.'%')->count() + 1;

        do {
            $number = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (static::withTrashed()->where('reference_number', $number)->exists());

        return $number;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_SENT => 'Sent to Supplier',
            self::STATUS_RECEIVED => 'Received',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }

    /** Bootstrap 5 badge colour for the current workflow state. */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'bg-secondary',
            self::STATUS_PENDING => 'bg-warning text-dark',
            self::STATUS_APPROVED => 'bg-success',
            self::STATUS_SENT => 'bg-info text-dark',
            self::STATUS_RECEIVED => 'bg-primary',
            self::STATUS_REJECTED, self::STATUS_CANCELLED => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public function getRequiresSecondApprovalAttribute(): bool
    {
        return (float) $this->total >= self::HIGH_VALUE_THRESHOLD;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->expected_delivery_date
            && $this->expected_delivery_date->isPast()
            && ! in_array($this->status, [self::STATUS_RECEIVED, self::STATUS_CANCELLED, self::STATUS_REJECTED], true);
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_SENT => 'Sent to Supplier',
            self::STATUS_RECEIVED => 'Received',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }
}
