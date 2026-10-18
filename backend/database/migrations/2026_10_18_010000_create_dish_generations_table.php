<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('dish_generations')) {
            Schema::create('dish_generations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('dish_model_id');
                $table->unsignedInteger('version_number')->default(1);
                
                // Generation configuration
                $table->string('mode')->default('standard'); // 'standard' (1 photo) or 'multiview' (2-4 photos)
                $table->string('model_version')->default('v3.1-20260211');
                $table->string('texture_quality')->default('standard'); // 'standard', 'detailed'
                $table->string('texture_alignment')->default('original_image');
                $table->boolean('pbr')->default(true);
                
                // Views photographed (front, left, back, right)
                $table->jsonb('views')->nullable();
                
                // Tripo V3 Task Tracking
                $table->string('tripo_task_id')->nullable()->index();
                $table->string('status')->default('pending')->index();
                $table->unsignedTinyInteger('progress')->default(0);
                
                // 3D Asset output
                $table->string('glb_path')->nullable();
                $table->string('glb_url')->nullable();
                $table->string('thumbnail_url')->nullable();
                $table->unsignedBigInteger('file_size_bytes')->nullable();
                $table->unsignedInteger('credits_consumed')->default(0);
                
                // Status, errors and metadata
                $table->text('error_message')->nullable();
                $table->jsonb('meta')->nullable();
                
                // Active version indicator
                $table->boolean('is_active')->default(false);
                
                $table->timestamps();

                // Foreign key constraint
                $table->foreign('dish_model_id')
                    ->references('id')
                    ->on('dish_models')
                    ->onDelete('cascade');

                // Indexes
                $table->unique(['dish_model_id', 'version_number']);
                $table->index(['dish_model_id', 'is_active']);
            });
        }

        // Migrate existing dishes to version 1 in dish_generations idempotently
        $existingDishes = DB::table('dish_models')->get();
        foreach ($existingDishes as $dish) {
            $alreadyExists = DB::table('dish_generations')
                ->where('dish_model_id', $dish->id)
                ->where('version_number', 1)
                ->exists();

            if (!$alreadyExists) {
                $meta = json_decode($dish->meta ?? '{}', true) ?: [];
                $credits = $meta['credits_consumed'] ?? ($dish->status === 'success' ? 30 : 0);

                DB::table('dish_generations')->insert([
                    'id' => (string) Str::uuid(),
                    'dish_model_id' => $dish->id,
                    'version_number' => 1,
                    'mode' => 'standard',
                    'model_version' => $dish->model_version ?: 'v3.1-20260211',
                    'texture_quality' => 'standard',
                    'texture_alignment' => 'original_image',
                    'pbr' => true,
                    'views' => json_encode([
                        [
                            'angle' => 'front',
                            'image_path' => $dish->image_path,
                            'image_url' => $dish->image_url,
                            'file_token' => $dish->tripo_file_token,
                            'original_filename' => $dish->original_filename,
                        ]
                    ]),
                    'tripo_task_id' => $dish->tripo_task_id,
                    'status' => $dish->status,
                    'progress' => $dish->progress,
                    'glb_path' => $dish->glb_path,
                    'glb_url' => $dish->glb_url,
                    'thumbnail_url' => $dish->thumbnail_url,
                    'file_size_bytes' => $dish->file_size_bytes,
                    'credits_consumed' => $credits,
                    'error_message' => $dish->error_message,
                    'meta' => $dish->meta,
                    'is_active' => true,
                    'created_at' => $dish->created_at ?: now(),
                    'updated_at' => $dish->updated_at ?: now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dish_generations');
    }
};
