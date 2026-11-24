<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Polymorphic workflow history (subject = PurchaseOrder). */
class PurchaseOrderApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'user_id',
        'action',
        'note',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'submitted' => 'Submitted for approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'sent' => 'Sent to supplier',
            'received' => 'Goods received',
            'cancelled' => 'Cancelled',
            default => ucfirst((string) $this->action),
        };
    }

    public function getBadgeClassAttribute(): string
    {
        return match ($this->action) {
            'approved', 'received' => 'bg-success',
            'rejected', 'cancelled' => 'bg-danger',
            'sent' => 'bg-info text-dark',
            default => 'bg-secondary',
        };
    }
}
