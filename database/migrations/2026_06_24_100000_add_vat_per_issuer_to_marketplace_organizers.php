<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-issuer VAT fields pe marketplace_organizers.
 *
 * Pana acum existau:
 *   - `vat_payer` (boolean) — flag global (folosit doar pe primary)
 *   - `tax_settings.vat_rate` (JSON nested) — folosit in reports si calc
 *
 * Adaugam fields dedicate per societate emitenta, ca sa putem trata complet
 * separate primary vs secondary in UI si invoice rendering:
 *   - primary_vat_payer + primary_vat_rate
 *   - secondary_vat_payer + secondary_vat_rate
 *
 * Backfill: primary_vat_payer = vat_payer existent (la save din UI; nu touch
 * automat pentru a evita interpretari gresite pe organizatori cu doar
 * tax_settings.vat_rate setat).
 *
 * Toate nullable / default 0 => zero impact pe organizers existenti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_organizers', 'primary_vat_payer')) {
                $table->boolean('primary_vat_payer')->default(false)->after('iban');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'primary_vat_rate')) {
                $table->decimal('primary_vat_rate', 5, 2)->nullable()->after('primary_vat_payer');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_vat_payer')) {
                $table->boolean('secondary_vat_payer')->default(false)->after('secondary_iban');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_vat_rate')) {
                $table->decimal('secondary_vat_rate', 5, 2)->nullable()->after('secondary_vat_payer');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            foreach (['secondary_vat_rate', 'secondary_vat_payer', 'primary_vat_rate', 'primary_vat_payer'] as $c) {
                if (Schema::hasColumn('marketplace_organizers', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
