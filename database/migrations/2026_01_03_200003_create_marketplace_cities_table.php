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
        Schema::create('marketplace_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()
                ->constrained('marketplace_regions')->nullOnDelete();
            $table->json('name'); // Translatable
            $table->string('slug');
            $table->json('description')->nullable(); // Translatable
            $table->string('country', 2)->default('RO'); // ISO country code
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone')->nullable();
            $table->string('image_url')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->string('icon')->nullable();
            $table->integer('population')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_capital')->default(false); // Capital of region
            $table->integer('venue_count')->default(0);
            $table->integer('event_count')->default(0);
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'slug']);
            $table->index(['marketplace_client_id', 'region_id']);
            $table->index(['marketplace_client_id', 'is_visible', 'sort_order']);
            $table->index(['marketplace_client_id', 'is_featured']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_cities');
    }
};
