<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cleanup coloane create de F0 initial care au devenit dead code dupa refactor:
 *
 *   - marketplace_tax_registries.invoice_series, last_invoice_number  (mutate pe organizer)
 *   - ticket_types.issuing_tax_registry_id                            (inlocuit cu issuing_company)
 *   - marketplace_ticket_types.issuing_tax_registry_id                (mirror)
 *   - invoices.marketplace_tax_registry_id                            (inutil dupa refactor)
 *   - marketplace_payouts.marketplace_tax_registry_id                 (inutil dupa refactor)
 *
 * Service_category ramane (folosit). Toate sunt nullable la momentul cleanup-ului
 * deci drop-ul nu pierde date semnificative — utilizatorul nu apucase sa configureze
 * valori (dovedit de feedback-ul din timpul refactor-ului: nu existau ecrane UI
 * functionale pentru a seta tax_registry_id).
 *
 * Idempotent: Schema::hasColumn check inainte de orice operatie.
 *
 * Down() recreeaza coloanele (cu acelasi shape ca migratiile originale 100001-100003,
 * 100006-100007), pentru rollback complet daca e nevoie.
 */
return new class extends Migration
{
    public function up(): void
    {
        // marketplace_tax_registries
        Schema::table('marketplace_tax_registries', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_tax_registries', 'last_invoice_number')) {
                $table->dropColumn('last_invoice_number');
            }
            if (Schema::hasColumn('marketplace_tax_registries', 'invoice_series')) {
                $table->dropColumn('invoice_series');
            }
        });

        // ticket_types
        Schema::table('ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_types', 'issuing_tax_registry_id')) {
                // Drop index inainte de coloana (Postgres cere asta cand exista)
                $indexes = collect(\DB::select("
                    SELECT indexname FROM pg_indexes
                    WHERE tablename = 'ticket_types' AND indexname = 'tt_issuing_tax_registry_idx'
                "));
                if ($indexes->isNotEmpty()) {
                    $table->dropIndex('tt_issuing_tax_registry_idx');
                }
                $table->dropColumn('issuing_tax_registry_id');
            }
        });

        // marketplace_ticket_types
        Schema::table('marketplace_ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_ticket_types', 'issuing_tax_registry_id')) {
                $indexes = collect(\DB::select("
                    SELECT indexname FROM pg_indexes
                    WHERE tablename = 'marketplace_ticket_types' AND indexname = 'mtt_issuing_tax_registry_idx'
                "));
                if ($indexes->isNotEmpty()) {
                    $table->dropIndex('mtt_issuing_tax_registry_idx');
                }
                $table->dropColumn('issuing_tax_registry_id');
            }
        });

        // invoices
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'marketplace_tax_registry_id')) {
                $indexes = collect(\DB::select("
                    SELECT indexname FROM pg_indexes
                    WHERE tablename = 'invoices' AND indexname = 'inv_mkt_tax_registry_idx'
                "));
                if ($indexes->isNotEmpty()) {
                    $table->dropIndex('inv_mkt_tax_registry_idx');
                }
                $table->dropColumn('marketplace_tax_registry_id');
            }
        });

        // marketplace_payouts
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_payouts', 'marketplace_tax_registry_id')) {
                $indexes = collect(\DB::select("
                    SELECT indexname FROM pg_indexes
                    WHERE tablename = 'marketplace_payouts' AND indexname = 'mp_mkt_tax_registry_idx'
                "));
                if ($indexes->isNotEmpty()) {
                    $table->dropIndex('mp_mkt_tax_registry_idx');
                }
                $table->dropColumn('marketplace_tax_registry_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_tax_registries', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_tax_registries', 'invoice_series')) {
                $table->string('invoice_series', 16)->nullable()->after('tax_rate');
            }
            if (!Schema::hasColumn('marketplace_tax_registries', 'last_invoice_number')) {
                $table->unsignedInteger('last_invoice_number')->default(0)->after('invoice_series');
            }
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_types', 'issuing_tax_registry_id')) {
                $table->unsignedBigInteger('issuing_tax_registry_id')->nullable()->after('requires_vehicle_info');
                $table->index('issuing_tax_registry_id', 'tt_issuing_tax_registry_idx');
            }
        });

        Schema::table('marketplace_ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_ticket_types', 'issuing_tax_registry_id')) {
                $table->unsignedBigInteger('issuing_tax_registry_id')->nullable()->after('requires_vehicle_info');
                $table->index('issuing_tax_registry_id', 'mtt_issuing_tax_registry_idx');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'marketplace_tax_registry_id')) {
                $table->unsignedBigInteger('marketplace_tax_registry_id')->nullable()->after('marketplace_organizer_id');
                $table->index('marketplace_tax_registry_id', 'inv_mkt_tax_registry_idx');
            }
        });

        Schema::table('marketplace_payouts', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_payouts', 'marketplace_tax_registry_id')) {
                $table->unsignedBigInteger('marketplace_tax_registry_id')->nullable()->after('marketplace_organizer_id');
                $table->index('marketplace_tax_registry_id', 'mp_mkt_tax_registry_idx');
            }
        });
    }
};
