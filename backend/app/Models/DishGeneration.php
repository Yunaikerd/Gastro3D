<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DishGeneration extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'dish_generations';

    protected $fillable = [
        'dish_model_id',
        'version_number',
        'mode',
        'model_version',
        'texture_quality',
        'texture_alignment',
        'pbr',
        'views',
        'tripo_task_id',
        'status',
        'progress',
        'glb_path',
        'glb_url',
        'thumbnail_url',
        'file_size_bytes',
        'credits_consumed',
        'error_message',
        'meta',
        'is_active',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'progress' => 'integer',
        'credits_consumed' => 'integer',
        'file_size_bytes' => 'integer',
        'pbr' => 'boolean',
        'is_active' => 'boolean',
        'views' => 'array',
        'meta' => 'array',
    ];

    /**
     * Parent Dish Model relation.
     */
    public function dishModel(): BelongsTo
    {
        return $this->belongsTo(DishModel::class, 'dish_model_id');
    }

    /**
     * Set this version as active, deactivating all sibling versions for the dish
     * and synchronizing the parent dish_models table with concurrency control and transactions.
     */
    public function activate(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            // Lock parent dish to prevent concurrency race
            $dish = DishModel::where('id', $this->dish_model_id)->lockForUpdate()->first();

            // Lock and deactivate all generations for this dish
            static::where('dish_model_id', $this->dish_model_id)
                ->lockForUpdate()
                ->update(['is_active' => false]);

            $this->update(['is_active' => true]);

            // Sync parent dish_models record for backward compatibility
            if ($dish) {
                $dish->update([
                    'status' => $this->status ?? 'pending',
                    'progress' => (int) ($this->progress ?? 0),
                    'glb_path' => $this->glb_path,
                    'glb_url' => $this->glb_url,
                    'thumbnail_url' => $this->thumbnail_url,
                    'tripo_task_id' => $this->tripo_task_id,
                    'model_version' => $this->model_version ?: ($dish->model_version ?: 'v3.1-20260211'),
                ]);
            }
        });
    }

    /**
     * Record dispatched Tripo task details on this generation.
     */
    public function recordTask(string $taskId, int $estimatedCredits, array $raw = []): void
    {
        $this->update([
            'tripo_task_id' => $taskId,
            'credits_consumed' => $estimatedCredits,
            'status' => 'queued',
            'progress' => 15,
            'meta' => array_merge($this->meta ?? [], ['v3_create_task_response' => $raw]),
        ]);

        if ($this->is_active && $this->dishModel) {
            $this->dishModel->update([
                'tripo_task_id' => $taskId,
                'status' => 'queued',
                'progress' => 15,
            ]);
        }
    }

    /**
     * Update generation progress and status directly from Tripo status payload.
     */
    public function syncFromTripoStatus(array $statusData): void
    {
        $status = $statusData['status'] ?? $this->status;
        $progress = isset($statusData['progress']) ? (int) $statusData['progress'] : $this->progress;
        $output = $statusData['output'] ?? [];
        $modelUrl = $output['model_url'] ?? $output['model'] ?? $this->glb_url;
        $thumbnailUrl = $output['rendered_image_url'] ?? $output['rendered_image'] ?? $this->thumbnail_url;
        $creditsConsumed = $statusData['credits_consumed'] ?? $this->credits_consumed;
        $errorMessage = null;

        if ($status === 'failed') {
            $errorMessage = $statusData['error_message'] ?? 'Generación fallida en Tripo AI.';
        }

        $this->update([
            'status' => $status,
            'progress' => $progress,
            'glb_url' => $modelUrl,
            'thumbnail_url' => $thumbnailUrl,
            'credits_consumed' => $creditsConsumed,
            'error_message' => $errorMessage,
        ]);

        if ($this->is_active && $this->dishModel) {
            $this->dishModel->update([
                'status' => $status,
                'progress' => $progress,
                'glb_url' => $modelUrl,
                'thumbnail_url' => $thumbnailUrl,
                'error_message' => $errorMessage,
            ]);
        }
    }
}
