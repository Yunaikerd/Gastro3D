<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'menu_categories';

    protected $fillable = [
        'establishment_id',
        'name',
        'slug',
        'description',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Parent establishment relation.
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class, 'establishment_id');
    }

    /**
     * Products belonging to this category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(MenuProduct::class, 'category_id')->orderBy('sort_order', 'asc');
    }

    /**
     * Available products belonging to this category for public display.
     */
    public function availableProducts(): HasMany
    {
        return $this->hasMany(MenuProduct::class, 'category_id')
            ->where('is_available', true)
            ->orderBy('sort_order', 'asc');
    }

    /**
     * Scope for active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for filtering by establishment.
     */
    public function scopeForEstablishment($query, string $establishmentId)
    {
        return $query->where('establishment_id', $establishmentId);
    }
}
