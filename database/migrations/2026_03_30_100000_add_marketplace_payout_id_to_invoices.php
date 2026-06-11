<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('marketplace_payout_id')->nullable()->after('marketplace_organizer_id')->constrained('marketplace_payouts')->nullOnDelete();
            $table->index('marketplace_payout_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marketplace_payout_id');
        });
    }
};
