<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Rename table music_genres -> artist_genres (safe in Postgres: FKs retarget automatically)
        if (Schema::hasTable('music_genres') && ! Schema::hasTable('artist_genres')) {
            Schema::rename('music_genres', 'artist_genres');
        }

        // 2) Ensure base tables exist
        foreach (['artists', 'artist_genres'] as $t) {
            if (! Schema::hasTable($t)) {
                throw new RuntimeException("Missing required table [{$t}]. Run base migrations first.");
            }
        }

        // 3) Create new pivot artist_artist_genre
        if (! Schema::hasTable('artist_artist_genre')) {
            Schema::create('artist_artist_genre', function (Blueprint $table) {
                $table->unsignedBigInteger('artist_id');
                $table->unsignedBigInteger('artist_genre_id');

                $table->primary(['artist_id', 'artist_genre_id'], 'artist_artist_genre_pk');

                $table->foreign('artist_id')->references('id')->on('artists')->cascadeOnDelete();
                $table->foreign('artist_genre_id')->references('id')->on('artist_genres')->cascadeOnDelete();

                $table->index(['artist_genre_id', 'artist_id'], 'artist_genre_idx');
            });
        }

        // 4) Migrate old pivot data: artist_music_genre(artist_id, music_genre_id) -> artist_artist_genre(artist_id, artist_genre_id)
        if (Schema::hasTable('artist_music_genre')) {
            $eventCol = Schema::hasColumn('artist_music_genre', 'artist_id') ? 'artist_id' : null;
            $mgCol    = Schema::hasColumn('artist_music_genre', 'music_genre_id') ? 'music_genre_id' : null;

            if ($eventCol && $mgCol) {
                DB::table('artist_music_genre')
                    ->select([$eventCol . ' as a', $mgCol . ' as g'])
                    ->whereNotNull($eventCol)
                    ->whereNotNull($mgCol)
                    ->distinct()
                    ->orderBy('a')
                    ->chunk(1000, function ($rows) {
                        $batch = [];
                        foreach ($rows as $r) {
                            $batch[] = ['artist_id' => (int) $r->a, 'artist_genre_id' => (int) $r->g];
                        }
                        foreach (array_chunk($batch, 200) as $chunk) {
                            try {
                                DB::table('artist_artist_genre')->insert($chunk);
                            } catch (\Throwable $e) {
                                foreach ($chunk as $row) {
                                    try { DB::table('artist_artist_genre')->insert($row); } catch (\Throwable $ignore) {}
                                }
                            }
                        }
                    });
            }

            // Drop old pivot last
            Schema::drop('artist_music_genre');
        }
    }

    public function down(): void
    {
        // Recreate old pivot empty
        if (! Schema::hasTable('artist_music_genre')) {
            Schema::create('artist_music_genre', function (Blueprint $table) {
                $table->unsignedBigInteger('artist_id');
                $table->unsignedBigInteger('music_genre_id');
                $table->primary(['artist_id', 'music_genre_id']);
            });
        }

        if (Schema::hasTable('artist_artist_genre')) {
            Schema::drop('artist_artist_genre');
        }

        if (Schema::hasTable('artist_genres') && ! Schema::hasTable('music_genres')) {
            Schema::rename('artist_genres', 'music_genres');
        }
    }
};
