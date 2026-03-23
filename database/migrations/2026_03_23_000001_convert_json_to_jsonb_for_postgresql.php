<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert all JSON columns to JSONB for PostgreSQL compatibility.
     *
     * PostgreSQL's JSON type does not support equality operators, which causes
     * failures with SELECT DISTINCT queries on tables containing JSON columns
     * (e.g., artists, venue_categories). JSONB supports equality and is the
     * recommended type for PostgreSQL.
     *
     * This migration is a no-op on non-PostgreSQL databases.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Query information_schema to find all JSON columns in the current database
        $jsonColumns = DB::select("
            SELECT table_name, column_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND data_type = 'json'
            ORDER BY table_name, ordinal_position
        ");

        foreach ($jsonColumns as $col) {
            $table = $col->table_name;
            $column = $col->column_name;

            // ALTER COLUMN to jsonb using a USING clause for automatic casting
            DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column}\" TYPE jsonb USING \"{$column}\"::jsonb");
        }
    }

    /**
     * Reverse: convert JSONB columns back to JSON.
     *
     * Note: This is a best-effort reverse. It converts ALL jsonb columns back
     * to json, which may affect columns that were already jsonb before this
     * migration ran.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $jsonbColumns = DB::select("
            SELECT table_name, column_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND data_type = 'jsonb'
            ORDER BY table_name, ordinal_position
        ");

        foreach ($jsonbColumns as $col) {
            $table = $col->table_name;
            $column = $col->column_name;

            DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column}\" TYPE json USING \"{$column}\"::json");
        }
    }
};
