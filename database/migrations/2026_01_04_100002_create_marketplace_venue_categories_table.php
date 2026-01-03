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
        Schema::create('marketplace_venue_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();

            $table->json('name'); // Translatable: {en: "...", ro: "..."}
            $table->string('slug', 191)->unique();
            $table->json('description')->nullable(); // Translatable
            $table->string('icon')->nullable(); // Icon class or emoji
            $table->string('color', 7)->nullable(); // Hex color
            $table->string('image_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            $table->timestamps();

            $table->index(['marketplace_client_id', 'is_active']);
            $table->index(['marketplace_client_id', 'sort_order']);
        });

        // Pivot table for venue-category relationships
        Schema::create('marketplace_venue_category_venue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_venue_category_id')
                ->constrained('marketplace_venue_categories')
                ->cascadeOnDelete();
            $table->foreignId('venue_id')
                ->constrained('venues')
                ->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['marketplace_venue_category_id', 'venue_id'], 'mvc_venue_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_venue_category_venue');
        Schema::dropIfExists('marketplace_venue_categories');
    }
};
