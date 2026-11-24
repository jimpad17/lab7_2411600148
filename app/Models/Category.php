<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function activeProducts(): HasMany
    {
        return $this->products()->where('is_active', true);
    }

    /** Many-to-many suppliers approved for this category. */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_categories')->withTimestamps();
    }

    public function getActiveProductsCountAttribute(): int
    {
        return (int) $this->activeProducts()->count();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
}
