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
}
