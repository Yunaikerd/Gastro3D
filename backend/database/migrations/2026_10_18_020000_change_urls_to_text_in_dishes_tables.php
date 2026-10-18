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
        Schema::table('dish_models', function (Blueprint $table) {
            $table->text('image_url')->change();
            $table->text('glb_url')->nullable()->change();
            $table->text('thumbnail_url')->nullable()->change();
        });

        Schema::table('dish_generations', function (Blueprint $table) {
            $table->text('glb_url')->nullable()->change();
            $table->text('thumbnail_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dish_models', function (Blueprint $table) {
            $table->string('image_url', 500)->change();
            $table->string('glb_url', 500)->nullable()->change();
            $table->string('thumbnail_url', 500)->nullable()->change();
        });

        Schema::table('dish_generations', function (Blueprint $table) {
            $table->string('glb_url', 500)->nullable()->change();
            $table->string('thumbnail_url', 500)->nullable()->change();
        });
    }
};
