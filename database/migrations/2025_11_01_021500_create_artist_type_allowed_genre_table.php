<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('artist_type_allowed_genre')) {
            Schema::create('artist_type_allowed_genre', function (Blueprint $table) {
                $table->id();

                // IMPORTANT: these must match your actual tables
                $table->foreignId('artist_type_id')
                      ->constrained('artist_types')
                      ->cascadeOnDelete();

                $table->foreignId('artist_genre_id')
                      ->constrained('artist_genres')
                      ->cascadeOnDelete();

                $table->unique(['artist_type_id', 'artist_genre_id'], 'artist_type_genre_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_type_allowed_genre');
    }
};
