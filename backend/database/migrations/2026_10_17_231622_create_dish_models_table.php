<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dish_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title')->nullable();
            $table->text('prompt')->nullable();
            
            // Image fields
            $table->string('original_filename')->nullable();
            $table->string('image_path');
            $table->string('image_url');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            
            // Tripo AI fields
            $table->string('tripo_file_token')->nullable();
            $table->string('tripo_task_id')->nullable()->index();
            $table->string('model_version')->default('v2.5-20250123');
            
            // Status & progress tracking
            $table->string('status')->default('pending')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            
            // Generated 3D assets
            $table->string('glb_path')->nullable();
            $table->string('glb_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            
            // Errors & metadata
            $table->text('error_message')->nullable();
            $table->jsonb('meta')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dish_models');
    }
};
