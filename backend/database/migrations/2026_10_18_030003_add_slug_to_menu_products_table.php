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
     * Adds an indexed, establishment-scoped unique slug column to menu_products
     * ensuring URL permanence upon dish renaming and preventing collisions within the same tenant.
     */
    public function up(): void
    {
        Schema::table('menu_products', function (Blueprint $table) {
            $table->string('slug', 150)->nullable()->after('name');
        });

        // Populate existing products safely using meta->slug or fallback to slugified name
        $existingProducts = DB::table('menu_products')->get();
        foreach ($existingProducts as $product) {
            $meta = json_decode($product->meta ?? '{}', true) ?: [];
            $assignedSlug = $meta['slug'] ?? Str::slug($product->name ?? 'producto');

            // Ensure uniqueness among existing rows during migration
            $count = DB::table('menu_products')
                ->where('establishment_id', $product->establishment_id)
                ->where('slug', $assignedSlug)
                ->where('id', '!=', $product->id)
                ->count();

            if ($count > 0) {
                $assignedSlug .= '-' . substr($product->id, 0, 8);
            }

            DB::table('menu_products')
                ->where('id', $product->id)
                ->update(['slug' => $assignedSlug]);
        }

        // Apply NOT NULL constraint and composite unique index
        Schema::table('menu_products', function (Blueprint $table) {
            $table->string('slug', 150)->nullable(false)->change();
            $table->unique(['establishment_id', 'slug'], 'uniq_menu_products_est_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_products', function (Blueprint $table) {
            $table->dropUnique('uniq_menu_products_est_slug');
            $table->dropColumn('slug');
        });
    }
};
