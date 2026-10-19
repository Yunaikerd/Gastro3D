<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuProductResource extends JsonResource
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
            'model_glb_url' => ($this->has_3d && $this->dish_model_id) ? $this->model_glb_url : null,
            'dietary_tags' => $this->dietary_tags ?? [],
            'allergens' => $this->allergens ?? [],
            'prep_time_minutes' => $this->prep_time_minutes,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
