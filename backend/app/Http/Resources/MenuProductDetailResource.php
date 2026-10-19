<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuProductDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'photo_url' => $this->photo_url,
            'is_featured' => (bool) $this->is_featured,
            'is_available' => (bool) $this->is_available,
            'has_3d' => (bool) $this->has_3d,
            'ar_enabled' => (bool) $this->ar_enabled,
            'model_3d' => ($this->has_3d && $this->dishModel) ? [
                'glb_url' => $this->dishModel->glb_url,
                'thumbnail_url' => $this->dishModel->thumbnail_url,
            ] : null,
            'dietary_tags' => $this->dietary_tags ?? [],
            'allergens' => $this->allergens ?? [],
            'prep_time_minutes' => $this->prep_time_minutes,
            'sort_order' => (int) $this->sort_order,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null,
            'establishment' => $this->establishment ? [
                'id' => $this->establishment->id,
                'name' => $this->establishment->name,
                'slug' => $this->establishment->slug,
                'currency' => $this->establishment->currency,
                'contact_whatsapp' => $this->establishment->contact_whatsapp,
            ] : null,
        ];
    }
}
