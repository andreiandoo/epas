<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexuri pentru interogările widget-ului de Android.
 *
 * Ce lipsea şi de ce contează:
 *
 * - `tickets(status)` — widget-ul numără biletele valide, iar tabela avea doar
 *   `(ticket_type_id, status)` şi `order_id`. Fără un index pe `status`,
 *   numărătoarea „all time” e scanare completă. Acelaşi index serveşte şi
 *   dashboard-ul de admin, care face exact aceeaşi numărătoare la 15 secunde.
 * - `orders(status, created_at)` — cifra „azi” filtrează pe amândouă; indexurile
 *   separate existente obligă baza să le combine la fiecare cerere.
 * - `orders(commission_amount, id)` parţial — lista de comisioane e
 *   `WHERE commission_amount > 0 ORDER BY id DESC LIMIT 5`, adică exact ce
 *   rezolvă un index parţial dintr-o singură căutare.
 *
 * Pe PostgreSQL se creează CONCURRENTLY, deci NU blochează scrierile: migraţia
 * poate rula pe producţie în timpul vânzărilor. De aceea foloseşte SQL brut şi
 * nu Schema::table() — Laravel rulează migraţiile într-o tranzacţie când poate,
 * iar `CREATE INDEX CONCURRENTLY` nu are voie în tranzacţie.
 *
 * Migraţia e opţională ca efect (nimic nu se strică fără ea), dar ieftină şi
 * aditivă. Pe o tabelă mare poate dura minute — e normal.
 */
return new class extends Migration
{
    /* Obligatoriu pentru CONCURRENTLY: fără el, Laravel deschide o tranzacţie. */
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            /* Pe alte drivere (SQLite în teste, MySQL) indexurile parţiale şi
               CONCURRENTLY nu există sau diferă. Producţia e PostgreSQL. */
            return;
        }

        if (Schema::hasTable('tickets') && Schema::hasColumn('tickets', 'status')) {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_status_created ON tickets (status, created_at)');
        }

        if (Schema::hasTable('orders')) {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_orders_status_created ON orders (status, created_at)');

            if (Schema::hasColumn('orders', 'commission_amount')) {
                DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_orders_commission_id ON orders (id DESC) WHERE commission_amount > 0');
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([
            'idx_tickets_status_created',
            'idx_orders_status_created',
            'idx_orders_commission_id',
        ] as $index) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$index}");
        }
    }
};
