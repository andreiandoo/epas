<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who created a decont, who marked it paid and who issued an invoice —
     * marketplace_admins ids, filled from now on (earlier rows stay null).
     * Plain columns, no foreign keys: nothing but the history reads them.
     */
    public function up(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('issued_by_admin_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('issued_by_admin_id');
        });

        Schema::table('marketplace_payouts', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'completed_by']);
        });
    }
};
