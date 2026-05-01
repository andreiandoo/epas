<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extinde service_orders pentru audienta artist:
 *  - marketplace_organizer_id devine nullable (ordinele artistului nu au organizator)
 *  - se adauga marketplace_artist_account_id (FK)
 *  - se adauga microservice_id (FK) ca scurtatura catre microserviciul activat
 *  - service_type devine VARCHAR (era ENUM cu doar 4 valori) ca sa permita 'extended_artist'
 *
 * Cross-DB: SQLite ignora ENUM constraint-urile (schimbarea e no-op acolo).
 * Pentru Postgres/MySQL apelam ALTER TABLE explicit prin DB::statement.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_orders')) {
            return;
        }

        // 1) Adauga coloanele noi (cross-DB safe via Schema::table)
        Schema::table('service_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('service_orders', 'marketplace_artist_account_id')) {
                $table->foreignId('marketplace_artist_account_id')
                    ->nullable()
                    ->after('marketplace_organizer_id')
                    ->constrained('marketplace_artist_accounts')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('service_orders', 'microservice_id')) {
                $table->foreignId('microservice_id')
                    ->nullable()
                    ->after('marketplace_event_id')
                    ->constrained('microservices')
                    ->nullOnDelete();
            }
        });

        // 2) Largeste service_type la VARCHAR (era ENUM(featuring,email,tracking,campaign))
        //    + relaxeaza marketplace_organizer_id (was NOT NULL)
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Postgres: enumurile inline sunt traduse la CHECK constraint pe coloana,
            // iar Laravel le numeste service_orders_service_type_check. Le aruncam
            // si convertim coloana la VARCHAR(50). marketplace_organizer_id devine
            // nullable cu DROP NOT NULL.
            DB::statement('ALTER TABLE service_orders DROP CONSTRAINT IF EXISTS service_orders_service_type_check');
            DB::statement('ALTER TABLE service_orders ALTER COLUMN service_type TYPE VARCHAR(50) USING service_type::text');
            DB::statement('ALTER TABLE service_orders ALTER COLUMN marketplace_organizer_id DROP NOT NULL');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE service_orders MODIFY COLUMN service_type VARCHAR(50) NOT NULL');
            DB::statement('ALTER TABLE service_orders MODIFY COLUMN marketplace_organizer_id BIGINT UNSIGNED NULL');
        }
        // SQLite: ENUM-urile sunt simulat doar via CHECK, dar pe versiunile recente
        // Laravel nu le adauga. Lasam coloana ca atare — accepta orice string.
    }

    public function down(): void
    {
        if (!Schema::hasTable('service_orders')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table) {
            if (Schema::hasColumn('service_orders', 'marketplace_artist_account_id')) {
                $table->dropForeign(['marketplace_artist_account_id']);
                $table->dropColumn('marketplace_artist_account_id');
            }
            if (Schema::hasColumn('service_orders', 'microservice_id')) {
                $table->dropForeign(['microservice_id']);
                $table->dropColumn('microservice_id');
            }
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Restabilim NOT NULL pe organizator (presupunand ca nu exista randuri NULL).
            DB::statement('ALTER TABLE service_orders ALTER COLUMN marketplace_organizer_id SET NOT NULL');
            // Restabilim CHECK-ul pentru ENUM-ul original.
            DB::statement("ALTER TABLE service_orders ADD CONSTRAINT service_orders_service_type_check CHECK (service_type IN ('featuring','email','tracking','campaign'))");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE service_orders MODIFY COLUMN marketplace_organizer_id BIGINT UNSIGNED NOT NULL');
            DB::statement("ALTER TABLE service_orders MODIFY COLUMN service_type ENUM('featuring','email','tracking','campaign') NOT NULL");
        }
    }
};
