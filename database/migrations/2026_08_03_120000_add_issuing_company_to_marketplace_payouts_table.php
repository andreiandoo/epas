<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tag a payout (decont) with the organizer's issuing company
     * ('primary' | 'secondary'). NULL = legacy / single-company decont, so the
     * PDF keeps using the organizer's default (primary) identity. Only the
     * leisure per-society "Generează decont" buttons set this to 'secondary'.
     */
    public function up(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            $table->string('issuing_company')->nullable()->after('event_id');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            $table->dropColumn('issuing_company');
        });
    }
};
