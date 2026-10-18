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
        Schema::create('menu_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('establishment_id')
                ->constrained('establishments')
                ->onDelete('cascade');
            $table->foreignUuid('category_id')
                ->constrained('menu_categories')
                ->onDelete('cascade');
            $table->foreignUuid('dish_model_id')
                ->nullable()
                ->constrained('dish_models')
                ->nullOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->text('photo_url')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available')->default(true);
            $table->boolean('has_3d')->default(false);
            $table->boolean('ar_enabled')->default(false);
            $table->json('dietary_tags')->nullable();
            $table->json('allergens')->nullable();
            $table->integer('prep_time_minutes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['establishment_id', 'category_id', 'is_available', 'sort_order'], 'idx_menu_prod_est_cat_avail_sort');
            $table->index(['dish_model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_products');
    }
};
