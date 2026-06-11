<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mkt_promo_codes', function (Blueprint $table) {
            // Multi-select ticket types: existing ticket_type_id stays for backward
            // compat with old codes; new codes use this JSON array.
            $table->json('applicable_ticket_type_ids')->nullable()->after('ticket_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('mkt_promo_codes', function (Blueprint $table) {
            $table->dropColumn('applicable_ticket_type_ids');
        });
    }
};
