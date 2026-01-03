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
        Schema::create('marketplace_event_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()
                ->constrained('marketplace_event_categories')->nullOnDelete();
            $table->json('name'); // Translatable
            $table->string('slug');
            $table->json('description')->nullable(); // Translatable
            $table->string('image_url')->nullable();
            $table->string('icon')->nullable(); // heroicon name or custom icon
            $table->string('icon_emoji')->nullable(); // 🎵, 🎭, etc.
            $table->string('color', 7)->nullable(); // Hex color
            $table->json('meta_title')->nullable(); // Translatable
            $table->json('meta_description')->nullable(); // Translatable
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('event_count')->default(0);
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'slug'], 'mp_evt_cats_client_slug_unique');
            $table->index(['marketplace_client_id', 'parent_id'], 'mp_evt_cats_parent_idx');
            $table->index(['marketplace_client_id', 'is_visible', 'sort_order'], 'mp_evt_cats_visible_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_event_categories');
    }
};
