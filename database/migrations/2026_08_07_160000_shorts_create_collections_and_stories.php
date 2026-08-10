<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editorial collections (B7) + ephemeral stories (B8).
 *
 * Stories reuse `shorts` rather than getting their own table: a story IS a short
 * with an expiry, and duplicating the model would mean duplicating playback,
 * telemetry and moderation with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('short_collections')) {
            Schema::create('short_collections', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('cover_path')->nullable();
                // null = global/editorial, visible on every marketplace.
                $table->foreignId('marketplace_client_id')->nullable()->index();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort']);
            });
        }

        if (! Schema::hasTable('short_collection_items')) {
            Schema::create('short_collection_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('short_collection_id')->constrained()->cascadeOnDelete();
                $table->foreignId('short_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('sort')->default(0);

                $table->unique(['short_collection_id', 'short_id'], 'short_collection_items_unique');
                $table->index(['short_collection_id', 'sort']);
            });
        }

        if (Schema::hasTable('shorts') && ! Schema::hasColumn('shorts', 'is_story')) {
            Schema::table('shorts', function (Blueprint $table) {
                $table->boolean('is_story')->default(false);
                // The stories tray reads exactly this pair.
                $table->index(['is_story', 'expires_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('short_collection_items');
        Schema::dropIfExists('short_collections');

        if (Schema::hasTable('shorts') && Schema::hasColumn('shorts', 'is_story')) {
            Schema::table('shorts', function (Blueprint $table) {
                $table->dropIndex(['is_story', 'expires_at']);
                $table->dropColumn('is_story');
            });
        }
    }
};
