<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * 1) Rename event_categories -> event_types (keep data)
         */
        if (Schema::hasTable('event_categories') && ! Schema::hasTable('event_types')) {
            Schema::rename('event_categories', 'event_types');
        }

        foreach ([
            'events'      => 'id',
            'event_types' => 'id',
            'event_genres'=> 'id',
            // keep music_genres for ARTISTS — do not drop or rename
        ] as $t => $pk) {
            if (! Schema::hasTable($t)) {
                throw new RuntimeException("Missing required table [{$t}]. Run base migrations first.");
            }
        }

        /**
         * 2) Create final pivot: event_event_type
         */
        if (! Schema::hasTable('event_event_type')) {
            Schema::create('event_event_type', function (Blueprint $table) {
                $table->unsignedBigInteger('event_id');
                $table->unsignedBigInteger('event_type_id');

                $table->primary(['event_id', 'event_type_id'], 'event_type_pk');

                $table->foreign('event_id')
                    ->references('id')->on('events')
                    ->cascadeOnDelete();

                $table->foreign('event_type_id')
                    ->references('id')->on('event_types')
                    ->cascadeOnDelete();

                $table->index(['event_type_id', 'event_id'], 'event_type_idx');
            });
        }

        /**
         * 3) Migrate legacy pivot data: event_event_category -> event_event_type
         */
        if (Schema::hasTable('event_event_category')) {
            $eventCol = Schema::hasColumn('event_event_category', 'event_id') ? 'event_id' : null;
            $catCol   = collect(['event_category_id','category_id'])->first(
                fn ($c) => Schema::hasColumn('event_event_category', $c)
            );

            if ($eventCol && $catCol) {
                DB::table('event_event_category')
                    ->select([$eventCol . ' as e', $catCol . ' as t'])
                    ->whereNotNull($eventCol)
                    ->whereNotNull($catCol)
                    ->distinct()
                    ->orderBy('e')
                    ->chunk(1000, function ($rows) {
                        $batch = [];
                        foreach ($rows as $r) {
                            $batch[] = ['event_id' => (int) $r->e, 'event_type_id' => (int) $r->t];
                        }
                        foreach (array_chunk($batch, 200) as $chunk) {
                            try {
                                DB::table('event_event_type')->insert($chunk);
                            } catch (\Throwable $e) {
                                foreach ($chunk as $row) {
                                    try { DB::table('event_event_type')->insert($row); } catch (\Throwable $ignore) {}
                                    }
                            }
                        }
                    });
            }

            Schema::drop('event_event_category');
        }

        /**
         * 4) EVENTS no longer use music genres -> drop ONLY the event<->music pivot
         *    Keep music_genres table for ARTISTS (artist_music_genre stays!)
         */
        if (Schema::hasTable('event_music_genre')) {
            Schema::drop('event_music_genre');
        }

        /**
         * 5) ARTIST TYPES taxonomy (new)
         */
        if (! Schema::hasTable('artist_types')) {
            Schema::create('artist_types', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 190);
                $table->string('slug', 190)->unique();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->foreign('parent_id')
                    ->references('id')->on('artist_types')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('artist_artist_type')) {
            Schema::create('artist_artist_type', function (Blueprint $table) {
                $table->unsignedBigInteger('artist_id');
                $table->unsignedBigInteger('artist_type_id');

                $table->primary(['artist_id', 'artist_type_id'], 'artist_type_pk');

                $table->foreign('artist_id')
                    ->references('id')->on('artists')
                    ->cascadeOnDelete();

                $table->foreign('artist_type_id')
                    ->references('id')->on('artist_types')
                    ->cascadeOnDelete();

                $table->index(['artist_type_id', 'artist_id'], 'artist_type_idx');
            });
        }
    }

    public function down(): void
    {
        // Drop new artist types pivots & table
        if (Schema::hasTable('artist_artist_type')) {
            Schema::drop('artist_artist_type');
        }
        if (Schema::hasTable('artist_types')) {
            Schema::drop('artist_types');
        }

        // Recreate legacy pivot (empty) just for symmetry — optional
        if (! Schema::hasTable('event_event_category')) {
            Schema::create('event_event_category', function (Blueprint $table) {
                $table->unsignedBigInteger('event_id');
                $table->unsignedBigInteger('event_category_id');
                $table->primary(['event_id', 'event_category_id']);
            });
        }

        if (Schema::hasTable('event_event_type')) {
            Schema::drop('event_event_type');
        }

        // Rename back event_types -> event_categories if needed
        if (Schema::hasTable('event_types') && ! Schema::hasTable('event_categories')) {
            Schema::rename('event_types', 'event_categories');
        }

        // NOTE: We do NOT recreate event_music_genre here.
        // music_genres table was never dropped; artist_music_genre remains intact.
    }
};
