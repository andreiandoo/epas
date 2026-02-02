<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: Using short constraint/index names to avoid MySQL's 64 character limit
     */
    public function up(): void
    {
        if (Schema::hasTable('marketplace_venue_categories')) {
            return;
        }

        Schema::create('marketplace_venue_categories', function (Blueprint $table) {
            $table->id();

            // Foreign key with short constraint name
            $table->unsignedBigInteger('marketplace_client_id');
            $table->foreign('marketplace_client_id', 'mvc_client_fk')
                ->references('id')
                ->on('marketplace_clients')
                ->cascadeOnDelete();

            $table->json('name'); // Translatable: {en: "...", ro: "..."}
            $table->string('slug', 191)->unique('mvc_slug_unique');
            $table->json('description')->nullable(); // Translatable
            $table->string('icon')->nullable(); // Icon class or emoji
            $table->string('color', 7)->nullable(); // Hex color
            $table->string('image_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            $table->timestamps();

            $table->index(['marketplace_client_id', 'is_active'], 'mvc_client_active_idx');
            $table->index(['marketplace_client_id', 'sort_order'], 'mvc_client_sort_idx');
        });

        // Pivot table for venue-category relationships
        if (Schema::hasTable('marketplace_venue_category_venue')) {
            return;
        }

        Schema::create('marketplace_venue_category_venue', function (Blueprint $table) {
            $table->id();

            // Foreign keys with short constraint names
            $table->unsignedBigInteger('marketplace_venue_category_id');
            $table->foreign('marketplace_venue_category_id', 'mvcv_category_fk')
                ->references('id')
                ->on('marketplace_venue_categories')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('venue_id');
            $table->foreign('venue_id', 'mvcv_venue_fk')
                ->references('id')
                ->on('venues')
                ->cascadeOnDelete();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['marketplace_venue_category_id', 'venue_id'], 'mvcv_unique');
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
