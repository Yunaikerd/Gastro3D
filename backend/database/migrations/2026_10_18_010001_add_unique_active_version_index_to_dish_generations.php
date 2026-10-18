<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQL partial unique index ensures only ONE row per dish_model_id can have is_active = true
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS dish_generations_single_active_idx ON dish_generations (dish_model_id) WHERE is_active = true;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS dish_generations_single_active_idx;');
    }
};
