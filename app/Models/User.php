<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The seeded Lab 4 account must stay active so ADMIN / ADMIN123 always works.
     */
    public const PROTECTED_ADMIN_EMAIL = 'jimpadillo@gmail.com';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'avatar',
    ];

    /**
     * Defaults also live on the model so factory-made / in-memory users are
     * active instead of relying only on the database column default.
     */
    protected $attributes = [
        'role' => 'staff',
        'is_active' => true,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' && $this->is_active;
    }

    /** Lab 7: managers are the first approval level for purchase orders. */
    public function isManager(): bool
    {
        return $this->role === 'manager' && $this->is_active;
    }

    public function isWarehouse(): bool
    {
        return $this->role === 'warehouse' && $this->is_active;
    }

    public function canApprovePurchaseOrders(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    public function canReceivePurchaseOrders(): bool
    {
        return $this->isAdmin() || $this->isWarehouse();
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'warehouse' => 'Warehouse Staff',
            default => 'Staff',
        };
    }

    public function createdPurchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'user_id');
    }

    public function approvedPurchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'approved_by');
    }

    public function purchaseOrderApprovals()
    {
        return $this->hasMany(PurchaseOrderApproval::class);
    }

    public static function roleOptions(): array
    {
        return [
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'warehouse' => 'Warehouse Staff',
            'staff' => 'Staff',
        ];
    }

    /**
     * This account is intentionally protected from deactivation/deletion.
     */
    public function isProtectedAdmin(): bool
    {
        return strtolower((string) $this->email) === self::PROTECTED_ADMIN_EMAIL;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function activities()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /** URL used by the profile picture; null means show the initials fallback. */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? \Illuminate\Support\Facades\Storage::url($this->avatar) : null;
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return strtoupper(implode('', array_map(
            fn (string $part) => $part[0] ?? '',
            array_slice($parts, 0, 2)
        )));
    }
}
