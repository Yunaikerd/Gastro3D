<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EstablishmentMenuResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $categoriesCollection = $this->relationLoaded('activeCategories')
            ? $this->activeCategories
            : ($this->relationLoaded('categories') ? $this->categories : collect());

        $totalCategories = $categoriesCollection->count();
        $allProducts = $categoriesCollection->flatMap(function ($category) {
            return $category->relationLoaded('availableProducts')
                ? $category->availableProducts
                : ($category->relationLoaded('products') ? $category->products : collect());
        });

        $totalProducts = $allProducts->count();
        $total3dProducts = $allProducts->where('has_3d', true)->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'business_type' => $this->business_type,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'logo_url' => $this->logo_url,
            'cover_url' => $this->cover_url,
            'contact' => [
                'whatsapp' => $this->contact_whatsapp,
                'email' => $this->contact_email,
                'phone' => $this->contact_phone,
                'address' => $this->address,
                'city' => $this->city,
                'country' => $this->country,
            ],
            'currency' => $this->currency,
            'theme_config' => $this->theme_config ?? [
                'primary_color' => '#2C1810',
                'accent_color' => '#C89666',
                'background_color' => '#FAF7F2',
                'card_color' => '#FFFFFF',
            ],
            'summary' => [
                'total_categories' => $totalCategories,
                'total_products' => $totalProducts,
                'total_3d_products' => $total3dProducts,
            ],
            'categories' => MenuCategoryResource::collection($categoriesCollection),
        ];
    }
}
