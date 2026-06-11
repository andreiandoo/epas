<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // This migration is PostgreSQL-specific (MySQL doesn't have JSONB type)
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Găsește toate coloanele de tip JSON (nu JSONB) din schema curentă și le convertește în JSONB.
        $cols = DB::select("
            SELECT table_schema, table_name, column_name
            FROM information_schema.columns
            WHERE data_type = 'json'
              AND table_schema NOT IN ('information_schema', 'pg_catalog')
        ");

        foreach ($cols as $c) {
            // Quote corect pentru schema / tabel / coloană
            $schema = $c->table_schema;
            $table  = $c->table_name;
            $column = $c->column_name;

            $qualifiedTable = sprintf('"%s"."%s"', $schema, $table);
            $quotedColumn   = sprintf('"%s"', $column);

            // Convertim în jsonb, păstrând valorile existente
            $sql = "ALTER TABLE {$qualifiedTable} ALTER COLUMN {$quotedColumn} TYPE jsonb USING {$quotedColumn}::jsonb";
            try {
                DB::statement($sql);
            } catch (\Throwable $e) {
                // Dacă migrația rulează și pe medii fără acea coloană sau cu deja jsonb, trecem peste
            }
        }
    }

    public function down(): void
    {
        // This migration is PostgreSQL-specific (MySQL doesn't have JSONB type)
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // (Opțional) Convertire inversă jsonb -> json
        $cols = DB::select("
            SELECT table_schema, table_name, column_name
            FROM information_schema.columns
            WHERE data_type = 'jsonb'
              AND table_schema NOT IN ('information_schema', 'pg_catalog')
        ");

        foreach ($cols as $c) {
            $schema = $c->table_schema;
            $table  = $c->table_name;
            $column = $c->column_name;

            $qualifiedTable = sprintf('"%s"."%s"', $schema, $table);
            $quotedColumn   = sprintf('"%s"', $column);

            $sql = "ALTER TABLE {$qualifiedTable} ALTER COLUMN {$quotedColumn} TYPE json USING {$quotedColumn}::json";
            try {
                DB::statement($sql);
            } catch (\Throwable $e) {
                // safe fallback
            }
        }
    }
};
