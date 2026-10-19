<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class MenuProduct extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'menu_products';

    protected $fillable = [
        'establishment_id',
        'category_id',
        'dish_model_id',
        'name',
        'slug',
        'description',
        'price',
        'photo_url',
        'is_featured',
        'is_available',
        'has_3d',
        'ar_enabled',
        'dietary_tags',
        'allergens',
        'prep_time_minutes',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'price' => 'float',
        'is_featured' => 'boolean',
        'is_available' => 'boolean',
        'has_3d' => 'boolean',
        'ar_enabled' => 'boolean',
        'dietary_tags' => 'array',
        'allergens' => 'array',
        'prep_time_minutes' => 'integer',
        'sort_order' => 'integer',
        'meta' => 'array',
    ];

    protected $appends = [
        'model_glb_url',
    ];

    /**
     * Booted lifecycle events to guarantee multi-tenant integrity.
     */
    protected static function booted(): void
    {
        static::saving(function (MenuProduct $product) {
            // 1. Multi-Tenant Invariant: Category must belong to the exact same establishment
            if ($product->category_id && $product->establishment_id) {
                $category = MenuCategory::find($product->category_id);
                if ($category && $category->establishment_id !== $product->establishment_id) {
                    throw new InvalidArgumentException(
                        "Violación de aislamiento multi-tenant: La categoría [{$product->category_id}] pertenece al establecimiento [{$category->establishment_id}], incompatible con el establecimiento [{$product->establishment_id}]."
                    );
                }
            }

            // 2. Automatically sync has_3d state based on dish_model_id
            if (!empty($product->dish_model_id)) {
                $product->has_3d = true;
            } else {
                $product->has_3d = false;
                $product->ar_enabled = false;
            }

            // 3. Stable slug generation & collision avoidance within establishment
            if (empty($product->attributes['slug']) && !empty($product->name)) {
                $baseSlug = \Illuminate\Support\Str::slug($product->name);
                $slugCandidate = $baseSlug;
                $counter = 1;

                while (
                    static::where('establishment_id', $product->establishment_id)
                        ->where('slug', $slugCandidate)
                        ->when(!empty($product->id), function ($query) use ($product) {
                            $query->where('id', '!=', $product->id);
                        })
                        ->exists()
                ) {
                    $counter++;
                    $slugCandidate = "{$baseSlug}-{$counter}";
                }

                $product->slug = $slugCandidate;
            }
        });
    }

    /**
     * Parent establishment relation.
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class, 'establishment_id');
    }

    /**
     * Assigned category relation.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    /**
     * Optional 3D Model relation from Gastro3D Studio.
     */
    public function dishModel(): BelongsTo
    {
        return $this->belongsTo(DishModel::class, 'dish_model_id');
    }

    /**
     * Accessor to seamlessly retrieve the GLB URL from the Studio dish model without copying files.
     */
    public function getModelGlbUrlAttribute(): ?string
    {
        if ($this->dishModel && $this->dishModel->glb_url) {
            return $this->dishModel->glb_url;
        }

        return null;
    }

    /**
     * Scope for available products.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope for featured products.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for products with verified 3D models.
     */
    public function scopeWith3d($query)
    {
        return $query->where('has_3d', true)->whereNotNull('dish_model_id');
    }

    /**
     * Scope for filtering by establishment.
     */
    public function scopeForEstablishment($query, string $establishmentId)
    {
        return $query->where('establishment_id', $establishmentId);
    }
}
