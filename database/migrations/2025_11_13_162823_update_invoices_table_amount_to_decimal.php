<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Drop old amount_cents column
            $table->dropColumn('amount_cents');

            // Add new amount column with decimal
            $table->decimal('amount', 10, 2)->after('due_date')->default(0);

            // Add billing period columns
            $table->date('period_start')->nullable()->after('issue_date');
            $table->date('period_end')->nullable()->after('period_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['amount', 'period_start', 'period_end']);
            $table->bigInteger('amount_cents')->after('due_date')->default(0);
        });
    }
};
