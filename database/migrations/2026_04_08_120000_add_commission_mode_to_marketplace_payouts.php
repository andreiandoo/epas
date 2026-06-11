<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            $table->string('commission_mode', 32)->nullable()->after('commission_amount');
            $table->string('invoice_recipient_type', 32)->nullable()->after('commission_mode');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            $table->dropColumn(['commission_mode', 'invoice_recipient_type']);
        });
    }
};
