<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Event <-> EventCategory
        if (! Schema::hasTable('event_event_category')) {
            Schema::create('event_event_category', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->foreignId('event_category_id')->constrained('event_categories')->cascadeOnDelete();
                $table->unique(['event_id', 'event_category_id'], 'event_event_category_unique');
            });
        }

        // Event <-> EventGenre
        if (! Schema::hasTable('event_event_genre')) {
            Schema::create('event_event_genre', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->foreignId('event_genre_id')->constrained('event_genres')->cascadeOnDelete();
                $table->unique(['event_id', 'event_genre_id'], 'event_event_genre_unique');
            });
        }

        // Event <-> MusicGenre
        if (! Schema::hasTable('event_music_genre')) {
            Schema::create('event_music_genre', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->foreignId('music_genre_id')->constrained('music_genres')->cascadeOnDelete();
                $table->unique(['event_id', 'music_genre_id'], 'event_music_genre_unique');
            });
        }

        // Event <-> EventTag
        if (! Schema::hasTable('event_event_tag')) {
            Schema::create('event_event_tag', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->foreignId('event_tag_id')->constrained('event_tags')->cascadeOnDelete();
                $table->unique(['event_id', 'event_tag_id'], 'event_event_tag_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_event_tag');
        Schema::dropIfExists('event_music_genre');
        Schema::dropIfExists('event_event_genre');
        Schema::dropIfExists('event_event_category');
    }
};
