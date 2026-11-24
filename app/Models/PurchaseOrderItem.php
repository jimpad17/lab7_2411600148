<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'unit_price',
        'received_quantity',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'received_quantity' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getLineTotalAttribute(): float
    {
        return round((int) $this->quantity * (float) $this->unit_price, 2);
    }

    public function getOutstandingQuantityAttribute(): int
    {
        return max(0, (int) $this->quantity - (int) $this->received_quantity);
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->outstanding_quantity === 0;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        if ($this->is_fully_received) {
            return 'bg-success';
        }

        return (int) $this->received_quantity > 0 ? 'bg-warning text-dark' : 'bg-secondary';
    }
}
