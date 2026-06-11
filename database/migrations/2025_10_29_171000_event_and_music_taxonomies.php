<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Event Genres (global)
        if (Schema::hasTable('event_genres')) {
            return;
        }

        Schema::create('event_genres', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190)->unique();
            $table->timestamps();
        });

        // Event <-> EventGenre (many-to-many)
        if (Schema::hasTable('event_genre_event')) {
            return;
        }

        Schema::create('event_genre_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('event_genre_id')->constrained('event_genres')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unique(['event_id','event_genre_id']);
        });

        // Event Categories (global)
        if (Schema::hasTable('event_categories')) {
            return;
        }

        Schema::create('event_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190)->unique();
            $table->timestamps();
        });

        if (Schema::hasTable('category_event')) {
            return;
        }

        Schema::create('category_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('event_category_id')->constrained('event_categories')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unique(['event_id','event_category_id']);
        });

        // Event Tags (per-tenant)
        if (Schema::hasTable('event_tags')) {
            return;
        }

        Schema::create('event_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 190);
            $table->timestamps();
            $table->unique(['tenant_id','name']);
        });

        if (Schema::hasTable('event_event_tag')) {
            return;
        }

        Schema::create('event_event_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('event_tag_id')->constrained('event_tags')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unique(['event_id','event_tag_id']);
        });

        // Music Genres (global) + Artist pivot
        if (Schema::hasTable('music_genres')) {
            return;
        }

        Schema::create('music_genres', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190)->unique();
            $table->timestamps();
        });

        if (Schema::hasTable('artist_music_genre')) {
            return;
        }

        Schema::create('artist_music_genre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('music_genre_id')->constrained('music_genres')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unique(['artist_id','music_genre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_music_genre');
        Schema::dropIfExists('music_genres');
        Schema::dropIfExists('event_event_tag');
        Schema::dropIfExists('event_tags');
        Schema::dropIfExists('category_event');
        Schema::dropIfExists('event_categories');
        Schema::dropIfExists('event_genre_event');
        Schema::dropIfExists('event_genres');
    }
};
