<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexul pe `orders.source_short_id`, construit CONCURENT.
 *
 * DE CE SEPARAT DE MIGRAREA CARE ADAUGA COLOANA
 * `orders` e un tabel mare de productie. Un `CREATE INDEX` obisnuit ia un lock
 * care blocheaza INSERT/UPDATE pe toata durata constructiei — practic pica
 * checkout-ul cat ruleaza migrarea. Adaugarea coloanei nullable, in schimb, e
 * instantanee pe Postgres.
 *
 * `CREATE INDEX CONCURRENTLY` nu blocheaza scrierile, dar NU poate rula intr-o
 * tranzactie. De aceea:
 *   - `$withinTransaction = false` — altfel Laravel il inveleste si Postgres
 *     raspunde "CREATE INDEX CONCURRENTLY cannot run inside a transaction block";
 *   - `IF NOT EXISTS`, ca migrarea sa poata fi reluata.
 *
 * Pe alte motoare (SQLite in teste, MySQL) `CONCURRENTLY` nu exista, deci
 * cadem pe un index obisnuit — acolo tabelul e mic si nu conteaza.
 */
return new class extends Migration
{
    /** CREATE INDEX CONCURRENTLY nu are voie sa fie intr-o tranzactie. */
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'source_short_id')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS orders_source_short_id_index ON orders (source_short_id)');
            return;
        }

        Schema::table('orders', function ($table) {
            $table->index('source_short_id', 'orders_source_short_id_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS orders_source_short_id_index');
            return;
        }

        Schema::table('orders', function ($table) {
            $table->dropIndex('orders_source_short_id_index');
        });
    }
};
