<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Establishment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'establishments';

    protected $fillable = [
        'name',
        'slug',
        'business_type',
        'tagline',
        'description',
        'logo_url',
        'cover_url',
        'contact_whatsapp',
        'contact_email',
        'contact_phone',
        'address',
        'city',
        'country',
        'currency',
        'theme_config',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'theme_config' => 'array',
    ];

    /**
     * All categories configured for this establishment.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(MenuCategory::class, 'establishment_id')->orderBy('sort_order', 'asc');
    }

    /**
     * Active categories visible in the public menu.
     */
    public function activeCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class, 'establishment_id')
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc');
    }

    /**
     * All products registered for this establishment.
     */
    public function products(): HasMany
    {
        return $this->hasMany(MenuProduct::class, 'establishment_id')->orderBy('sort_order', 'asc');
    }

    /**
     * Available products displayed in the public menu.
     */
    public function availableProducts(): HasMany
    {
        return $this->hasMany(MenuProduct::class, 'establishment_id')
            ->where('is_available', true)
            ->orderBy('sort_order', 'asc');
    }

    /**
     * Scope for active establishments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for querying establishment by URL slug.
     */
    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', strtolower(trim($slug)));
    }
}
