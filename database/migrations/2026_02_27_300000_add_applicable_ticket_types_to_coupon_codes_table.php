<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_codes', function (Blueprint $table) {
            $table->json('applicable_ticket_types')->nullable()->after('applicable_events');
        });
    }

    public function down(): void
    {
        Schema::table('coupon_codes', function (Blueprint $table) {
            $table->dropColumn('applicable_ticket_types');
        });
    }
};
