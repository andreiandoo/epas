<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            // Comision inclus în preț care NU se reține din decont: organizatorul
            // primește și suma asta, iar comisionul i se facturează separat (cu TVA).
            // NULL = decont vechi, comisionul a fost reținut (amount = net).
            $table->decimal('invoiced_commission_amount', 12, 2)->nullable()->after('net_override');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            $table->dropColumn('invoiced_commission_amount');
        });
    }
};
