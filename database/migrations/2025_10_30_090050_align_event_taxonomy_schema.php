<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $tables = [
            'event_categories',
            'event_genres',
            'music_genres',
            'event_tags',
        ];

        foreach ($tables as $table) {
            // 1) Asigură coloanele de bază
            if (! Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $t) {
                    $t->id();
                    $t->string('name', 190);
                    $t->string('slug', 190)->unique();
                    $t->text('description')->nullable();
                    $t->timestamps();
                });
                continue; // următorul tabel
            }

            // Dacă există deja, ne asigurăm că are coloanele necesare
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'name')) {
                    $t->string('name', 190)->after('id');
                }

                if (! Schema::hasColumn($table, 'slug')) {
                    // îl facem temporar nullable ca să-l populăm programatic
                    $t->string('slug', 190)->nullable()->after('name');
                }

                if (! Schema::hasColumn($table, 'description')) {
                    $t->text('description')->nullable()->after('slug');
                }

                if (! Schema::hasColumn($table, 'created_at')) {
                    $t->timestamps();
                }
            });

            // 2) Populează slug-urile lipsă din name
            if (Schema::hasColumn($table, 'slug') && Schema::hasColumn($table, 'name')) {
                DB::table($table)
                    ->select('id', 'name', 'slug')
                    ->whereNull('slug')
                    ->orWhere('slug', '')
                    ->orderBy('id')
                    ->chunkById(500, function ($rows) use ($table) {
                        foreach ($rows as $row) {
                            $slug = Str::slug($row->name ?? '');
                            if ($slug === '') {
                                $slug = 'item-'.$row->id;
                            }
                            DB::table($table)->where('id', $row->id)->update(['slug' => $slug]);
                        }
                    });
            }

            // 3) Impune NOT NULL pe slug (PostgreSQL) și indexul unic, tolerant dacă există deja
            if (Schema::hasColumn($table, 'slug')) {
                // NOT NULL
                try {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN slug SET NOT NULL");
                } catch (\Throwable $e) {
                    // ignorăm dacă e deja NOT NULL
                }

                // UNIQUE INDEX (Postgres are IF NOT EXISTS)
                try {
                    DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS {$table}_slug_unique ON {$table} (slug)");
                } catch (\Throwable $e) {
                    // dacă există deja un unique constraint de tip constraint, e ok
                }
            }
        }
    }

    public function down(): void
    {
        // Nu ștergem coloane; e o migrare de aliniere (safe).
        // Dacă ai nevoie, poți adăuga logică de revert, dar nu recomand aici.
    }
};
