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
        if (Schema::hasTable('marketplace_regions')) {
            return; // Table already exists from previous partial migration
        }

        Schema::create('marketplace_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->json('name'); // Translatable
            $table->string('slug');
            $table->json('description')->nullable(); // Translatable
            $table->string('code', 10)->nullable(); // e.g., 'B' for București, 'CJ' for Cluj
            $table->string('country', 2)->default('RO'); // ISO country code
            $table->string('image_url')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 7)->nullable(); // Hex color
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('city_count')->default(0);
            $table->integer('event_count')->default(0);
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'slug'], 'mp_regions_client_slug_unique');
            $table->index(['marketplace_client_id', 'is_visible', 'sort_order'], 'mp_regions_visible_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_regions');
    }
};
