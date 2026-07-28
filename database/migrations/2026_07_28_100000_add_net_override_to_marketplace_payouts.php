<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            // Admin-set absolute net-payable override. When NULL (the default for
            // every existing payout) nothing changes — final_net_amount keeps
            // deriving the net live from tickets. When set, it pins the payable
            // for a specific payout whose automatic computation is wrong (e.g. a
            // fraud-adjusted decont). Never applied globally.
            $table->decimal('net_override', 12, 2)->nullable()->after('adjustments_amount');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            $table->dropColumn('net_override');
        });
    }
};
