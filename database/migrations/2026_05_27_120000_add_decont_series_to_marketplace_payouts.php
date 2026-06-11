<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a decont_series column to marketplace_payouts.
 *
 * Separate from `reference` (the immutable PAY-XXXX-{id} identifier): the
 * series is a human-friendly, marketplace-configurable number built from
 * the client's `decont_prefix` + an incrementing `decont_next_number`
 * (e.g. DECAMB1, DECAMB2...). Assigned on creation for NEW payouts only;
 * existing rows stay null and fall back to the reference everywhere.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            $table->string('decont_series', 40)
                ->nullable()
                ->index()
                ->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            $table->dropColumn('decont_series');
        });
    }
};
