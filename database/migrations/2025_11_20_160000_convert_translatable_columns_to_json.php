<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tables and columns to convert to JSON for multi-language support.
     * Existing string data will be migrated to English locale.
     */
    protected array $tableColumns = [
        'microservices' => ['name', 'description', 'short_description'],
        'venues' => ['name', 'description'],
        'event_types' => ['name', 'description'],
        'artist_types' => ['name', 'description'],
        'event_genres' => ['name', 'description'],
        'artist_genres' => ['name', 'description'],
    ];

    public function up(): void
    {
        foreach ($this->tableColumns as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }

                // Step 1: Migrate existing data to JSON format
                $records = DB::table($table)->get();

                foreach ($records as $record) {
                    $value = $record->$column;

                    // Skip if already JSON
                    if ($this->isJson($value)) {
                        continue;
                    }

                    // Convert string to JSON with 'en' locale
                    $jsonValue = json_encode(['en' => $value ?? '']);

                    DB::table($table)
                        ->where('id', $record->id)
                        ->update([$column => $jsonValue]);
                }

                // Note: We skip altering column type to JSON because:
                // - MySQL doesn't allow JSON columns with indexes
                // - SQLite doesn't support column type changes
                // - TEXT columns work fine with JSON strings via Eloquent casts
                //
                // If you want native JSON type for PostgreSQL, uncomment below:
                // $driver = DB::connection()->getDriverName();
                // if ($driver === 'pgsql') {
                //     DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE JSONB USING {$column}::JSONB");
                // }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tableColumns as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }

                // Convert JSON back to string (extract 'en' value)
                $records = DB::table($table)->get();

                foreach ($records as $record) {
                    $value = $record->$column;

                    if ($this->isJson($value)) {
                        $decoded = json_decode($value, true);
                        $stringValue = $decoded['en'] ?? '';

                        DB::table($table)
                            ->where('id', $record->id)
                            ->update([$column => $stringValue]);
                    }
                }

                // Column type reversion not needed since we keep TEXT type
            }
        }
    }

    /**
     * Check if a string is valid JSON
     */
    protected function isJson($string): bool
    {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
};
