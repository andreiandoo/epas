<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // artist ↔ artist_type
        if (! Schema::hasTable('artist_artist_type')) {
            Schema::create('artist_artist_type', function (Blueprint $table) {
                $table->id();
                $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
                $table->foreignId('artist_type_id')->constrained('artist_types')->cascadeOnDelete();
                $table->unique(['artist_id','artist_type_id'], 'artist_type_unique');
            });
        }

        // artist ↔ artist_genre
        if (! Schema::hasTable('artist_artist_genre')) {
            Schema::create('artist_artist_genre', function (Blueprint $table) {
                $table->id();
                $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
                $table->foreignId('artist_genre_id')->constrained('artist_genres')->cascadeOnDelete();
                $table->unique(['artist_id','artist_genre_id'], 'artist_genre_unique');
            });
        }

        // (opțional) migrare dintr-un pivot vechi, dacă există: artist_music_genre
        if (Schema::hasTable('artist_music_genre')) {
            try {
                $rows = DB::table('artist_music_genre')->select('artist_id','music_genre_id')->get();
                foreach ($rows as $r) {
                    // presupunem că ai redenumit music_genres → artist_genres păstrând ID-urile;
                    // dacă nu, comentează block-ul acesta.
                    DB::table('artist_artist_genre')->updateOrInsert(
                        ['artist_id' => $r->artist_id, 'artist_genre_id' => $r->music_genre_id],
                        []
                    );
                }
            } catch (\Throwable $e) {
                // ignorăm dacă nu se pot copia rândurile
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_artist_type');
        Schema::dropIfExists('artist_artist_genre');
    }
};
