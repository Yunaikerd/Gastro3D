<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DishModel extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'dish_models';

    protected $fillable = [
        'title',
        'prompt',
        'original_filename',
        'image_path',
        'image_url',
        'mime_type',
        'file_size_bytes',
        'tripo_file_token',
        'tripo_task_id',
        'model_version',
        'status',
        'progress',
        'glb_path',
        'glb_url',
        'thumbnail_url',
        'error_message',
        'meta',
    ];

    protected $casts = [
        'progress' => 'integer',
        'file_size_bytes' => 'integer',
        'meta' => 'array',
    ];

    /**
     * All generations / versions associated with this dish.
     */
    public function generations()
    {
        return $this->hasMany(DishGeneration::class, 'dish_model_id')->orderBy('version_number', 'asc');
    }

    /**
     * The currently active generation / version.
     */
    public function activeGeneration()
    {
        return $this->hasOne(DishGeneration::class, 'dish_model_id')->where('is_active', true);
    }

    /**
     * Menu products across all establishments that reference this 3D dish model.
     */
    public function menuProducts()
    {
        return $this->hasMany(MenuProduct::class, 'dish_model_id');
    }

    /**
     * Helper to create a new generation version for this dish with transaction and concurrency control.
     */
    public function createNewVersion(array $attributes): DishGeneration
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($attributes) {
            // Lock parent dish to prevent concurrent version numbering race condition
            $dish = static::where('id', $this->id)->lockForUpdate()->first();

            // Lock existing child generations for update
            $dish->generations()->lockForUpdate()->pluck('id');

            $nextVersion = ((int) $dish->generations()->max('version_number')) + 1;
            $makeActive = $attributes['is_active'] ?? true;
            $modelVersion = $attributes['model_version'] ?? $dish->model_version ?? 'v3.1-20260211';

            if ($makeActive) {
                $dish->generations()->update(['is_active' => false]);
            }

            $generation = $dish->generations()->create(array_merge([
                'status' => 'pending',
                'progress' => 0,
            ], $attributes, [
                'version_number' => $nextVersion,
                'model_version' => $modelVersion,
                'is_active' => $makeActive,
            ]));

            if ($makeActive) {
                $dish->update([
                    'status' => $generation->status ?? 'pending',
                    'progress' => (int) ($generation->progress ?? 0),
                    'glb_path' => $generation->glb_path,
                    'glb_url' => $generation->glb_url,
                    'thumbnail_url' => $generation->thumbnail_url,
                    'tripo_task_id' => $generation->tripo_task_id,
                    'model_version' => $generation->model_version ?? $modelVersion,
                ]);
            }

            return $generation;
        });
    }
}
