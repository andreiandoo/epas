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
            $table->text('description')->nullable()->after('number');
            $table->decimal('subtotal', 10, 2)->default(0)->after('due_date');
            $table->decimal('vat_rate', 5, 2)->default(0)->after('subtotal');
            $table->decimal('vat_amount', 10, 2)->default(0)->after('vat_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['description', 'subtotal', 'vat_rate', 'vat_amount']);
        });
    }
};
