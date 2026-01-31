<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure base taxonomy tables exist (no-op if already there)
        foreach ([
            'event_categories' => 'id',
            'event_genres'     => 'id',
            'music_genres'     => 'id',
            'event_tags'       => 'id',
            'events'           => 'id',
        ] as $table => $pk) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException("Missing required table [{$table}]. Please run your base migrations first.");
            }
        }

        // 1) Create final pivot tables if missing
        $this->createPivotIfMissing('event_event_category', 'event_id', 'event_category_id', 'events', 'event_categories');
        $this->createPivotIfMissing('event_event_genre', 'event_id', 'event_genre_id', 'events', 'event_genres');
        $this->createPivotIfMissing('event_music_genre', 'event_id', 'music_genre_id', 'events', 'music_genres');
        $this->createPivotIfMissing('event_event_tag', 'event_id', 'event_tag_id', 'events', 'event_tags');

        // 2) Copy data from legacy pivots (if they exist)
        $this->migrateLegacyPivot(
            legacyTable: 'category_event',
            legacyEventCol: $this->firstExistingColumn('category_event', ['event_id']),
            legacyTaxCol: $this->firstExistingColumn('category_event', ['category_id','event_category_id']),
            newTable: 'event_event_category',
            newEventCol: 'event_id',
            newTaxCol: 'event_category_id'
        );

        $this->migrateLegacyPivot(
            legacyTable: 'event_genre_event',
            legacyEventCol: $this->firstExistingColumn('event_genre_event', ['event_id']),
            legacyTaxCol: $this->firstExistingColumn('event_genre_event', ['event_genre_id','genre_id']),
            newTable: 'event_event_genre',
            newEventCol: 'event_id',
            newTaxCol: 'event_genre_id'
        );

        $this->migrateLegacyPivot(
            legacyTable: 'music_genre_event',
            legacyEventCol: $this->firstExistingColumn('music_genre_event', ['event_id']),
            legacyTaxCol: $this->firstExistingColumn('music_genre_event', ['music_genre_id','genre_id']),
            newTable: 'event_music_genre',
            newEventCol: 'event_id',
            newTaxCol: 'music_genre_id'
        );

        // Some projects name tag relations both ways:
        $this->migrateLegacyPivot(
            legacyTable: 'event_tag',
            legacyEventCol: $this->firstExistingColumn('event_tag', ['event_id']),
            legacyTaxCol: $this->firstExistingColumn('event_tag', ['event_tag_id','tag_id']),
            newTable: 'event_event_tag',
            newEventCol: 'event_id',
            newTaxCol: 'event_tag_id'
        );

        $this->migrateLegacyPivot(
            legacyTable: 'tag_event',
            legacyEventCol: $this->firstExistingColumn('tag_event', ['event_id']),
            legacyTaxCol: $this->firstExistingColumn('tag_event', ['event_tag_id','tag_id']),
            newTable: 'event_event_tag',
            newEventCol: 'event_id',
            newTaxCol: 'event_tag_id'
        );

        // 3) Drop legacy tables if they exist
        $this->dropIfExists('category_event');
        $this->dropIfExists('event_genre_event');
        $this->dropIfExists('music_genre_event');
        $this->dropIfExists('event_tag');
        $this->dropIfExists('tag_event');
    }

    public function down(): void
    {
        // Keep it simple: drop the normalized pivots.
        foreach ([
            'event_event_category',
            'event_event_genre',
            'event_music_genre',
            'event_event_tag',
        ] as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::drop($tbl);
            }
        }
        // We don't recreate legacy tables on down() to avoid re-fragmentation.
    }

    private function createPivotIfMissing(
        string $pivot,
        string $eventCol,
        string $taxCol,
        string $eventTable,
        string $taxTable
    ): void {
        if (! Schema::hasTable($pivot)) {
            Schema::create($pivot, function (Blueprint $table) use ($eventCol, $taxCol, $eventTable, $taxTable) {
                $table->unsignedBigInteger($eventCol);
                $table->unsignedBigInteger($taxCol);

                $table->primary([$eventCol, $taxCol], "{$eventCol}_{$taxCol}_pk");

                $table->foreign($eventCol)->references('id')->on($eventTable)->cascadeOnDelete();
                $table->foreign($taxCol)->references('id')->on($taxTable)->cascadeOnDelete();

                $table->index([$taxCol, $eventCol], "{$taxCol}_{$eventCol}_idx");
            });
        }
    }

    private function migrateLegacyPivot(
        string $legacyTable,
        ?string $legacyEventCol,
        ?string $legacyTaxCol,
        string $newTable,
        string $newEventCol,
        string $newTaxCol
    ): void {
        if (! Schema::hasTable($legacyTable)) {
            return;
        }
        if (! $legacyEventCol || ! $legacyTaxCol) {
            return; // cannot map columns reliably
        }

        // Fetch distinct pairs in chunks and insert ignore/skip duplicates by primary key
        $query = DB::table($legacyTable)
            ->select([$legacyEventCol . ' as e', $legacyTaxCol . ' as t'])
            ->whereNotNull($legacyEventCol)
            ->whereNotNull($legacyTaxCol)
            ->distinct();

        $query->orderBy('e')->chunk(1000, function ($rows) use ($newTable, $newEventCol, $newTaxCol) {
            $batch = [];
            foreach ($rows as $r) {
                $batch[] = [
                    $newEventCol => (int) $r->e,
                    $newTaxCol   => (int) $r->t,
                ];
            }
            if (! empty($batch)) {
                // Try insert; duplicates will throw — catch & ignore per-row
                foreach (array_chunk($batch, 200) as $chunk) {
                    try {
                        DB::table($newTable)->insert($chunk);
                    } catch (\Throwable $e) {
                        // Fallback: insert rows one by one ignoring duplicates
                        foreach ($chunk as $row) {
                            try {
                                DB::table($newTable)->insert($row);
                            } catch (\Throwable $ignore) {}
                        }
                    }
                }
            }
        });
    }

    private function dropIfExists(string $table): void
    {
        if (Schema::hasTable($table)) {
            Schema::drop($table);
        }
    }

    private function firstExistingColumn(string $table, array $candidates): ?string
    {
        if (! Schema::hasTable($table)) {
            return null;
        }
        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) {
                return $col;
            }
        }
        return null;
    }
};
