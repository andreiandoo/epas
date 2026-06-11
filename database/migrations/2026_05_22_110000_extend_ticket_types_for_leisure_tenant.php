<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E1 — Leisure tenant: TicketType extensions.
 *
 * Adds JSON fields for variable-duration rentals, per-day pricing rules,
 * seasons, and overtime surcharge configuration. All fields are nullable
 * with sane defaults; existing tickets (theater, festival, marketplace)
 * are unaffected.
 *
 * Reuses already-present columns: service_category, service_duration_minutes,
 * is_subscription, is_entry_ticket, valid_date, sale_stock, daily_capacity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_types', 'leisure_duration_variants')) {
                $table->json('leisure_duration_variants')->nullable();
            }
            if (! Schema::hasColumn('ticket_types', 'leisure_pricing_rules')) {
                $table->json('leisure_pricing_rules')->nullable();
            }
            if (! Schema::hasColumn('ticket_types', 'leisure_seasons')) {
                $table->json('leisure_seasons')->nullable();
            }
            if (! Schema::hasColumn('ticket_types', 'leisure_is_overtime_chargeable')) {
                $table->boolean('leisure_is_overtime_chargeable')->default(false);
            }
            if (! Schema::hasColumn('ticket_types', 'leisure_overtime_surcharge_cents')) {
                $table->integer('leisure_overtime_surcharge_cents')->nullable();
            }
            if (! Schema::hasColumn('ticket_types', 'leisure_overtime_interval_minutes')) {
                $table->unsignedInteger('leisure_overtime_interval_minutes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            foreach ([
                'leisure_duration_variants',
                'leisure_pricing_rules',
                'leisure_seasons',
                'leisure_is_overtime_chargeable',
                'leisure_overtime_surcharge_cents',
                'leisure_overtime_interval_minutes',
            ] as $c) {
                if (Schema::hasColumn('ticket_types', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
